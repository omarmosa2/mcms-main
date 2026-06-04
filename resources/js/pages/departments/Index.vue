<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import DepartmentController from '@/actions/App/Http/Controllers/Departments/DepartmentController';
import { AddDepartmentDialog } from '@/components/dialogs';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';
import DepartmentEditDialog from './components/DepartmentEditDialog.vue';
import DepartmentStatsCards from './components/DepartmentStatsCards.vue';
import DepartmentTable from './components/DepartmentTable.vue';
import DepartmentViewDialog from './components/DepartmentViewDialog.vue';
import type {
    ActiveFilter,
    Department,
    DepartmentSortField,
    PaginatedResponse,
    SortDirection,
} from './components/types';

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

const openEditDepartment = (department: Department): void => {
    editingDepartment.value = department;
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

        <DepartmentStatsCards
            :total-departments="departments.meta.total"
            :active-count="activeDepartmentsOnPage"
            :inactive-count="inactiveDepartmentsOnPage"
            :total-doctors="totalDoctors"
        />

        <DepartmentTable
            v-model:search="localSearch"
            v-model:active-filter="localIsActive"
            v-model:rows-per-page="localRowsPerPage"
            v-model:selected-ids="selectedDepartmentIds"
            :departments="departments.data"
            :page="localPage"
            :total-pages="totalLocalPages"
            :visible-from="localVisibleFrom"
            :visible-to="localVisibleTo"
            :total-departments="departments.meta.total"
            :sort-by="localSortBy"
            :sort-direction="localSortDirection"
            :are-all-selected="areAllDepartmentsSelected"
            :can-delete="can('department.delete')"
            :can-update="can('department.update')"
            @toggle-sort="toggleSort"
            @previous-page="goToPreviousPage"
            @next-page="goToNextPage"
            @reset-filters="resetLocalFilters"
            @toggle-all-selection="toggleAllDepartmentsSelection"
            @clear-selection="clearSelectedDepartments"
            @view="openViewDepartment"
            @edit="openEditDepartment"
            @delete="deleteDepartment"
        />

        <AddDepartmentDialog
            :open="isCreateDialogOpen"
            @update:open="(value) => (isCreateDialogOpen = value)"
            @success="() => {}"
        />

        <DepartmentViewDialog
            :open="viewingDepartment !== null"
            :department="viewingDepartment"
            @update:open="(val) => { if (!val) viewingDepartment = null }"
        />

        <DepartmentEditDialog
            :open="editingDepartment !== null"
            :department="editingDepartment"
            @update:open="(val) => { if (!val) editingDepartment = null }"
        />

        <ConfirmationDialog
            :open="isConfirmOpen"
            :options="confirmOptions"
            @confirm="handleConfirmDelete"
            @cancel="handleConfirmCancel"
            @update:open="handleConfirmCancel"
        />
    </div>
</template>