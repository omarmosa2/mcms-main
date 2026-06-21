<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ClinicController from '@/actions/App/Http/Controllers/Clinics/ClinicController';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';
import ClinicFormModal from './components/ClinicFormModal.vue';
import ClinicStatsCards from './components/ClinicStatsCards.vue';
import ClinicTable from './components/ClinicTable.vue';
import ClinicViewDialog from './components/ClinicViewDialog.vue';
import type {
    ActiveFilter,
    Clinic,
    ClinicSortField,
    PaginatedResponse,
    SortDirection,
} from './components/types';

const { clinics, filters } = defineProps<{
    clinics: PaginatedResponse<Clinic>;
    filters: {
        search: string | null;
        is_active: boolean | null;
        per_page: number;
        sort_by: ClinicSortField | null;
        sort_direction: SortDirection | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'العيادات',
                href: ClinicController.index(),
            },
        ],
    },
});

const { can } = usePermissions();
const {
    isOpen: isConfirmOpen,
    options: confirmOptions,
    confirm,
    close: closeConfirm,
    handleConfirm: handleConfirmDelete,
    handleCancel: handleConfirmCancel,
} = useConfirm();
const toast = useToast();
const page = usePage();

const viewingClinic = ref<Clinic | null>(null);
const editingClinic = ref<Clinic | null>(null);
const isCreateDialogOpen = ref(false);
const selectedClinicIds = ref<number[]>([]);

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

const activeRoleLabel = computed<string>(
    () => roleLabels[primaryRole.value] ?? roleLabels.staff,
);

const localSearch = ref<string>(filters.search ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(clinics.meta.current_page);

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

const allowedSortFields: ClinicSortField[] = [
    'name',
    'code',
    'is_active',
    'employees_count',
    'created_at',
];

const resolveInitialSortBy = (): ClinicSortField => {
    const sortBy = filters.sort_by;

    if (sortBy !== null && allowedSortFields.includes(sortBy as ClinicSortField)) {
        return sortBy;
    }

    return 'created_at';
};

const localSortBy = ref<ClinicSortField>(resolveInitialSortBy());
const localSortDirection = ref<SortDirection>(
    filters.sort_direction === 'asc' ? 'asc' : 'desc',
);

const visibleClinics = computed<Clinic[]>(() => clinics.data);
const totalLocalPages = computed<number>(() => Math.max(1, clinics.meta.last_page));
const localVisibleFrom = computed<number>(() => clinics.meta.from ?? 0);
const localVisibleTo = computed<number>(() => clinics.meta.to ?? 0);

const activeClinicsOnPage = computed<number>(
    () => visibleClinics.value.filter((clinic) => clinic.is_active).length,
);

const inactiveClinicsOnPage = computed<number>(
    () => visibleClinics.value.filter((clinic) => !clinic.is_active).length,
);

const totalDoctors = computed<number>(() =>
    visibleClinics.value.reduce(
        (sum, clinic) => sum + (clinic.employees_count ?? 0),
        0,
    ),
);

const selectableClinicIds = computed<number[]>(() =>
    visibleClinics.value.map((clinic) => clinic.id),
);

const areAllClinicsSelected = computed<boolean>(() => {
    if (selectableClinicIds.value.length === 0) {
        return false;
    }

    return selectableClinicIds.value.every((clinicId) =>
        selectedClinicIds.value.includes(clinicId),
    );
});

const defaultRowsPerPage = 15;
const isSyncingFromServer = ref(false);
let clinicFiltersDebounceTimeout: ReturnType<typeof setTimeout> | null = null;

const buildIndexQuery = (
    overrides: Partial<{
        search: string;
        is_active: ActiveFilter;
        per_page: number;
        page: number;
        sort_by: ClinicSortField;
        sort_direction: SortDirection;
    }> = {},
) => {
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

const reloadClinics = (
    overrides: Partial<{
        search: string;
        is_active: ActiveFilter;
        per_page: number;
        page: number;
        sort_by: ClinicSortField;
        sort_direction: SortDirection;
    }> = {},
    debounce = false,
): void => {
    if (isSyncingFromServer.value) {
        return;
    }

    const executeReload = (): void => {
        router.cancelAll();
        router.get(ClinicController.index.url(), buildIndexQuery(overrides), {
            only: ['clinics', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    if (debounce) {
        if (clinicFiltersDebounceTimeout !== null) {
            clearTimeout(clinicFiltersDebounceTimeout);
        }

        clinicFiltersDebounceTimeout = setTimeout(executeReload, 320);

        return;
    }

    executeReload();
};

const toggleSort = (field: ClinicSortField): void => {
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
    localIsActive.value = 'all';
    localRowsPerPage.value = defaultRowsPerPage;
    localSortBy.value = 'created_at';
    localSortDirection.value = 'desc';
    localPage.value = 1;
    isSyncingFromServer.value = false;

    reloadClinics({
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
    reloadClinics({ page: localPage.value });
};

const goToNextPage = (): void => {
    if (localPage.value >= totalLocalPages.value) {
        return;
    }

    localPage.value += 1;
    reloadClinics({ page: localPage.value });
};

watch(
    () => [
        filters.search,
        filters.is_active,
        filters.per_page,
        filters.sort_by,
        filters.sort_direction,
        clinics.meta.current_page,
    ],
    () => {
        isSyncingFromServer.value = true;
        localSearch.value = filters.search ?? '';
        localIsActive.value = resolveInitialActiveFilter();
        localRowsPerPage.value = filters.per_page;
        localSortBy.value = resolveInitialSortBy();
        localSortDirection.value = filters.sort_direction === 'asc' ? 'asc' : 'desc';
        localPage.value = clinics.meta.current_page;
        isSyncingFromServer.value = false;
    },
    { immediate: true },
);

watch(
    () => localSearch.value,
    () => {
        localPage.value = 1;
        reloadClinics({ page: 1, search: localSearch.value.trim() }, true);
    },
);

watch(
    () => localIsActive.value,
    () => {
        localPage.value = 1;
        reloadClinics({ page: 1, is_active: localIsActive.value });
    },
);

watch(
    () => localRowsPerPage.value,
    () => {
        localPage.value = 1;
        reloadClinics({ page: 1, per_page: localRowsPerPage.value });
    },
);

watch(
    () => [localSortBy.value, localSortDirection.value],
    () => {
        localPage.value = 1;
        reloadClinics({
            page: 1,
            sort_by: localSortBy.value,
            sort_direction: localSortDirection.value,
        });
    },
);

watch(
    () => selectableClinicIds.value,
    (ids) => {
        selectedClinicIds.value = selectedClinicIds.value.filter((id) =>
            ids.includes(id),
        );
    },
);

onBeforeUnmount(() => {
    if (clinicFiltersDebounceTimeout !== null) {
        clearTimeout(clinicFiltersDebounceTimeout);
        clinicFiltersDebounceTimeout = null;
    }
});

const toggleAllClinicsSelection = (event: Event): void => {
    const target = event.target as HTMLInputElement;

    selectedClinicIds.value = target.checked
        ? [...selectableClinicIds.value]
        : [];
};

const clearSelectedClinics = (): void => {
    selectedClinicIds.value = [];
};

const openViewClinic = (clinic: Clinic): void => {
    viewingClinic.value = clinic;
};

const openEditClinic = (clinic: Clinic): void => {
    editingClinic.value = clinic;
};

const deleteClinic = async (clinic: Clinic) => {
    const confirmed = await confirm({
        title: 'حذف العيادة',
        description: `هل أنت متأكد من حذف العيادة "${clinic.name}"؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(ClinicController.destroy(clinic.id), {
            onSuccess: () => {
                closeConfirm();
                toast.success('تم حذف العيادة بنجاح');
            },
            onError: () => {
                toast.error('فشل حذف العيادة');
            },
        });
    }
};
</script>

<template>
    <Head title="العيادات" />

    <div class="container-modern space-y-8 py-5" dir="rtl">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex items-center gap-4">
                <div>
                    <h1 class="page-title text-[2.35rem]">إدارة العيادات</h1>
                    <p class="page-subtitle mt-2 text-base">
                        إدارة بيانات العيادات وأيام وساعات الدوام المتاحة للحجز
                    </p>
                </div>
                <span class="inline-flex items-center rounded-full border border-border bg-card px-3 py-1 text-xs font-semibold text-muted-foreground">
                    {{ activeRoleLabel }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="can('department.create')"
                    variant="default"
                    size="lg"
                    class="h-12 rounded-2xl bg-primary px-6 text-sm font-bold text-primary-foreground shadow-primary/30 hover:bg-primary/90"
                    @click="isCreateDialogOpen = true"
                >
                    <Plus class="size-4" />
                    إضافة عيادة جديدة
                </Button>
            </div>
        </div>

        <ClinicStatsCards
            :total-departments="clinics.meta.total"
            :active-count="activeClinicsOnPage"
            :inactive-count="inactiveClinicsOnPage"
            :total-doctors="totalDoctors"
        />

        <ClinicTable
            v-model:search="localSearch"
            v-model:active-filter="localIsActive"
            v-model:rows-per-page="localRowsPerPage"
            v-model:selected-ids="selectedClinicIds"
            :clinics="clinics.data"
            :page="localPage"
            :total-pages="totalLocalPages"
            :visible-from="localVisibleFrom"
            :visible-to="localVisibleTo"
            :total-clinics="clinics.meta.total"
            :sort-by="localSortBy"
            :sort-direction="localSortDirection"
            :are-all-selected="areAllClinicsSelected"
            :can-delete="can('department.delete')"
            :can-update="can('department.update')"
            @toggle-sort="toggleSort"
            @previous-page="goToPreviousPage"
            @next-page="goToNextPage"
            @reset-filters="resetLocalFilters"
            @toggle-all-selection="toggleAllClinicsSelection"
            @clear-selection="clearSelectedClinics"
            @view="openViewClinic"
            @edit="openEditClinic"
            @delete="deleteClinic"
        />

        <ClinicFormModal
            :open="isCreateDialogOpen"
            @update:open="(value) => (isCreateDialogOpen = value)"
            @saved="() => {}"
        />

        <ClinicViewDialog
            :open="viewingClinic !== null"
            :department="viewingClinic"
            @update:open="(val) => { if (!val) viewingClinic = null }"
        />

        <ClinicFormModal
            :open="editingClinic !== null"
            :department="editingClinic"
            @update:open="(val) => { if (!val) editingClinic = null }"
            @saved="editingClinic = null"
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
