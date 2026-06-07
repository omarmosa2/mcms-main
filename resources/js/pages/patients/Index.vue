<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, Plus, Upload } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import PatientImportExportController from '@/actions/App/Http/Controllers/Patients/PatientImportExportController';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';
import PatientCreateSheet from './components/PatientCreateSheet.vue';
import PatientDeleteDialog from './components/PatientDeleteDialog.vue';
import PatientEditDialog from './components/PatientEditDialog.vue';
import PatientQuickAddForm from './components/PatientQuickAddForm.vue';
import PatientTable from './components/PatientTable.vue';
import PatientViewDialog from './components/PatientViewDialog.vue';
import type {
    ActiveFilter,
    PaginatedResponse,
    Patient,
    PatientSortField,
    SortDirection,
} from './components/types';

const { patients, filters } = defineProps<{
    patients: PaginatedResponse<Patient>;
    filters: {
        search: string | null;
        per_page: number;
        sort_by: PatientSortField | null;
        sort_direction: SortDirection | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'المرضى',
                href: PatientController.index(),
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

const viewingPatient = ref<Patient | null>(null);
const editingPatient = ref<Patient | null>(null);
const deletingPatient = ref<Patient | null>(null);
const isCreateSheetOpen = ref(false);
const isQuickAddOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const isDeleting = ref(false);

const localSearch = ref<string>(filters.search ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(patients.meta.current_page);

const allowedPatientSortFields: PatientSortField[] = [
    'file_number',
    'full_name',
    'date_of_birth',
    'gender',
    'phone',
    'email',
    'created_at',
];

const resolveInitialSortBy = (): PatientSortField => {
    const sortBy = filters.sort_by;

    if (
        sortBy !== null &&
        allowedPatientSortFields.includes(sortBy as PatientSortField)
    ) {
        return sortBy;
    }

    return 'created_at';
};

const localSortBy = ref<PatientSortField>(resolveInitialSortBy());
const localSortDirection = ref<SortDirection>(
    filters.sort_direction === 'asc' ? 'asc' : 'desc',
);

const isSyncingFromServer = ref(false);
let patientFiltersDebounceTimeout: ReturnType<typeof setTimeout> | null = null;

const totalLocalPages = computed<number>(() =>
    Math.max(1, patients.meta.last_page),
);
const localVisibleFrom = computed<number>(() => patients.meta.from ?? 0);
const localVisibleTo = computed<number>(() => patients.meta.to ?? 0);

const activeFilters = computed<ActiveFilter[]>(() => {
    const f: ActiveFilter[] = [];

    if (localSearch.value.trim()) {
        f.push({
            key: 'search',
            label: 'بحث',
            value: localSearch.value.trim(),
        });
    }

    return f;
});

const buildIndexQuery = (
    overrides: Partial<{
        search: string;
        per_page: number;
        page: number;
        sort_by: PatientSortField;
        sort_direction: SortDirection;
    }> = {},
): {
    search?: string;
    per_page: number;
    page: number;
    sort_by: PatientSortField;
    sort_direction: SortDirection;
} => {
    return {
        search: localSearch.value.trim(),
        per_page: localRowsPerPage.value,
        page: localPage.value,
        sort_by: localSortBy.value,
        sort_direction: localSortDirection.value,
        ...overrides,
    };
};

const reloadPatients = (
    overrides: Partial<{
        search: string;
        per_page: number;
        page: number;
        sort_by: PatientSortField;
        sort_direction: SortDirection;
    }> = {},
    debounce = false,
): void => {
    if (isSyncingFromServer.value) {
        return;
    }

    const executeReload = (): void => {
        router.cancelAll();
        router.get(PatientController.index.url(), buildIndexQuery(overrides), {
            only: ['patients', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    if (debounce) {
        if (patientFiltersDebounceTimeout !== null) {
            clearTimeout(patientFiltersDebounceTimeout);
        }

        patientFiltersDebounceTimeout = setTimeout(executeReload, 320);

        return;
    }

    executeReload();
};

const toggleSort = (field: PatientSortField): void => {
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
    localRowsPerPage.value = 15;
    localSortBy.value = 'created_at';
    localSortDirection.value = 'desc';
    localPage.value = 1;
    isSyncingFromServer.value = false;
    reloadPatients({
        search: '',
        per_page: 15,
        page: 1,
        sort_by: 'created_at',
        sort_direction: 'desc',
    });
};

const handleRemoveFilter = (key: string) => {
    if (key === 'search') {
        localSearch.value = '';
    }
};

const handleDeleteConfirm = async () => {
    if (!deletingPatient.value) {
        return;
    }

    isDeleting.value = true;

    try {
        await router.delete(
            PatientController.destroy(deletingPatient.value.id),
            {
                onSuccess: () => {
                    toast.success('تم حذف المريض بنجاح');
                    isDeleteDialogOpen.value = false;
                    deletingPatient.value = null;
                },
                onError: () => {
                    toast.error('فشل حذف المريض');
                },
            },
        );
    } finally {
        isDeleting.value = false;
    }
};

const handleDeletePatient = async (patient: Patient) => {
    const confirmed = await confirm({
        title: 'حذف المريض',
        description: `هل أنت متأكد من حذف المريض "${patient.full_name || patient.first_name + ' ' + patient.last_name}" (ملف: ${patient.file_number || '—'})؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(PatientController.destroy(patient.id), {
            onSuccess: () => {
                toast.success('تم حذف المريض بنجاح');
            },
            onError: () => {
                toast.error('فشل حذف المريض');
            },
        });
    }
};

const openEditPatient = (patient: Patient) => {
    editingPatient.value = patient;
};

const handleQuickAddCreated = () => {
    reloadPatients({ page: 1 });
};

const handleCompleteFile = (data: {
    id: number;
    name: string;
    dateOfBirth: string;
    gender: string;
    phone: string;
}) => {
    const nameParts = data.name.split(' ');
    editingPatient.value = {
        id: data.id,
        file_number: '',
        first_name: nameParts[0] ?? '',
        last_name: nameParts.slice(1).join(' ') ?? '',
        full_name: data.name,
        date_of_birth: data.dateOfBirth || null,
        age: null,
        gender: data.gender || null,
        phone: data.phone || null,
        email: null,
        national_id: null,
        emergency_contact_name: null,
        emergency_contact_phone: null,
        notes: null,
        chronic_conditions: [],
        allergies: [],
        current_medications: [],
        attachments: [],
        created_at: null,
        updated_at: null,
    };
};

watch(
    () => [
        filters.search,
        filters.per_page,
        filters.sort_by,
        filters.sort_direction,
        patients.meta.current_page,
    ],
    () => {
        isSyncingFromServer.value = true;
        localSearch.value = filters.search ?? '';
        localRowsPerPage.value = filters.per_page;
        localSortBy.value = resolveInitialSortBy();
        localSortDirection.value =
            filters.sort_direction === 'asc' ? 'asc' : 'desc';
        localPage.value = patients.meta.current_page;
        isSyncingFromServer.value = false;
    },
    { immediate: true },
);

watch(
    () => localSearch.value,
    () => {
        localPage.value = 1;
        reloadPatients({ page: 1, search: localSearch.value.trim() }, true);
    },
);

watch(
    () => localRowsPerPage.value,
    () => {
        localPage.value = 1;
        reloadPatients({ page: 1, per_page: localRowsPerPage.value });
    },
);

watch(
    () => [localSortBy.value, localSortDirection.value],
    () => {
        localPage.value = 1;
        reloadPatients({
            page: 1,
            sort_by: localSortBy.value,
            sort_direction: localSortDirection.value,
        });
    },
);

onBeforeUnmount(() => {
    if (patientFiltersDebounceTimeout !== null) {
        clearTimeout(patientFiltersDebounceTimeout);
        patientFiltersDebounceTimeout = null;
    }
});
</script>

<template>
    <Head title="المرضى" />

    <div class="container-modern space-y-4 py-5" dir="rtl">
        <div
            class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <h1 class="page-title text-[2.35rem]">إدارة المرضى</h1>
                <p class="page-subtitle mt-2 text-base">
                    إدارة معلومات المرضى وسجلاتهم الطبية
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Button
                    v-if="can('patient.create')"
                    variant="ghost"
                    size="lg"
                    class="h-12 rounded-2xl border border-[#DDE9F3] bg-white px-5 text-sm font-semibold text-[#1A2B3F] shadow-[0_10px_24px_-22px_rgb(15_42_71_/_0.34)] hover:bg-[#F7FBFE] hover:text-[#075985]"
                    @click="isCreateSheetOpen = true"
                >
                    إضافة متقدمة
                </Button>
                <Link
                    v-if="can('patient.view')"
                    :href="PatientImportExportController.export()"
                    class="inline-flex h-12 items-center gap-2 rounded-2xl border border-[#DDE9F3] bg-white px-5 text-sm font-semibold text-[#1A2B3F] shadow-[0_10px_24px_-22px_rgb(15_42_71_/_0.34)] transition-all duration-200 hover:bg-[#F7FBFE] hover:text-[#075985]"
                >
                    <Download class="size-4" />
                    تصدير
                </Link>
                <Link
                    v-if="can('patient.create')"
                    :href="PatientImportExportController.import()"
                    class="inline-flex h-12 items-center gap-2 rounded-2xl border border-[#DDE9F3] bg-white px-5 text-sm font-semibold text-[#1A2B3F] shadow-[0_10px_24px_-22px_rgb(15_42_71_/_0.34)] transition-all duration-200 hover:bg-[#F7FBFE] hover:text-[#075985]"
                >
                    <Upload class="size-4" />
                    استيراد
                </Link>
                <Button
                    v-if="can('patient.create')"
                    variant="default"
                    size="lg"
                    class="h-12 rounded-2xl bg-[#0EA5E9] px-6 text-sm font-bold text-white shadow-[0_18px_34px_-22px_rgb(14_165_233_/_0.9)] transition-all duration-200 hover:bg-[#0284C7] active:scale-[0.98] disabled:opacity-40"
                    @click="isQuickAddOpen = !isQuickAddOpen"
                >
                    <Plus class="size-4" />
                    إضافة سريعة
                </Button>
            </div>
        </div>

        <PatientQuickAddForm
            v-if="can('patient.create') && isQuickAddOpen"
            v-show="can('patient.create') && isQuickAddOpen"
            @created="handleQuickAddCreated"
            @complete-file="handleCompleteFile"
        />

        <PatientTable
            :patients="patients.data"
            :search="localSearch"
            :rows-per-page="localRowsPerPage"
            :current-page="localPage"
            :total-pages="totalLocalPages"
            :visible-from="localVisibleFrom"
            :visible-to="localVisibleTo"
            :total-records="patients.meta.total"
            :sort-by="localSortBy"
            :sort-direction="localSortDirection"
            :active-filters="activeFilters"
            @update:search="localSearch = $event"
            @update:rows-per-page="localRowsPerPage = $event"
            @previous-page="
                localPage -= 1;
                reloadPatients({ page: localPage });
            "
            @next-page="
                localPage += 1;
                reloadPatients({ page: localPage });
            "
            @sort="toggleSort"
            @remove-filter="handleRemoveFilter"
            @clear-filters="resetLocalFilters"
            @delete="handleDeletePatient"
            @edit="openEditPatient"
            @toggle-quick-add="isQuickAddOpen = !isQuickAddOpen"
        />

        <PatientCreateSheet
            :open="isCreateSheetOpen"
            @update:open="isCreateSheetOpen = $event"
        />

        <PatientViewDialog
            :patient="viewingPatient"
            @close="viewingPatient = null"
        />

        <PatientEditDialog
            :patient="editingPatient"
            @close="editingPatient = null"
            @saved="
                editingPatient = null;
                reloadPatients();
            "
        />

        <PatientDeleteDialog
            :patient="deletingPatient"
            :loading="isDeleting"
            @close="
                isDeleteDialogOpen = false;
                deletingPatient = null;
            "
            @confirm="handleDeleteConfirm"
            @update:open="isDeleteDialogOpen = $event"
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
