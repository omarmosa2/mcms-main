<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { destroy as destroyFollowUp, store as storeFollowUp, update as updateFollowUp } from '@/actions/App/Http/Controllers/MedicalRecords/FollowUpController';
import { index as medicalRecordsIndex, show as showMedicalRecord, update as updateMedicalRecord } from '@/actions/App/Http/Controllers/MedicalRecords/MedicalRecordController';
import { exportMethod as exportMedicalRecord } from '@/actions/App/Http/Controllers/MedicalRecords/MedicalRecordExportController';
import { destroy as destroyTreatmentPlan, store as storeTreatmentPlan, update as updateTreatmentPlan } from '@/actions/App/Http/Controllers/MedicalRecords/TreatmentPlanController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';
import {
    AlertTriangle,
    ArrowLeft,
    CalendarClock,
    ClipboardCheck,
    ClipboardList,
    Download,
    FileClock,
    FlaskConical,
    HeartPulse,
    History,
    Image,
    LockKeyhole,
    Paperclip,
    Pill,
    Plus,
    Printer,
    Save,
    Search,
    Send,
    ShieldCheck,
    Sparkles,
    Stethoscope,
    Syringe,
    Trash2,
    User,
} from 'lucide-vue-next';
import { computed, ref, watch, watchEffect } from 'vue';

type Person = {
    id: number;
    name: string;
};

type PatientProfileItem = {
    id: number;
    condition?: string;
    allergy?: string;
    medication?: string;
};

type PatientAttachment = {
    id: number;
    original_name: string;
    mime_type: string | null;
    extension: string | null;
    size_bytes: number;
    uploaded_at: string | null;
    uploaded_by: Person | null;
    download_url: string;
};

type LabOrder = {
    id: number;
    test_code: string | null;
    test_name: string;
    status: string;
    ordered_at: string | null;
    notes: string | null;
    orderer: Person | null;
    results: Array<{
        id: number;
        result_value: string | null;
        unit: string | null;
        reference_range: string | null;
        notes: string | null;
        resulted_at: string | null;
    }>;
};

type RadiologyOrder = {
    id: number;
    study_code: string | null;
    study_name: string;
    modality: string | null;
    status: string;
    ordered_at: string | null;
    notes: string | null;
    orderer: Person | null;
    reports: Array<{
        id: number;
        report_text: string | null;
        reported_at: string | null;
    }>;
};

type Patient = {
    id: number;
    full_name: string;
    file_number: number;
    phone: string | null;
    date_of_birth: string | null;
    gender: string | null;
    chronic_conditions: PatientProfileItem[];
    allergies: PatientProfileItem[];
    medications: PatientProfileItem[];
    attachments: PatientAttachment[];
    lab_orders: LabOrder[];
    radiology_orders: RadiologyOrder[];
};

type Department = {
    id: number;
    name: string;
    clinic_type: string | null;
};

type TreatmentPlan = {
    id: number;
    medical_record_id: number;
    patient_id: number;
    doctor_id: number | null;
    doctor: Person | null;
    title: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    status: string;
    created_at: string | null;
    updated_at: string | null;
};

type FollowUp = {
    id: number;
    medical_record_id: number | null;
    patient_id: number;
    doctor_id: number | null;
    doctor: Person | null;
    follow_up_date: string | null;
    notes: string | null;
    recommended_action: string | null;
    status: string;
    created_at: string | null;
    updated_at: string | null;
};

type Prescription = {
    id: number;
    prescription_number: string;
    status: string;
    diagnosis: string | null;
    notes: string | null;
    issued_at: string | null;
    dispensed_at: string | null;
    prescriber: Person | null;
    items: Array<{
        id: number;
        medication_name: string;
        dosage: string;
        frequency: string;
        duration: string | null;
        quantity: number;
        instructions: string | null;
    }>;
};

type AuditLog = {
    id: number;
    action: string;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    metadata: Record<string, unknown> | null;
    occurred_at: string | null;
    user: Person | null;
};

type MedicalRecord = {
    id: number;
    clinic_id: number;
    patient_id: number;
    patient: Patient;
    department_id: number | null;
    department: Department | null;
    appointment_id: number | null;
    doctor_id: number | null;
    doctor: Person | null;
    record_number: string;
    clinic_type: string | null;
    form_data: Record<string, string | number | null> | null;
    chief_complaint: string | null;
    primary_diagnosis: string | null;
    secondary_diagnosis: string | null;
    clinical_notes: string | null;
    examination: string | null;
    status: string;
    visit_date: string | null;
    creator: Person | null;
    prescriptions: Prescription[];
    treatment_plans: TreatmentPlan[];
    follow_ups: FollowUp[];
    audit_logs: AuditLog[];
    created_at: string | null;
    updated_at: string | null;
};

const props = defineProps<{
    record: MedicalRecord | { data: MedicalRecord };
}>();

const record = 'data' in props.record ? props.record.data : props.record;

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: 'السجلات الطبية',
                href: medicalRecordsIndex.url(),
            },
            {
                title: `سجل #${record.record_number}`,
                href: showMedicalRecord.url({ recordId: record.id }),
            },
        ],
    });
});

const { can } = usePermissions();
const { success: toastSuccess } = useToast();

const activeTab = ref('overview');
const showTreatmentPlanDialog = ref(false);
const showFollowUpDialog = ref(false);
const autosaveState = ref<'idle' | 'saving' | 'saved'>('idle');
let autosaveTimeout: ReturnType<typeof setTimeout> | null = null;

const clinicalForm = useForm({
    chief_complaint: record.chief_complaint ?? '',
    examination: record.examination ?? '',
    primary_diagnosis: record.primary_diagnosis ?? '',
    secondary_diagnosis: record.secondary_diagnosis ?? '',
    clinical_notes: record.clinical_notes ?? '',
});

watch(
    () => ({
        chief_complaint: clinicalForm.chief_complaint,
        examination: clinicalForm.examination,
        primary_diagnosis: clinicalForm.primary_diagnosis,
        secondary_diagnosis: clinicalForm.secondary_diagnosis,
        clinical_notes: clinicalForm.clinical_notes,
    }),
    () => {
        if (!can('medical_record.update')) {
            return;
        }

        autosaveState.value = 'saving';

        if (autosaveTimeout) {
            clearTimeout(autosaveTimeout);
        }

        autosaveTimeout = setTimeout(() => {
            clinicalForm.patch(updateMedicalRecord.url({ recordId: record.id }), {
                preserveScroll: true,
                onSuccess: () => {
                    autosaveState.value = 'saved';
                    window.setTimeout(() => {
                        autosaveState.value = 'idle';
                    }, 1800);
                },
            });
        }, 1200);
    },
    { deep: true },
);

const treatmentPlanForm = useForm({
    medical_record_id: record.id,
    patient_id: record.patient_id,
    title: '',
    description: '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
    status: 'new',
});

const followUpForm = useForm({
    medical_record_id: record.id,
    patient_id: record.patient_id,
    follow_up_date: '',
    notes: '',
    recommended_action: '',
    status: 'scheduled',
});

const tabs = [
    { id: 'overview', label: 'Overview', arabic: 'نظرة عامة', icon: ClipboardList },
    { id: 'history', label: 'History', arabic: 'التاريخ المرضي', icon: History },
    { id: 'vitals', label: 'Vitals', arabic: 'العلامات الحيوية', icon: HeartPulse },
    { id: 'examination', label: 'Examination', arabic: 'الفحص السريري', icon: Stethoscope },
    { id: 'diagnoses', label: 'Diagnoses', arabic: 'التشخيصات', icon: ClipboardCheck },
    { id: 'treatment', label: 'Treatment Plan', arabic: 'خطة العلاج', icon: Syringe },
    { id: 'prescriptions', label: 'Prescriptions', arabic: 'الوصفات', icon: Pill },
    { id: 'labs', label: 'Labs', arabic: 'المخبر', icon: FlaskConical },
    { id: 'imaging', label: 'Imaging', arabic: 'الأشعة', icon: Image },
    { id: 'followups', label: 'Follow-ups', arabic: 'المتابعات', icon: CalendarClock },
    { id: 'attachments', label: 'Attachments', arabic: 'المرفقات', icon: Paperclip },
    { id: 'audit', label: 'Audit Log', arabic: 'التدقيق', icon: FileClock },
];

const visitMetrics = computed(() => [
    { label: 'رقم السجل', value: record.record_number },
    { label: 'رقم الزيارة', value: record.appointment_id ? `APT-${record.appointment_id}` : 'غير مرتبط' },
    { label: 'رقم الملف', value: record.patient?.file_number ?? '—' },
    { label: 'الجنس', value: genderLabel(record.patient?.gender) },
    { label: 'العمر', value: calculateAge(record.patient?.date_of_birth) },
    { label: 'الهاتف', value: record.patient?.phone ?? '—' },
    { label: 'العيادة', value: record.department?.name ?? '—' },
    { label: 'الطبيب', value: record.doctor?.name ?? '—' },
    { label: 'تاريخ الزيارة', value: formatDate(record.visit_date) },
    { label: 'نوع الزيارة', value: visitTypeLabel(record.form_data?.visit_type) },
]);

const formData = computed(() => record.form_data ?? {});

const vitalSigns = computed(() => {
    const items = [
        { key: 'weight', label: 'الوزن', unit: 'kg', value: valueOf(['weight', 'weight_kg']) },
        { key: 'height', label: 'الطول', unit: 'cm', value: valueOf(['height', 'height_cm']) },
        { key: 'bmi', label: 'BMI', unit: '', value: calculatedBmi.value },
        { key: 'blood_pressure', label: 'ضغط الدم', unit: '', value: valueOf(['blood_pressure', 'bp']) },
        { key: 'pulse', label: 'النبض', unit: 'bpm', value: valueOf(['pulse', 'heart_rate']) },
        { key: 'temperature', label: 'الحرارة', unit: '°C', value: valueOf(['temperature', 'temp']) },
        { key: 'respiratory_rate', label: 'التنفس', unit: '/min', value: valueOf(['respiratory_rate', 'rr']) },
        { key: 'spo2', label: 'SpO2', unit: '%', value: valueOf(['spo2', 'oxygen_saturation']) },
        { key: 'random_glucose', label: 'سكر عشوائي', unit: 'mg/dL', value: valueOf(['random_glucose', 'blood_glucose']) },
    ];

    return items;
});

const calculatedBmi = computed(() => {
    const weight = Number(valueOf(['weight', 'weight_kg']));
    const heightCm = Number(valueOf(['height', 'height_cm']));

    if (!weight || !heightCm) {
        return valueOf(['bmi']) || '—';
    }

    return (weight / ((heightCm / 100) ** 2)).toFixed(1);
});

const completionAlerts = computed(() => {
    const alerts = [];

    if (!clinicalForm.chief_complaint) {
        alerts.push('الشكوى الرئيسية غير مكتملة.');
    }
    if (!clinicalForm.primary_diagnosis) {
        alerts.push('لا يوجد تشخيص رئيسي مؤكد بعد.');
    }
    if (record.patient?.allergies?.length === 0) {
        alerts.push('لم يتم توثيق الحساسية أو نفيها.');
    }
    if (record.follow_ups?.some((followUp) => followUp.status === 'scheduled')) {
        alerts.push('يوجد موعد متابعة مجدول يحتاج مراقبة.');
    }

    return alerts;
});

const timelineItems = computed(() => [
    ...record.treatment_plans.map((plan) => ({
        id: `plan-${plan.id}`,
        date: plan.start_date ?? plan.created_at,
        title: plan.title,
        description: plan.description,
        status: plan.status,
        type: 'خطة علاج',
    })),
    ...record.follow_ups.map((followUp) => ({
        id: `follow-${followUp.id}`,
        date: followUp.follow_up_date ?? followUp.created_at,
        title: followUp.recommended_action ?? 'متابعة',
        description: followUp.notes,
        status: followUp.status,
        type: 'متابعة',
    })),
].sort((a, b) => new Date(a.date ?? '').getTime() - new Date(b.date ?? '').getTime()));

const canEditClinical = computed(() => can('medical_record.update'));

function submitTreatmentPlan() {
    treatmentPlanForm.post(storeTreatmentPlan.url(), {
        preserveScroll: true,
        onSuccess: () => {
            showTreatmentPlanDialog.value = false;
            treatmentPlanForm.reset();
            toastSuccess('تم إضافة خطة العلاج بنجاح.');
            router.reload();
        },
    });
}

function submitFollowUp() {
    followUpForm.post(storeFollowUp.url(), {
        preserveScroll: true,
        onSuccess: () => {
            showFollowUpDialog.value = false;
            followUpForm.reset();
            toastSuccess('تم إضافة المتابعة بنجاح.');
            router.reload();
        },
    });
}

function deleteTreatmentPlan(planId: number) {
    router.delete(destroyTreatmentPlan.url({ planId }), {
        preserveScroll: true,
        onSuccess: () => {
            toastSuccess('تم حذف خطة العلاج.');
            router.reload();
        },
    });
}

function deleteFollowUp(followUpId: number) {
    router.delete(destroyFollowUp.url({ followUpId }), {
        preserveScroll: true,
        onSuccess: () => {
            toastSuccess('تم حذف المتابعة.');
            router.reload();
        },
    });
}

function updateTreatmentPlanStatus(planId: number, status: string) {
    router.put(updateTreatmentPlan.url({ planId }), { status }, {
        preserveScroll: true,
        onSuccess: () => {
            toastSuccess('تم تحديث حالة خطة العلاج.');
            router.reload();
        },
    });
}

function updateFollowUpStatus(followUpId: number, status: string) {
    router.put(updateFollowUp.url({ followUpId }), { status }, {
        preserveScroll: true,
        onSuccess: () => {
            toastSuccess('تم تحديث حالة المتابعة.');
            router.reload();
        },
    });
}

function valueOf(keys: string[]): string {
    for (const key of keys) {
        const value = formData.value[key];

        if (value !== null && value !== undefined && `${value}`.trim() !== '') {
            return `${value}`;
        }
    }

    return '';
}

function genderLabel(gender: string | null | undefined): string {
    if (gender === 'male') {
        return 'ذكر';
    }
    if (gender === 'female') {
        return 'أنثى';
    }

    return '—';
}

function calculateAge(date: string | null | undefined): string {
    if (!date) {
        return '—';
    }

    const birthDate = new Date(date);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDelta = today.getMonth() - birthDate.getMonth();

    if (monthDelta < 0 || (monthDelta === 0 && today.getDate() < birthDate.getDate())) {
        age -= 1;
    }

    return `${age} سنة`;
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

function visitTypeLabel(value: unknown): string {
    const labels: Record<string, string> = {
        first_visit: 'كشفية أولى',
        review: 'مراجعة',
        emergency: 'إسعافية',
        follow_up: 'متابعة',
        consultation: 'استشارة',
    };

    return labels[String(value ?? '')] ?? (value ? String(value) : 'غير محدد');
}

function statusLabel(status: string): string {
    const labels: Record<string, string> = {
        active: 'حاضر',
        cancelled: 'ملغاة',
        canceled: 'ملغاة',
        completed: 'مكتملة',
        dispensed: 'مصروفة',
        draft: 'مسودة',
        in_progress: 'جارٍ التنفيذ',
        issued: 'مصُدرة',
        missed: 'فائتة',
        new: 'مخطط',
        ordered: 'مطلوب',
        reported: 'مُبلّغ',
        resulted: 'جاهز',
        sample_collected: 'أُخذت العينة',
        scheduled: 'مجدولة',
    };

    return labels[status] ?? status;
}

function statusClass(status: string): string {
    const classes: Record<string, string> = {
        active: 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
        cancelled: 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:text-rose-300',
        canceled: 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:text-rose-300',
        completed: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        dispensed: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        draft: 'border-slate-500/20 bg-slate-500/10 text-slate-700 dark:text-slate-300',
        in_progress: 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        issued: 'border-indigo-500/20 bg-indigo-500/10 text-indigo-700 dark:text-indigo-300',
        missed: 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:text-rose-300',
        new: 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
        ordered: 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
        reported: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        resulted: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        sample_collected: 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        scheduled: 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    };

    return classes[status] ?? 'border-border bg-muted/50 text-muted-foreground';
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

function fileSize(size: number): string {
    if (size < 1024) {
        return `${size} B`;
    }
    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / 1024 / 1024).toFixed(1)} MB`;
}
</script>

<template>
    <Head :title="`السجل الطبي الإلكتروني - ${record.record_number}`" />

    <div class="mx-auto w-full max-w-[1800px] space-y-4 p-3 sm:p-4 lg:p-6" dir="rtl">
        <section class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
            <div class="flex flex-col gap-4 border-b border-border bg-muted/30 p-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center">
                    <Link :href="medicalRecordsIndex.url()" class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-background text-muted-foreground transition hover:text-foreground">
                        <ArrowLeft class="size-4" />
                    </Link>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-semibold text-foreground">السجل الطبي الإلكتروني</h1>
                            <Badge :class="statusClass(record.status)" class="border">{{ statusLabel(record.status) }}</Badge>
                            <Badge variant="outline">{{ clinicTypeLabel(record.clinic_type) }}</Badge>
                        </div>
                        <p class="mt-1 truncate text-sm text-muted-foreground">
                            {{ record.patient?.full_name }} · {{ record.record_number }} · {{ record.department?.name ?? 'بدون عيادة' }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-xs text-muted-foreground">
                        <Save class="size-3.5" />
                        <span v-if="autosaveState === 'saving'">جارٍ الحفظ التلقائي</span>
                        <span v-else-if="autosaveState === 'saved'">تم الحفظ</span>
                        <span v-else>Autosave جاهز</span>
                    </div>
                    <a :href="exportMedicalRecord.url({ recordId: record.id })" class="inline-flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-xs font-medium transition hover:bg-muted">
                        <Download class="size-3.5" />
                        PDF
                    </a>
                </div>
            </div>

            <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <div class="flex items-center gap-3">
                        <div class="flex size-12 items-center justify-center rounded-lg bg-cyan-500/10 text-cyan-700 dark:text-cyan-300">
                            <User class="size-6" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-lg font-semibold">{{ record.patient?.full_name }}</p>
                            <p class="text-xs text-muted-foreground">ملف {{ record.patient?.file_number }} · {{ genderLabel(record.patient?.gender) }} · {{ calculateAge(record.patient?.date_of_birth) }}</p>
                        </div>
                    </div>
                </div>
                <div v-for="metric in visitMetrics.slice(1, 5)" :key="metric.label" class="rounded-lg border border-border bg-background p-3">
                    <p class="text-xs text-muted-foreground">{{ metric.label }}</p>
                    <p class="mt-1 truncate text-sm font-semibold">{{ metric.value }}</p>
                </div>
            </div>
        </section>

        <div v-if="completionAlerts.length > 0" class="grid gap-2 lg:grid-cols-3">
            <div v-for="alert in completionAlerts" :key="alert" class="flex items-start gap-2 rounded-lg border border-amber-500/25 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-200">
                <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                <span>{{ alert }}</span>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="rounded-lg border border-border bg-card p-2 lg:sticky lg:top-4 lg:h-fit">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-right text-sm transition"
                    :class="activeTab === tab.id ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                    @click="activeTab = tab.id"
                >
                    <component :is="tab.icon" class="size-4 shrink-0" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">{{ tab.arabic }}</span>
                        <span class="block truncate text-[0.7rem] opacity-75">{{ tab.label }}</span>
                    </span>
                </button>
            </aside>

            <main class="min-w-0 space-y-4">
                <section v-show="activeTab === 'overview'" class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <div v-for="metric in visitMetrics" :key="metric.label" class="rounded-lg border border-border bg-card p-4">
                            <p class="text-xs text-muted-foreground">{{ metric.label }}</p>
                            <p class="mt-1 truncate text-sm font-semibold">{{ metric.value }}</p>
                        </div>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                        <div class="rounded-lg border border-border bg-card p-4">
                            <div class="mb-3 flex items-center justify-between border-b border-border pb-3">
                                <h2 class="text-sm font-semibold">الشكوى الرئيسية Chief Complaint</h2>
                                <Sparkles class="size-4 text-cyan-600" />
                            </div>
                            <textarea
                                v-model="clinicalForm.chief_complaint"
                                rows="7"
                                :readonly="!canEditClinical"
                                class="min-h-40 w-full resize-y rounded-lg border border-border bg-background px-3 py-2 text-sm leading-7 outline-none focus:ring-2 focus:ring-ring"
                                placeholder="الشكوى الرئيسية، مدة الأعراض، الشدة، بداية الأعراض، والعوامل التي تزيد أو تخفف الأعراض..."
                            />
                            <InputError :message="clinicalForm.errors.chief_complaint" />
                        </div>

                        <div class="rounded-lg border border-border bg-card p-4">
                            <h2 class="mb-3 border-b border-border pb-3 text-sm font-semibold">ملخص السلامة السريرية</h2>
                            <div class="space-y-3">
                                <div class="rounded-lg border border-rose-500/20 bg-rose-500/5 p-3">
                                    <p class="text-xs font-semibold text-rose-700 dark:text-rose-300">الحساسيات</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <Badge v-for="allergy in record.patient.allergies" :key="allergy.id" variant="outline">{{ allergy.allergy }}</Badge>
                                        <span v-if="record.patient.allergies.length === 0" class="text-xs text-muted-foreground">لا توجد بيانات</span>
                                    </div>
                                </div>
                                <div class="rounded-lg border border-border p-3">
                                    <p class="text-xs font-semibold">الأمراض المزمنة</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <Badge v-for="condition in record.patient.chronic_conditions" :key="condition.id" variant="outline">{{ condition.condition }}</Badge>
                                        <span v-if="record.patient.chronic_conditions.length === 0" class="text-xs text-muted-foreground">لا توجد بيانات</span>
                                    </div>
                                </div>
                                <div class="rounded-lg border border-border p-3">
                                    <p class="text-xs font-semibold">الأدوية الحالية</p>
                                    <div class="mt-2 space-y-1">
                                        <p v-for="medication in record.patient.medications" :key="medication.id" class="text-xs text-muted-foreground">{{ medication.medication }}</p>
                                        <span v-if="record.patient.medications.length === 0" class="text-xs text-muted-foreground">لا توجد بيانات</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-show="activeTab === 'history'" class="grid gap-4 xl:grid-cols-3">
                    <div class="rounded-lg border border-border bg-card p-4">
                        <h2 class="mb-3 text-sm font-semibold">الأمراض السابقة</h2>
                        <div class="grid gap-2">
                            <label v-for="condition in ['سكري', 'ضغط', 'أمراض قلب', 'ربو', 'أمراض كلوية', 'أمراض كبد', 'اضطرابات الغدة', 'أمراض عصبية', 'أمراض نفسية']" :key="condition" class="flex items-center justify-between rounded-lg border border-border px-3 py-2 text-sm">
                                <span>{{ condition }}</span>
                                <span class="size-2 rounded-full" :class="record.patient.chronic_conditions.some((item) => item.condition === condition) ? 'bg-emerald-500' : 'bg-muted'" />
                            </label>
                        </div>
                    </div>
                    <div class="rounded-lg border border-border bg-card p-4">
                        <h2 class="mb-3 text-sm font-semibold">الحساسية</h2>
                        <div class="space-y-2">
                            <div v-for="allergy in record.patient.allergies" :key="allergy.id" class="rounded-lg border border-rose-500/20 bg-rose-500/5 p-3 text-sm">{{ allergy.allergy }}</div>
                            <p v-if="record.patient.allergies.length === 0" class="text-sm text-muted-foreground">لا توجد حساسيات مسجلة.</p>
                        </div>
                    </div>
                    <div class="rounded-lg border border-border bg-card p-4">
                        <h2 class="mb-3 text-sm font-semibold">التاريخ العائلي والاجتماعي</h2>
                        <dl class="grid gap-3 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">التدخين</dt>
                                <dd class="font-medium">{{ valueOf(['smoking']) || 'غير موثق' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">المهنة</dt>
                                <dd class="font-medium">{{ valueOf(['occupation']) || 'غير موثق' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">الحالة الاجتماعية</dt>
                                <dd class="font-medium">{{ valueOf(['social_status', 'marital_status']) || 'غير موثق' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">عمليات سابقة</dt>
                                <dd class="whitespace-pre-wrap font-medium">{{ valueOf(['previous_surgeries']) || 'غير موثق' }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <section v-show="activeTab === 'vitals'" class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <div v-for="vital in vitalSigns" :key="vital.key" class="rounded-lg border border-border bg-card p-4">
                            <p class="text-xs text-muted-foreground">{{ vital.label }}</p>
                            <p class="mt-2 text-2xl font-semibold">{{ vital.value || '—' }} <span class="text-xs font-normal text-muted-foreground">{{ vital.unit }}</span></p>
                        </div>
                    </div>
                    <div class="rounded-lg border border-border bg-card p-4">
                        <h2 class="mb-4 text-sm font-semibold">اتجاه العلامات الحيوية</h2>
                        <div class="flex h-52 items-end gap-3 rounded-lg border border-border bg-muted/20 p-4">
                            <div v-for="(vital, index) in vitalSigns.filter((item) => item.value && !Number.isNaN(Number.parseFloat(item.value)))" :key="vital.key" class="flex min-w-14 flex-1 flex-col items-center gap-2">
                                <div class="w-full rounded-t-md bg-cyan-500/70" :style="{ height: `${Math.max(18, Math.min(100, Number.parseFloat(vital.value) + index * 4))}%` }" />
                                <span class="text-center text-[0.65rem] text-muted-foreground">{{ vital.label }}</span>
                            </div>
                            <div v-if="vitalSigns.every((item) => !item.value)" class="flex h-full w-full items-center justify-center text-sm text-muted-foreground">
                                لا توجد علامات حيوية موثقة لهذا السجل.
                            </div>
                        </div>
                    </div>
                </section>

                <section v-show="activeTab === 'examination'" class="rounded-lg border border-border bg-card p-4">
                    <div class="mb-3 flex items-center justify-between border-b border-border pb-3">
                        <h2 class="text-sm font-semibold">الفحص السريري Physical Examination</h2>
                        <Badge variant="outline">Autosave</Badge>
                    </div>
                    <div class="grid gap-3 lg:grid-cols-2">
                        <div v-for="section in ['المظهر العام', 'الرأس والعنق', 'القلب', 'الرئتين', 'البطن', 'الأطراف', 'الجلد', 'الجهاز العصبي', 'الجهاز العضلي الهيكلي']" :key="section" class="rounded-lg border border-border p-3">
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-sm font-medium">{{ section }}</p>
                                <Badge variant="outline">{{ valueOf([`exam_${section}`]) || 'طبيعي/غير موثق' }}</Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">{{ valueOf([`exam_notes_${section}`]) || 'لا توجد ملاحظات منفصلة.' }}</p>
                        </div>
                    </div>
                    <textarea
                        v-model="clinicalForm.examination"
                        rows="8"
                        :readonly="!canEditClinical"
                        class="mt-4 min-h-44 w-full resize-y rounded-lg border border-border bg-background px-3 py-2 text-sm leading-7 outline-none focus:ring-2 focus:ring-ring"
                        placeholder="ملخص الفحص السريري العام والملاحظات الإضافية..."
                    />
                </section>

                <section v-show="activeTab === 'diagnoses'" class="space-y-4">
                    <div class="rounded-lg border border-border bg-card p-4">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-border pb-3">
                            <h2 class="text-sm font-semibold">Assessment / Diagnosis</h2>
                            <div class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-xs text-muted-foreground">
                                <Search class="size-3.5" />
                                ICD-10 Search Ready
                            </div>
                        </div>
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div>
                                <Label>التشخيص الرئيسي</Label>
                                <textarea v-model="clinicalForm.primary_diagnosis" :readonly="!canEditClinical" rows="5" class="mt-1 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm leading-7" />
                            </div>
                            <div>
                                <Label>التشخيصات الثانوية</Label>
                                <textarea v-model="clinicalForm.secondary_diagnosis" :readonly="!canEditClinical" rows="5" class="mt-1 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm leading-7" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <Label>ملاحظات سريرية</Label>
                            <textarea v-model="clinicalForm.clinical_notes" :readonly="!canEditClinical" rows="5" class="mt-1 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm leading-7" />
                        </div>
                    </div>
                </section>

                <section v-show="activeTab === 'treatment'" class="space-y-4">
                    <div class="rounded-lg border border-border bg-card p-4">
                        <div class="mb-3 flex items-center justify-between border-b border-border pb-3">
                            <h2 class="text-sm font-semibold">خطط العلاج</h2>
                            <Button v-if="can('medical_record.update')" size="sm" variant="outline" @click="showTreatmentPlanDialog = true">
                                <Plus class="me-1 size-3.5" />
                                إضافة
                            </Button>
                        </div>
                        <div class="space-y-3">
                            <div v-for="plan in record.treatment_plans" :key="plan.id" class="rounded-lg border border-border p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium">{{ plan.title }}</p>
                                        <p class="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">{{ plan.description ?? 'بدون وصف' }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Badge :class="statusClass(plan.status)" class="border">{{ statusLabel(plan.status) }}</Badge>
                                        <button v-if="can('medical_record.delete')" type="button" class="text-rose-600" @click="deleteTreatmentPlan(plan.id)">
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-4 text-xs text-muted-foreground">
                                    <span>الطبيب: {{ plan.doctor?.name ?? '—' }}</span>
                                    <span>من: {{ formatDate(plan.start_date) }}</span>
                                    <span>إلى: {{ formatDate(plan.end_date) }}</span>
                                </div>
                                <div v-if="plan.status !== 'completed' && plan.status !== 'cancelled'" class="mt-3 flex gap-2">
                                    <button v-if="can('medical_record.update')" type="button" class="text-xs text-cyan-700 hover:underline" @click="updateTreatmentPlanStatus(plan.id, plan.status === 'new' ? 'in_progress' : 'completed')">
                                        {{ plan.status === 'new' ? 'بدء التنفيذ' : 'إكمال' }}
                                    </button>
                                </div>
                            </div>
                            <p v-if="record.treatment_plans.length === 0" class="py-8 text-center text-sm text-muted-foreground">لا توجد خطط علاج بعد.</p>
                        </div>
                    </div>
                    <div class="rounded-lg border border-border bg-card p-4">
                        <h2 class="mb-4 text-sm font-semibold">Timeline العلاج والمتابعة</h2>
                        <div class="space-y-3">
                            <div v-for="item in timelineItems" :key="item.id" class="flex gap-3">
                                <div class="mt-1 size-3 rounded-full bg-cyan-500" />
                                <div class="flex-1 rounded-lg border border-border p-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-sm font-medium">{{ item.title }}</p>
                                        <Badge :class="statusClass(item.status)" class="border">{{ statusLabel(item.status) }}</Badge>
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground">{{ item.type }} · {{ formatDate(item.date) }}</p>
                                    <p v-if="item.description" class="mt-2 whitespace-pre-wrap text-sm text-muted-foreground">{{ item.description }}</p>
                                </div>
                            </div>
                            <p v-if="timelineItems.length === 0" class="text-sm text-muted-foreground">لا يوجد timeline بعد.</p>
                        </div>
                    </div>
                </section>

                <section v-show="activeTab === 'prescriptions'" class="rounded-lg border border-border bg-card p-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-border pb-3">
                        <h2 class="text-sm font-semibold">الوصفات الطبية</h2>
                        <div class="flex gap-2">
                            <Button size="sm" variant="outline"><Printer class="me-1 size-3.5" />PDF</Button>
                            <Button size="sm" variant="outline"><Send class="me-1 size-3.5" />إرسال</Button>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div v-for="prescription in record.prescriptions" :key="prescription.id" class="rounded-lg border border-border p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-medium">{{ prescription.prescription_number }}</p>
                                <Badge :class="statusClass(prescription.status)" class="border">{{ statusLabel(prescription.status) }}</Badge>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">الطبيب: {{ prescription.prescriber?.name ?? '—' }} · {{ formatDateTime(prescription.issued_at) }}</p>
                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="text-xs text-muted-foreground">
                                        <tr class="border-b border-border">
                                            <th class="py-2 text-right">الدواء</th>
                                            <th class="py-2 text-right">الجرعة</th>
                                            <th class="py-2 text-right">التكرار</th>
                                            <th class="py-2 text-right">المدة</th>
                                            <th class="py-2 text-right">التعليمات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="item in prescription.items" :key="item.id" class="border-b border-border/50">
                                            <td class="py-2">{{ item.medication_name }}</td>
                                            <td class="py-2">{{ item.dosage }}</td>
                                            <td class="py-2">{{ item.frequency }}</td>
                                            <td class="py-2">{{ item.duration ?? '—' }}</td>
                                            <td class="py-2">{{ item.instructions ?? '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <p v-if="record.prescriptions.length === 0" class="py-8 text-center text-sm text-muted-foreground">لا توجد وصفات مرتبطة بهذا السجل.</p>
                    </div>
                </section>

                <section v-show="activeTab === 'labs'" class="rounded-lg border border-border bg-card p-4">
                    <h2 class="mb-3 border-b border-border pb-3 text-sm font-semibold">Laboratory Orders</h2>
                    <div class="grid gap-3 xl:grid-cols-2">
                        <div v-for="order in record.patient.lab_orders" :key="order.id" class="rounded-lg border border-border p-4">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-medium">{{ order.test_name }}</p>
                                <Badge :class="statusClass(order.status)" class="border">{{ statusLabel(order.status) }}</Badge>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">{{ order.test_code ?? 'Custom' }} · {{ formatDateTime(order.ordered_at) }}</p>
                            <div v-if="order.results.length > 0" class="mt-3 space-y-2">
                                <div v-for="result in order.results" :key="result.id" class="rounded-lg bg-muted/40 p-3 text-sm">
                                    <p>{{ result.result_value ?? 'نتيجة مرفقة' }} <span class="text-xs text-muted-foreground">{{ result.unit }}</span></p>
                                    <p class="text-xs text-muted-foreground">Reference: {{ result.reference_range ?? '—' }}</p>
                                </div>
                            </div>
                        </div>
                        <p v-if="record.patient.lab_orders.length === 0" class="py-8 text-center text-sm text-muted-foreground xl:col-span-2">لا توجد طلبات مخبرية.</p>
                    </div>
                </section>

                <section v-show="activeTab === 'imaging'" class="rounded-lg border border-border bg-card p-4">
                    <h2 class="mb-3 border-b border-border pb-3 text-sm font-semibold">Imaging</h2>
                    <div class="grid gap-3 xl:grid-cols-2">
                        <div v-for="order in record.patient.radiology_orders" :key="order.id" class="rounded-lg border border-border p-4">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-medium">{{ order.study_name }}</p>
                                <Badge :class="statusClass(order.status)" class="border">{{ statusLabel(order.status) }}</Badge>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">{{ order.modality ?? order.study_code ?? 'Imaging' }} · {{ formatDateTime(order.ordered_at) }}</p>
                            <div class="mt-3 rounded-lg border border-dashed border-border bg-muted/30 p-6 text-center text-sm text-muted-foreground">
                                معاينة DICOM / PDF داخل الصفحة عند توفر الملفات
                            </div>
                            <p v-for="report in order.reports" :key="report.id" class="mt-3 whitespace-pre-wrap rounded-lg bg-muted/40 p-3 text-sm">{{ report.report_text }}</p>
                        </div>
                        <p v-if="record.patient.radiology_orders.length === 0" class="py-8 text-center text-sm text-muted-foreground xl:col-span-2">لا توجد طلبات صور شعاعية.</p>
                    </div>
                </section>

                <section v-show="activeTab === 'followups'" class="rounded-lg border border-border bg-card p-4">
                    <div class="mb-3 flex items-center justify-between border-b border-border pb-3">
                        <h2 class="text-sm font-semibold">المتابعات</h2>
                        <Button v-if="can('medical_record.update')" size="sm" variant="outline" @click="showFollowUpDialog = true">
                            <Plus class="me-1 size-3.5" />
                            إضافة
                        </Button>
                    </div>
                    <div class="space-y-3">
                        <div v-for="followUp in record.follow_ups" :key="followUp.id" class="rounded-lg border border-border p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium">{{ formatDate(followUp.follow_up_date) }}</p>
                                    <p class="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">{{ followUp.notes ?? 'بدون ملاحظات' }}</p>
                                    <p v-if="followUp.recommended_action" class="mt-1 text-sm text-cyan-700 dark:text-cyan-300">الإجراء: {{ followUp.recommended_action }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge :class="statusClass(followUp.status)" class="border">{{ statusLabel(followUp.status) }}</Badge>
                                    <button v-if="can('medical_record.delete')" type="button" class="text-rose-600" @click="deleteFollowUp(followUp.id)">
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </div>
                            <div v-if="followUp.status === 'scheduled'" class="mt-3 flex gap-2">
                                <button v-if="can('medical_record.update')" type="button" class="text-xs text-emerald-700 hover:underline" @click="updateFollowUpStatus(followUp.id, 'completed')">تمّت</button>
                                <button v-if="can('medical_record.update')" type="button" class="text-xs text-rose-700 hover:underline" @click="updateFollowUpStatus(followUp.id, 'missed')">فائتة</button>
                            </div>
                        </div>
                        <p v-if="record.follow_ups.length === 0" class="py-8 text-center text-sm text-muted-foreground">لا توجد متابعات.</p>
                    </div>
                </section>

                <section v-show="activeTab === 'attachments'" class="rounded-lg border border-border bg-card p-4">
                    <h2 class="mb-3 border-b border-border pb-3 text-sm font-semibold">الملفات والمرفقات</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-muted-foreground">
                                <tr class="border-b border-border">
                                    <th class="py-2 text-right">الاسم</th>
                                    <th class="py-2 text-right">النوع</th>
                                    <th class="py-2 text-right">الحجم</th>
                                    <th class="py-2 text-right">تاريخ الرفع</th>
                                    <th class="py-2 text-right">الطبيب/المستخدم</th>
                                    <th class="py-2 text-right">إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="attachment in record.patient.attachments" :key="attachment.id" class="border-b border-border/50">
                                    <td class="py-3">{{ attachment.original_name }}</td>
                                    <td class="py-3">{{ attachment.extension ?? attachment.mime_type ?? 'ملف' }}</td>
                                    <td class="py-3">{{ fileSize(attachment.size_bytes) }}</td>
                                    <td class="py-3">{{ formatDateTime(attachment.uploaded_at) }}</td>
                                    <td class="py-3">{{ attachment.uploaded_by?.name ?? '—' }}</td>
                                    <td class="py-3">
                                        <a :href="attachment.download_url" class="text-cyan-700 hover:underline">تحميل</a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="record.patient.attachments.length === 0" class="py-8 text-center text-sm text-muted-foreground">لا توجد مرفقات.</p>
                    </div>
                </section>

                <section v-show="activeTab === 'audit'" class="rounded-lg border border-border bg-card p-4">
                    <div class="mb-3 flex items-center gap-2 border-b border-border pb-3">
                        <LockKeyhole class="size-4 text-emerald-600" />
                        <h2 class="text-sm font-semibold">Audit Log</h2>
                        <Badge variant="outline">غير قابل للحذف</Badge>
                    </div>
                    <div class="space-y-3">
                        <div v-for="log in record.audit_logs" :key="log.id" class="rounded-lg border border-border p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-medium">{{ log.action }}</p>
                                <p class="text-xs text-muted-foreground">{{ formatDateTime(log.occurred_at) }}</p>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">المستخدم: {{ log.user?.name ?? 'النظام' }}</p>
                            <div class="mt-3 grid gap-3 lg:grid-cols-2">
                                <pre class="max-h-40 overflow-auto rounded-lg bg-muted/40 p-3 text-xs">{{ log.old_values ?? {} }}</pre>
                                <pre class="max-h-40 overflow-auto rounded-lg bg-muted/40 p-3 text-xs">{{ log.new_values ?? {} }}</pre>
                            </div>
                        </div>
                        <p v-if="record.audit_logs.length === 0" class="py-8 text-center text-sm text-muted-foreground">لا توجد عمليات تدقيق مرتبطة بهذا السجل بعد.</p>
                    </div>
                </section>

                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="grid gap-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p class="text-muted-foreground">أنشئ بواسطة</p>
                            <p class="font-medium">{{ record.creator?.name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">تاريخ الإنشاء</p>
                            <p class="font-medium">{{ formatDateTime(record.created_at) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">آخر تحديث</p>
                            <p class="font-medium">{{ formatDateTime(record.updated_at) }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <ShieldCheck class="size-4 text-emerald-600" />
                            <span>الصلاحيات مطبقة عبر RBAC</span>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <Dialog v-model:open="showTreatmentPlanDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>إضافة خطة علاج</DialogTitle>
                <DialogDescription>أضف خطة علاج جديدة لهذا السجل الطبي</DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitTreatmentPlan">
                <div class="flex flex-col gap-1.5">
                    <Label>العنوان *</Label>
                    <Input v-model="treatmentPlanForm.title" placeholder="عنوان خطة العلاج" />
                    <InputError :message="treatmentPlanForm.errors.title" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>الوصف</Label>
                    <textarea v-model="treatmentPlanForm.description" rows="3" class="flex w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <Label>تاريخ البداية</Label>
                        <Input v-model="treatmentPlanForm.start_date" type="date" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>تاريخ النهاية</Label>
                        <Input v-model="treatmentPlanForm.end_date" type="date" />
                    </div>
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>الحالة</Label>
                    <select v-model="treatmentPlanForm.status" class="flex h-10 w-full rounded-lg border border-border bg-background px-3 text-sm">
                        <option value="new">مخطط</option>
                        <option value="in_progress">جارٍ التنفيذ</option>
                        <option value="completed">مكتمل</option>
                    </select>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showTreatmentPlanDialog = false">إلغاء</Button>
                    <Button type="submit" :disabled="treatmentPlanForm.processing">
                        {{ treatmentPlanForm.processing ? 'جارٍ الحفظ...' : 'حفظ' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="showFollowUpDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>إضافة متابعة</DialogTitle>
                <DialogDescription>أضف متابعة جديدة لهذا السجل الطبي</DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitFollowUp">
                <div class="flex flex-col gap-1.5">
                    <Label>تاريخ المتابعة *</Label>
                    <Input v-model="followUpForm.follow_up_date" type="date" />
                    <InputError :message="followUpForm.errors.follow_up_date" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>سبب المتابعة / الملاحظات</Label>
                    <textarea v-model="followUpForm.notes" rows="3" class="flex w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>التوصيات</Label>
                    <Input v-model="followUpForm.recommended_action" placeholder="الإجراء الموصى به..." />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showFollowUpDialog = false">إلغاء</Button>
                    <Button type="submit" :disabled="followUpForm.processing">
                        {{ followUpForm.processing ? 'جارٍ الحفظ...' : 'حفظ' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
