<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, Plus } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import DoctorProfileController from '@/actions/App/Http/Controllers/Doctors/DoctorProfileController';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';
import DoctorTable from './components/DoctorTable.vue';
import DoctorCreateSheet from './components/DoctorCreateSheet.vue';
import DoctorViewDialog from './components/DoctorViewDialog.vue';
import DoctorEditDialog from './components/DoctorEditDialog.vue';

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

const formatStatus = (status: DoctorProfileStatus): string => {
    return statusLabels[status] ?? status.replace('_', ' ');
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

const handleViewProfile = (profile: DoctorProfile): void => {
    viewingProfile.value = profile;
};

const closeViewProfile = (): void => {
    viewingProfile.value = null;
};

const handleEditProfile = (profile: DoctorProfile): void => {
    editingProfile.value = profile;
};

const closeEditProfile = (): void => {
    editingProfile.value = null;
};

const handleDeleteProfile = (profile: DoctorProfile): void => {
    deleteDoctorProfile(profile);
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
            <DoctorTable
                :doctor-profiles="doctor_profiles"
                :visible-profiles="visibleProfiles"
                :local-search="localSearch"
                :local-status="localStatus"
                :local-department-id="localDepartmentId"
                :local-rows-per-page="localRowsPerPage"
                :local-page="localPage"
                :local-sort-by="localSortBy"
                :local-sort-direction="localSortDirection"
                :total-local-pages="totalLocalPages"
                :local-visible-from="localVisibleFrom"
                :local-visible-to="localVisibleTo"
                :selected-profile-ids="selectedProfileIds"
                :are-all-profiles-selected="areAllProfilesSelected"
                :can-delete-profile="can('doctor_profile.delete')"
                :active-filters="activeFilters"
                :status-options="statusOptions"
                :department-options="departmentOptions"
                @update:local-search="localSearch = $event"
                @update:local-status="localStatus = $event"
                @update:local-department-id="localDepartmentId = $event"
                @update:local-rows-per-page="localRowsPerPage = $event"
                @update-selected-profile-ids="selectedProfileIds = $event"
                @toggle-sort="toggleSort($event)"
                @previous-page="goToPreviousPage()"
                @next-page="goToNextPage()"
                @reset-filters="resetLocalFilters()"
                @remove-filter="handleRemoveFilter($event)"
                @toggle-all-selection="toggleAllProfilesSelection($event)"
                @view-profile="handleViewProfile($event)"
                @edit-profile="handleEditProfile($event)"
                @delete-profile="handleDeleteProfile($event)"
            />
        </div>

        <DoctorCreateSheet
            :open="isCreateSheetOpen"
            :doctors="doctors"
            :departments="departments"
            :status-options="status_options"
            @update:open="isCreateSheetOpen = $event"
        />

        <DoctorViewDialog
            :profile="viewingProfile"
            @close="closeViewProfile"
        />

        <DoctorEditDialog
            :profile="editingProfile"
            :doctors="doctors"
            :departments="departments"
            :status-options="status_options"
            @close="closeEditProfile"
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
