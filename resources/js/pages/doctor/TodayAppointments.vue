<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarClock,
    CheckCircle2,
    Clock,
    FileText,
    IdCard,
    ListChecks,
    Search,
    Stethoscope,
    UserCheck,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import { Input } from '@/components/ui/input';

type Patient = {
    id: number;
    first_name: string;
    last_name: string;
    file_number: number;
    phone: string | null;
    date_of_birth: string | null;
    gender: string | null;
};

type Appointment = {
    id: number;
    scheduled_for: string;
    status: string;
    appointment_type: string | null;
    duration_minutes: number;
    cost: string | null;
    notes: string | null;
    patient: Patient | null;
    medical_record_id: number | null;
};

const props = defineProps<{
    appointments: Appointment[];
    date: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'مساحة الطبيب', href: '/doctor/workspace' },
            { title: 'مواعيد اليوم', href: '/doctor/appointments/today' },
        ],
    },
});

const search = ref('');
const statusFilter = ref('');
const completingAppointmentId = ref<number | null>(null);

const statusOptions = [
    { label: 'الكل', value: '' },
    { label: 'قيد الانتظار', value: 'pending' },
    { label: 'مكتمل', value: 'completed' },
];

const formatTime = (dateTime: string): string => {
    const date = new Date(dateTime);

    return date.toLocaleTimeString('ar-SA', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusLabel = (status: string): string => {
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

const getStatusBadgeClass = (status: string): string => {
    const statusMap: Record<string, string> = {
        scheduled: 'border-info/25 bg-info/10 text-info',
        confirmed: 'border-info/25 bg-info/10 text-info',
        arrived: 'border-primary/25 bg-primary/10 text-primary',
        completed: 'border-success/25 bg-success/10 text-success',
        canceled: 'border-destructive/25 bg-destructive/10 text-destructive',
        no_show: 'border-warning/25 bg-warning/10 text-warning',
    };

    return statusMap[status] ?? 'border-border bg-secondary text-muted-foreground';
};

const getVisitTypeLabel = (type: string | null): string => {
    if (type === 'first_visit') {
        return 'زيارة أولى';
    }

    if (type === 'review') {
        return 'متابعة';
    }

    return '—';
};

const getGenderLabel = (gender: string | null): string => {
    if (gender === 'male') {
        return 'ذكر';
    }

    if (gender === 'female') {
        return 'أنثى';
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

const medicalRecordHref = (appointment: Appointment): string => {
    if (appointment.medical_record_id) {
        return `/medical-records/${appointment.medical_record_id}`;
    }

    if (appointment.patient?.id) {
        return `/medical-records/create?patient_id=${appointment.patient.id}`;
    }

    return '/medical-records';
};

const canCompleteAppointment = (appointment: Appointment): boolean => {
    return ['scheduled', 'confirmed', 'arrived'].includes(appointment.status);
};

const completeAppointment = (appointment: Appointment): void => {
    if (!canCompleteAppointment(appointment) || completingAppointmentId.value !== null) {
        return;
    }

    completingAppointmentId.value = appointment.id;

    router.patch(
        AppointmentController.transitionStatus.url(appointment.id),
        { status: 'completed' },
        {
            preserveScroll: true,
            onFinish: () => {
                completingAppointmentId.value = null;
            },
        },
    );
};

const calculateAge = (dateOfBirth: string | null): number | null => {
    if (!dateOfBirth) {
        return null;
    }

    const birthDate = new Date(dateOfBirth);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }

    return age;
};

const filteredAppointments = computed<Appointment[]>(() => {
    let results = props.appointments;

    if (statusFilter.value === 'pending') {
        results = results.filter((appointment) =>
            ['scheduled', 'confirmed', 'arrived'].includes(appointment.status),
        );
    } else if (statusFilter.value === 'completed') {
        results = results.filter((appointment) => appointment.status === 'completed');
    }

    if (!search.value.trim()) {
        return results;
    }

    const term = search.value.trim().toLowerCase();

    return results.filter((appointment) => {
        const fullName = patientName(appointment.patient).toLowerCase();
        const fileNumber = appointment.patient?.file_number
            ? String(appointment.patient.file_number)
            : '';

        return fullName.includes(term) || fileNumber.includes(term);
    });
});

const displayDate = computed(() => {
    const date = new Date(props.date);

    return date.toLocaleDateString('ar-SA', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
});

const stats = computed(() => ({
    total: props.appointments.length,
    pending: props.appointments.filter((appointment) =>
        ['scheduled', 'confirmed', 'arrived'].includes(appointment.status),
    ).length,
    arrived: props.appointments.filter((appointment) => appointment.status === 'arrived').length,
    completed: props.appointments.filter((appointment) => appointment.status === 'completed').length,
}));

const visibleCount = computed(() => filteredAppointments.value.length);
</script>

<template>
    <Head title="مواعيد اليوم" />

    <div class="container-modern space-y-5 py-5" dir="rtl">
        <section class="glass-panel-soft overflow-hidden">
            <div class="flex flex-col gap-5 border-b border-border/70 px-5 py-5 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex min-w-0 items-center gap-4">
                    <div class="flex size-14 shrink-0 items-center justify-center rounded-2xl border border-primary/15 bg-primary/10 text-primary shadow-sm">
                        <CalendarClock class="size-7" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h1 class="page-title leading-tight">مواعيد اليوم</h1>
                            <span class="inline-flex items-center rounded-full border border-primary/20 bg-primary/10 px-2.5 py-0.5 text-[0.72rem] font-semibold text-primary">
                                صلاحيات الطبيب
                            </span>
                        </div>
                        <p class="mt-1 text-sm font-medium text-muted-foreground">
                            {{ displayDate }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-[minmax(11rem,12rem)_minmax(16rem,22rem)] sm:items-center">
                    <select
                        v-model="statusFilter"
                        class="pattern-field-clay h-11 cursor-pointer rounded-xl px-3 py-1.5 text-sm"
                        aria-label="تصفية حالة الموعد"
                    >
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <div class="relative">
                        <Search class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="search"
                            placeholder="بحث بالاسم أو رقم الملف..."
                            class="pattern-field-clay h-11 rounded-xl pr-9"
                        />
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-border bg-card/95 p-4 shadow-card">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-bold text-muted-foreground">الإجمالي</p>
                    <span class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <CalendarClock class="size-4.5" />
                    </span>
                </div>
                <p class="mt-3 text-2xl font-black text-foreground tabular-nums">{{ stats.total }}</p>
            </article>

            <article class="rounded-2xl border border-info/20 bg-info/5 p-4 shadow-card">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-bold text-muted-foreground">في الانتظار</p>
                    <span class="flex size-9 items-center justify-center rounded-xl bg-info/10 text-info">
                        <Clock class="size-4.5" />
                    </span>
                </div>
                <p class="mt-3 text-2xl font-black text-info tabular-nums">{{ stats.pending }}</p>
            </article>

            <article class="rounded-2xl border border-primary/20 bg-primary/5 p-4 shadow-card">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-bold text-muted-foreground">حضر</p>
                    <span class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <UserCheck class="size-4.5" />
                    </span>
                </div>
                <p class="mt-3 text-2xl font-black text-primary tabular-nums">{{ stats.arrived }}</p>
            </article>

            <article class="rounded-2xl border border-success/20 bg-success/5 p-4 shadow-card">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-bold text-muted-foreground">مكتمل</p>
                    <span class="flex size-9 items-center justify-center rounded-xl bg-success/10 text-success">
                        <CheckCircle2 class="size-4.5" />
                    </span>
                </div>
                <p class="mt-3 text-2xl font-black text-success tabular-nums">{{ stats.completed }}</p>
            </article>
        </section>

        <section class="glass-panel-soft overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-border/70 bg-secondary/20 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl border border-primary/15 bg-primary/10 text-primary">
                        <ListChecks class="size-4.5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-foreground">جدول المواعيد</h2>
                        <p class="text-xs text-muted-foreground">
                            {{ visibleCount }} من {{ stats.total }} موعد ظاهر
                        </p>
                    </div>
                </div>
                <span class="inline-flex w-fit items-center gap-1.5 rounded-full border border-border/80 bg-background px-3 py-1.5 text-xs font-semibold text-muted-foreground shadow-sm">
                    <Stethoscope class="size-3.5 text-primary" />
                    إجراءات الطبيب فقط
                </span>
            </div>

            <div v-if="filteredAppointments.length > 0" class="p-5">
                <div class="overflow-x-auto rounded-2xl border border-border/70 bg-card shadow-sm">
                    <table class="w-full min-w-[920px] border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr class="bg-secondary/50">
                                <th class="border-b border-border px-4 py-3 text-right text-[0.72rem] font-bold text-muted-foreground">
                                    الوقت
                                </th>
                                <th class="border-b border-border px-4 py-3 text-right text-[0.72rem] font-bold text-muted-foreground">
                                    المريض
                                </th>
                                <th class="border-b border-border px-4 py-3 text-right text-[0.72rem] font-bold text-muted-foreground">
                                    نوع الزيارة
                                </th>
                                <th class="border-b border-border px-4 py-3 text-right text-[0.72rem] font-bold text-muted-foreground">
                                    الحالة
                                </th>
                                <th class="border-b border-border px-4 py-3 text-right text-[0.72rem] font-bold text-muted-foreground">
                                    الإجراءات
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="appointment in filteredAppointments"
                                :key="appointment.id"
                                class="transition-colors hover:bg-primary/[0.03]"
                            >
                                <td class="border-b border-border/60 px-4 py-3">
                                    <div class="inline-flex items-center gap-2 rounded-xl border border-border/60 bg-secondary/30 px-2.5 py-1.5">
                                        <Clock class="size-3.5 text-primary" />
                                        <span class="font-semibold tabular-nums text-foreground">
                                            {{ formatTime(appointment.scheduled_for) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="border-b border-border/60 px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="font-bold text-foreground">
                                            {{ patientName(appointment.patient) }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-muted-foreground">
                                            {{ patientFileNumber(appointment.patient) }}
                                            <span v-if="appointment.patient?.gender" class="mx-1">•</span>
                                            <span v-if="appointment.patient?.gender">{{ getGenderLabel(appointment.patient.gender) }}</span>
                                            <template v-if="calculateAge(appointment.patient?.date_of_birth ?? null)">
                                                <span class="mx-1">•</span>
                                                {{ calculateAge(appointment.patient?.date_of_birth ?? null) }} سنة
                                            </template>
                                        </p>
                                    </div>
                                </td>
                                <td class="border-b border-border/60 px-4 py-3">
                                    <span class="font-medium text-foreground/85">
                                        {{ getVisitTypeLabel(appointment.appointment_type) }}
                                    </span>
                                </td>
                                <td class="border-b border-border/60 px-4 py-3">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[0.72rem] font-medium"
                                        :class="getStatusBadgeClass(appointment.status)"
                                    >
                                        <span class="size-1.5 rounded-full bg-current" />
                                        {{ getStatusLabel(appointment.status) }}
                                    </span>
                                </td>
                                <td class="border-b border-border/60 px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-1 rounded-xl border border-border/60 bg-secondary/20 p-1">
                                        <button
                                            v-if="canCompleteAppointment(appointment)"
                                            type="button"
                                            class="inline-flex h-8 items-center gap-1 rounded-lg px-2.5 text-xs font-semibold text-success transition hover:bg-success/10 disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="completingAppointmentId !== null"
                                            aria-label="تحويل الموعد إلى مكتمل"
                                            @click="completeAppointment(appointment)"
                                        >
                                            <CheckCircle2 class="size-3.5" />
                                            مكتمل
                                        </button>
                                        <Link
                                            :href="medicalRecordHref(appointment)"
                                            class="inline-flex h-8 items-center gap-1 rounded-lg px-2.5 text-xs font-semibold transition"
                                            :class="appointment.status === 'completed'
                                                ? 'text-success hover:bg-success/10'
                                                : 'text-primary hover:bg-primary/10'"
                                        >
                                            <component :is="appointment.status === 'completed' ? FileText : Stethoscope" class="size-3.5" />
                                            {{ appointment.status === 'completed' ? 'عرض السجل' : 'فتح السجل' }}
                                        </Link>
                                        <Link
                                            v-if="appointment.patient?.id"
                                            :href="`/patients/${appointment.patient.id}/card`"
                                            class="inline-flex h-8 items-center gap-1 rounded-lg px-2.5 text-xs font-semibold text-muted-foreground transition hover:bg-background hover:text-foreground"
                                        >
                                            <IdCard class="size-3.5" />
                                            بطاقة المريض
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else class="px-5 py-16 text-center">
                <div class="mx-auto flex max-w-md flex-col items-center gap-2 text-muted-foreground">
                    <div class="flex size-14 items-center justify-center rounded-2xl bg-background shadow-sm">
                        <XCircle class="size-7" />
                    </div>
                    <p class="text-sm font-bold text-foreground">
                        {{ search ? 'لا توجد نتائج مطابقة' : 'لا توجد مواعيد اليوم' }}
                    </p>
                    <p class="text-xs">
                        غيّر التصفية أو البحث لعرض مواعيد أخرى ضمن جدول اليوم.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
