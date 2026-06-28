<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarDays,
    ClipboardList,
    Clock,
    FileText,
    Stethoscope,
} from 'lucide-vue-next';
import { computed } from 'vue';

type ClinicInfo = {
    name: string | null;
    specialty: string | null;
};

type DoctorInfo = {
    name: string;
    specialty: string | null;
};

type Stats = {
    today_appointments: number;
    examined_today: number;
    pending_follow_ups: number;
    active_treatment_plans: number;
};

type Patient = {
    id: number;
    first_name: string;
    last_name: string;
    file_number: number;
};

type Appointment = {
    id: number;
    scheduled_for: string;
    status: string;
    appointment_type: string | null;
    patient: Patient | null;
    medical_record_id: number | null;
};

defineProps<{
    clinic: ClinicInfo;
    doctor: DoctorInfo;
    stats: Stats;
    today_schedule: Appointment[];
    upcoming_appointments: Appointment[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'مساحة الطبيب', href: '/doctor/workspace' },
        ],
    },
});

const currentDate = computed(() => {
    const now = new Date();

    return now.toLocaleDateString('ar-SA', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
});

const currentTime = computed(() => {
    const now = new Date();

    return now.toLocaleTimeString('ar-SA', {
        hour: '2-digit',
        minute: '2-digit',
    });
});

const formatTime = (dateTime: string) => {
    const date = new Date(dateTime);

    return date.toLocaleTimeString('ar-SA', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusLabel = (status: string) => {
    const statusMap: Record<string, string> = {
        scheduled: 'مجدول',
        confirmed: 'مؤكد',
        arrived: 'حضر',
        completed: 'مكتمل',
        canceled: 'ملغي',
        no_show: 'لم يحضر',
    };

    return statusMap[status] ?? status;
};

const getStatusBadgeClass = (status: string) => {
    const statusMap: Record<string, string> = {
        scheduled: 'bg-blue-50 text-blue-700 border-blue-200',
        confirmed: 'bg-sky-50 text-sky-700 border-sky-200',
        arrived: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        completed: 'bg-teal-50 text-teal-700 border-teal-200',
        canceled: 'bg-red-50 text-red-700 border-red-200',
        no_show: 'bg-amber-50 text-amber-700 border-amber-200',
    };

    return statusMap[status] ?? 'bg-slate-50 text-slate-700 border-slate-200';
};

const getVisitTypeLabel = (type: string | null) => {
    if (type === 'first_visit') {
        return 'زيارة أولى';
    }

    if (type === 'review') {
        return 'متابعة';
    }

    return '—';
};

const patientName = (patient: Patient | null): string => {
    if (!patient) {
        return 'مريض غير مرتبط';
    }

    return `${patient.first_name} ${patient.last_name}`.trim() || 'مريض غير مرتبط';
};

const patientFileNumber = (patient: Patient | null): string => {
    return patient?.file_number ? `ملف #${patient.file_number}` : 'بدون رقم ملف';
};

const medicalRecordHref = (apt: Appointment): string => {
    if (apt.medical_record_id) {
        return `/medical-records/${apt.medical_record_id}`;
    }

    if (apt.patient?.id) {
        return `/medical-records/create?patient_id=${apt.patient.id}`;
    }

    return '/medical-records';
};
</script>

<template>
    <Head title="مساحة الطبيب" />

    <div class="container-modern space-y-6 py-6" dir="rtl">
        <!-- Welcome Header with Clinic Identity -->
        <section class="rounded-2xl border border-[#E2ECF6] bg-gradient-to-l from-[#F0F9FF] to-white p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-2 text-sm text-[#0284C7]">
                        <Stethoscope class="size-4" />
                        <span class="font-medium">{{ clinic.specialty ?? clinic.name ?? 'العيادة' }}</span>
                    </div>
                    <h1 class="mt-2 text-2xl font-bold tracking-normal text-[#111827] md:text-3xl">
                        مرحباً، د. {{ doctor.name }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ currentDate }} — {{ currentTime }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        href="/doctor/appointments/today"
                        class="inline-flex items-center gap-1.5 rounded-2xl bg-[#0EA5E9] px-4 py-2 text-sm font-medium text-white shadow-[0_10px_24px_-16px_rgb(14_165_233_/_0.75)] transition-all duration-200 hover:bg-[#0284C7]"
                    >
                        <CalendarClock class="size-4" />
                        مواعيد اليوم
                    </Link>
                    <Link
                        href="/medical-records"
                        class="inline-flex items-center gap-1.5 rounded-2xl border border-[#DDE9F3] bg-white px-4 py-2 text-sm font-medium text-[#47677F] transition-all duration-200 hover:bg-[#F7FBFE] hover:text-[#075985]"
                    >
                        <FileText class="size-4" />
                        السجلات الطبية
                    </Link>
                </div>
            </div>
        </section>

        <!-- Summary Cards -->
        <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <Link href="/doctor/appointments/today" class="card-float card-hover">
                <div class="flex items-center gap-3">
                    <div class="icon-container bg-[#EAF7FE] text-[#0EA5E9]">
                        <CalendarClock class="size-5" />
                    </div>
                    <div>
                        <p class="metric-label">مواعيد اليوم</p>
                        <p class="metric-value mt-0.5">{{ stats.today_appointments }}</p>
                    </div>
                </div>
            </Link>

            <div class="card-float card-hover">
                <div class="flex items-center gap-3">
                    <div class="icon-container bg-[#E0F2FE] text-[#0284C7]">
                        <Stethoscope class="size-5" />
                    </div>
                    <div>
                        <p class="metric-label">تم فحصهم اليوم</p>
                        <p class="metric-value mt-0.5">{{ stats.examined_today }}</p>
                    </div>
                </div>
            </div>

            <Link href="/doctor/follow-ups" class="card-float card-hover">
                <div class="flex items-center gap-3">
                    <div class="icon-container bg-[#FEF3C7] text-[#F59E0B]">
                        <ClipboardList class="size-5" />
                    </div>
                    <div>
                        <p class="metric-label">متابعات معلقة</p>
                        <p class="metric-value mt-0.5">{{ stats.pending_follow_ups }}</p>
                    </div>
                </div>
            </Link>

            <div class="card-float card-hover">
                <div class="flex items-center gap-3">
                    <div class="icon-container bg-[#E0E7FF] text-[#6366F1]">
                        <FileText class="size-5" />
                    </div>
                    <div>
                        <p class="metric-label">خطط علاجية نشطة</p>
                        <p class="metric-value mt-0.5">{{ stats.active_treatment_plans }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Content -->
        <section class="grid gap-6 xl:grid-cols-2">
            <!-- Today's Schedule -->
            <article class="card-float">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Clock class="size-4 text-slate-400" />
                        <h2 class="text-sm font-semibold text-slate-900">جدول اليوم</h2>
                    </div>
                    <Link
                        href="/doctor/appointments/today"
                        class="text-xs font-medium text-[#0284C7] transition hover:underline"
                    >
                        عرض الكل
                    </Link>
                </div>

                <div v-if="today_schedule.length > 0" class="space-y-2">
                    <div
                        v-for="apt in today_schedule"
                        :key="apt.id"
                        class="flex items-center justify-between rounded-xl border border-[#E2ECF6] bg-[#F7FAFD] p-3 transition-colors hover:bg-[#EAF7FE]"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#E2ECF6] bg-white text-xs font-semibold tabular-nums text-[#47677F]">
                                {{ formatTime(apt.scheduled_for) }}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-900">
                                    {{ patientName(apt.patient) }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ patientFileNumber(apt.patient) }} • {{ getVisitTypeLabel(apt.appointment_type) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                :class="getStatusBadgeClass(apt.status)"
                            >
                                {{ getStatusLabel(apt.status) }}
                            </span>
                            <Link
                                :href="medicalRecordHref(apt)"
                                class="rounded-lg p-1.5 text-slate-400 transition hover:bg-white hover:text-[#0284C7]"
                            >
                                <Stethoscope class="size-4" />
                            </Link>
                        </div>
                    </div>
                </div>
                <div v-else class="py-10 text-center">
                    <CalendarClock class="mx-auto size-10 text-slate-200 mb-3" />
                    <p class="text-sm text-slate-400">لا توجد مواعيد اليوم</p>
                </div>
            </article>

            <!-- Upcoming Appointments -->
            <article class="card-float">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <CalendarDays class="size-4 text-slate-400" />
                        <h2 class="text-sm font-semibold text-slate-900">المواعيد القادمة</h2>
                    </div>
                </div>

                <div v-if="upcoming_appointments.length > 0" class="space-y-2">
                    <div
                        v-for="apt in upcoming_appointments"
                        :key="apt.id"
                        class="flex items-center justify-between rounded-xl border border-[#E2ECF6] bg-[#F7FAFD] p-3 transition-colors hover:bg-[#EAF7FE]"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#E2ECF6] bg-white text-xs font-semibold tabular-nums text-[#47677F]">
                                {{ formatTime(apt.scheduled_for) }}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-900">
                                    {{ patientName(apt.patient) }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ new Date(apt.scheduled_for).toLocaleDateString('ar-SA') }}
                                </p>
                            </div>
                        </div>
                        <span
                            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                            :class="getStatusBadgeClass(apt.status)"
                        >
                            {{ getStatusLabel(apt.status) }}
                        </span>
                    </div>
                </div>
                <div v-else class="py-10 text-center">
                    <CalendarDays class="mx-auto size-10 text-slate-200 mb-3" />
                    <p class="text-sm text-slate-400">لا توجد مواعيد قادمة</p>
                </div>
            </article>
        </section>

        <!-- Quick Links -->
        <section>
            <h2 class="section-label mb-3">روابط سريعة</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Link
                    href="/doctor/appointments/today"
                    class="group flex items-start gap-3.5 rounded-2xl border border-[#E2ECF6] bg-white p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-[#BFE3F5] hover:shadow-card-hover"
                >
                    <div class="icon-container-sm shrink-0 bg-[#EAF7FE] text-[#0EA5E9] transition-all duration-200 group-hover:bg-[#0EA5E9] group-hover:text-white">
                        <CalendarClock class="size-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">مواعيد اليوم</h3>
                        <p class="mt-0.5 text-xs text-slate-500">عرض وإدارة مواعيد اليوم</p>
                    </div>
                </Link>

                <Link
                    href="/medical-records"
                    class="group flex items-start gap-3.5 rounded-2xl border border-[#E2ECF6] bg-white p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-[#BFE3F5] hover:shadow-card-hover"
                >
                    <div class="icon-container-sm shrink-0 bg-[#E0F2FE] text-[#0284C7] transition-all duration-200 group-hover:bg-[#0284C7] group-hover:text-white">
                        <FileText class="size-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">السجلات الطبية</h3>
                        <p class="mt-0.5 text-xs text-slate-500">سجلات المرضى والتشخيصات</p>
                    </div>
                </Link>

                <Link
                    href="/doctor/prescriptions"
                    class="group flex items-start gap-3.5 rounded-2xl border border-[#E2ECF6] bg-white p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-[#BFE3F5] hover:shadow-card-hover"
                >
                    <div class="icon-container-sm shrink-0 bg-[#FEF3C7] text-[#F59E0B] transition-all duration-200 group-hover:bg-[#F59E0B] group-hover:text-white">
                        <Stethoscope class="size-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">الوصفات الطبية</h3>
                        <p class="mt-0.5 text-xs text-slate-500">الوصفات التي وصفتها</p>
                    </div>
                </Link>

                <Link
                    href="/doctor/follow-ups"
                    class="group flex items-start gap-3.5 rounded-2xl border border-[#E2ECF6] bg-white p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-[#BFE3F5] hover:shadow-card-hover"
                >
                    <div class="icon-container-sm shrink-0 bg-[#E0E7FF] text-[#6366F1] transition-all duration-200 group-hover:bg-[#6366F1] group-hover:text-white">
                        <ClipboardList class="size-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">المتابعات</h3>
                        <p class="mt-0.5 text-xs text-slate-500">مواعيد المتابعة المجدولة</p>
                    </div>
                </Link>
            </div>
        </section>
    </div>
</template>
