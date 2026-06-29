<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Calendar,
    Clock,
    Download,
    ExternalLink,
    FileText,
    IdCard,
    Pencil,
    Plus,
    Printer,
    Save,
    Stethoscope,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PatientCardController from '@/actions/App/Http/Controllers/Patients/PatientCardController';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import type { Patient } from './components/types';

type Resource<T> = { data: T };

type Visit = {
    id: number;
    patient_id: number;
    appointment_id: number | null;
    doctor_id: number | null;
    doctor?: { id: number; name: string } | null;
    clinic_id: number | null;
    clinic?: { id: number; name: string; clinic_type: string | null } | null;
    visit_date: string | null;
    visit_time: string | null;
    visit_reason: string | null;
    chief_complaint: string | null;
    general_notes: string | null;
    new_symptoms: string | null;
    medical_or_surgical_complaint: string | null;
    diagnosis: string | null;
    prescribed_treatment_or_referral: string | null;
    signature: string | null;
    notes: string | null;
};

type AppointmentData = {
    id: number;
    patient_id: number;
    doctor_id: number | null;
    appointment_number: string;
    scheduled_for: string;
    duration_minutes: number;
    appointment_type: string | null;
    status: string;
    clinic?: {
        id: number;
        name: string;
    } | null;
    patient?: {
        id?: number;
        full_name?: string;
    };
    doctor?: {
        id?: number;
        name?: string;
        specialty?: string | null;
        clinic?: {
            id: number;
            name: string;
        } | null;
    };
};

type Option = {
    id: number;
    name: string;
    clinic_id?: number | null;
    specialty?: string | null;
    clinic_type?: string | null;
};

const props = defineProps<{
    patient: Resource<Patient>;
    visits: Resource<Visit[]>;
    doctors: Option[];
    clinics: Option[];
    card: {
        clinic_name: string | null;
        project_name: string | null;
        page_number: string | null;
        date: string | null;
        doctor: string | null;
        clinic: string | null;
    };
    permissions: {
        can_manage_visits: boolean;
        can_manage_appointments: boolean;
    };
    activeAppointment: AppointmentData | null;
    currentUser: {
        id: number;
        name: string;
        is_doctor: boolean;
        doctor_id: number | null;
        clinic_id: number | null;
    } | null;
}>();

const patient = computed(() => props.patient.data);
const visits = computed(() => props.visits.data ?? []);
const page = usePage();

const dash = '—';
const showForm = ref(false);
const editingVisit = ref<Visit | null>(null);

const isLinkedToAppointment = computed(() => props.activeAppointment !== null);
const isDoctorAutoFilled = computed(() => !isLinkedToAppointment.value && props.currentUser?.is_doctor === true);
const isFieldReadonly = computed(() => isLinkedToAppointment.value || isDoctorAutoFilled.value);

const clinicName = computed(() => display(props.card.clinic_name ?? page.props.clinic_name));
const projectName = computed(() => display(props.card.project_name ?? page.props.name));
const patientName = computed(() => display(patient.value.full_name));
const latestVisit = computed(() => visits.value[0] ?? null);
const displayedDoctorName = computed(() => display(props.card.doctor ?? props.activeAppointment?.doctor?.name));
const displayedClinicName = computed(() => display(props.card.clinic ?? props.activeAppointment?.doctor?.clinic?.name ?? props.activeAppointment?.clinic?.name));

const genderLabel = computed(() => {
    const labels: Record<string, string> = {
        male: 'ذكر',
        female: 'أنثى',
        other: 'آخر',
    };

    return labels[patient.value.gender ?? ''] ?? dash;
});

const appointmentDoctorName = computed(() => props.activeAppointment?.doctor?.name ?? dash);
const appointmentClinicName = computed(() => props.activeAppointment?.doctor?.clinic?.name ?? props.activeAppointment?.clinic?.name ?? dash);
const appointmentDate = computed(() => {
    const iso = props.activeAppointment?.scheduled_for;

    if (!iso) {
        return dash;
    }

    return new Date(iso).toISOString().slice(0, 10);
});
const appointmentTime = computed(() => {
    const iso = props.activeAppointment?.scheduled_for;

    if (!iso) {
        return dash;
    }

    const d = new Date(iso);

    return d.toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit', hour12: true });
});
const appointmentType = computed(() => {
    const t = props.activeAppointment?.appointment_type;

    if (t === 'review') {
        return 'مراجعة';
    }

    if (t === 'first_visit') {
        return 'كشفية أولى';
    }

    return dash;
});
const appointmentStatus = computed(() => {
    const labels: Record<string, string> = {
        arrived: 'وصل',
        canceled: 'ملغى',
        completed: 'مكتمل',
        confirmed: 'مؤكد',
        no_show: 'لم يحضر',
        scheduled: 'محجوز',
    };

    return labels[props.activeAppointment?.status ?? ''] ?? dash;
});
const appointmentSummary = computed(() => {
    if (!props.activeAppointment) {
        return null;
    }

    return [props.activeAppointment.appointment_number, appointmentDate.value, appointmentTime.value]
        .filter((value) => value && value !== dash)
        .join(' - ');
});

const currentUserDoctorName = computed(() => {
    if (!props.currentUser?.is_doctor) {
        return null;
    }

    const doctor = props.doctors.find((d) => d.id === props.currentUser!.doctor_id);

    return doctor?.name ?? props.currentUser?.name ?? null;
});

const currentUserClinicName = computed(() => {
    if (!props.currentUser?.is_doctor || !props.currentUser?.clinic_id) {
        return null;
    }

    const dept = props.clinics.find((d) => d.id === props.currentUser!.clinic_id);

    return dept?.name ?? null;
});

const form = useForm({
    appointment_id: '',
    visit_date: new Date().toISOString().slice(0, 10),
    visit_time: '',
    doctor_id: '',
    clinic_id: '',
    visit_reason: '',
    chief_complaint: '',
    general_notes: '',
    new_symptoms: '',
    medical_or_surgical_complaint: '',
    diagnosis: '',
    prescribed_treatment_or_referral: '',
    signature: '',
    notes: '',
});

function display(value: unknown): string {
    if (value === null || value === undefined) {
        return dash;
    }

    const text = String(value).trim();

    return text === '' ? dash : text;
}

function formatDate(value: string | null): string {
    if (!value) {
        return dash;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return dash;
    }

    return date.toISOString().slice(0, 10);
}

function openCreateForm(): void {
    editingVisit.value = null;
    form.clearErrors();

    if (props.activeAppointment) {
        form.appointment_id = props.activeAppointment.id.toString();
        form.doctor_id = props.activeAppointment.doctor_id?.toString() ?? '';
        form.clinic_id = (props.activeAppointment.doctor?.clinic?.id ?? props.activeAppointment.clinic?.id)?.toString() ?? '';
        form.visit_date = appointmentDate.value;
        const iso = props.activeAppointment.scheduled_for;

        if (iso) {
            const d = new Date(iso);
            form.visit_time = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
        }
    } else {
        form.appointment_id = '';
        form.visit_date = new Date().toISOString().slice(0, 10);
        form.visit_time = '';

        if (props.currentUser?.is_doctor) {
            form.doctor_id = props.currentUser.doctor_id?.toString() ?? '';
            form.clinic_id = props.currentUser.clinic_id?.toString() ?? '';
        } else {
            form.doctor_id = '';
            form.clinic_id = '';
        }
    }

    form.visit_reason = '';
    form.chief_complaint = '';
    form.general_notes = '';
    form.new_symptoms = '';
    form.medical_or_surgical_complaint = '';
    form.diagnosis = '';
    form.prescribed_treatment_or_referral = '';
    form.signature = '';
    form.notes = '';
    showForm.value = true;
}

function openEditForm(visit: Visit): void {
    editingVisit.value = visit;
    form.clearErrors();
    form.appointment_id = visit.appointment_id?.toString() ?? '';
    form.visit_date = visit.visit_date ?? new Date().toISOString().slice(0, 10);
    form.visit_time = visit.visit_time ?? '';
    form.doctor_id = visit.doctor_id?.toString() ?? '';
    form.clinic_id = visit.clinic_id?.toString() ?? '';
    form.visit_reason = visit.visit_reason ?? '';
    form.chief_complaint = visit.chief_complaint ?? '';
    form.general_notes = visit.general_notes ?? '';
    form.new_symptoms = visit.new_symptoms ?? '';
    form.medical_or_surgical_complaint = visit.medical_or_surgical_complaint ?? '';
    form.diagnosis = visit.diagnosis ?? '';
    form.prescribed_treatment_or_referral = visit.prescribed_treatment_or_referral ?? '';
    form.signature = visit.signature ?? '';
    form.notes = visit.notes ?? '';
    showForm.value = true;
}

function closeForm(): void {
    showForm.value = false;
    editingVisit.value = null;
    form.clearErrors();
}

function submitVisit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeForm(),
    };

    if (editingVisit.value) {
        form.patch(
            PatientCardController.update.url({
                patientId: patient.value.id,
                visitId: editingVisit.value.id,
            }),
            options,
        );

        return;
    }

    form.post(PatientCardController.store.url(patient.value.id), options);
}

function deleteVisit(visit: Visit): void {
    if (!window.confirm('هل تريد حذف هذه الزيارة؟')) {
        return;
    }

    router.delete(
        PatientCardController.destroy.url({
            patientId: patient.value.id,
            visitId: visit.id,
        }),
        {
            preserveScroll: true,
        },
    );
}

function printCard(): void {
    window.print();
}

watch(
    () => form.doctor_id,
    (doctorId) => {
        if (isLinkedToAppointment.value) {
            return;
        }

        const selectedDoctor = props.doctors.find((doctor) => doctor.id.toString() === doctorId);

        if (selectedDoctor?.clinic_id) {
            form.clinic_id = selectedDoctor.clinic_id.toString();
        }
    },
);
</script>

<template>
    <Head :title="`بطاقة مريض - ${patientName}`" />

    <div class="container-modern space-y-4 py-5" dir="rtl">
        <nav class="no-print flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
            <Link :href="PatientController.index.url()" class="hover:text-foreground">المرضى</Link>
            <span>/</span>
            <span>بطاقة مريض</span>
            <span>/</span>
            <span class="font-semibold text-foreground">{{ patientName }}</span>
        </nav>

        <div class="no-print flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="page-title">بطاقة مريض</h1>
                <p class="page-subtitle mt-1">عرض زيارات المريض الطبية وترتيبها من الأحدث إلى الأقدم</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Link
                    :href="PatientController.index.url()"
                    class="inline-flex h-10 items-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-semibold text-foreground transition-colors hover:bg-muted"
                >
                    <ArrowRight class="size-4" />
                    الرجوع إلى صفحة المرضى
                </Link>
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-semibold text-foreground transition-colors hover:bg-muted"
                    @click="printCard"
                >
                    <Printer class="size-4" />
                    طباعة البطاقة
                </button>
                <a
                    :href="PatientCardController.exportPdf.url(patient.id)"
                    class="inline-flex h-10 items-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-semibold text-foreground transition-colors hover:bg-muted"
                >
                    <Download class="size-4" />
                    تصدير PDF
                </a>
                <Button
                    v-if="permissions.can_manage_visits"
                    class="h-10 rounded-xl"
                    @click="openCreateForm"
                >
                    <Plus class="size-4" />
                    إضافة زيارة
                </Button>
            </div>
        </div>

        <section
            v-if="showForm && permissions.can_manage_visits"
            class="no-print rounded-2xl border border-border bg-card p-4 shadow-card"
        >
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold">{{ editingVisit ? 'تعديل زيارة' : 'إضافة زيارة جديدة' }}</h2>
                    <p v-if="isLinkedToAppointment" class="text-xs text-muted-foreground">
                        هذه الزيارة مرتبطة بالموعد رقم {{ activeAppointment?.appointment_number }} — جميع البيانات تُسحب تلقائياً من الموعد.
                    </p>
                    <p v-else class="text-xs text-muted-foreground">سيتم ربط الزيارة بهذا المريض فقط.</p>
                </div>
                <button
                    type="button"
                    class="inline-flex size-9 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                    @click="closeForm"
                >
                    <X class="size-4" />
                </button>
            </div>

            <div
                v-if="isLinkedToAppointment"
                class="mb-4 rounded-xl border border-primary/20 bg-primary/5 p-4"
            >
                <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-primary">
                    <Stethoscope class="size-4" />
                    بيانات الموعد المرتبط
                </div>
                <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                    <div class="flex items-center gap-2">
                        <Stethoscope class="size-3.5 text-muted-foreground" />
                        <div>
                            <span class="text-[0.65rem] text-muted-foreground">الطبيب</span>
                            <p class="font-medium">{{ appointmentDoctorName }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <FileText class="size-3.5 text-muted-foreground" />
                        <div>
                            <span class="text-[0.65rem] text-muted-foreground">العيادة</span>
                            <p class="font-medium">{{ appointmentClinicName }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Calendar class="size-3.5 text-muted-foreground" />
                        <div>
                            <span class="text-[0.65rem] text-muted-foreground">التاريخ</span>
                            <p class="font-medium">{{ appointmentDate }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Clock class="size-3.5 text-muted-foreground" />
                        <div>
                            <span class="text-[0.65rem] text-muted-foreground">الوقت</span>
                            <p class="font-medium">{{ appointmentTime }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <form class="grid grid-cols-1 gap-4 lg:grid-cols-4" @submit.prevent="submitVisit">
                <input v-if="form.appointment_id" type="hidden" :value="form.appointment_id" name="appointment_id" />
                <input v-if="isFieldReadonly && form.doctor_id" type="hidden" :value="form.doctor_id" name="doctor_id" />
                <input v-if="isFieldReadonly && form.clinic_id" type="hidden" :value="form.clinic_id" name="clinic_id" />

                <label v-if="!isLinkedToAppointment" class="space-y-1.5">
                    <span class="text-xs font-semibold text-muted-foreground">تاريخ الزيارة</span>
                    <input v-model="form.visit_date" type="date" class="pattern-field-clay" required />
                    <InputError :message="form.errors.visit_date" />
                </label>
                <div v-else class="space-y-1.5">
                    <span class="text-xs font-semibold text-muted-foreground">تاريخ الزيارة</span>
                    <div class="pattern-field-clay flex h-10 items-center bg-muted/50 px-3 text-sm">{{ appointmentDate }}</div>
                </div>

                <label v-if="!isFieldReadonly" class="space-y-1.5">
                    <span class="text-xs font-semibold text-muted-foreground">الطبيب</span>
                    <select v-model="form.doctor_id" class="pattern-field-clay">
                        <option value="">—</option>
                        <option v-for="doctor in doctors" :key="doctor.id" :value="doctor.id.toString()">
                            {{ doctor.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.doctor_id" />
                </label>
                <div v-else class="space-y-1.5">
                    <span class="text-xs font-semibold text-muted-foreground">الطبيب</span>
                    <div class="pattern-field-clay flex h-10 items-center bg-muted/50 px-3 text-sm">
                        {{ isLinkedToAppointment ? appointmentDoctorName : (currentUserDoctorName ?? dash) }}
                    </div>
                </div>

                <label v-if="!isFieldReadonly" class="space-y-1.5">
                    <span class="text-xs font-semibold text-muted-foreground">العيادة</span>
                    <select v-model="form.clinic_id" class="pattern-field-clay">
                        <option value="">—</option>
                        <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id.toString()">
                            {{ clinic.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.clinic_id" />
                </label>
                <div v-else class="space-y-1.5">
                    <span class="text-xs font-semibold text-muted-foreground">العيادة</span>
                    <div class="pattern-field-clay flex h-10 items-center bg-muted/50 px-3 text-sm">
                        {{ isLinkedToAppointment ? appointmentClinicName : (currentUserClinicName ?? dash) }}
                    </div>
                </div>

                <label class="space-y-1.5">
                    <span class="text-xs font-semibold text-muted-foreground">التوقيع</span>
                    <input v-model="form.signature" type="text" class="pattern-field-clay" />
                    <InputError :message="form.errors.signature" />
                </label>

                <label class="space-y-1.5 lg:col-span-2">
                    <span class="text-xs font-semibold text-muted-foreground">السبب للزيارة</span>
                    <textarea v-model="form.visit_reason" class="pattern-field-clay" />
                    <InputError :message="form.errors.visit_reason" />
                </label>

                <label class="space-y-1.5 lg:col-span-2">
                    <span class="text-xs font-semibold text-muted-foreground">الشكوى الرئيسية</span>
                    <textarea v-model="form.chief_complaint" class="pattern-field-clay" />
                    <InputError :message="form.errors.chief_complaint" />
                </label>

                <label class="space-y-1.5">
                    <span class="text-xs font-semibold text-muted-foreground">أعراض جديدة</span>
                    <textarea v-model="form.new_symptoms" class="pattern-field-clay" />
                    <InputError :message="form.errors.new_symptoms" />
                </label>

                <label class="space-y-1.5">
                    <span class="text-xs font-semibold text-muted-foreground">الشكوى المرضية أو الجراحية</span>
                    <textarea v-model="form.medical_or_surgical_complaint" class="pattern-field-clay" />
                    <InputError :message="form.errors.medical_or_surgical_complaint" />
                </label>

                <label class="space-y-1.5">
                    <span class="text-xs font-semibold text-muted-foreground">التشخيص</span>
                    <textarea v-model="form.diagnosis" class="pattern-field-clay" />
                    <InputError :message="form.errors.diagnosis" />
                </label>

                <label class="space-y-1.5">
                    <span class="text-xs font-semibold text-muted-foreground">العلاج الموصوف أو الإحالة</span>
                    <textarea v-model="form.prescribed_treatment_or_referral" class="pattern-field-clay" />
                    <InputError :message="form.errors.prescribed_treatment_or_referral" />
                </label>

                <label class="space-y-1.5 lg:col-span-2">
                    <span class="text-xs font-semibold text-muted-foreground">ملاحظات عامة</span>
                    <textarea v-model="form.general_notes" class="pattern-field-clay" />
                    <InputError :message="form.errors.general_notes" />
                </label>

                <label class="space-y-1.5 lg:col-span-2">
                    <span class="text-xs font-semibold text-muted-foreground">ملاحظات</span>
                    <textarea v-model="form.notes" class="pattern-field-clay" />
                    <InputError :message="form.errors.notes" />
                </label>

                <div class="flex items-center gap-2 lg:col-span-4">
                    <Button type="submit" :disabled="form.processing" class="h-10 rounded-xl">
                        <Save class="size-4" />
                        حفظ الزيارة
                    </Button>
                    <Button type="button" variant="outline" class="h-10 rounded-xl" @click="closeForm">
                        إلغاء
                    </Button>
                </div>
            </form>
        </section>

        <article class="print-area overflow-hidden rounded-2xl border border-border bg-card shadow-card">
            <header class="border-b border-border bg-muted/30 p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-card">
                            <IdCard class="size-7" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-muted-foreground">{{ clinicName }}</p>
                            <h2 class="text-2xl font-bold text-foreground">بطاقة مريض</h2>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3 lg:min-w-[430px]">
                        <div class="rounded-xl border border-border bg-card p-3">
                            <span class="block text-xs text-muted-foreground">رقم الصفحة</span>
                            <strong>{{ display(card.page_number) }}</strong>
                        </div>
                        <div class="rounded-xl border border-border bg-card p-3">
                            <span class="block text-xs text-muted-foreground">التاريخ</span>
                            <strong>{{ display(card.date) }}</strong>
                        </div>
                        <div class="rounded-xl border border-border bg-card p-3">
                            <span class="block text-xs text-muted-foreground">رقم المريض</span>
                            <strong>{{ display(patient.file_number) }}</strong>
                        </div>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div class="info-box">
                        <span>اسم المجمع الطبي</span>
                        <strong>{{ projectName }}</strong>
                    </div>
                    <div class="info-box">
                        <span>اسم المريض</span>
                        <strong>{{ patientName }}</strong>
                    </div>
                    
                    <div class="info-box">
                        <span>العمر</span>
                        <strong>{{ patient.age !== null ? `${patient.age} سنة` : dash }}</strong>
                    </div>
                    <div class="info-box">
                        <span>الجنس</span>
                        <strong>{{ genderLabel }}</strong>
                    </div>
                    <div class="info-box">
                        <span>رقم الهاتف</span>
                        <strong>{{ display(patient.phone) }}</strong>
                    </div>
                    <div class="info-box">
                        <span>الطبيب</span>
                        <strong>{{ displayedDoctorName }}</strong>
                    </div>
                    <div class="info-box">
                        <span>العيادة</span>
                        <strong>{{ displayedClinicName }}</strong>
                    </div>
                    <div class="info-box">
                        <span>تاريخ الميلاد</span>
                        <strong>{{ formatDate(patient.date_of_birth) }}</strong>
                    </div>
                </div>
            </header>

            <section class="border-b border-border p-5">
                <div class="mb-3 flex items-center gap-2">
                    <FileText class="size-5 text-primary" />
                    <h2 class="text-lg font-bold">معلومات الزيارة</h2>
                </div>
                <div
                    v-if="activeAppointment"
                    class="mb-4 rounded-xl border border-primary/20 bg-primary/5 p-4"
                >
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2 text-sm font-semibold text-primary">
                            <Calendar class="size-4" />
                            <span>بيانات الموعد المحجوز اليوم</span>
                        </div>
                        <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                            {{ appointmentStatus }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-3 xl:grid-cols-6">
                        <div>
                            <span class="block text-[0.65rem] font-semibold text-muted-foreground">رقم الموعد</span>
                            <strong>{{ display(activeAppointment.appointment_number) }}</strong>
                        </div>
                        <div>
                            <span class="block text-[0.65rem] font-semibold text-muted-foreground">التاريخ</span>
                            <strong>{{ appointmentDate }}</strong>
                        </div>
                        <div>
                            <span class="block text-[0.65rem] font-semibold text-muted-foreground">الوقت</span>
                            <strong>{{ appointmentTime }}</strong>
                        </div>
                        <div>
                            <span class="block text-[0.65rem] font-semibold text-muted-foreground">نوع الموعد</span>
                            <strong>{{ appointmentType }}</strong>
                        </div>
                        <div>
                            <span class="block text-[0.65rem] font-semibold text-muted-foreground">الطبيب</span>
                            <strong>{{ appointmentDoctorName }}</strong>
                        </div>
                        <div>
                            <span class="block text-[0.65rem] font-semibold text-muted-foreground">العيادة</span>
                            <strong>{{ appointmentClinicName }}</strong>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                    <div class="info-box min-h-24">
                        <span>السبب للزيارة</span>
                        <strong>{{ display(latestVisit?.visit_reason ?? appointmentSummary) }}</strong>
                    </div>
                    <div class="info-box min-h-24">
                        <span>الشكوى الرئيسية</span>
                        <strong>{{ display(latestVisit?.chief_complaint) }}</strong>
                    </div>
                    <div class="info-box min-h-24">
                        <span>ملاحظات عامة</span>
                        <strong>{{ display(latestVisit?.general_notes) }}</strong>
                    </div>
                </div>
            </section>

            <section class="p-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold">جدول الزيارات الطبية</h2>
                    <span class="text-xs text-muted-foreground">{{ visits.length }} زيارة</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1150px] border-collapse text-sm">
                        <thead>
                            <tr class="bg-muted/70">
                                <th class="paper-th">تاريخ الزيارة</th>
                                <th class="paper-th">الوقت</th>
                                <th class="paper-th">الموعد</th>
                                <th class="paper-th">أعراض جديدة</th>
                                <th class="paper-th">الشكوى المرضية أو الجراحية</th>
                                <th class="paper-th">التشخيص</th>
                                <th class="paper-th">العلاج الموصوف أو الإحالة</th>
                                <th class="paper-th">اسم الطبيب</th>
                                <th class="paper-th">التوقيع</th>
                                <th class="paper-th">ملاحظات</th>
                                <th v-if="permissions.can_manage_visits" class="paper-th no-print">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="visit in visits" :key="visit.id" class="align-top">
                                <td class="paper-td whitespace-nowrap">{{ formatDate(visit.visit_date) }}</td>
                                <td class="paper-td whitespace-nowrap">{{ visit.visit_time ?? dash }}</td>
                                <td class="paper-td whitespace-nowrap">
                                    <span v-if="visit.appointment_id" class="inline-flex items-center gap-1 text-primary">
                                        <ExternalLink class="size-3" />
                                        مرتبط
                                    </span>
                                    <span v-else>{{ dash }}</span>
                                </td>
                                <td class="paper-td">{{ display(visit.new_symptoms) }}</td>
                                <td class="paper-td">{{ display(visit.medical_or_surgical_complaint) }}</td>
                                <td class="paper-td">{{ display(visit.diagnosis) }}</td>
                                <td class="paper-td">{{ display(visit.prescribed_treatment_or_referral) }}</td>
                                <td class="paper-td">{{ display(visit.doctor?.name) }}</td>
                                <td class="paper-td">{{ display(visit.signature) }}</td>
                                <td class="paper-td">{{ display(visit.notes) }}</td>
                                <td v-if="permissions.can_manage_visits" class="paper-td no-print">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-lg text-primary hover:bg-primary/10"
                                            title="تعديل"
                                            @click="openEditForm(visit)"
                                        >
                                            <Pencil class="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-lg text-destructive hover:bg-destructive/10"
                                            title="حذف"
                                            @click="deleteVisit(visit)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="visits.length === 0">
                                <td :colspan="permissions.can_manage_visits ? 11 : 10" class="paper-td py-12 text-center text-muted-foreground">
                                    <span v-if="activeAppointment">
                                        يوجد موعد محجوز اليوم لهذا المريض، ولم يتم تسجيل زيارة طبية مرتبطة به بعد.
                                    </span>
                                    <span v-else>لا توجد زيارات مسجلة لهذا المريض.</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </article>
    </div>
</template>

<style>
.info-box {
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--card);
    padding: 0.75rem;
}

.info-box span {
    display: block;
    margin-bottom: 0.25rem;
    color: var(--muted-foreground);
    font-size: 0.75rem;
    font-weight: 600;
}

.info-box strong {
    color: var(--foreground);
    font-size: 0.92rem;
    line-height: 1.65;
    white-space: pre-wrap;
}

.paper-th,
.paper-td {
    border: 1px solid var(--border);
    padding: 0.7rem;
    text-align: right;
    vertical-align: top;
}

.paper-th {
    color: var(--muted-foreground);
    font-size: 0.75rem;
    font-weight: 800;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 10mm;
    }

    body * {
        visibility: hidden;
    }

    .print-area,
    .print-area * {
        visibility: visible;
    }

    .print-area {
        position: absolute;
        inset: 0;
        width: 100%;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }

    .no-print,
    .no-print * {
        display: none !important;
    }

    .container-modern {
        max-width: none !important;
        padding: 0 !important;
    }

    .paper-th,
    .paper-td {
        padding: 5px;
        font-size: 9px;
    }

    .info-box {
        break-inside: avoid;
        border-radius: 6px;
        padding: 6px;
    }
}
</style>
