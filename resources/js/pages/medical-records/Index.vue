<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarClock,
    Eye,
    FileText,
    Filter,
    Plus,
    Search,
    Stethoscope,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import { FilterBar, FilterSearch, FilterSelect } from '@/components/ui/filter';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';

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

type Patient = {
    id: number;
    full_name: string;
    file_number: number;
};

type Clinic = {
    id: number;
    name: string;
    code: string | null;
};

type Doctor = {
    id: number;
    name: string;
};

type MedicalRecord = {
    id: number;
    clinic_id: number;
    patient_id: number;
    patient: Patient;
    clinic: Clinic | null;
    appointment_id: number | null;
    doctor_id: number | null;
    doctor: Doctor | null;
    record_number: string;
    clinic_type: string | null;
    form_data: Record<string, unknown> | null;
    chief_complaint: string | null;
    primary_diagnosis: string | null;
    secondary_diagnosis: string | null;
    clinical_notes: string | null;
    examination: string | null;
    status: string;
    visit_date: string | null;
    created_at: string | null;
    updated_at: string | null;
};

type PaginatedResponse<T> = {
    data: T[];
    links: PaginationNavigation;
    meta: PaginationMeta;
};

const { records, clinics, doctors, clinicTypes, filters, is_doctor } = defineProps<{
    records: PaginatedResponse<MedicalRecord>;
    clinics: Clinic[];
    doctors: Doctor[];
    clinicTypes: string[];
    filters: {
        search: string | null;
        per_page: number;
        clinic_id: number | null;
        doctor_id: number | null;
        clinic_type: string | null;
        status: string | null;
        date_from: string | null;
        date_to: string | null;
        diagnosis: string | null;
        sort_by: string;
        sort_direction: string;
    };
    is_doctor: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'السجلات الطبية',
                href: '/medical-records',
            },
        ],
    },
});

const { can } = usePermissions();
const { isOpen: isConfirmOpen, options: confirmOptions, confirm, close: closeConfirm, handleConfirm: handleConfirmDelete, handleCancel: handleConfirmCancel } = useConfirm();
const { success: toastSuccess } = useToast();

const deleteRecordId = ref<number | null>(null);

const search = ref(filters.search ?? '');
const selectedClinic = ref<string>(filters.clinic_id?.toString() ?? '');
const selectedDoctor = ref<string>(filters.doctor_id?.toString() ?? '');
const selectedClinicType = ref(filters.clinic_type ?? '');
const selectedStatus = ref(filters.status ?? '');
const dateFrom = ref(filters.date_from ?? '');
const dateTo = ref(filters.date_to ?? '');
const diagnosis = ref(filters.diagnosis ?? '');

const clinicTypeOptions = computed(() => [
    { label: 'جميع الأنواع', value: '' },
    ...clinicTypes.map((type) => ({ label: clinicTypeLabel(type), value: type })),
]);

const statusOptions = [
    { label: 'جميع الحالات', value: '' },
    { label: 'مسودة', value: 'draft' },
    { label: 'نشط', value: 'active' },
    { label: 'مكتمل', value: 'completed' },
    { label: 'ملغي', value: 'cancelled' },
];

const clinicOptions = computed(() => [
    { label: 'جميع العيادات', value: '' },
    ...clinics.map((d) => ({ label: d.name, value: d.id.toString() })),
]);

const doctorOptions = computed(() => [
    { label: 'جميع الأطباء', value: '' },
    ...doctors.map((d) => ({ label: d.name, value: d.id.toString() })),
]);

const activeFilters = computed(() => {
    const filters: Array<{ key: string; label: string; value: string | null }> = [];

    if (search.value) {
        filters.push({ key: 'search', label: 'البحث', value: search.value });
    }
    if (selectedClinicType.value) {
        const opt = clinicTypeOptions.value.find((o) => o.value === selectedClinicType.value);
        filters.push({ key: 'clinic_type', label: 'نوع العيادة', value: opt?.label ?? selectedClinicType.value });
    }
    if (selectedClinic.value) {
        const opt = clinicOptions.value.find((o) => o.value === selectedClinic.value);
        filters.push({ key: 'clinic_id', label: 'العيادة', value: opt?.label ?? null });
    }
    if (selectedDoctor.value) {
        const opt = doctorOptions.value.find((o) => o.value === selectedDoctor.value);
        filters.push({ key: 'doctor_id', label: 'الطبيب', value: opt?.label ?? null });
    }
    if (selectedStatus.value) {
        const opt = statusOptions.find((o) => o.value === selectedStatus.value);
        filters.push({ key: 'status', label: 'الحالة', value: opt?.label ?? selectedStatus.value });
    }
    if (dateFrom.value) {
        filters.push({ key: 'date_from', label: 'من تاريخ', value: dateFrom.value });
    }
    if (dateTo.value) {
        filters.push({ key: 'date_to', label: 'إلى تاريخ', value: dateTo.value });
    }
    if (diagnosis.value) {
        filters.push({ key: 'diagnosis', label: 'التشخيص', value: diagnosis.value });
    }

    return filters;
});

function handleRemoveFilter(key: string) {
    switch (key) {
        case 'search':
            search.value = '';
            break;
        case 'clinic_type':
            selectedClinicType.value = '';
            break;
        case 'clinic_id':
            selectedClinic.value = '';
            break;
        case 'doctor_id':
            selectedDoctor.value = '';
            break;
        case 'status':
            selectedStatus.value = '';
            break;
        case 'date_from':
            dateFrom.value = '';
            break;
        case 'date_to':
            dateTo.value = '';
            break;
        case 'diagnosis':
            diagnosis.value = '';
            break;
    }
}

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

watch(search, (value) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
});

watch(
    [selectedClinic, selectedDoctor, selectedClinicType, selectedStatus, dateFrom, dateTo, diagnosis],
    () => {
        applyFilters();
    },
);

function applyFilters() {
    const params: Record<string, string> = {};

    if (search.value) {
        params.search = search.value;
    }
    if (selectedClinic.value) {
        params.clinic_id = selectedClinic.value;
    }
    if (selectedDoctor.value) {
        params.doctor_id = selectedDoctor.value;
    }
    if (selectedClinicType.value) {
        params.clinic_type = selectedClinicType.value;
    }
    if (selectedStatus.value) {
        params.status = selectedStatus.value;
    }
    if (dateFrom.value) {
        params.date_from = dateFrom.value;
    }
    if (dateTo.value) {
        params.date_to = dateTo.value;
    }
    if (diagnosis.value) {
        params.diagnosis = diagnosis.value;
    }

    router.get('/medical-records', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters() {
    search.value = '';
    selectedClinic.value = '';
    selectedDoctor.value = '';
    selectedClinicType.value = '';
    selectedStatus.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    diagnosis.value = '';
    router.get('/medical-records', {}, { preserveState: true, preserveScroll: true });
}

async function handleDelete(recordId: number) {
    const confirmed = await confirm({
        title: 'تأكيد الحذف',
        description: 'هل أنت متأكد من حذف هذا السجل الطبي؟',
        variant: 'destructive',
        confirmText: 'حذف',
        cancelText: 'إلغاء',
    });

    if (confirmed) {
        deleteRecordId.value = recordId;
        handleConfirmDelete();
        router.delete(`/medical-records/${recordId}`, {
            preserveScroll: true,
            onSuccess: () => {
                closeConfirm();
                toastSuccess('تم حذف السجل الطبي بنجاح.');
            },
            onFinish: () => {
                deleteRecordId.value = null;
            },
        });
    }
}

function clinicTypeLabel(type: string | null): string {
    if (!type) {
        return 'غير محدد';
    }
    const labels: Record<string, string> = {
        internal_medicine: 'باطنية',
        pediatrics: 'أطفال',
        gynecology: 'نسائية وتوليد',
        orthopedics: 'عظام',
        dermatology: 'جلدية',
        ophthalmology: 'عيون',
        ent: 'أنف وأذن وحنجرة',
        cardiology: 'قلب',
        neurology: 'أعصاب',
        psychiatry: 'نفسية',
        general_surgery: 'جراحة عامة',
        urology: 'مسالك بولية',
        dental: 'أسنان',
        other: 'أخرى',
    };

    return labels[type] ?? type;
}

function statusLabel(status: string): string {
    const labels: Record<string, string> = {
        draft: 'مسودة',
        active: 'نشط',
        completed: 'مكتمل',
        cancelled: 'ملغي',
    };

    return labels[status] ?? status;
}

function statusClass(status: string): string {
    const classes: Record<string, string> = {
        draft: 'bg-gray-500/10 text-gray-600 border-gray-500/20',
        active: 'bg-blue-500/10 text-blue-600 border-blue-500/20',
        completed: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
        cancelled: 'bg-red-500/10 text-red-600 border-red-500/20',
    };

    return classes[status] ?? 'bg-muted/50 text-muted-foreground border-border/40';
}

function formatDate(date: string | null): string {
    if (!date) {
        return '—';
    }

    return new Date(date).toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatDateTime(date: string | null): string {
    if (!date) {
        return '—';
    }

    return new Date(date).toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="السجلات الطبية" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10">
                    <Stethoscope class="size-5 text-primary" />
                </div>
                <div>
                    <h1 class="page-title">السجلات الطبية</h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ records.meta.total }} سجل طبي
                    </p>
                </div>
            </div>

            <Link
                v-if="can('medical_record.create')"
                href="/medical-records/create"
                class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90"
            >
                <Plus class="size-4" />
                سجل طبي جديد
            </Link>
        </div>

        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-[240px] flex-1">
                    <FilterSearch v-model="search" placeholder="بحث بالاسم أو رقم السجل أو التشخيص..." />
                </div>
                <FilterSelect v-if="!is_doctor" v-model="selectedClinicType" :options="clinicTypeOptions" placeholder="نوع العيادة" />
                <FilterSelect v-if="!is_doctor" v-model="selectedClinic" :options="clinicOptions" placeholder="العيادة" />
                <FilterSelect v-if="!is_doctor" v-model="selectedDoctor" :options="doctorOptions" placeholder="الطبيب" />
                <FilterSelect v-model="selectedStatus" :options="statusOptions" placeholder="الحالة" />
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <Label class="text-xs text-muted-foreground">من</Label>
                    <Input
                        v-model="dateFrom"
                        type="date"
                        class="h-8 w-36 text-xs"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Label class="text-xs text-muted-foreground">إلى</Label>
                    <Input
                        v-model="dateTo"
                        type="date"
                        class="h-8 w-36 text-xs"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Label class="text-xs text-muted-foreground">التشخيص</Label>
                    <Input
                        v-model="diagnosis"
                        placeholder="بحث بالتشخيص..."
                        class="h-8 w-48 text-xs"
                    />
                </div>
                <Button variant="ghost" size="sm" class="h-8 text-xs" @click="resetFilters">
                    إعادة تعيين
                </Button>
            </div>

            <FilterBar
                v-if="activeFilters.length > 0"
                :active-filters="activeFilters"
                @remove="handleRemoveFilter"
                @clear-all="resetFilters"
            />
        </div>

        <div class="glass-panel-soft overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border/50">
                            <th class="px-4 py-3 text-right text-xs font-semibold text-muted-foreground">التاريخ</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-muted-foreground">المريض</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-muted-foreground">الطبيب</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-muted-foreground">العيادة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-muted-foreground">التشخيص</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-muted-foreground">الحالة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-muted-foreground">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="record in records.data"
                            :key="record.id"
                            class="border-b border-border/30 transition-colors hover:bg-muted/30"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <CalendarClock class="size-3.5 text-muted-foreground" />
                                    <span class="text-xs">{{ formatDate(record.visit_date) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div>
                                    <p class="font-medium">{{ record.patient?.full_name ?? '—' }}</p>
                                    <p class="text-xs text-muted-foreground">ملف: {{ record.patient?.file_number ?? '—' }}</p>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs">{{ record.doctor?.name ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div>
                                    <p class="text-xs">{{ record.clinic?.name ?? '—' }}</p>
                                    <Badge v-if="record.clinic_type" variant="outline" class="mt-1 text-[0.6rem]">
                                        {{ clinicTypeLabel(record.clinic_type) }}
                                    </Badge>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <p class="max-w-[200px] truncate text-xs" :title="record.primary_diagnosis ?? ''">
                                    {{ record.primary_diagnosis ?? '—' }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <Badge :class="statusClass(record.status)">{{ statusLabel(record.status) }}</Badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <Link
                                        :href="`/medical-records/${record.id}`"
                                        class="inline-flex items-center gap-1 rounded-lg border border-border/60 bg-background/40 px-2 py-1 text-xs transition-colors hover:bg-background/60"
                                    >
                                        <Eye class="size-3" />
                                        عرض
                                    </Link>
                                    <button
                                        v-if="can('medical_record.delete')"
                                        class="inline-flex items-center gap-1 rounded-lg border border-red-500/20 bg-red-500/5 px-2 py-1 text-xs text-red-600 transition-colors hover:bg-red-500/10"
                                        @click="handleDelete(record.id)"
                                    >
                                        حذف
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="records.data.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <FileText class="size-8 text-muted-foreground/50" />
                                    <p class="text-sm text-muted-foreground">لا توجد سجلات طبية</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="records.meta.last_page > 1" class="flex items-center justify-between border-t border-border/50 px-4 py-3">
                <p class="text-xs text-muted-foreground">
                    عرض {{ records.meta.from }} إلى {{ records.meta.to }} من {{ records.meta.total }}
                </p>
                <div class="flex items-center gap-1">
                    <Link
                        v-if="records.links.prev"
                        :href="records.links.prev"
                        class="rounded-lg border border-border/60 px-3 py-1.5 text-xs transition-colors hover:bg-muted/50"
                        preserve-scroll
                        preserve-state
                    >
                        السابق
                    </Link>
                    <span class="px-3 py-1.5 text-xs text-muted-foreground">
                        صفحة {{ records.meta.current_page }} من {{ records.meta.last_page }}
                    </span>
                    <Link
                        v-if="records.links.next"
                        :href="records.links.next"
                        class="rounded-lg border border-border/60 px-3 py-1.5 text-xs transition-colors hover:bg-muted/50"
                        preserve-scroll
                        preserve-state
                    >
                        التالي
                    </Link>
                </div>
            </div>
        </div>
    </div>

    <ConfirmationDialog
        :open="isConfirmOpen"
        :options="confirmOptions"
        @confirm="handleConfirmDelete"
        @cancel="handleConfirmCancel"
        @update:open="(v) => !v && handleConfirmCancel()"
    />
</template>
