<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Building2,
    CheckCircle2,
    Plus,
    Users,
    XCircle,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import DepartmentController from '@/actions/App/Http/Controllers/Departments/DepartmentController';
import { AddDepartmentDialog } from '@/components/dialogs';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    FilterBar,
    FilterSearch,
    FilterSelect,
} from '@/components/ui/filter';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';

type Department = {
    id: number;
    clinic_id: number;
    name: string;
    code: string | null;
    description: string | null;
    is_active: boolean;
    doctor_profiles_count: number;
    created_by: number | null;
    updated_by: number | null;
    creator?: {
        id: number;
        name: string;
    } | null;
    updater?: {
        id: number;
        name: string;
    } | null;
    created_at: string | null;
    updated_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginationMeta = {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

type PaginationNavigation = {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
};

type PaginatedResponse<T> = {
    data: T[];
    links: PaginationNavigation;
    meta: PaginationMeta;
};

type DepartmentSortField =
    | 'name'
    | 'code'
    | 'is_active'
    | 'doctor_profiles_count'
    | 'created_at';

type SortDirection = 'asc' | 'desc';
type ActiveFilter = 'all' | 'active' | 'inactive';

const { departments, filters } = defineProps<{
    departments: PaginatedResponse<Department>;
    filters: {
        search: string | null;
        is_active: boolean | null;
        per_page: number;
        sort_by: DepartmentSortField | null;
        sort_direction: SortDirection | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'الأقسام',
                href: DepartmentController.index(),
            },
        ],
    },
});

const { can } = usePermissions();
const {
    isOpen: isConfirmOpen,
    options: confirmOptions,
    confirm,
    handleConfirm: handleConfirmDelete,
    handleCancel: handleConfirmCancel,
} = useConfirm();
const toast = useToast();
const page = usePage();
const viewingDepartment = ref<Department | null>(null);
const editingDepartment = ref<Department | null>(null);
const isCreateDialogOpen = ref(false);

const roleNames = computed<string[]>(() => {
    return (
        (page.props.auth as { roles?: string[] } | undefined)?.roles ?? []
    ).filter((value): value is string => typeof value === 'string');
});

const primaryRole = computed<string>(() => {
    const rolePriority = [
        'super_admin',
        'admin',
        'clinic_admin',
        'doctor',
        'receptionist',
        'accountant',
    ];

    return (
        rolePriority.find((role) => roleNames.value.includes(role)) ?? 'staff'
    );
});

const roleLabels: Record<string, string> = {
    super_admin: 'مدير النظام',
    admin: 'مدير',
    clinic_admin: 'مدير العيادة',
    doctor: 'طبيب',
    receptionist: 'استقبال',
    accountant: 'محاسب',
    staff: 'موظف',
};

const activeRoleLabel = computed<string>(
    () => roleLabels[primaryRole.value] ?? roleLabels.staff,
);

const localSearch = ref<string>(filters.search ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(departments.meta.current_page);

const resolveInitialActiveFilter = (): ActiveFilter => {
    if (filters.is_active === true) {
        return 'active';
    }

    if (filters.is_active === false) {
        return 'inactive';
    }

    return 'all';
};

const localIsActive = ref<ActiveFilter>(resolveInitialActiveFilter());

const allowedSortFields: DepartmentSortField[] = [
    'name',
    'code',
    'is_active',
    'doctor_profiles_count',
    'created_at',
];

const resolveInitialSortBy = (): DepartmentSortField => {
    const sortBy = filters.sort_by;

    if (
        sortBy !== null &&
        allowedSortFields.includes(sortBy as DepartmentSortField)
    ) {
        return sortBy;
    }

    return 'created_at';
};

const localSortBy = ref<DepartmentSortField>(resolveInitialSortBy());
const localSortDirection = ref<SortDirection>(
    filters.sort_direction === 'asc' ? 'asc' : 'desc',
);

const visibleDepartments = computed<Department[]>(() => departments.data);
const totalLocalPages = computed<number>(() => {
    return Math.max(1, departments.meta.last_page);
});
const localVisibleFrom = computed<number>(() => {
    return departments.meta.from ?? 0;
});
const localVisibleTo = computed<number>(() => {
    return departments.meta.to ?? 0;
});

const activeDepartmentsOnPage = computed<number>(
    () =>
        visibleDepartments.value.filter((department) => department.is_active)
            .length,
);
const inactiveDepartmentsOnPage = computed<number>(
    () =>
        visibleDepartments.value.filter((department) => !department.is_active)
            .length,
);

const totalDoctors = computed<number>(() =>
    visibleDepartments.value.reduce(
        (sum, dept) => sum + (dept.doctor_profiles_count ?? 0),
        0,
    ),
);

const defaultRowsPerPage = 15;
const isSyncingFromServer = ref(false);
let departmentFiltersDebounceTimeout: ReturnType<typeof setTimeout> | null =
    null;

const buildIndexQuery = (
    overrides: Partial<{
        search: string;
        is_active: ActiveFilter;
        per_page: number;
        page: number;
        sort_by: DepartmentSortField;
        sort_direction: SortDirection;
    }> = {},
): {
    search?: string;
    is_active?: '1' | '0';
    per_page: number;
    page: number;
    sort_by: DepartmentSortField;
    sort_direction: SortDirection;
} => {
    const activeFilter = overrides.is_active ?? localIsActive.value;
    const isActiveQuery =
        activeFilter === 'active'
            ? '1'
            : activeFilter === 'inactive'
              ? '0'
              : undefined;

    return {
        search: (overrides.search ?? localSearch.value).trim(),
        is_active: isActiveQuery,
        per_page: overrides.per_page ?? localRowsPerPage.value,
        page: overrides.page ?? localPage.value,
        sort_by: overrides.sort_by ?? localSortBy.value,
        sort_direction: overrides.sort_direction ?? localSortDirection.value,
    };
};

const reloadDepartments = (
    overrides: Partial<{
        search: string;
        is_active: ActiveFilter;
        per_page: number;
        page: number;
        sort_by: DepartmentSortField;
        sort_direction: SortDirection;
    }> = {},
    debounce = false,
): void => {
    if (isSyncingFromServer.value) {
        return;
    }

    const executeReload = (): void => {
        router.cancelAll();
        router.get(
            DepartmentController.index.url(),
            buildIndexQuery(overrides),
            {
                only: ['departments', 'filters'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    if (debounce) {
        if (departmentFiltersDebounceTimeout !== null) {
            clearTimeout(departmentFiltersDebounceTimeout);
        }

        departmentFiltersDebounceTimeout = setTimeout(executeReload, 320);

        return;
    }

    executeReload();
};

const sortIconFor = (field: DepartmentSortField) => {
    if (localSortBy.value !== field) {
        return ArrowUpDown;
    }

    return localSortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

const toggleSort = (field: DepartmentSortField): void => {
    if (localSortBy.value === field) {
        localSortDirection.value =
            localSortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        localSortBy.value = field;
        localSortDirection.value = 'asc';
    }
};

const resetLocalFilters = (): void => {
    isSyncingFromServer.value = true;
    localSearch.value = '';
    localIsActive.value = 'all';
    localRowsPerPage.value = defaultRowsPerPage;
    localSortBy.value = 'created_at';
    localSortDirection.value = 'desc';
    localPage.value = 1;
    isSyncingFromServer.value = false;

    reloadDepartments({
        search: '',
        is_active: 'all',
        per_page: defaultRowsPerPage,
        page: 1,
        sort_by: 'created_at',
        sort_direction: 'desc',
    });
};

const goToPreviousPage = (): void => {
    if (localPage.value <= 1) {
        return;
    }

    localPage.value -= 1;
    reloadDepartments({ page: localPage.value });
};

const goToNextPage = (): void => {
    if (localPage.value >= totalLocalPages.value) {
        return;
    }

    localPage.value += 1;
    reloadDepartments({ page: localPage.value });
};

watch(
    () => [
        filters.search,
        filters.is_active,
        filters.per_page,
        filters.sort_by,
        filters.sort_direction,
        departments.meta.current_page,
    ],
    () => {
        isSyncingFromServer.value = true;
        localSearch.value = filters.search ?? '';
        localIsActive.value = resolveInitialActiveFilter();
        localRowsPerPage.value = filters.per_page;
        localSortBy.value = resolveInitialSortBy();
        localSortDirection.value =
            filters.sort_direction === 'asc' ? 'asc' : 'desc';
        localPage.value = departments.meta.current_page;
        isSyncingFromServer.value = false;
    },
    { immediate: true },
);

watch(
    () => localSearch.value,
    () => {
        localPage.value = 1;
        reloadDepartments({ page: 1, search: localSearch.value.trim() }, true);
    },
);

watch(
    () => localIsActive.value,
    () => {
        localPage.value = 1;
        reloadDepartments({ page: 1, is_active: localIsActive.value });
    },
);

watch(
    () => localRowsPerPage.value,
    () => {
        localPage.value = 1;
        reloadDepartments({ page: 1, per_page: localRowsPerPage.value });
    },
);

watch(
    () => [localSortBy.value, localSortDirection.value],
    () => {
        localPage.value = 1;
        reloadDepartments({
            page: 1,
            sort_by: localSortBy.value,
            sort_direction: localSortDirection.value,
        });
    },
);

onBeforeUnmount(() => {
    if (departmentFiltersDebounceTimeout !== null) {
        clearTimeout(departmentFiltersDebounceTimeout);
        departmentFiltersDebounceTimeout = null;
    }
});

const selectedDepartmentIds = ref<number[]>([]);

const selectableDepartmentIds = computed<number[]>(() =>
    visibleDepartments.value.map((department) => department.id),
);

const areAllDepartmentsSelected = computed<boolean>(() => {
    if (selectableDepartmentIds.value.length === 0) {
        return false;
    }

    return selectableDepartmentIds.value.every((departmentId) =>
        selectedDepartmentIds.value.includes(departmentId),
    );
});

watch(
    () => selectableDepartmentIds.value,
    (ids) => {
        selectedDepartmentIds.value = selectedDepartmentIds.value.filter((id) =>
            ids.includes(id),
        );
    },
);

const toggleAllDepartmentsSelection = (event: Event): void => {
    const target = event.target as HTMLInputElement;

    selectedDepartmentIds.value = target.checked
        ? [...selectableDepartmentIds.value]
        : [];
};

const clearSelectedDepartments = (): void => {
    selectedDepartmentIds.value = [];
};

const openViewDepartment = (department: Department): void => {
    viewingDepartment.value = department;
};

const closeViewDepartment = (): void => {
    viewingDepartment.value = null;
};

const openEditDepartment = (department: Department): void => {
    editingDepartment.value = department;
};

const closeEditDepartment = (): void => {
    editingDepartment.value = null;
};

const departmentStatusClass = (isActive: boolean): string => {
    if (isActive) {
        return 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/35 dark:bg-success-500/15 dark:text-success-100';
    }

    return 'border-destructive/70 bg-destructive/10 text-destructive dark:border-destructive/35 dark:bg-destructive/15 dark:text-destructive-foreground';
};

const departmentStatusDotClass = (isActive: boolean): string => {
    return isActive ? 'bg-success-500' : 'bg-destructive';
};

const statusOptions = computed(() => [
    { label: 'الكل', value: 'all' },
    { label: 'نشط', value: 'active' },
    { label: 'غير نشط', value: 'inactive' },
]);

const activeFilters = computed(() => {
    const filters: { key: string; label: string; value: string | null }[] = [];

    if (localSearch.value.trim()) {
        filters.push({ key: 'search', label: 'بحث', value: localSearch.value.trim() });
    }

    if (localIsActive.value !== 'all') {
        filters.push({ key: 'is_active', label: 'الحالة', value: localIsActive.value === 'active' ? 'نشط' : 'غير نشط' });
    }

    return filters;
});

const handleRemoveFilter = (key: string) => {
    if (key === 'search') {
        localSearch.value = '';
    } else if (key === 'is_active') {
        localIsActive.value = 'all';
    }
};

const deleteDepartment = async (department: Department) => {
    const confirmed = await confirm({
        title: 'حذف القسم',
        description: `هل أنت متأكد من حذف القسم "${department.name}"؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(DepartmentController.destroy(department.id), {
            onSuccess: () => {
                toast.success('تم حذف القسم بنجاح');
            },
            onError: () => {
                toast.error('فشل حذف القسم');
            },
        });
    }
};
</script>

<template>
    <Head title="الأقسام" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="page-title">الأقسام</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        إدارة أقسام العيادة وهيكلها التنظيمي.
                    </p>
                </div>
                <span
                    class="inline-flex items-center rounded-full border border-border/60 bg-muted/40 px-2.5 py-0.5 text-[0.7rem] font-medium text-muted-foreground"
                >
                    {{ activeRoleLabel }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="can('department.create')"
                    variant="clay"
                    size="sm"
                    class="h-8 rounded-lg px-3 text-xs"
                    @click="isCreateDialogOpen = true"
                >
                    <Plus class="size-3.5" />
                    إنشاء قسم
                </Button>
            </div>
        </div>

        <section class="grid gap-3 md:grid-cols-4">
            <article class="rounded-xl border border-border/70 bg-card p-4">
                <div class="flex items-center justify-between">
                    <p class="section-label">إجمالي الأقسام</p>
                    <Building2 class="size-4 text-muted-foreground" />
                </div>
                <p class="card-value text-2xl">{{ departments.meta.total }}</p>
            </article>
            <article class="rounded-xl border border-border/70 bg-card p-4">
                <div class="flex items-center justify-between">
                    <p class="section-label">نشط</p>
                    <CheckCircle2 class="size-4 text-success-500" />
                </div>
                <p class="card-value text-2xl text-success-600 dark:text-success-400">{{ activeDepartmentsOnPage }}</p>
            </article>
            <article class="rounded-xl border border-border/70 bg-card p-4">
                <div class="flex items-center justify-between">
                    <p class="section-label">غير نشط</p>
                    <XCircle class="size-4 text-destructive" />
                </div>
                <p class="card-value text-2xl text-destructive">{{ inactiveDepartmentsOnPage }}</p>
            </article>
            <article class="rounded-xl border border-border/70 bg-card p-4">
                <div class="flex items-center justify-between">
                    <p class="section-label">الأطباء</p>
                    <Users class="size-4 text-info-500" />
                </div>
                <p class="card-value text-2xl text-info-600 dark:text-info-400">{{ totalDoctors }}</p>
            </article>
        </section>

        <div class="glass-panel-soft p-5">
            <div
                class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3"
            >
                <h3 class="pattern-typographic-title text-[0.76rem]">
                    قائمة الأقسام
                </h3>
                <span class="text-xs text-muted-foreground">
                    الإجمالي: {{ departments.meta.total }}
                </span>
            </div>

            <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
                <div class="grid gap-3 md:grid-cols-[repeat(3,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="departments_search">بحث</Label>
                        <FilterSearch
                            id="departments_search"
                            v-model="localSearch"
                            placeholder="الاسم، الرمز، أو الوصف"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="departments_status">الحالة</Label>
                        <FilterSelect
                            id="departments_status"
                            v-model="localIsActive"
                            :options="statusOptions"
                            placeholder="الكل"
                        />
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-[repeat(2,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2 md:max-w-44">
                        <Label for="departments_per_page">صفوف لكل صفحة</Label>
                        <select
                            id="departments_per_page"
                            v-model.number="localRowsPerPage"
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>
                <FilterBar
                    v-if="activeFilters.length > 0"
                    :active-filters="activeFilters"
                    @remove="handleRemoveFilter"
                    @clear-all="resetLocalFilters"
                />
            </div>

            <Form
                v-if="
                    can('department.delete') &&
                    selectedDepartmentIds.length > 0
                "
                v-bind="DepartmentController.bulkDestroy.form()"
                class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3"
                v-slot="{ processing }"
            >
                <input
                    v-for="departmentId in selectedDepartmentIds"
                    :key="`selected-department-${departmentId}`"
                    type="hidden"
                    name="ids[]"
                    :value="departmentId"
                />

                <Button
                    type="submit"
                    variant="destructive"
                    size="sm"
                    :disabled="processing"
                >
                    حذف المحدد ({{ selectedDepartmentIds.length }})
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="clearSelectedDepartments"
                >
                    إلغاء التحديد
                </Button>
            </Form>

            <div class="ui-table-shell">
                <table class="ui-table md:min-w-[940px]">
                    <thead>
                        <tr>
                            <th
                                v-if="can('department.delete')"
                                class="px-3 py-2"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-border"
                                    :checked="areAllDepartmentsSelected"
                                    @change="toggleAllDepartmentsSelection"
                                />
                            </th>
                            <th class="px-3 py-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="toggleSort('name')"
                                >
                                    الاسم
                                    <component
                                        :is="sortIconFor('name')"
                                        class="size-3.5"
                                    />
                                </button>
                            </th>
                            <th class="px-3 py-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="toggleSort('code')"
                                >
                                    الرمز
                                    <component
                                        :is="sortIconFor('code')"
                                        class="size-3.5"
                                    />
                                </button>
                            </th>
                            <th class="px-3 py-2">الوصف</th>
                            <th class="px-3 py-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="
                                        toggleSort('doctor_profiles_count')
                                    "
                                >
                                    الأطباء
                                    <component
                                        :is="
                                            sortIconFor(
                                                'doctor_profiles_count',
                                            )
                                        "
                                        class="size-3.5"
                                    />
                                </button>
                            </th>
                            <th class="px-3 py-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="toggleSort('is_active')"
                                >
                                    الحالة
                                    <component
                                        :is="sortIconFor('is_active')"
                                        class="size-3.5"
                                    />
                                </button>
                            </th>
                            <th class="px-3 py-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="toggleSort('created_at')"
                                >
                                    تاريخ الإنشاء
                                    <component
                                        :is="sortIconFor('created_at')"
                                        class="size-3.5"
                                    />
                                </button>
                            </th>
                            <th class="px-3 py-2 text-start">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="department in visibleDepartments"
                            :key="department.id"
                            class="ui-table-row align-top"
                        >
                            <td
                                v-if="can('department.delete')"
                                class="px-3 py-2"
                                data-label="تحديد"
                            >
                                <input
                                    v-model="selectedDepartmentIds"
                                    type="checkbox"
                                    class="size-4 rounded border-border"
                                    :value="department.id"
                                />
                            </td>
                            <td
                                class="px-3 py-2 font-medium"
                                data-label="الاسم"
                            >
                                {{ department.name }}
                            </td>
                            <td class="px-3 py-2" data-label="الرمز">
                                {{ department.code ?? '-' }}
                            </td>
                            <td
                                class="max-w-xs px-3 py-2"
                                data-label="الوصف"
                            >
                                <span
                                    class="line-clamp-2 text-sm text-muted-foreground"
                                >
                                    {{ department.description ?? '-' }}
                                </span>
                            </td>
                            <td class="px-3 py-2" data-label="الأطباء">
                                <span
                                    class="inline-flex rounded-full border border-border/70 bg-background/80 px-2.5 py-1 text-xs font-medium"
                                >
                                    {{ department.doctor_profiles_count }}
                                </span>
                            </td>
                            <td class="px-3 py-2" data-label="الحالة">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        departmentStatusClass(
                                            department.is_active,
                                        )
                                    "
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="
                                            departmentStatusDotClass(
                                                department.is_active,
                                            )
                                        "
                                    ></span>
                                    {{
                                        department.is_active
                                            ? 'نشط'
                                            : 'غير نشط'
                                    }}
                                </span>
                            </td>
                            <td
                                class="px-3 py-2"
                                data-label="تاريخ الإنشاء"
                            >
                                {{
                                    department.created_at !== null
                                        ? new Date(
                                              department.created_at,
                                          ).toLocaleDateString('ar-EG')
                                        : '-'
                                }}
                            </td>
                            <td
                                class="table-cell-actions px-3 py-2 md:text-start"
                                data-label="الإجراءات"
                            >
                                <div
                                    class="flex flex-wrap justify-end gap-2"
                                >
                                    <Button
                                        type="button"
                                        variant="neumorphic"
                                        size="sm"
                                        class="h-8 px-3 text-xs"
                                        @click="
                                            openViewDepartment(department)
                                        "
                                    >
                                        عرض
                                    </Button>
                                    <Button
                                        v-if="can('department.update')"
                                        type="button"
                                        variant="clay"
                                        size="sm"
                                        class="h-8 px-3 text-xs"
                                        @click="
                                            openEditDepartment(department)
                                        "
                                    >
                                        تعديل
                                    </Button>
                                    <Button
                                        v-if="can('department.delete')"
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        class="h-8 px-3 text-xs"
                                        @click="
                                            deleteDepartment(department)
                                        "
                                    >
                                        حذف
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-if="visibleDepartments.length === 0"
                            class="table-empty-state"
                        >
                            <td
                                :colspan="can('department.delete') ? 8 : 7"
                                class="px-3 py-10 text-center text-muted-foreground"
                            >
                                لا توجد أقسام مطابقة.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2"
            >
                <p class="text-xs text-muted-foreground">
                    عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من
                    {{ departments.meta.total }} سجل
                </p>
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="neumorphic"
                        size="sm"
                        class="h-8 px-3 text-xs"
                        :disabled="localPage === 1"
                        @click="goToPreviousPage"
                    >
                        السابق
                    </Button>
                    <span class="text-xs font-semibold text-foreground/85">
                        صفحة {{ localPage }} / {{ totalLocalPages }}
                    </span>
                    <Button
                        type="button"
                        variant="neumorphic"
                        size="sm"
                        class="h-8 px-3 text-xs"
                        :disabled="localPage >= totalLocalPages"
                        @click="goToNextPage"
                    >
                        التالي
                    </Button>
                </div>
            </div>
        </div>

        <AddDepartmentDialog
            :open="isCreateDialogOpen"
            @update:open="(value) => (isCreateDialogOpen = value)"
            @success="() => {}"
        />

        <Dialog
            :open="viewingDepartment !== null"
            @update:open="(open) => !open && closeViewDepartment()"
        >
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ viewingDepartment?.name ?? 'تفاصيل القسم' }}
                    </DialogTitle>
                    <DialogDescription>
                        ملف القسم الكامل وسياق الملكية.
                    </DialogDescription>
                </DialogHeader>

                <dl
                    v-if="viewingDepartment"
                    class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4 sm:grid-cols-2"
                >
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            الرمز
                        </dt>
                        <dd class="text-sm">
                            {{ viewingDepartment.code ?? '-' }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            الحالة
                        </dt>
                        <dd class="text-sm capitalize">
                            {{
                                viewingDepartment.is_active ? 'نشط' : 'غير نشط'
                            }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            الأطباء المرتبطون
                        </dt>
                        <dd class="text-sm">
                            {{ viewingDepartment.doctor_profiles_count }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            آخر تحديث بواسطة
                        </dt>
                        <dd class="text-sm">
                            {{ viewingDepartment.updater?.name ?? '-' }}
                        </dd>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            الوصف
                        </dt>
                        <dd class="text-sm leading-6 text-muted-foreground">
                            {{ viewingDepartment.description ?? 'لا يوجد وصف' }}
                        </dd>
                    </div>
                </dl>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="neumorphic"
                        @click="closeViewDepartment"
                    >
                        إغلاق
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="editingDepartment !== null"
            @update:open="(open) => !open && closeEditDepartment()"
        >
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>تعديل القسم</DialogTitle>
                    <DialogDescription>
                        تحديث اسم القسم، الحالة، والوصف.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-if="editingDepartment && can('department.update')"
                    v-bind="
                        DepartmentController.update.form(editingDepartment.id)
                    "
                    class="space-y-4"
                    :options="{ preserveScroll: true }"
                    @success="closeEditDepartment"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_department_name">الاسم</Label>
                            <Input
                                id="edit_department_name"
                                name="name"
                                :value="editingDepartment.name"
                                required
                                class="pattern-field-clay"
                            />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_department_code">الرمز</Label>
                            <Input
                                id="edit_department_code"
                                name="code"
                                :value="editingDepartment.code ?? ''"
                                class="pattern-field-clay"
                            />
                            <InputError :message="errors.code" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_department_description">الوصف</Label>
                        <textarea
                            id="edit_department_description"
                            name="description"
                            rows="3"
                            class="pattern-field-clay"
                            :value="editingDepartment.description ?? ''"
                        />
                        <InputError :message="errors.description" />
                    </div>

                    <div
                        class="flex items-center gap-2 rounded-xl border border-border/60 bg-background/50 px-3 py-2"
                    >
                        <input type="hidden" name="is_active" value="0" />
                        <input
                            id="edit_department_is_active"
                            name="is_active"
                            type="checkbox"
                            value="1"
                            class="size-4 rounded border-border"
                            :checked="editingDepartment.is_active"
                        />
                        <Label for="edit_department_is_active" class="text-sm">
                            قسم نشط
                        </Label>
                    </div>
                    <InputError :message="errors.is_active" />

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="neumorphic"
                            :disabled="processing"
                            @click="closeEditDepartment"
                        >
                            إلغاء
                        </Button>
                        <Button
                            type="submit"
                            variant="clay"
                            :disabled="processing"
                        >
                            حفظ التغييرات
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <ConfirmationDialog
            :open="isConfirmOpen"
            :options="confirmOptions"
            @confirm="handleConfirmDelete"
            @cancel="handleConfirmCancel"
            @update:open="handleConfirmCancel"
        />
    </div>
</template>
