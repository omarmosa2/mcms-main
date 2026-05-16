<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, Plus } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import DoctorProfileController from '@/actions/App/Http/Controllers/Doctors/DoctorProfileController';
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';

type DoctorProfileStatus = 'active' | 'on_leave' | 'inactive';

type DoctorOption = {
    id: number;
    name: string;
    email: string | null;
};

type DepartmentOption = {
    id: number;
    name: string;
    code: string | null;
    is_active: boolean;
};

type DoctorProfile = {
    id: number;
    clinic_id: number;
    user_id: number;
    department_id: number | null;
    license_number: string | null;
    specialty: string;
    consultation_duration_minutes: number;
    status: DoctorProfileStatus;
    work_schedule: Record<string, unknown> | null;
    bio: string | null;
    user?: DoctorOption | null;
    department?: DepartmentOption | null;
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

type DoctorProfileSortField =
    | 'specialty'
    | 'license_number'
    | 'consultation_duration_minutes'
    | 'status'
    | 'created_at';

type SortDirection = 'asc' | 'desc';
type StatusFilter = 'all' | DoctorProfileStatus;

const { doctor_profiles, doctors, departments, status_options, filters } = defineProps<{
    doctor_profiles: PaginatedResponse<DoctorProfile>;
    doctors: DoctorOption[];
    departments: DepartmentOption[];
    status_options: DoctorProfileStatus[];
    filters: {
        status: DoctorProfileStatus | null;
        department_id: number | null;
        search: string | null;
        per_page: number;
        sort_by: DoctorProfileSortField | null;
        sort_direction: SortDirection | null;
    };
    is_doctor_scope: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'الأطباء',
                href: DoctorProfileController.index(),
            },
        ],
    },
});

const { can } = usePermissions();
const { isOpen: isConfirmOpen, options: confirmOptions, confirm, handleConfirm: handleConfirmDelete, handleCancel: handleConfirmCancel } = useConfirm();
const toast = useToast();
const page = usePage();
const viewingProfile = ref<DoctorProfile | null>(null);
const editingProfile = ref<DoctorProfile | null>(null);
const isCreateSheetOpen = ref(false);

const roleNames = computed<string[]>(() => {
    return (
        ((page.props.auth as { roles?: string[] } | undefined)?.roles ?? [])
            .filter((value): value is string => typeof value === 'string')
    );
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

    return rolePriority.find((role) => roleNames.value.includes(role)) ?? 'staff';
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

const activeRoleLabel = computed<string>(() => roleLabels[primaryRole.value] ?? roleLabels.staff);

const statusLabels: Record<DoctorProfileStatus, string> = {
    active: 'نشط',
    on_leave: 'في إجازة',
    inactive: 'غير نشط',
};

const localSearch = ref<string>(filters.search ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(doctor_profiles.meta.current_page);

const resolveInitialStatusFilter = (): StatusFilter => {
    return filters.status ?? 'all';
};

const localStatus = ref<StatusFilter>(resolveInitialStatusFilter());
const localDepartmentId = ref<number | null>(filters.department_id);

const allowedSortFields: DoctorProfileSortField[] = [
    'specialty',
    'license_number',
    'consultation_duration_minutes',
    'status',
    'created_at',
];

const resolveInitialSortBy = (): DoctorProfileSortField => {
    const sortBy = filters.sort_by;

    if (sortBy !== null && allowedSortFields.includes(sortBy as DoctorProfileSortField)) {
        return sortBy;
    }

    return 'created_at';
};

const localSortBy = ref<DoctorProfileSortField>(resolveInitialSortBy());
const localSortDirection = ref<SortDirection>(
    filters.sort_direction === 'asc' ? 'asc' : 'desc',
);

const visibleProfiles = computed<DoctorProfile[]>(() => doctor_profiles.data);
const totalLocalPages = computed<number>(() => Math.max(1, doctor_profiles.meta.last_page));
const localVisibleFrom = computed<number>(() => doctor_profiles.meta.from ?? 0);
const localVisibleTo = computed<number>(() => doctor_profiles.meta.to ?? 0);

const defaultRowsPerPage = 15;
const isSyncingFromServer = ref(false);
let profileFiltersDebounceTimeout: ReturnType<typeof setTimeout> | null = null;

const buildIndexQuery = (
    overrides: Partial<{
        status: StatusFilter;
        department_id: number | null;
        search: string;
        per_page: number;
        page: number;
        sort_by: DoctorProfileSortField;
        sort_direction: SortDirection;
    }> = {},
): {
    status?: DoctorProfileStatus;
    department_id?: number;
    search?: string;
    per_page: number;
    page: number;
    sort_by: DoctorProfileSortField;
    sort_direction: SortDirection;
} => {
    const statusValue = overrides.status ?? localStatus.value;
    const departmentIdValue = overrides.department_id ?? localDepartmentId.value;

    return {
        status: statusValue === 'all' ? undefined : statusValue,
        department_id: departmentIdValue ?? undefined,
        search: (overrides.search ?? localSearch.value).trim(),
        per_page: overrides.per_page ?? localRowsPerPage.value,
        page: overrides.page ?? localPage.value,
        sort_by: overrides.sort_by ?? localSortBy.value,
        sort_direction: overrides.sort_direction ?? localSortDirection.value,
    };
};

const reloadProfiles = (
    overrides: Partial<{
        status: StatusFilter;
        department_id: number | null;
        search: string;
        per_page: number;
        page: number;
        sort_by: DoctorProfileSortField;
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
            DoctorProfileController.index.url(),
            buildIndexQuery(overrides),
            {
                only: ['doctor_profiles', 'filters'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    if (debounce) {
        if (profileFiltersDebounceTimeout !== null) {
            clearTimeout(profileFiltersDebounceTimeout);
        }

        profileFiltersDebounceTimeout = setTimeout(executeReload, 320);

        return;
    }

    executeReload();
};

const sortIconFor = (field: DoctorProfileSortField) => {
    if (localSortBy.value !== field) {
        return ArrowUpDown;
    }

    return localSortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

const toggleSort = (field: DoctorProfileSortField): void => {
    if (localSortBy.value === field) {
        localSortDirection.value = localSortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        localSortBy.value = field;
        localSortDirection.value = 'asc';
    }
};

const resetLocalFilters = (): void => {
    isSyncingFromServer.value = true;
    localSearch.value = '';
    localStatus.value = 'all';
    localDepartmentId.value = null;
    localRowsPerPage.value = defaultRowsPerPage;
    localSortBy.value = 'created_at';
    localSortDirection.value = 'desc';
    localPage.value = 1;
    isSyncingFromServer.value = false;

    reloadProfiles({
        status: 'all',
        department_id: null,
        search: '',
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
    reloadProfiles({ page: localPage.value });
};

const goToNextPage = (): void => {
    if (localPage.value >= totalLocalPages.value) {
        return;
    }

    localPage.value += 1;
    reloadProfiles({ page: localPage.value });
};

watch(
    () => [
        filters.status,
        filters.department_id,
        filters.search,
        filters.per_page,
        filters.sort_by,
        filters.sort_direction,
        doctor_profiles.meta.current_page,
    ],
    () => {
        isSyncingFromServer.value = true;
        localStatus.value = resolveInitialStatusFilter();
        localDepartmentId.value = filters.department_id;
        localSearch.value = filters.search ?? '';
        localRowsPerPage.value = filters.per_page;
        localSortBy.value = resolveInitialSortBy();
        localSortDirection.value = filters.sort_direction === 'asc' ? 'asc' : 'desc';
        localPage.value = doctor_profiles.meta.current_page;
        isSyncingFromServer.value = false;
    },
    { immediate: true },
);

watch(
    () => localSearch.value,
    () => {
        localPage.value = 1;
        reloadProfiles({ page: 1, search: localSearch.value.trim() }, true);
    },
);

watch(
    () => localStatus.value,
    () => {
        localPage.value = 1;
        reloadProfiles({ page: 1, status: localStatus.value });
    },
);

watch(
    () => localDepartmentId.value,
    () => {
        localPage.value = 1;
        reloadProfiles({ page: 1, department_id: localDepartmentId.value });
    },
);

watch(
    () => localRowsPerPage.value,
    () => {
        localPage.value = 1;
        reloadProfiles({ page: 1, per_page: localRowsPerPage.value });
    },
);

watch(
    () => [localSortBy.value, localSortDirection.value],
    () => {
        localPage.value = 1;
        reloadProfiles({
            page: 1,
            sort_by: localSortBy.value,
            sort_direction: localSortDirection.value,
        });
    },
);

onBeforeUnmount(() => {
    if (profileFiltersDebounceTimeout !== null) {
        clearTimeout(profileFiltersDebounceTimeout);
        profileFiltersDebounceTimeout = null;
    }
});

const selectedProfileIds = ref<number[]>([]);

const selectableProfileIds = computed<number[]>(() =>
    visibleProfiles.value.map((profile) => profile.id),
);

const areAllProfilesSelected = computed<boolean>(() => {
    if (selectableProfileIds.value.length === 0) {
        return false;
    }

    return selectableProfileIds.value.every((profileId) =>
        selectedProfileIds.value.includes(profileId),
    );
});

watch(
    () => selectableProfileIds.value,
    (ids) => {
        selectedProfileIds.value = selectedProfileIds.value.filter((id) => ids.includes(id));
    },
);

const toggleAllProfilesSelection = (event: Event): void => {
    const target = event.target as HTMLInputElement;

    selectedProfileIds.value = target.checked ? [...selectableProfileIds.value] : [];
};

const clearSelectedProfiles = (): void => {
    selectedProfileIds.value = [];
};

const openViewProfile = (profile: DoctorProfile): void => {
    viewingProfile.value = profile;
};

const closeViewProfile = (): void => {
    viewingProfile.value = null;
};

const openEditProfile = (profile: DoctorProfile): void => {
    editingProfile.value = profile;
};

const closeEditProfile = (): void => {
    editingProfile.value = null;
};

const formatStatus = (status: DoctorProfileStatus): string => {
    return statusLabels[status] ?? status.replace('_', ' ');
};

const statusClass = (status: DoctorProfileStatus): string => {
    if (status === 'active') {
        return 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/35 dark:bg-success-500/15 dark:text-success-100';
    }

    if (status === 'on_leave') {
        return 'border-warning-300/70 bg-warning-50 text-warning-800 dark:border-warning-500/35 dark:bg-warning-500/15 dark:text-warning-100';
    }

    return 'border-destructive/70 bg-destructive/10 text-destructive dark:border-destructive/35 dark:bg-destructive/15 dark:text-destructive-foreground';
};

const statusDotClass = (status: DoctorProfileStatus): string => {
    if (status === 'active') {
        return 'bg-success-500';
    }

    if (status === 'on_leave') {
        return 'bg-warning-500';
    }

    return 'bg-destructive';
};

const doctorLabel = (profile: DoctorProfile): string => {
    return profile.user?.name ?? `Doctor #${profile.user_id}`;
};

const departmentLabel = (profile: DoctorProfile): string => {
    if (profile.department === null || profile.department === undefined) {
        return 'غير معين';
    }

    return profile.department.code !== null
        ? `${profile.department.name} (${profile.department.code})`
        : profile.department.name;
};

const stringifyWorkSchedule = (workSchedule: Record<string, unknown> | null): string => {
    if (workSchedule === null) {
        return '';
    }

    return JSON.stringify(workSchedule, null, 2);
};

const activeFilters = computed(() => {
    const filters: { key: string; label: string; value: string | null }[] = [];

    if (localSearch.value.trim()) {
        filters.push({ key: 'search', label: 'بحث', value: localSearch.value.trim() });
    }

    if (localStatus.value !== 'all') {
        filters.push({ key: 'status', label: 'الحالة', value: formatStatus(localStatus.value as DoctorProfileStatus) });
    }

    if (localDepartmentId.value) {
        const dept = departments.find(d => d.id === localDepartmentId.value);
        filters.push({ key: 'department_id', label: 'القسم', value: dept?.name || String(localDepartmentId.value) });
    }

    return filters;
});

const statusOptions = computed(() => {
    return [{ label: 'الكل', value: 'all' }, ...status_options.map(s => ({ label: formatStatus(s), value: s }))];
});

const departmentOptions = computed(() =>
    departments.map(dept => ({ label: dept.name, value: dept.id }))
);

const handleRemoveFilter = (key: string) => {
    if (key === 'search') {
        localSearch.value = '';
    } else if (key === 'status') {
        localStatus.value = 'all';
    } else if (key === 'department_id') {
        localDepartmentId.value = null;
    }
};

const deleteDoctorProfile = async (profile: DoctorProfile) => {
    const confirmed = await confirm({
        title: 'حذف ملف الطبيب',
        description: `هل أنت متأكد من حذف الطبيب "${profile.user?.name || profile.specialty}"؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(DoctorProfileController.destroy(profile.id), {
            onSuccess: () => {
                toast.success('تم حذف ملف الطبيب بنجاح');
            },
            onError: () => {
                toast.error('فشل حذف ملف الطبيب');
            },
        });
    }
};
</script>

<template>
    <Head title="الأطباء" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="page-title">الأطباء</h1>
                    <p class="mt-1 text-sm text-muted-foreground">إدارة ملفات الأطباء والتخصصات وتعيين الأقسام.</p>
                </div>
                <span class="inline-flex items-center rounded-full border border-border/60 bg-muted/40 px-2.5 py-0.5 text-[0.7rem] font-medium text-muted-foreground">
                    {{ activeRoleLabel }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="can('doctor_profile.create')"
                    variant="clay"
                    size="sm"
                    class="h-8 rounded-lg px-3 text-xs"
                    @click="isCreateSheetOpen = true"
                >
                    <Plus class="size-3.5" />
                    إنشاء ملف طبيب
                </Button>
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-3">
            <section
                :class="[
                    'glass-panel-soft p-5',
                    can('doctor_profile.create') ? 'xl:col-span-3' : 'xl:col-span-3',
                ]"
            >
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                    <h3 class="pattern-typographic-title text-[0.76rem]">
                        ملفات الأطباء
                    </h3>
                    <span class="text-xs text-muted-foreground">
                        الإجمالي: {{ doctor_profiles.meta.total }}
                    </span>
                </div>

                <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
                    <div class="grid gap-3 md:grid-cols-[repeat(4,minmax(0,1fr))] md:items-end">
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="doctor_profiles_search">بحث</Label>
                            <FilterSearch
                                id="doctor_profiles_search"
                                v-model="localSearch"
                                placeholder="طبيب، تخصص، ترخيص، أو قسم"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_profiles_status">الحالة</Label>
                            <FilterSelect
                                id="doctor_profiles_status"
                                v-model="localStatus"
                                :options="statusOptions"
                                placeholder="الكل"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_profiles_department">القسم</Label>
                            <FilterSelect
                                id="doctor_profiles_department"
                                v-model="localDepartmentId"
                                :options="departmentOptions"
                                placeholder="الكل"
                            />
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-[repeat(2,minmax(0,1fr))] md:items-end">
                        <div class="grid gap-2">
                            <Label for="doctor_profiles_per_page">صفوف</Label>
                            <select
                                id="doctor_profiles_per_page"
                                v-model.number="localRowsPerPage"
                                class="pattern-field-clay h-9 px-3 py-1.5"
                            >
                                <option :value="10">10</option>
                                <option :value="15">15</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
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
                    v-if="can('doctor_profile.delete') && selectedProfileIds.length > 0"
                    v-bind="DoctorProfileController.bulkDestroy.form()"
                    class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3"
                    v-slot="{ processing }"
                    @success="clearSelectedProfiles"
                >
                    <input
                        v-for="profileId in selectedProfileIds"
                        :key="`selected-doctor-profile-${profileId}`"
                        type="hidden"
                        name="ids[]"
                        :value="profileId"
                    />

                    <Button
                        type="submit"
                        variant="destructive"
                        size="sm"
                        class="h-8 px-3 text-xs"
                        :disabled="processing"
                    >
                        حذف المحدد ({{ selectedProfileIds.length }})
                    </Button>

                    <Button
                        type="button"
                        variant="neumorphic"
                        size="sm"
                        class="h-8 px-3 text-xs"
                        :disabled="processing"
                        @click="clearSelectedProfiles"
                    >
                        إلغاء التحديد
                    </Button>
                </Form>

                <div class="overflow-x-auto rounded-2xl border border-border/70">
                    <table class="ui-table min-w-full text-sm">
                        <thead class="ui-table-head">
                            <tr>
                                <th
                                    v-if="can('doctor_profile.delete')"
                                    class="w-10 px-3 py-2"
                                >
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-border"
                                        :checked="areAllProfilesSelected"
                                        @change="toggleAllProfilesSelection"
                                    />
                                </th>
                                <th class="px-3 py-2">الطبيب</th>
                                <th class="px-3 py-2">القسم</th>
                                <th class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                        @click="toggleSort('specialty')"
                                    >
                                        التخصص
                                        <component
                                            :is="sortIconFor('specialty')"
                                            class="size-3.5"
                                        />
                                    </button>
                                </th>
                                <th class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                        @click="toggleSort('license_number')"
                                    >
                                        الترخيص
                                        <component
                                            :is="sortIconFor('license_number')"
                                            class="size-3.5"
                                        />
                                    </button>
                                </th>
                                <th class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                        @click="toggleSort('consultation_duration_minutes')"
                                    >
                                        المدة
                                        <component
                                            :is="sortIconFor('consultation_duration_minutes')"
                                            class="size-3.5"
                                        />
                                    </button>
                                </th>
                                <th class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                        @click="toggleSort('status')"
                                    >
                                        الحالة
                                        <component
                                            :is="sortIconFor('status')"
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
                                <th class="px-3 py-2 text-right">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="profile in visibleProfiles"
                                :key="profile.id"
                                class="ui-table-row align-top"
                            >
                                <td
                                    v-if="can('doctor_profile.delete')"
                                    class="px-3 py-2"
                                >
                                    <input
                                        v-model="selectedProfileIds"
                                        type="checkbox"
                                        class="size-4 rounded border-border"
                                        :value="profile.id"
                                    />
                                </td>

                                <td class="px-3 py-2 font-medium">
                                    <div class="leading-5">
                                        <p class="text-sm font-semibold">
                                            {{ doctorLabel(profile) }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ profile.user?.email ?? '-' }}
                                        </p>
                                    </div>
                                </td>

                                <td class="px-3 py-2">
                                    <span class="text-sm">
                                        {{ departmentLabel(profile) }}
                                    </span>
                                </td>

                                <td class="px-3 py-2">
                                    {{ profile.specialty }}
                                </td>

                                <td class="px-3 py-2">
                                    {{ profile.license_number ?? '-' }}
                                </td>

                                <td class="px-3 py-2">
                                    {{ profile.consultation_duration_minutes }} دقيقة
                                </td>

                                <td class="px-3 py-2">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium capitalize"
                                        :class="statusClass(profile.status)"
                                    >
                                        <span
                                            class="w-1.5 h-1.5 rounded-full"
                                            :class="statusDotClass(profile.status)"
                                        ></span>
                                        {{ formatStatus(profile.status) }}
                                    </span>
                                </td>

                                <td class="px-3 py-2">
                                    {{
                                        profile.created_at !== null
                                            ? new Date(profile.created_at).toLocaleDateString('ar-SA')
                                            : '-'
                                    }}
                                </td>

                                <td class="table-cell-actions px-3 py-2 md:text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="neumorphic"
                                            size="sm"
                                            class="h-8 px-3 text-xs"
                                            @click="openViewProfile(profile)"
                                        >
                                            عرض
                                        </Button>

                                        <Button
                                            v-if="can('doctor_profile.update')"
                                            type="button"
                                            variant="clay"
                                            size="sm"
                                            class="h-8 px-3 text-xs"
                                            @click="openEditProfile(profile)"
                                        >
                                            تعديل
                                        </Button>

                                        <Button
                                            v-if="can('doctor_profile.delete')"
                                            type="button"
                                            size="sm"
                                            variant="destructive"
                                            class="h-8 px-3 text-xs"
                                            @click="deleteDoctorProfile(profile)"
                                        >
                                            حذف
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr
                                v-if="visibleProfiles.length === 0"
                                class="table-empty-state"
                            >
                                <td
                                    :colspan="can('doctor_profile.delete') ? 9 : 8"
                                    class="px-3 py-10 text-center text-muted-foreground"
                                >
                                    لا توجد ملفات أطباء مطابقة.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2"
                >
                    <p class="text-xs text-muted-foreground">
                        عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من {{ doctor_profiles.meta.total }} سجل
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
            </section>
        </div>

        <Sheet :open="isCreateSheetOpen" @update:open="isCreateSheetOpen = $event">
            <SheetContent side="left" class="w-full sm:max-w-lg">
                <SheetHeader class="text-right">
                    <SheetTitle>إنشاء ملف طبيب</SheetTitle>
                    <SheetDescription>إضافة ملف طبيب جديد.</SheetDescription>
                </SheetHeader>

                <Form
                    v-bind="DoctorProfileController.store.form()"
                    class="mt-6 space-y-4"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="doctor_user_id">الطبيب</Label>
                        <select
                            id="doctor_user_id"
                            name="user_id"
                            required
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="" disabled selected>
                                اختر طبيب
                            </option>
                            <option
                                v-for="doctor in doctors"
                                :key="`doctor-option-${doctor.id}`"
                                :value="doctor.id"
                            >
                                {{ doctor.name }}{{ doctor.email ? ` (${doctor.email})` : '' }}
                            </option>
                        </select>
                        <InputError :message="errors.user_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="doctor_department_id">القسم</Label>
                        <select
                            id="doctor_department_id"
                            name="department_id"
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="">
                                غير معين
                            </option>
                            <option
                                v-for="department in departments"
                                :key="`department-option-${department.id}`"
                                :value="department.id"
                            >
                                {{
                                    department.code !== null
                                        ? `${department.name} (${department.code})`
                                        : department.name
                                }}
                            </option>
                        </select>
                        <InputError :message="errors.department_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="doctor_specialty">التخصص</Label>
                        <Input
                            id="doctor_specialty"
                            name="specialty"
                            required
                            placeholder="الطب الباطني"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.specialty" />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_license_number">رقم الترخيص</Label>
                            <Input
                                id="doctor_license_number"
                                name="license_number"
                                placeholder="LIC-20260012"
                                class="pattern-field-clay"
                            />
                            <InputError :message="errors.license_number" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_consultation_duration">المدة (دقيقة)</Label>
                            <Input
                                id="doctor_consultation_duration"
                                name="consultation_duration_minutes"
                                type="number"
                                min="5"
                                max="480"
                                value="30"
                                class="pattern-field-clay"
                            />
                            <InputError :message="errors.consultation_duration_minutes" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="doctor_status">الحالة</Label>
                        <select
                            id="doctor_status"
                            name="status"
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option
                                v-for="statusOption in status_options"
                                :key="`status-option-${statusOption}`"
                                :value="statusOption"
                                :selected="statusOption === 'active'"
                            >
                                {{ formatStatus(statusOption) }}
                            </option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="doctor_work_schedule">جدول العمل (JSON)</Label>
                        <textarea
                            id="doctor_work_schedule"
                            name="work_schedule"
                            rows="4"
                            class="pattern-field-clay font-mono text-xs"
                            placeholder='{"sunday":["09:00-13:00"],"monday":["09:00-13:00","17:00-20:00"]}'
                        />
                        <InputError :message="errors.work_schedule" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="doctor_bio">السيرة الذاتية</Label>
                        <textarea
                            id="doctor_bio"
                            name="bio"
                            rows="3"
                            class="pattern-field-clay"
                            placeholder="نبذة تعريفية قصيرة"
                        />
                        <InputError :message="errors.bio" />
                    </div>

                    <p
                        v-if="doctors.length === 0"
                        class="rounded-xl border border-amber-300/60 bg-amber-100/65 px-3 py-2 text-xs text-amber-900 dark:border-amber-500/35 dark:bg-amber-500/12 dark:text-amber-100"
                    >
                        لا يوجد أطباء في هذه العيادة بعد.
                    </p>

                    <Button
                        :disabled="processing || doctors.length === 0"
                        variant="clay"
                        class="w-full"
                    >
                        إنشاء ملف طبيب
                    </Button>
                </Form>
            </SheetContent>
        </Sheet>

        <Dialog
            :open="viewingProfile !== null"
            @update:open="(open) => !open && closeViewProfile()"
        >
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ viewingProfile ? doctorLabel(viewingProfile) : 'تفاصيل ملف الطبيب' }}
                    </DialogTitle>
                    <DialogDescription>
                        تفاصيل الملف، رابط القسم، وجدول العمل.
                    </DialogDescription>
                </DialogHeader>

                <dl
                    v-if="viewingProfile"
                    class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4 sm:grid-cols-2"
                >
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">
                            التخصص
                        </dt>
                        <dd class="text-sm">
                            {{ viewingProfile.specialty }}
                        </dd>
                    </div>

                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">
                            الحالة
                        </dt>
                        <dd class="text-sm capitalize">
                            {{ formatStatus(viewingProfile.status) }}
                        </dd>
                    </div>

                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">
                            القسم
                        </dt>
                        <dd class="text-sm">
                            {{ departmentLabel(viewingProfile) }}
                        </dd>
                    </div>

                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">
                            مدة الاستشارة
                        </dt>
                        <dd class="text-sm">
                            {{ viewingProfile.consultation_duration_minutes }} دقيقة
                        </dd>
                    </div>

                    <div class="space-y-1 sm:col-span-2">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">
                            رقم الترخيص
                        </dt>
                        <dd class="text-sm">
                            {{ viewingProfile.license_number ?? '-' }}
                        </dd>
                    </div>

                    <div class="space-y-1 sm:col-span-2">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">
                            جدول العمل
                        </dt>
                        <dd class="rounded-lg border border-border/60 bg-background/60 p-3">
                            <pre class="overflow-x-auto text-xs text-muted-foreground">{{ stringifyWorkSchedule(viewingProfile.work_schedule) || '-' }}</pre>
                        </dd>
                    </div>

                    <div class="space-y-1 sm:col-span-2">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">
                            السيرة الذاتية
                        </dt>
                        <dd class="text-sm leading-6 text-muted-foreground">
                            {{ viewingProfile.bio ?? 'لا توجد سيرة ذاتية' }}
                        </dd>
                    </div>
                </dl>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="closeViewProfile"
                    >
                        إغلاق
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="editingProfile !== null"
            @update:open="(open) => !open && closeEditProfile()"
        >
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>تعديل ملف الطبيب</DialogTitle>
                    <DialogDescription>
                        تحديث تعيين الطبيب، تفاصيل الملف، وجدول العمل.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-if="editingProfile && can('doctor_profile.update')"
                    v-bind="DoctorProfileController.update.form(editingProfile.id)"
                    class="space-y-4"
                    :options="{ preserveScroll: true }"
                    @success="closeEditProfile"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_doctor_user_id">الطبيب</Label>
                            <select
                                id="edit_doctor_user_id"
                                name="user_id"
                                class="pattern-field-clay h-9 px-3 py-1.5"
                                :value="editingProfile.user_id"
                            >
                                <option
                                    v-for="doctor in doctors"
                                    :key="`edit-doctor-option-${doctor.id}`"
                                    :value="doctor.id"
                                >
                                    {{ doctor.name }}{{ doctor.email ? ` (${doctor.email})` : '' }}
                                </option>
                            </select>
                            <InputError :message="errors.user_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit_doctor_department_id">القسم</Label>
                            <select
                                id="edit_doctor_department_id"
                                name="department_id"
                                class="pattern-field-clay h-9 px-3 py-1.5"
                                :value="editingProfile.department_id ?? ''"
                            >
                                <option value="">
                                    غير معين
                                </option>
                                <option
                                    v-for="department in departments"
                                    :key="`edit-department-option-${department.id}`"
                                    :value="department.id"
                                >
                                    {{
                                        department.code !== null
                                            ? `${department.name} (${department.code})`
                                            : department.name
                                    }}
                                </option>
                            </select>
                            <InputError :message="errors.department_id" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_doctor_specialty">التخصص</Label>
                            <Input
                                id="edit_doctor_specialty"
                                name="specialty"
                                :value="editingProfile.specialty"
                                required
                                class="pattern-field-clay"
                            />
                            <InputError :message="errors.specialty" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit_doctor_license_number">رقم الترخيص</Label>
                            <Input
                                id="edit_doctor_license_number"
                                name="license_number"
                                :value="editingProfile.license_number ?? ''"
                                class="pattern-field-clay"
                            />
                            <InputError :message="errors.license_number" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_doctor_consultation_duration">المدة (دقيقة)</Label>
                            <Input
                                id="edit_doctor_consultation_duration"
                                name="consultation_duration_minutes"
                                type="number"
                                min="5"
                                max="480"
                                :value="editingProfile.consultation_duration_minutes"
                                class="pattern-field-clay"
                            />
                            <InputError :message="errors.consultation_duration_minutes" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit_doctor_status">الحالة</Label>
                            <select
                                id="edit_doctor_status"
                                name="status"
                                class="pattern-field-clay h-9 px-3 py-1.5"
                                :value="editingProfile.status"
                            >
                                <option
                                    v-for="statusOption in status_options"
                                    :key="`edit-status-option-${statusOption}`"
                                    :value="statusOption"
                                >
                                    {{ formatStatus(statusOption) }}
                                </option>
                            </select>
                            <InputError :message="errors.status" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_doctor_work_schedule">جدول العمل (JSON)</Label>
                        <textarea
                            id="edit_doctor_work_schedule"
                            name="work_schedule"
                            rows="4"
                            class="pattern-field-clay font-mono text-xs"
                            :value="stringifyWorkSchedule(editingProfile.work_schedule)"
                        />
                        <InputError :message="errors.work_schedule" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_doctor_bio">السيرة الذاتية</Label>
                        <textarea
                            id="edit_doctor_bio"
                            name="bio"
                            rows="3"
                            class="pattern-field-clay"
                            :value="editingProfile.bio ?? ''"
                        />
                        <InputError :message="errors.bio" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="neumorphic"
                            :disabled="processing"
                            @click="closeEditProfile"
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
