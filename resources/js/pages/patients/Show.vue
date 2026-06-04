<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Calendar,
    CreditCard,
    FileText,
    Heart,
    Paperclip,
    Pill,
    Stethoscope,
    TestTube,
    User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';

type VisitHistoryItem = {
    id: number;
    visit_number: string;
    status: string;
    doctor: { id: number; name: string } | null;
    started_at: string | null;
    in_progress_at: string | null;
    completed_at: string | null;
};

type Attachment = {
    id: number;
    patient_id: number;
    original_name: string;
    mime_type: string | null;
    extension: string | null;
    size_bytes: number;
    uploaded_at: string | null;
    uploaded_by?: { id: number; name: string; email: string } | null;
    download_url: string;
};

type Appointment = {
    id: number;
    status: string;
    doctor: { id: number; name: string } | null;
    scheduled_for: string | null;
    duration_minutes: string | null;
    notes: string | null;
    created_at: string | null;
};

type Invoice = {
    id: number;
    status: string;
    subtotal_amount: string;
    discount_amount: string;
    tax_amount: string;
    total_amount: string;
    paid_amount: string;
    balance_amount: string;
    issued_at: string | null;
    created_at: string | null;
};

type Prescription = {
    id: number;
    status: string;
    prescriber: { id: number; name: string } | null;
    notes: string | null;
    issued_at: string | null;
    dispensed_at: string | null;
    created_at: string | null;
};

type LabOrder = {
    id: number;
    status: string;
    priority: string | null;
    orderer: { id: number; name: string } | null;
    notes: string | null;
    ordered_at: string | null;
    completed_at: string | null;
    created_at: string | null;
};

type RadiologyOrder = {
    id: number;
    status: string;
    priority: string | null;
    orderer: { id: number; name: string } | null;
    notes: string | null;
    ordered_at: string | null;
    completed_at: string | null;
    created_at: string | null;
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
    visit_history: VisitHistoryItem[];
    attachments: Attachment[];
    appointments: Appointment[];
    invoices: Invoice[];
    prescriptions: Prescription[];
    lab_orders: LabOrder[];
    radiology_orders: RadiologyOrder[];
    created_at: string | null;
    updated_at: string | null;
};

const { patient } = defineProps<{
    patient: Patient;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'المرضى',
                href: PatientController.index(),
            },
            {
                title: patient.full_name,
                href: PatientController.show.url(patient.id),
            },
        ],
    },
});

const { can } = usePermissions();

const activeTab = ref('info');

const tabs = [
    { id: 'info', label: 'المعلومات الأساسية', icon: User },
    { id: 'medical', label: 'السجل الطبي', icon: Heart },
    { id: 'visits', label: 'الزيارات', icon: Stethoscope },
    { id: 'appointments', label: 'المواعيد', icon: Calendar },
    { id: 'invoices', label: 'الفواتير', icon: CreditCard },
    { id: 'prescriptions', label: 'الوصفات', icon: Pill },
    { id: 'lab', label: 'الفحوصات', icon: TestTube },
    { id: 'attachments', label: 'المرفقات', icon: Paperclip },
];

const genderLabel = computed(() => {
    const labels: Record<string, string> = { male: 'ذكر', female: 'أنثى', other: 'آخر' };

    return patient.gender ? labels[patient.gender] ?? 'غير محدد' : 'غير محدد';
});

const genderClass = computed(() => {
    if (patient.gender === 'male') {
return 'bg-blue-500/10 text-blue-600 border-blue-500/20';
}

    if (patient.gender === 'female') {
return 'bg-pink-500/10 text-pink-600 border-pink-500/20';
}

    return 'bg-muted/50 text-muted-foreground border-border/40';
});

const appointmentStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
        scheduled: 'مجدول',
        confirmed: 'مؤكد',
        arrived: 'وصل',
        completed: 'مكتمل',
        canceled: 'ملغي',
        no_show: 'لم يحضر',
    };

    return labels[status] ?? status;
};

const appointmentStatusClass = (status: string) => {
    const classes: Record<string, string> = {
        scheduled: 'bg-blue-500/10 text-blue-600 border-blue-500/20',
        confirmed: 'bg-green-500/10 text-green-600 border-green-500/20',
        arrived: 'bg-yellow-500/10 text-yellow-600 border-yellow-500/20',
        completed: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
        canceled: 'bg-red-500/10 text-red-600 border-red-500/20',
        no_show: 'bg-gray-500/10 text-gray-600 border-gray-500/20',
    };

    return classes[status] ?? 'bg-muted/50 text-muted-foreground border-border/40';
};

const visitStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
        started: 'بدأت',
        in_progress: 'قيد التنفيذ',
        completed: 'مكتملة',
    };

    return labels[status] ?? status;
};

const visitStatusClass = (status: string) => {
    const classes: Record<string, string> = {
        started: 'bg-blue-500/10 text-blue-600 border-blue-500/20',
        in_progress: 'bg-yellow-500/10 text-yellow-600 border-yellow-500/20',
        completed: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
    };

    return classes[status] ?? 'bg-muted/50 text-muted-foreground border-border/40';
};

const invoiceStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
        draft: 'مسودة',
        issued: 'صادرة',
        partially_paid: 'مدفوعة جزئياً',
        paid: 'مدفوعة',
        void: 'ملغاة',
    };

    return labels[status] ?? status;
};

const invoiceStatusClass = (status: string) => {
    const classes: Record<string, string> = {
        draft: 'bg-gray-500/10 text-gray-600 border-gray-500/20',
        issued: 'bg-blue-500/10 text-blue-600 border-blue-500/20',
        partially_paid: 'bg-yellow-500/10 text-yellow-600 border-yellow-500/20',
        paid: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
        void: 'bg-red-500/10 text-red-600 border-red-500/20',
    };

    return classes[status] ?? 'bg-muted/50 text-muted-foreground border-border/40';
};

const prescriptionStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
        draft: 'مسودة',
        issued: 'صادرة',
        dispensed: 'مصروفة',
        canceled: 'ملغاة',
    };

    return labels[status] ?? status;
};

const prescriptionStatusClass = (status: string) => {
    const classes: Record<string, string> = {
        draft: 'bg-gray-500/10 text-gray-600 border-gray-500/20',
        issued: 'bg-blue-500/10 text-blue-600 border-blue-500/20',
        dispensed: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
        canceled: 'bg-red-500/10 text-red-600 border-red-500/20',
    };

    return classes[status] ?? 'bg-muted/50 text-muted-foreground border-border/40';
};

const labOrderStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
        pending: 'قيد الانتظار',
        in_progress: 'قيد التنفيذ',
        completed: 'مكتمل',
        canceled: 'ملغي',
    };

    return labels[status] ?? status;
};

const labOrderStatusClass = (status: string) => {
    const classes: Record<string, string> = {
        pending: 'bg-yellow-500/10 text-yellow-600 border-yellow-500/20',
        in_progress: 'bg-blue-500/10 text-blue-600 border-blue-500/20',
        completed: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
        canceled: 'bg-red-500/10 text-red-600 border-red-500/20',
    };

    return classes[status] ?? 'bg-muted/50 text-muted-foreground border-border/40';
};

const formatFileSize = (bytes: number) => {
    if (bytes < 1024) {
return bytes + ' B';
}

    if (bytes < 1024 * 1024) {
return (bytes / 1024).toFixed(1) + ' KB';
}

    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const formatDate = (date: string | null) => {
    if (!date) {
return 'غير محدد';
}

    return new Date(date).toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatShortDate = (date: string | null) => {
    if (!date) {
return 'غير محدد';
}

    return new Date(date).toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head :title="patient.full_name" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Link :href="PatientController.index()" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" />
                    العودة للقائمة
                </Link>
                <div>
                    <h1 class="page-title">{{ patient.full_name }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        رقم الملف: <span class="font-mono text-foreground">{{ patient.file_number }}</span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Link
                    v-if="can('patient.update')"
                    :href="PatientController.index()"
                    class="inline-flex items-center gap-2 rounded-xl border border-border/60 bg-background/40 px-4 py-2 text-sm font-medium transition-colors hover:bg-background/60 min-h-[44px]"
                >
                    تعديل
                </Link>
            </div>
        </div>

        <div class="rounded-xl border border-border/70 bg-card px-4 py-3">
            <div class="flex flex-wrap items-center gap-4 md:gap-6">
                <div class="flex items-center gap-2">
                    <User class="size-4 text-muted-foreground" />
                    <span class="text-sm text-muted-foreground">الجنس</span>
                    <Badge :class="genderClass">{{ genderLabel }}</Badge>
                </div>
                <div class="flex items-center gap-2">
                    <Calendar class="size-4 text-muted-foreground" />
                    <span class="text-sm text-muted-foreground">العمر</span>
                    <span class="text-sm font-semibold">{{ patient.age ? patient.age + ' سنة' : 'غير محدد' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <Stethoscope class="size-4 text-muted-foreground" />
                    <span class="text-sm text-muted-foreground">الزيارات</span>
                    <span class="text-sm font-semibold">{{ patient.visit_history.length }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <CreditCard class="size-4 text-muted-foreground" />
                    <span class="text-sm text-muted-foreground">الفواتير</span>
                    <span class="text-sm font-semibold">{{ patient.invoices.length }}</span>
                </div>
            </div>
        </div>

        <div class="glass-panel-soft p-5">
            <div class="mb-4 flex flex-wrap gap-2 border-b pb-3">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                    :class="activeTab === tab.id ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'"
                    @click="activeTab = tab.id"
                >
                    <component :is="tab.icon" class="size-4" />
                    {{ tab.label }}
                </button>
            </div>

            <div v-if="activeTab === 'info'" class="space-y-4">
                <div class="rounded-xl border border-border/70 bg-background/55 p-4">
                    <h3 class="mb-3 text-sm font-semibold">البيانات الشخصية</h3>
                    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">رقم الملف</dt>
                            <dd class="font-mono text-sm">{{ patient.file_number }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الاسم الكامل</dt>
                            <dd class="text-sm">{{ patient.full_name }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الجنس</dt>
                            <dd class="text-sm capitalize">{{ genderLabel }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">تاريخ الميلاد</dt>
                            <dd class="text-sm">{{ formatShortDate(patient.date_of_birth) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">العمر</dt>
                            <dd class="text-sm">{{ patient.age ? patient.age + ' سنة' : 'غير محدد' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">رقم الهوية</dt>
                            <dd class="text-sm">{{ patient.national_id ?? 'غير محدد' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-border/70 bg-background/55 p-4">
                    <h3 class="mb-3 text-sm font-semibold">بيانات الاتصال</h3>
                    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الهاتف</dt>
                            <dd class="text-sm" :class="patient.phone ? '' : 'text-muted-foreground'">{{ patient.phone ?? 'غير محدد' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">البريد الإلكتروني</dt>
                            <dd class="text-sm" :class="patient.email ? '' : 'text-muted-foreground'">{{ patient.email ?? 'غير محدد' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-border/70 bg-background/55 p-4">
                    <h3 class="mb-3 text-sm font-semibold">جهة اتصال للطوارئ</h3>
                    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الاسم</dt>
                            <dd class="text-sm" :class="patient.emergency_contact_name ? '' : 'text-muted-foreground'">{{ patient.emergency_contact_name ?? 'غير محدد' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الهاتف</dt>
                            <dd class="text-sm" :class="patient.emergency_contact_phone ? '' : 'text-muted-foreground'">{{ patient.emergency_contact_phone ?? 'غير محدد' }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-if="patient.notes" class="rounded-xl border border-border/70 bg-background/55 p-4">
                    <h3 class="mb-3 text-sm font-semibold">ملاحظات</h3>
                    <p class="text-sm whitespace-pre-wrap">{{ patient.notes }}</p>
                </div>
            </div>

            <div v-if="activeTab === 'medical'" class="space-y-4">
                <div class="rounded-xl border border-border/70 bg-background/55 p-4">
                    <h3 class="mb-3 text-sm font-semibold">أمراض مزمنة</h3>
                    <div v-if="patient.chronic_conditions.length > 0" class="flex flex-wrap gap-2">
                        <Badge v-for="(condition, index) in patient.chronic_conditions" :key="index" variant="outline" class="border-red-500/30 text-red-600 bg-red-500/5">
                            {{ condition }}
                        </Badge>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">لا توجد أمراض مزمنة مسجلة.</p>
                </div>

                <div class="rounded-xl border border-border/70 bg-background/55 p-4">
                    <h3 class="mb-3 text-sm font-semibold">حساسية</h3>
                    <div v-if="patient.allergies.length > 0" class="flex flex-wrap gap-2">
                        <Badge v-for="(allergy, index) in patient.allergies" :key="index" variant="outline" class="border-amber-500/30 text-amber-600 bg-amber-500/5">
                            {{ allergy }}
                        </Badge>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">لا توجد حساسية مسجلة.</p>
                </div>

                <div class="rounded-xl border border-border/70 bg-background/55 p-4">
                    <h3 class="mb-3 text-sm font-semibold">أدوية حالية</h3>
                    <div v-if="patient.current_medications.length > 0" class="flex flex-wrap gap-2">
                        <Badge v-for="(medication, index) in patient.current_medications" :key="index" variant="outline" class="border-blue-500/30 text-blue-600 bg-blue-500/5">
                            {{ medication }}
                        </Badge>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">لا توجد أدوية حالية مسجلة.</p>
                </div>
            </div>

            <div v-if="activeTab === 'visits'" class="space-y-4">
                <div v-if="patient.visit_history.length > 0" class="space-y-3">
                    <div v-for="visit in patient.visit_history" :key="visit.id" class="rounded-xl border border-border/70 bg-background/55 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-sm">{{ visit.visit_number }}</span>
                                <Badge :class="visitStatusClass(visit.status)">{{ visitStatusLabel(visit.status) }}</Badge>
                            </div>
                            <span class="text-xs text-muted-foreground">{{ formatShortDate(visit.started_at) }}</span>
                        </div>
                        <div v-if="visit.doctor" class="mt-2 text-sm text-muted-foreground">
                            الطبيب: <span class="text-foreground">{{ visit.doctor.name }}</span>
                        </div>
                    </div>
                </div>
                <p v-else class="rounded-xl border border-border/70 bg-background/55 p-8 text-center text-sm text-muted-foreground">لا توجد زيارات مسجلة.</p>
            </div>

            <div v-if="activeTab === 'appointments'" class="space-y-4">
                <div v-if="patient.appointments.length > 0" class="space-y-3">
                    <div v-for="appointment in patient.appointments" :key="appointment.id" class="rounded-xl border border-border/70 bg-background/55 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <Badge :class="appointmentStatusClass(appointment.status)">{{ appointmentStatusLabel(appointment.status) }}</Badge>
                            </div>
                            <span class="text-xs text-muted-foreground">{{ formatDate(appointment.scheduled_for) }}</span>
                        </div>
                        <div v-if="appointment.doctor" class="mt-2 text-sm text-muted-foreground">
                            الطبيب: <span class="text-foreground">{{ appointment.doctor.name }}</span>
                        </div>
                        <div v-if="appointment.duration_minutes" class="mt-1 text-xs text-muted-foreground">
                            المدة: {{ appointment.duration_minutes }} دقيقة
                        </div>
                        <p v-if="appointment.notes" class="mt-2 text-sm text-muted-foreground whitespace-pre-wrap">{{ appointment.notes }}</p>
                    </div>
                </div>
                <p v-else class="rounded-xl border border-border/70 bg-background/55 p-8 text-center text-sm text-muted-foreground">لا توجد مواعيد مسجلة.</p>
            </div>

            <div v-if="activeTab === 'invoices'" class="space-y-4">
                <div v-if="patient.invoices.length > 0" class="space-y-3">
                    <div v-for="invoice in patient.invoices" :key="invoice.id" class="rounded-xl border border-border/70 bg-background/55 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <Badge :class="invoiceStatusClass(invoice.status)">{{ invoiceStatusLabel(invoice.status) }}</Badge>
                            </div>
                            <span class="text-xs text-muted-foreground">{{ formatShortDate(invoice.created_at) }}</span>
                        </div>
                        <dl class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="space-y-1">
                                <dt class="text-[0.6rem] font-semibold tracking-normal text-muted-foreground uppercase">المبلغ</dt>
                                <dd class="text-sm font-semibold">{{ invoice.total_amount }} ر.س</dd>
                            </div>
                            <div class="space-y-1">
                                <dt class="text-[0.6rem] font-semibold tracking-normal text-muted-foreground uppercase">المدفوع</dt>
                                <dd class="text-sm text-emerald-600">{{ invoice.paid_amount }} ر.س</dd>
                            </div>
                            <div class="space-y-1">
                                <dt class="text-[0.6rem] font-semibold tracking-normal text-muted-foreground uppercase">المتبقي</dt>
                                <dd class="text-sm" :class="parseFloat(invoice.balance_amount) > 0 ? 'text-red-600' : 'text-muted-foreground'">{{ invoice.balance_amount }} ر.س</dd>
                            </div>
                            <div class="space-y-1">
                                <dt class="text-[0.6rem] font-semibold tracking-normal text-muted-foreground uppercase">الخصم</dt>
                                <dd class="text-sm text-muted-foreground">{{ invoice.discount_amount }} ر.س</dd>
                            </div>
                        </dl>
                    </div>
                </div>
                <p v-else class="rounded-xl border border-border/70 bg-background/55 p-8 text-center text-sm text-muted-foreground">لا توجد فواتير مسجلة.</p>
            </div>

            <div v-if="activeTab === 'prescriptions'" class="space-y-4">
                <div v-if="patient.prescriptions.length > 0" class="space-y-3">
                    <div v-for="prescription in patient.prescriptions" :key="prescription.id" class="rounded-xl border border-border/70 bg-background/55 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <Badge :class="prescriptionStatusClass(prescription.status)">{{ prescriptionStatusLabel(prescription.status) }}</Badge>
                            </div>
                            <span class="text-xs text-muted-foreground">{{ formatShortDate(prescription.created_at) }}</span>
                        </div>
                        <div v-if="prescription.prescriber" class="mt-2 text-sm text-muted-foreground">
                            الطبيب: <span class="text-foreground">{{ prescription.prescriber.name }}</span>
                        </div>
                        <p v-if="prescription.notes" class="mt-2 text-sm text-muted-foreground whitespace-pre-wrap">{{ prescription.notes }}</p>
                        <div v-if="prescription.issued_at" class="mt-2 text-xs text-muted-foreground">
                            تاريخ الإصدار: {{ formatDate(prescription.issued_at) }}
                        </div>
                        <div v-if="prescription.dispensed_at" class="mt-1 text-xs text-muted-foreground">
                            تاريخ الصرف: {{ formatDate(prescription.dispensed_at) }}
                        </div>
                    </div>
                </div>
                <p v-else class="rounded-xl border border-border/70 bg-background/55 p-8 text-center text-sm text-muted-foreground">لا توجد وصفات مسجلة.</p>
            </div>

            <div v-if="activeTab === 'lab'" class="space-y-4">
                <div class="space-y-4">
                    <div>
                        <h3 class="mb-3 text-sm font-semibold">فحوصات المختبر</h3>
                        <div v-if="patient.lab_orders.length > 0" class="space-y-3">
                            <div v-for="lab in patient.lab_orders" :key="lab.id" class="rounded-xl border border-border/70 bg-background/55 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-3">
                                        <Badge :class="labOrderStatusClass(lab.status)">{{ labOrderStatusLabel(lab.status) }}</Badge>
                                        <Badge v-if="lab.priority" variant="outline" class="border-purple-500/30 text-purple-600 bg-purple-500/5">
                                            {{ lab.priority === 'urgent' ? 'عاجل' : lab.priority === 'routine' ? 'عادي' : lab.priority }}
                                        </Badge>
                                    </div>
                                    <span class="text-xs text-muted-foreground">{{ formatShortDate(lab.created_at) }}</span>
                                </div>
                                <div v-if="lab.orderer" class="mt-2 text-sm text-muted-foreground">
                                    الطبيب: <span class="text-foreground">{{ lab.orderer.name }}</span>
                                </div>
                                <p v-if="lab.notes" class="mt-2 text-sm text-muted-foreground whitespace-pre-wrap">{{ lab.notes }}</p>
                            </div>
                        </div>
                        <p v-else class="rounded-xl border border-border/70 bg-background/55 p-8 text-center text-sm text-muted-foreground">لا توجد فحوصات مختبر مسجلة.</p>
                    </div>

                    <div>
                        <h3 class="mb-3 text-sm font-semibold">فحوصات الأشعة</h3>
                        <div v-if="patient.radiology_orders.length > 0" class="space-y-3">
                            <div v-for="radiology in patient.radiology_orders" :key="radiology.id" class="rounded-xl border border-border/70 bg-background/55 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-3">
                                        <Badge :class="labOrderStatusClass(radiology.status)">{{ labOrderStatusLabel(radiology.status) }}</Badge>
                                        <Badge v-if="radiology.priority" variant="outline" class="border-purple-500/30 text-purple-600 bg-purple-500/5">
                                            {{ radiology.priority === 'urgent' ? 'عاجل' : radiology.priority === 'routine' ? 'عادي' : radiology.priority }}
                                        </Badge>
                                    </div>
                                    <span class="text-xs text-muted-foreground">{{ formatShortDate(radiology.created_at) }}</span>
                                </div>
                                <div v-if="radiology.orderer" class="mt-2 text-sm text-muted-foreground">
                                    الطبيب: <span class="text-foreground">{{ radiology.orderer.name }}</span>
                                </div>
                                <p v-if="radiology.notes" class="mt-2 text-sm text-muted-foreground whitespace-pre-wrap">{{ radiology.notes }}</p>
                            </div>
                        </div>
                        <p v-else class="rounded-xl border border-border/70 bg-background/55 p-8 text-center text-sm text-muted-foreground">لا توجد فحوصات أشعة مسجلة.</p>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'attachments'" class="space-y-4">
                <div v-if="patient.attachments.length > 0" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="attachment in patient.attachments" :key="attachment.id" class="rounded-xl border border-border/70 bg-background/55 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ attachment.original_name }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ formatFileSize(attachment.size_bytes) }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ formatShortDate(attachment.uploaded_at) }}</p>
                            </div>
                            <a
                                :href="attachment.download_url"
                                class="inline-flex items-center gap-1 rounded-lg border border-border/60 bg-background/40 px-2 py-1 text-xs font-medium transition-colors hover:bg-background/60"
                                target="_blank"
                            >
                                <FileText class="size-3" />
                                تحميل
                            </a>
                        </div>
                    </div>
                </div>
                <p v-else class="rounded-xl border border-border/70 bg-background/55 p-8 text-center text-sm text-muted-foreground">لا توجد مرفقات.</p>
            </div>
        </div>
    </div>
</template>
