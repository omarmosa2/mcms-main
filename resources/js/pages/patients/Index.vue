<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Download,
    Upload,
    Users,
    Plus,
    Eye,
    Pencil,
    Trash2,
    MoreVertical,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import PatientImportExportController from '@/actions/App/Http/Controllers/Patients/PatientImportExportController';
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
import { FilterBar, FilterSearch } from '@/components/ui/filter';
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

type PatientVisitHistoryItem = {
    id: number;
    visit_number: string;
    status: string;
    doctor: {
        id: number;
        name: string;
    } | null;
    started_at: string | null;
    in_progress_at: string | null;
    completed_at: string | null;
};

type PatientAttachment = {
    id: number;
    patient_id: number;
    original_name: string;
    mime_type: string | null;
    extension: string | null;
    size_bytes: number;
    uploaded_at: string | null;
    uploaded_by?: {
        id: number;
        name: string;
        email: string;
    } | null;
    download_url: string;
};

type Patient = {
    id: number;
    file_number: string;
    first_name: string;
    last_name: string;
    full_name: string;
    date_of_birth: string | null;
    age: number | null;
    gender: string | null;
    phone: string | null;
    email: string | null;
    national_id: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
    chronic_conditions: string[];
    allergies: string[];
    current_medications: string[];
    visit_history: PatientVisitHistoryItem[];
    attachments: PatientAttachment[];
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

type PatientSortField =
    | 'file_number'
    | 'full_name'
    | 'date_of_birth'
    | 'gender'
    | 'phone'
    | 'email'
    | 'created_at';

type SortDirection = 'asc' | 'desc';

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
const { isOpen: isConfirmOpen, options: confirmOptions, confirm, handleConfirm: handleConfirmDelete, handleCancel: handleConfirmCancel } = useConfirm();
const toast = useToast();

const selectedPatientIds = ref<number[]>([]);
const viewingPatient = ref<Patient | null>(null);
const editingPatient = ref<Patient | null>(null);
const patientProfileError = ref<string | null>(null);
const isPatientProfileLoading = ref(false);
const isCreateSheetOpen = ref(false);
const isQuickAddOpen = ref(false);
const lastCreatedPatientId = ref<number | null>(null);
const lastCreatedPatientName = ref<string | null>(null);

const quickAddFirstName = ref('');
const quickAddLastName = ref('');
const quickAddPhone = ref('');
const quickAddGender = ref('');
const quickAddDateOfBirth = ref('');
const quickAddProcessing = ref(false);
const quickAddErrors = ref<Record<string, string>>({});

const createChronicConditions = ref<string[]>(['']);
const createAllergies = ref<string[]>(['']);
const createCurrentMedications = ref<string[]>(['']);
const editChronicConditions = ref<string[]>(['']);
const editAllergies = ref<string[]>(['']);
const editCurrentMedications = ref<string[]>(['']);

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

    if (sortBy !== null && allowedPatientSortFields.includes(sortBy as PatientSortField)) {
        return sortBy;
    }

    return 'created_at';
};

const localSortBy = ref<PatientSortField>(resolveInitialSortBy());
const localSortDirection = ref<SortDirection>(
    filters.sort_direction === 'asc' ? 'asc' : 'desc',
);

const visiblePatients = computed<Patient[]>(() => patients.data);

const totalLocalPages = computed<number>(() => {
    return Math.max(1, patients.meta.last_page);
});

const localVisibleFrom = computed<number>(() => {
    return patients.meta.from ?? 0;
});

const localVisibleTo = computed<number>(() => {
    return patients.meta.to ?? 0;
});

const defaultRowsPerPage = 15;
const isSyncingFromServer = ref(false);
let patientFiltersDebounceTimeout: ReturnType<typeof setTimeout> | null = null;

const ensureMedicalList = (items: string[] | null | undefined): string[] => {
    const normalized = (items ?? [])
        .map((item) => item.trim())
        .filter((item) => item.length > 0);

    return normalized.length > 0 ? normalized : [''];
};

const addMedicalItem = (collection: string[]): void => {
    collection.push('');
};

const removeMedicalItem = (collection: string[], index: number): void => {
    if (collection.length <= 1) {
        collection.splice(0, collection.length, '');

        return;
    }

    collection.splice(index, 1);
};

const resetCreateMedicalLists = (): void => {
    createChronicConditions.value = [''];
    createAllergies.value = [''];
    createCurrentMedications.value = [''];
};

const hydrateEditMedicalLists = (patient: Patient): void => {
    editChronicConditions.value = ensureMedicalList(patient.chronic_conditions);
    editAllergies.value = ensureMedicalList(patient.allergies);
    editCurrentMedications.value = ensureMedicalList(patient.current_medications);
};

const formatBytes = (sizeBytes: number): string => {
    if (sizeBytes < 1024) {
        return `${sizeBytes} B`;
    }

    if (sizeBytes < 1024 * 1024) {
        return `${(sizeBytes / 1024).toFixed(1)} KB`;
    }

    return `${(sizeBytes / (1024 * 1024)).toFixed(1)} MB`;
};

const formatDateTime = (value: string | null): string => {
    if (value === null) {
        return '-';
    }

    return new Date(value).toLocaleString('ar-SA');
};

const formatVisitStatus = (value: string): string => {
    const labels: Record<string, string> = {
        started: 'بدأت',
        in_progress: 'قيد التنفيذ',
        completed: 'مكتملة',
        canceled: 'ملغاة',
    };

    return labels[value] ?? value.replaceAll('_', ' ');
};

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
    const query: {
        search?: string;
        per_page: number;
        page: number;
        sort_by: PatientSortField;
        sort_direction: SortDirection;
    } = {
        search: localSearch.value.trim(),
        per_page: localRowsPerPage.value,
        page: localPage.value,
        sort_by: localSortBy.value,
        sort_direction: localSortDirection.value,
        ...overrides,
    };

    return query;
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

const sortIconFor = (field: PatientSortField) => {
    if (localSortBy.value !== field) {
        return ArrowUpDown;
    }

    return localSortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

const toggleSort = (field: PatientSortField): void => {
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
    localRowsPerPage.value = defaultRowsPerPage;
    localSortBy.value = 'created_at';
    localSortDirection.value = 'desc';
    localPage.value = 1;
    isSyncingFromServer.value = false;
    reloadPatients({
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
    reloadPatients({ page: localPage.value });
};

const goToNextPage = (): void => {
    if (localPage.value >= totalLocalPages.value) {
        return;
    }

    localPage.value += 1;
    reloadPatients({ page: localPage.value });
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
        localSortDirection.value = filters.sort_direction === 'asc' ? 'asc' : 'desc';
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

const selectablePatientIds = computed<number[]>(() =>
    visiblePatients.value.map((patient) => patient.id),
);

const areAllPatientsSelected = computed<boolean>(() => {
    if (selectablePatientIds.value.length === 0) {
        return false;
    }

    return selectablePatientIds.value.every((patientId) =>
        selectedPatientIds.value.includes(patientId),
    );
});

watch(
    () => selectablePatientIds.value,
    (ids) => {
        selectedPatientIds.value = selectedPatientIds.value.filter((id) =>
            ids.includes(id),
        );
    },
);

const toggleAllPatientsSelection = (event: Event): void => {
    const target = event.target as HTMLInputElement;
    selectedPatientIds.value = target.checked
        ? [...selectablePatientIds.value]
        : [];
};

const clearSelectedPatients = (): void => {
    selectedPatientIds.value = [];
};

const fetchPatientDetails = async (patientId: number): Promise<Patient> => {
    const response = await fetch(PatientController.show.url(patientId), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Failed to load patient details');
    }

    const payload = (await response.json()) as { data?: Patient };

    if (payload.data === undefined) {
        throw new Error('Invalid patient payload');
    }

    return payload.data;
};

const openViewPatient = async (patient: Patient): Promise<void> => {
    viewingPatient.value = patient;
    patientProfileError.value = null;
    isPatientProfileLoading.value = true;

    try {
        const detailedPatient = await fetchPatientDetails(patient.id);

        if (viewingPatient.value?.id === patient.id) {
            viewingPatient.value = detailedPatient;
        }
    } catch {
        if (viewingPatient.value?.id === patient.id) {
            patientProfileError.value = 'تعذر تحميل الملف الكامل للمريض.';
        }
    } finally {
        if (viewingPatient.value?.id === patient.id) {
            isPatientProfileLoading.value = false;
        }
    }
};

const openEditPatient = async (patient: Patient): Promise<void> => {
    editingPatient.value = patient;
    hydrateEditMedicalLists(patient);
    patientProfileError.value = null;
    isPatientProfileLoading.value = true;

    try {
        const detailedPatient = await fetchPatientDetails(patient.id);

        if (editingPatient.value?.id === patient.id) {
            editingPatient.value = detailedPatient;
            hydrateEditMedicalLists(detailedPatient);
        }
    } catch {
        if (editingPatient.value?.id === patient.id) {
            patientProfileError.value = 'تعذر تحميل الملف الكامل للمريض.';
        }
    } finally {
        if (editingPatient.value?.id === patient.id) {
            isPatientProfileLoading.value = false;
        }
    }
};

const closeViewPatient = (): void => {
    viewingPatient.value = null;
    patientProfileError.value = null;
    isPatientProfileLoading.value = false;
};

const closeEditPatient = (): void => {
    editingPatient.value = null;
    editChronicConditions.value = [''];
    editAllergies.value = [''];
    editCurrentMedications.value = [''];
    patientProfileError.value = null;
    isPatientProfileLoading.value = false;
};

const patientGenderClass = (gender: string | null): string => {
    if (gender === 'male') {
        return 'bg-[#EFF6FF] text-[#1D4ED8]';
    }

    if (gender === 'female') {
        return 'bg-[#FDF2F8] text-[#9D174D]';
    }

    if (gender === 'other') {
        return 'bg-[#F3F4F6] text-[#6B7280]';
    }

    return 'bg-[#F3F4F6] text-[#6B7280]';
};

const patientGenderLabel = (gender: string | null): string => {
    const labels: Record<string, string> = {
        male: 'ذكر',
        female: 'أنثى',
        other: 'آخر',
    };

    return labels[gender ?? ''] ?? 'غير محدد';
};

const activeFilters = computed(() => {
    const f: { key: string; label: string; value: string | null }[] = [];

    if (localSearch.value.trim()) {
        f.push({ key: 'search', label: 'بحث', value: localSearch.value.trim() });
    }

    return f;
});

const handleRemoveFilter = (key: string) => {
    if (key === 'search') {
        localSearch.value = '';
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

const handleBulkDelete = async () => {
    const confirmed = await confirm({
        title: 'حذف المرضى',
        description: `هل أنت متأكد من حذف ${selectedPatientIds.value.length} مريض؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(PatientController.bulkDestroy.url(), {
            data: { ids: selectedPatientIds.value },
            onSuccess: () => {
                clearSelectedPatients();
                toast.success(`تم حذف ${selectedPatientIds.value.length} مريض بنجاح`);
            },
            onError: () => {
                toast.error('فشل حذف المرضى');
            },
        });
    }
};

const resetQuickAdd = () => {
    quickAddFirstName.value = '';
    quickAddLastName.value = '';
    quickAddPhone.value = '';
    quickAddGender.value = '';
    quickAddDateOfBirth.value = '';
    quickAddErrors.value = {};
};

const handleQuickAdd = async (saveAndAddNext = true) => {
    quickAddProcessing.value = true;
    quickAddErrors.value = {};

    try {
        const formData = new FormData();
        formData.append('first_name', quickAddFirstName.value);
        formData.append('last_name', quickAddLastName.value);
        formData.append('phone', quickAddPhone.value);
        formData.append('gender', quickAddGender.value);
        formData.append('date_of_birth', quickAddDateOfBirth.value);

        const response = await fetch(PatientController.store.url(), {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            if (response.status === 422) {
                const errorData = await response.json();
                quickAddErrors.value = errorData.errors ?? {};

                return;
            }

            toast.error('فشل إضافة المريض');

            return;
        }

        const result = await response.json();

        lastCreatedPatientId.value = result.data?.id ?? null;
        lastCreatedPatientName.value = `${quickAddFirstName.value} ${quickAddLastName.value}`;
        toast.success(`تم إضافة ${lastCreatedPatientName.value} بنجاح`);

        if (saveAndAddNext) {
            resetQuickAdd();
            reloadPatients({ page: 1 });
        } else {
            reloadPatients({ page: 1 });
        }
    } catch {
        toast.error('حدث خطأ أثناء إضافة المريض');
    } finally {
        quickAddProcessing.value = false;
    }
};

const handleQuickAddKeyDown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        handleQuickAdd(true);
    }
};

const openCompletePatientFile = () => {
    if (lastCreatedPatientId.value) {
        openEditPatient({
            id: lastCreatedPatientId.value,
            file_number: '',
            first_name: lastCreatedPatientName?.value?.split(' ')[0] ?? '',
            last_name: lastCreatedPatientName?.value?.split(' ').slice(1).join(' ') ?? '',
            full_name: lastCreatedPatientName.value ?? '',
            date_of_birth: quickAddDateOfBirth.value || null,
            age: null,
            gender: quickAddGender.value || null,
            phone: quickAddPhone.value || null,
            email: null,
            national_id: null,
            emergency_contact_name: null,
            emergency_contact_phone: null,
            notes: null,
            chronic_conditions: [],
            allergies: [],
            current_medications: [],
            visit_history: [],
            attachments: [],
            created_at: null,
            updated_at: null,
        });
    }
};


</script>

<template>
    <Head title="المرضى" />

    <div class="container-modern space-y-6 py-6" dir="rtl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">المرضى</h1>
                <p class="mt-1 text-sm text-slate-500">سجل المرضى والبيانات الديموغرافية</p>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="can('patient.create')"
                    variant="ghost"
                    size="sm"
                    class="h-9 rounded-lg px-3 text-xs"
                    @click="isCreateSheetOpen = true"
                >
                    إضافة متقدمة
                </Button>
                <Link
                    v-if="can('patient.view')"
                    :href="PatientImportExportController.export()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200/80 bg-white px-3.5 py-2 text-sm font-medium text-slate-600 transition-all duration-200 hover:bg-slate-50 hover:text-slate-800 min-h-[40px]"
                >
                    <Download class="size-4" />
                    تصدير
                </Link>
                <Link
                    v-if="can('patient.create')"
                    :href="PatientImportExportController.import()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200/80 bg-white px-3.5 py-2 text-sm font-medium text-slate-600 transition-all duration-200 hover:bg-slate-50 hover:text-slate-800 min-h-[40px]"
                >
                    <Upload class="size-4" />
                    استيراد
                </Link>
                <Button
                    v-if="can('patient.create')"
                    variant="default"
                    size="sm"
                    class="h-9 rounded-lg bg-[#0F9D7A] px-3 text-xs text-white hover:bg-[#0B7A5E] shadow-sm"
                    @click="isQuickAddOpen = !isQuickAddOpen"
                >
                    <Plus class="size-3.5" />
                    إضافة سريعة
                </Button>
            </div>
        </div>

        <div class="h-px w-full bg-slate-100/80"></div>

        <section v-if="can('patient.create') && isQuickAddOpen" class="rounded-xl border border-[#0F9D7A]/15 bg-[#E7F7F2]/20 p-4">
            <div v-if="lastCreatedPatientId" class="mb-3 flex items-center gap-2 rounded-lg border border-[#22C55E]/20 bg-[#F0FDF4]/60 px-3 py-2">
                <span class="text-xs font-medium text-[#166534]">✅ تم إضافة: {{ lastCreatedPatientName }}</span>
                <Button type="button" variant="ghost" size="sm" class="h-6 px-2 text-xs text-[#0F9D7A]" @click="openCompletePatientFile">
                    إكمال الملف
                </Button>
            </div>

            <div class="grid gap-3 md:grid-cols-5 md:items-end">
                <div class="grid gap-1.5">
                    <Label for="quick_first_name" class="text-xs font-medium text-slate-600">الاسم الأول *</Label>
                    <Input
                        id="quick_first_name"
                        v-model="quickAddFirstName"
                        placeholder="محمد"
                        class="h-9 text-sm bg-white border-slate-200/80 rounded-lg"
                        @keydown="handleQuickAddKeyDown"
                    />
                    <p v-if="quickAddErrors.first_name" class="text-xs text-destructive">{{ quickAddErrors.first_name[0] }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="quick_last_name" class="text-xs font-medium text-slate-600">اسم العائلة *</Label>
                    <Input
                        id="quick_last_name"
                        v-model="quickAddLastName"
                        placeholder="أحمد"
                        class="h-9 text-sm bg-white border-slate-200/80 rounded-lg"
                        @keydown="handleQuickAddKeyDown"
                    />
                    <p v-if="quickAddErrors.last_name" class="text-xs text-destructive">{{ quickAddErrors.last_name[0] }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="quick_phone" class="text-xs font-medium text-slate-600">الهاتف</Label>
                    <Input
                        id="quick_phone"
                        v-model="quickAddPhone"
                        placeholder="0599123456"
                        class="h-9 text-sm bg-white border-slate-200/80 rounded-lg"
                        @keydown="handleQuickAddKeyDown"
                    />
                    <p v-if="quickAddErrors.phone" class="text-xs text-destructive">{{ quickAddErrors.phone[0] }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="quick_gender" class="text-xs font-medium text-slate-600">الجنس</Label>
                    <select
                        id="quick_gender"
                        v-model="quickAddGender"
                        class="h-9 rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#0F9D7A]/15 focus:border-[#0F9D7A]"
                        @keydown="handleQuickAddKeyDown"
                    >
                        <option value="">اختر</option>
                        <option value="male">ذكر</option>
                        <option value="female">أنثى</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="quick_dob" class="text-xs font-medium text-slate-600">تاريخ الميلاد</Label>
                    <Input
                        id="quick_dob"
                        v-model="quickAddDateOfBirth"
                        type="date"
                        class="h-9 text-sm bg-white border-slate-200/80 rounded-lg"
                        @keydown="handleQuickAddKeyDown"
                    />
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <span class="text-xs text-slate-400">اضغط Enter للحفظ وإضافة آخر</span>
                <div class="flex items-center gap-2">
                    <Button type="button" variant="ghost" size="sm" class="h-9 px-3 text-xs rounded-lg" @click="resetQuickAdd">مسح</Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-9 px-4 text-xs rounded-lg border-slate-200/80 text-slate-600 hover:bg-slate-50"
                        :disabled="quickAddProcessing || !quickAddFirstName || !quickAddLastName"
                        @click="handleQuickAdd(false)"
                    >
                        حفظ فقط
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="h-9 px-4 text-xs rounded-lg bg-[#0F9D7A] text-white hover:bg-[#0B7A5E] shadow-sm"
                        :disabled="quickAddProcessing || !quickAddFirstName || !quickAddLastName"
                        @click="handleQuickAdd(true)"
                    >
                        حفظ وإضافة آخر
                    </Button>
                </div>
            </div>
        </section>

        <div class="card-float p-4">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="grid gap-1.5 md:max-w-40">
                        <Label for="patients_per_page" class="text-xs font-medium text-slate-500">صفوف لكل صفحة</Label>
                        <select
                            id="patients_per_page"
                            v-model.number="localRowsPerPage"
                            class="h-9 rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#0F9D7A]/15 focus:border-[#0F9D7A]"
                        >
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    <span class="text-xs font-medium text-slate-400 mt-5">الإجمالي: {{ patients.meta.total }}</span>
                </div>
                <div class="grid gap-1.5 md:max-w-sm">
                    <FilterSearch
                        id="patients_search"
                        v-model="localSearch"
                        placeholder="رقم الملف، الاسم، الهاتف، البريد"
                    />
                </div>
            </div>

            <FilterBar
                v-if="activeFilters.length > 0"
                :active-filters="activeFilters"
                @remove="handleRemoveFilter"
                @clear-all="resetLocalFilters"
            />

            <div
                v-if="can('patient.delete') && selectedPatientIds.length > 0"
                class="mb-3 flex flex-wrap items-center gap-2 rounded-lg border border-[#EF4444]/15 bg-[#FEF2F2]/50 p-3"
            >
                <Button type="button" variant="destructive" size="sm" class="rounded-lg" @click="handleBulkDelete">
                    حذف المحدد ({{ selectedPatientIds.length }})
                </Button>
                <Button type="button" variant="ghost" size="sm" class="rounded-lg" @click="clearSelectedPatients">إلغاء التحديد</Button>
            </div>

            <div class="ui-table-shell">
                <table class="ui-table w-full">
                    <thead>
                        <tr class="bg-slate-50/40">
                            <th v-if="can('patient.delete')" class="px-4 py-3 text-xs font-medium text-slate-500">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-slate-200/80"
                                    :checked="areAllPatientsSelected"
                                    @change="toggleAllPatientsSelection"
                                />
                            </th>
                            <th class="px-4 py-3">
                                <button type="button" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 transition hover:text-slate-900" @click="toggleSort('file_number')">
                                    رقم الملف
                                    <component :is="sortIconFor('file_number')" class="size-3" />
                                </button>
                            </th>
                            <th class="px-4 py-3">
                                <button type="button" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 transition hover:text-slate-900" @click="toggleSort('full_name')">
                                    الاسم
                                    <component :is="sortIconFor('full_name')" class="size-3" />
                                </button>
                            </th>
                            <th class="px-4 py-3">
                                <button type="button" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 transition hover:text-slate-900" @click="toggleSort('date_of_birth')">
                                    تاريخ الميلاد
                                    <component :is="sortIconFor('date_of_birth')" class="size-3" />
                                </button>
                            </th>
                            <th class="px-4 py-3">
                                <button type="button" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 transition hover:text-slate-900" @click="toggleSort('gender')">
                                    الجنس
                                    <component :is="sortIconFor('gender')" class="size-3" />
                                </button>
                            </th>
                            <th class="px-4 py-3">
                                <button type="button" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 transition hover:text-slate-900" @click="toggleSort('phone')">
                                    الهاتف
                                    <component :is="sortIconFor('phone')" class="size-3" />
                                </button>
                            </th>
                            <th class="px-4 py-3">
                                <button type="button" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 transition hover:text-slate-900" @click="toggleSort('email')">
                                    البريد
                                    <component :is="sortIconFor('email')" class="size-3" />
                                </button>
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="patient in visiblePatients"
                            :key="patient.id"
                            class="group transition-colors duration-150 hover:bg-slate-50/50"
                            style="height: 52px; border-bottom: 0.5px solid #E8ECF1;"
                        >
                            <td v-if="can('patient.delete')" class="px-4 py-2" data-label="تحديد">
                                <input
                                    v-model="selectedPatientIds"
                                    type="checkbox"
                                    class="size-4 rounded border-slate-200/80"
                                    :value="patient.id"
                                />
                            </td>
                            <td class="px-4 py-2" data-label="رقم الملف">
                                <span class="font-mono text-xs text-slate-400">{{ patient.file_number }}</span>
                            </td>
                            <td class="px-4 py-2 text-sm font-medium text-[#0F9D7A]" data-label="الاسم">
                                {{ patient.full_name }}
                            </td>
                            <td class="px-4 py-2 text-sm" data-label="تاريخ الميلاد">
                                <span :class="patient.date_of_birth ? 'text-slate-700' : 'text-slate-400'">
                                    {{ patient.date_of_birth ?? 'غير محدد' }}
                                </span>
                            </td>
                            <td class="px-4 py-2" data-label="الجنس">
                                <span
                                    class="inline-flex items-center rounded-[999px] px-2.5 py-0.5 text-xs font-medium"
                                    :class="patientGenderClass(patient.gender)"
                                >
                                    {{ patientGenderLabel(patient.gender) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm" data-label="الهاتف">
                                <span :class="patient.phone ? 'text-slate-700' : 'text-slate-400'">
                                    {{ patient.phone ?? 'غير محدد' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm" data-label="البريد">
                                <span class="block max-w-[18rem] break-all" :title="patient.email ?? 'غير محدد'" :class="patient.email ? 'text-slate-700' : 'text-slate-400'">
                                    {{ patient.email ?? 'غير محدد' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 md:text-right" data-label="الإجراءات">
                                <!-- Desktop: icon buttons on hover -->
                                <div class="hidden md:flex items-center justify-end gap-1">
                                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150">
                                        <Link
                                            v-if="can('patient.view')"
                                            :href="PatientController.show.url(patient.id)"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-100 text-slate-400 transition-all duration-150 hover:border-[#0F9D7A]/30 hover:text-[#0F9D7A] hover:bg-[#E7F7F2]/40"
                                            :aria-label="`عرض ${patient.full_name}`"
                                        >
                                            <Eye class="size-3.5" />
                                        </Link>
                                        <button
                                            v-if="can('patient.update')"
                                            type="button"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-100 text-slate-400 transition-all duration-150 hover:border-[#0F9D7A]/30 hover:text-[#0F9D7A] hover:bg-[#E7F7F2]/40"
                                            :aria-label="`تعديل ${patient.full_name}`"
                                            @click="openEditPatient(patient)"
                                        >
                                            <Pencil class="size-3.5" />
                                        </button>
                                        <button
                                            v-if="can('patient.delete')"
                                            type="button"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-100 text-slate-400 transition-all duration-150 hover:border-[#EF4444]/30 hover:text-[#EF4444] hover:bg-[#FEF2F2]/40"
                                            :aria-label="`حذف ${patient.full_name}`"
                                            @click="handleDeletePatient(patient)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                                <!-- Mobile: single menu button -->
                                <div class="flex md:hidden items-center justify-end">
                                    <button
                                        type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-100 text-slate-400"
                                        :aria-label="`إجراءات ${patient.full_name}`"
                                    >
                                        <MoreVertical class="size-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="visiblePatients.length === 0">
                            <td :colspan="can('patient.delete') ? 8 : 7" class="px-4 py-20">
                                <div class="flex flex-col items-center justify-center text-center">
                                    <Users class="size-12 text-slate-200 mb-4" />
                                    <h3 class="text-lg font-semibold text-slate-900 mb-1">لا يوجد مرضى</h3>
                                    <p class="text-sm text-slate-400 mb-5">جرب تغيير كلمة البحث أو أضف مريضاً جديداً</p>
                                    <Button
                                        v-if="can('patient.create')"
                                        variant="default"
                                        size="sm"
                                        class="rounded-lg bg-[#0F9D7A] text-white hover:bg-[#0B7A5E] shadow-sm"
                                        @click="isQuickAddOpen = true"
                                    >
                                        إضافة سريعة
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-100/80 bg-slate-50/40 px-4 py-2.5">
                <p class="text-xs text-slate-500">
                    عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من {{ patients.meta.total }} سجل
                </p>
                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" class="h-9 px-3 text-xs rounded-lg border-slate-200/80" :disabled="localPage === 1" @click="goToPreviousPage">السابق</Button>
                    <span class="text-xs font-semibold text-slate-600">صفحة {{ localPage }} / {{ totalLocalPages }}</span>
                    <Button type="button" variant="outline" size="sm" class="h-9 px-3 text-xs rounded-lg border-slate-200/80" :disabled="localPage >= totalLocalPages" @click="goToNextPage">التالي</Button>
                </div>
            </div>
        </div>

        <Sheet :open="isCreateSheetOpen" @update:open="isCreateSheetOpen = $event">
            <SheetContent side="left" class="w-full sm:max-w-lg">
                <SheetHeader class="text-right">
                    <SheetTitle>مريض جديد</SheetTitle>
                    <SheetDescription>تسجيل مريض جديد.</SheetDescription>
                </SheetHeader>

                <Form
                    v-bind="PatientController.store.form()"
                    class="mt-6 space-y-4"
                    reset-on-success
                    @success="resetCreateMedicalLists"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="file_number">
                            رقم الملف
                            <span class="text-xs text-muted-foreground">(يُولّد تلقائياً إذا ترك فارغاً)</span>
                        </Label>
                        <Input id="file_number" name="file_number" placeholder="MRN-20250421-0001" class="pattern-field-clay" />
                        <InputError :message="errors.file_number" />
                    </div>

                    <div class="grid gap-2 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="first_name">الاسم الأول</Label>
                            <Input id="first_name" name="first_name" required class="pattern-field-clay" />
                            <InputError :message="errors.first_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="last_name">اسم العائلة</Label>
                            <Input id="last_name" name="last_name" required class="pattern-field-clay" />
                            <InputError :message="errors.last_name" />
                        </div>
                    </div>

                    <div class="grid gap-2 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="date_of_birth">تاريخ الميلاد</Label>
                            <Input id="date_of_birth" name="date_of_birth" type="date" class="pattern-field-clay" />
                            <InputError :message="errors.date_of_birth" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="gender">الجنس</Label>
                            <select id="gender" name="gender" class="pattern-field-clay h-10 px-3 py-1.5">
                                <option value="">غير محدد</option>
                                <option value="male">ذكر</option>
                                <option value="female">أنثى</option>
                                <option value="other">آخر</option>
                            </select>
                            <InputError :message="errors.gender" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone">الهاتف</Label>
                        <Input id="phone" name="phone" class="pattern-field-clay" />
                        <InputError :message="errors.phone" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">البريد الإلكتروني</Label>
                        <Input id="email" name="email" type="email" class="pattern-field-clay" />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="national_id">رقم الهوية</Label>
                        <Input id="national_id" name="national_id" class="pattern-field-clay" />
                        <InputError :message="errors.national_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">ملاحظات</Label>
                        <textarea id="notes" name="notes" rows="3" class="pattern-field-clay"></textarea>
                        <InputError :message="errors.notes" />
                    </div>

                    <div class="grid gap-2 rounded-xl border border-border/60 bg-background/40 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <Label class="text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground">أمراض مزمنة</Label>
                            <Button type="button" size="sm" variant="neumorphic" class="h-10 px-2 text-xs" @click="addMedicalItem(createChronicConditions)">إضافة</Button>
                        </div>
                        <div v-for="(item, index) in createChronicConditions" :key="`create-chronic-${index}`" class="flex items-center gap-2">
                            <Input :name="`chronic_conditions[${index}]`" v-model="createChronicConditions[index]" placeholder="اسم المرض" class="pattern-field-clay" />
                            <Button type="button" size="sm" variant="ghost" class="h-10 px-2 text-xs" @click="removeMedicalItem(createChronicConditions, index)">حذف</Button>
                        </div>
                        <InputError :message="errors.chronic_conditions" />
                    </div>

                    <div class="grid gap-2 rounded-xl border border-border/60 bg-background/40 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <Label class="text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground">حساسية</Label>
                            <Button type="button" size="sm" variant="neumorphic" class="h-10 px-2 text-xs" @click="addMedicalItem(createAllergies)">إضافة</Button>
                        </div>
                        <div v-for="(item, index) in createAllergies" :key="`create-allergy-${index}`" class="flex items-center gap-2">
                            <Input :name="`allergies[${index}]`" v-model="createAllergies[index]" placeholder="اسم الحساسية" class="pattern-field-clay" />
                            <Button type="button" size="sm" variant="ghost" class="h-10 px-2 text-xs" @click="removeMedicalItem(createAllergies, index)">حذف</Button>
                        </div>
                        <InputError :message="errors.allergies" />
                    </div>

                    <div class="grid gap-2 rounded-xl border border-border/60 bg-background/40 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <Label class="text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground">أدوية حالية</Label>
                            <Button type="button" size="sm" variant="neumorphic" class="h-10 px-2 text-xs" @click="addMedicalItem(createCurrentMedications)">إضافة</Button>
                        </div>
                        <div v-for="(item, index) in createCurrentMedications" :key="`create-medication-${index}`" class="flex items-center gap-2">
                            <Input :name="`current_medications[${index}]`" v-model="createCurrentMedications[index]" placeholder="اسم الدواء" class="pattern-field-clay" />
                            <Button type="button" size="sm" variant="ghost" class="h-10 px-2 text-xs" @click="removeMedicalItem(createCurrentMedications, index)">حذف</Button>
                        </div>
                        <InputError :message="errors.current_medications" />
                    </div>

                    <Button :disabled="processing" variant="clay" class="w-full min-h-[44px]">إنشاء مريض</Button>
                </Form>
            </SheetContent>
        </Sheet>

        <Dialog :open="viewingPatient !== null" @update:open="(open) => !open && closeViewPatient()">
            <DialogContent class="sm:max-w-4xl" aria-label="تفاصيل المريض">
                <DialogHeader>
                    <DialogTitle>{{ viewingPatient?.full_name ?? 'تفاصيل المريض' }}</DialogTitle>
                    <DialogDescription>ملف المريض الكامل.</DialogDescription>
                </DialogHeader>

                <div v-if="isPatientProfileLoading" class="grid gap-2 rounded-xl border border-border/70 bg-background/55 p-4">
                    <div class="h-3 w-2/3 animate-pulse motion-reduce:animate-none motion-reduce:opacity-30 rounded bg-muted" />
                    <div class="h-3 w-1/2 animate-pulse motion-reduce:animate-none motion-reduce:opacity-30 rounded bg-muted" />
                    <div class="h-3 w-4/5 animate-pulse motion-reduce:animate-none motion-reduce:opacity-30 rounded bg-muted" />
                </div>

                <p v-if="patientProfileError !== null" class="rounded-lg border border-destructive/35 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ patientProfileError }}
                </p>

                <div v-if="viewingPatient" class="grid gap-4">
                    <dl class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4 sm:grid-cols-2">
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">رقم الملف</dt>
                            <dd class="font-mono text-sm">{{ viewingPatient.file_number }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">الاسم الكامل</dt>
                            <dd class="text-sm">{{ viewingPatient.full_name }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">الجنس</dt>
                            <dd class="text-sm capitalize">{{ patientGenderLabel(viewingPatient.gender) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">تاريخ الميلاد</dt>
                            <dd class="text-sm">{{ viewingPatient.date_of_birth ?? 'غير محدد' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">العمر</dt>
                            <dd class="text-sm">{{ viewingPatient.age ?? 'غير متوفر' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">الهاتف</dt>
                            <dd class="text-sm">{{ viewingPatient.phone ?? 'غير محدد' }}</dd>
                        </div>
                        <div class="space-y-1 sm:col-span-2">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">البريد الإلكتروني</dt>
                            <dd class="text-sm">{{ viewingPatient.email ?? 'غير محدد' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">رقم الهوية</dt>
                            <dd class="text-sm">{{ viewingPatient.national_id ?? 'غير محدد' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">جهة اتصال الطوارئ</dt>
                            <dd class="text-sm">
                                {{ viewingPatient.emergency_contact_name ? `${viewingPatient.emergency_contact_name} (${viewingPatient.emergency_contact_phone ?? 'بدون هاتف'})` : 'غير محدد' }}
                            </dd>
                        </div>
                        <div class="space-y-1 sm:col-span-2">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">ملاحظات</dt>
                            <dd class="text-sm leading-6 text-muted-foreground">{{ viewingPatient.notes ?? 'لا توجد ملاحظات' }}</dd>
                        </div>
                    </dl>

                    <div class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4 sm:grid-cols-3">
                        <div class="space-y-2">
                            <h4 class="text-[0.7rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">أمراض مزمنة</h4>
                            <ul v-if="viewingPatient.chronic_conditions.length > 0" class="space-y-1 text-sm">
                                <li v-for="(item, index) in viewingPatient.chronic_conditions" :key="`view-chronic-${index}`" class="rounded-md border border-border/60 bg-background/70 px-2 py-1">{{ item }}</li>
                            </ul>
                            <p v-else class="text-sm text-muted-foreground">غير محددة</p>
                        </div>
                        <div class="space-y-2">
                            <h4 class="text-[0.7rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">حساسية</h4>
                            <ul v-if="viewingPatient.allergies.length > 0" class="space-y-1 text-sm">
                                <li v-for="(item, index) in viewingPatient.allergies" :key="`view-allergy-${index}`" class="rounded-md border border-border/60 bg-background/70 px-2 py-1">{{ item }}</li>
                            </ul>
                            <p v-else class="text-sm text-muted-foreground">غير محددة</p>
                        </div>
                        <div class="space-y-2">
                            <h4 class="text-[0.7rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">أدوية حالية</h4>
                            <ul v-if="viewingPatient.current_medications.length > 0" class="space-y-1 text-sm">
                                <li v-for="(item, index) in viewingPatient.current_medications" :key="`view-medication-${index}`" class="rounded-md border border-border/60 bg-background/70 px-2 py-1">{{ item }}</li>
                            </ul>
                            <p v-else class="text-sm text-muted-foreground">غير محددة</p>
                        </div>
                    </div>

                    <div class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h4 class="text-[0.7rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">سجل الزيارات</h4>
                            <span class="text-xs text-muted-foreground">آخر {{ viewingPatient.visit_history.length }} زيارة</span>
                        </div>
                        <div v-if="viewingPatient.visit_history.length > 0" class="space-y-2">
                            <div v-for="visit in viewingPatient.visit_history" :key="`view-visit-${visit.id}`" class="rounded-lg border border-border/60 bg-background/70 px-3 py-2 text-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-semibold">{{ visit.visit_number }}</span>
                                    <span class="capitalize text-muted-foreground">{{ formatVisitStatus(visit.status) }}</span>
                                </div>
                                <p class="text-xs text-muted-foreground">الطبيب: {{ visit.doctor?.name ?? 'غير محدد' }}</p>
                                <p class="text-xs text-muted-foreground">بدأت: {{ formatDateTime(visit.started_at) }}</p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">لا يوجد سجل زيارات بعد.</p>
                    </div>

                    <div class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h4 class="text-[0.7rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">المرفقات</h4>
                        </div>

                        <Form
                            v-if="can('patient.update')"
                            v-bind="PatientController.storeAttachment.form(viewingPatient.id)"
                            class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]"
                            :options="{ preserveState: true, preserveScroll: true }"
                            @success="viewingPatient !== null && openViewPatient(viewingPatient)"
                            #default="{ errors, processing }"
                        >
                            <Input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" class="pattern-field-clay" />
                            <Button type="submit" variant="clay" class="h-10 px-4" :disabled="processing">رفع</Button>
                            <InputError :message="errors.file" class="sm:col-span-2" />
                        </Form>

                        <div v-if="viewingPatient.attachments.length > 0" class="space-y-2">
                            <div v-for="attachment in viewingPatient.attachments" :key="`view-attachment-${attachment.id}`" class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/60 bg-background/70 px-3 py-2">
                                <div class="space-y-0.5">
                                    <p class="text-sm font-medium">{{ attachment.original_name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ attachment.mime_type ?? 'نوع غير معروف' }} - {{ formatBytes(attachment.size_bytes) }}</p>
                                    <p class="text-xs text-muted-foreground">تم الرفع: {{ formatDateTime(attachment.uploaded_at) }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a :href="attachment.download_url" class="inline-flex h-10 items-center rounded-full border border-border/70 bg-background/80 px-3 text-xs font-semibold transition hover:bg-background">تحميل</a>
                                    <Link
                                        v-if="can('patient.update')"
                                        :href="PatientController.destroyAttachment([viewingPatient.id, attachment.id])"
                                        method="delete"
                                        as="button"
                                        class="inline-flex h-10 items-center rounded-full border border-destructive/35 bg-destructive/8 px-3 text-xs font-semibold text-destructive transition hover:bg-destructive/15"
                                        @success="viewingPatient !== null && openViewPatient(viewingPatient)"
                                    >
                                        حذف
                                    </Link>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">لا توجد مرفقات.</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" class="min-h-[44px]" @click="closeViewPatient">إغلاق</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="editingPatient !== null" @update:open="(open) => !open && closeEditPatient()">
            <DialogContent class="sm:max-w-2xl" aria-label="تعديل المريض">
                <DialogHeader>
                    <DialogTitle>تعديل المريض</DialogTitle>
                    <DialogDescription>تحديث بيانات المريض.</DialogDescription>
                </DialogHeader>

                <p v-if="isPatientProfileLoading" class="rounded-lg border border-border/70 bg-background/60 px-3 py-2 text-xs text-muted-foreground">جاري تحميل تفاصيل المريض...</p>
                <p v-if="patientProfileError !== null" class="rounded-lg border border-destructive/35 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ patientProfileError }}</p>

                <Form
                    v-if="editingPatient"
                    v-bind="PatientController.update.form(editingPatient.id)"
                    class="space-y-4"
                    :options="{ preserveScroll: true }"
                    @success="closeEditPatient"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_patient_file_number">رقم الملف</Label>
                            <Input id="edit_patient_file_number" name="file_number" :value="editingPatient.file_number" class="pattern-field-clay" required />
                            <InputError :message="errors.file_number" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_patient_national_id">رقم الهوية</Label>
                            <Input id="edit_patient_national_id" name="national_id" :value="editingPatient.national_id ?? ''" class="pattern-field-clay" />
                            <InputError :message="errors.national_id" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_patient_first_name">الاسم الأول</Label>
                            <Input id="edit_patient_first_name" name="first_name" :value="editingPatient.first_name" class="pattern-field-clay" required />
                            <InputError :message="errors.first_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_patient_last_name">اسم العائلة</Label>
                            <Input id="edit_patient_last_name" name="last_name" :value="editingPatient.last_name" class="pattern-field-clay" required />
                            <InputError :message="errors.last_name" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="edit_patient_dob">تاريخ الميلاد</Label>
                            <Input id="edit_patient_dob" name="date_of_birth" type="date" :value="editingPatient.date_of_birth ?? ''" class="pattern-field-clay" />
                            <InputError :message="errors.date_of_birth" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_patient_gender">الجنس</Label>
                            <select id="edit_patient_gender" name="gender" class="pattern-field-clay h-10 px-3 py-2" :value="editingPatient.gender ?? ''">
                                <option value="">غير محدد</option>
                                <option value="male">ذكر</option>
                                <option value="female">أنثى</option>
                                <option value="other">آخر</option>
                            </select>
                            <InputError :message="errors.gender" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_patient_phone">الهاتف</Label>
                            <Input id="edit_patient_phone" name="phone" :value="editingPatient.phone ?? ''" class="pattern-field-clay" />
                            <InputError :message="errors.phone" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_patient_email">البريد الإلكتروني</Label>
                        <Input id="edit_patient_email" name="email" type="email" :value="editingPatient.email ?? ''" class="pattern-field-clay" />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_patient_emergency_name">اسم جهة اتصال الطوارئ</Label>
                            <Input id="edit_patient_emergency_name" name="emergency_contact_name" :value="editingPatient.emergency_contact_name ?? ''" class="pattern-field-clay" />
                            <InputError :message="errors.emergency_contact_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_patient_emergency_phone">هاتف جهة اتصال الطوارئ</Label>
                            <Input id="edit_patient_emergency_phone" name="emergency_contact_phone" :value="editingPatient.emergency_contact_phone ?? ''" class="pattern-field-clay" />
                            <InputError :message="errors.emergency_contact_phone" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_patient_notes">ملاحظات</Label>
                        <textarea id="edit_patient_notes" name="notes" rows="3" class="pattern-field-clay" :value="editingPatient.notes ?? ''"></textarea>
                        <InputError :message="errors.notes" />
                    </div>

                    <div class="grid gap-2 rounded-xl border border-border/60 bg-background/40 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <Label class="text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground">أمراض مزمنة</Label>
                            <Button type="button" size="sm" variant="neumorphic" class="h-10 px-2 text-xs" @click="addMedicalItem(editChronicConditions)">إضافة</Button>
                        </div>
                        <div v-for="(item, index) in editChronicConditions" :key="`edit-chronic-${index}`" class="flex items-center gap-2">
                            <Input :name="`chronic_conditions[${index}]`" v-model="editChronicConditions[index]" placeholder="اسم المرض" class="pattern-field-clay" />
                            <Button type="button" size="sm" variant="ghost" class="h-10 px-2 text-xs" @click="removeMedicalItem(editChronicConditions, index)">حذف</Button>
                        </div>
                        <InputError :message="errors.chronic_conditions" />
                    </div>

                    <div class="grid gap-2 rounded-xl border border-border/60 bg-background/40 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <Label class="text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground">حساسية</Label>
                            <Button type="button" size="sm" variant="neumorphic" class="h-10 px-2 text-xs" @click="addMedicalItem(editAllergies)">إضافة</Button>
                        </div>
                        <div v-for="(item, index) in editAllergies" :key="`edit-allergy-${index}`" class="flex items-center gap-2">
                            <Input :name="`allergies[${index}]`" v-model="editAllergies[index]" placeholder="اسم الحساسية" class="pattern-field-clay" />
                            <Button type="button" size="sm" variant="ghost" class="h-10 px-2 text-xs" @click="removeMedicalItem(editAllergies, index)">حذف</Button>
                        </div>
                        <InputError :message="errors.allergies" />
                    </div>

                    <div class="grid gap-2 rounded-xl border border-border/60 bg-background/40 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <Label class="text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground">أدوية حالية</Label>
                            <Button type="button" size="sm" variant="neumorphic" class="h-10 px-2 text-xs" @click="addMedicalItem(editCurrentMedications)">إضافة</Button>
                        </div>
                        <div v-for="(item, index) in editCurrentMedications" :key="`edit-medication-${index}`" class="flex items-center gap-2">
                            <Input :name="`current_medications[${index}]`" v-model="editCurrentMedications[index]" placeholder="اسم الدواء" class="pattern-field-clay" />
                            <Button type="button" size="sm" variant="ghost" class="h-10 px-2 text-xs" @click="removeMedicalItem(editCurrentMedications, index)">حذف</Button>
                        </div>
                        <InputError :message="errors.current_medications" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="ghost" :disabled="processing" class="min-h-[44px]" @click="closeEditPatient">إلغاء</Button>
                        <Button type="submit" variant="clay" :disabled="processing" class="min-h-[44px]">حفظ التغييرات</Button>
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
