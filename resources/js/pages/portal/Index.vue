<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Calendar,
    CalendarClock,
    Clock,
    FileText,
    Phone,
    User,
    AlertCircle,
    CheckCircle2,
    XCircle,
    Timer,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';

type Appointment = {
    id: number;
    appointment_number: string;
    scheduled_for: string | null;
    duration_minutes: number;
    status: string;
    cancel_reason: string | null;
    notes: string | null;
};

type Patient = {
    id: number;
    full_name: string;
    file_number: string;
    phone: string | null;
};

const {
    token,
    expires_at,
    patient,
    appointments,
    actions,
} = defineProps<{
    token: string;
    expires_at: string | null;
    patient: Patient;
    appointments: Appointment[];
    actions: {
        can_reschedule: boolean;
        can_cancel: boolean;
    };
}>();

const isRescheduling = ref<number | null>(null);
const isCancelling = ref<number | null>(null);
const newDate = ref<string>('');
const cancelReason = ref<string>('');

const formatDate = (value: string | null): string => {
    if (!value) {
return 'غير مجدول';
}

    return new Date(value).toLocaleString('ar-SA');
};

const statusConfig: Record<string, { label: string; icon: any; class: string }> = {
    scheduled: { label: 'مجدول', icon: Calendar, class: 'border-warning-300/70 bg-warning-100/80 text-warning-800 dark:border-warning-500/40 dark:bg-warning-500/15 dark:text-warning-100' },
    confirmed: { label: 'مؤكد', icon: CheckCircle2, class: 'border-success-300/70 bg-success-100/80 text-success-800 dark:border-success-500/40 dark:bg-success-500/15 dark:text-success-100' },
    arrived: { label: 'وصل', icon: Timer, class: 'border-info-300/70 bg-info-100/80 text-info-800 dark:border-info-500/40 dark:bg-info-500/15 dark:text-info-100' },
    cancelled: { label: 'ملغي', icon: XCircle, class: 'border-destructive/70 bg-destructive/10 text-destructive dark:border-destructive/40 dark:bg-destructive/15 dark:text-destructive-foreground' },
    completed: { label: 'مكتمل', icon: CheckCircle2, class: 'border-success-300/70 bg-success-100/80 text-success-800 dark:border-success-500/40 dark:bg-success-500/15 dark:text-success-100' },
    no_show: { label: 'لم يحضر', icon: AlertCircle, class: 'border-muted-foreground/30 bg-muted/80 text-muted-foreground dark:border-muted-foreground/40 dark:bg-muted/15 dark:text-muted-foreground' },
};

const getStatusConfig = (status: string) => {
    return statusConfig[status] ?? { label: status, icon: AlertCircle, class: 'border-border/70 bg-background/80 text-muted-foreground' };
};

const activeAppointments = computed(() => appointments.filter(a => !['cancelled', 'completed', 'no_show'].includes(a.status)));
const pastAppointments = computed(() => appointments.filter(a => ['cancelled', 'completed', 'no_show'].includes(a.status)));

const handleReschedule = async (appointmentId: number) => {
    if (!newDate.value) {
return;
}

    isRescheduling.value = appointmentId;

    try {
        const response = await fetch(AppointmentController.update.url(appointmentId, { query: { plainToken: token } }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify({ action: 'reschedule', scheduled_for: newDate.value }),
        });

        if (response.ok) {
            window.location.reload();
        }
    } finally {
        isRescheduling.value = null;
    }
};

const handleCancel = async (appointmentId: number) => {
    if (!cancelReason.value.trim()) {
return;
}

    isCancelling.value = appointmentId;

    try {
        const response = await fetch(AppointmentController.update.url(appointmentId, { query: { plainToken: token } }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify({ action: 'cancel', cancel_reason: cancelReason.value }),
        });

        if (response.ok) {
            window.location.reload();
        }
    } finally {
        isCancelling.value = null;
    }
};

const expiresInDays = computed(() => {
    if (!expires_at) {
return null;
}

    const diff = new Date(expires_at).getTime() - Date.now();

    return Math.ceil(diff / (1000 * 60 * 60 * 24));
});
</script>

<template>
    <Head title="بوابة المريض" />

    <div class="min-h-screen bg-gradient-to-br from-surface-secondary to-info-50 dark:from-slate-950 dark:to-slate-900" dir="rtl">
        <div class="mx-auto max-w-3xl px-4 py-8 md:py-12">
            <!-- Header -->
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-info-500/10 text-info-600 dark:text-info-300">
                    <User class="size-8" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">بوابة المريض</h1>
                <p class="mt-1 text-sm text-muted-foreground">إدارة مواعيدك ومعلوماتك الطبية</p>
            </div>

            <!-- Patient Card -->
            <div class="glass-panel-lux mb-6 p-6">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-info-500/10 text-info-600 dark:text-info-300">
                        <User class="size-6" />
                    </div>
                    <div class="flex-1">
                        <h2 class="text-lg font-semibold">{{ patient.full_name }}</h2>
                        <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                            <span class="inline-flex items-center gap-1.5">
                                <FileText class="size-3.5" />
                                {{ patient.file_number }}
                            </span>
                            <span v-if="patient.phone" class="inline-flex items-center gap-1.5">
                                <Phone class="size-3.5" />
                                {{ patient.phone }}
                            </span>
                        </div>
                    </div>
                    <div v-if="expiresInDays !== null" class="text-right">
                        <p class="text-xs text-muted-foreground">ينتهي الوصول في</p>
                        <p class="text-lg font-semibold" :class="expiresInDays <= 1 ? 'text-destructive' : 'text-info-600 dark:text-info-300'">
                            {{ expiresInDays }} {{ expiresInDays === 1 ? 'يوم' : 'أيام' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Active Appointments -->
            <div class="mb-6">
                <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold tracking-tight">
                    <CalendarClock class="size-4 text-info-600 dark:text-info-300" />
                    المواعيد القادمة
                </h3>

                <div v-if="activeAppointments.length === 0" class="glass-panel-soft p-8 text-center">
                    <Calendar class="mx-auto mb-3 size-10 text-muted-foreground/50" />
                    <p class="text-sm font-medium text-muted-foreground">لا توجد مواعيد قادمة</p>
                    <p class="mt-1 text-xs text-muted-foreground">ستظهر مواعيدك المجدولة هنا.</p>
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="appointment in activeAppointments"
                        :key="appointment.id"
                        class="glass-panel-soft overflow-hidden"
                    >
                        <div class="p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-sm font-medium">{{ appointment.appointment_number }}</span>
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                                            :class="getStatusConfig(appointment.status).class"
                                        >
                                            <component :is="getStatusConfig(appointment.status).icon" class="size-3" />
                                            {{ getStatusConfig(appointment.status).label }}
                                        </span>
                                    </div>
                                    <div class="mt-2 flex items-center gap-4 text-sm text-muted-foreground">
                                        <span class="inline-flex items-center gap-1.5">
                                            <Clock class="size-3.5" />
                                            {{ formatDate(appointment.scheduled_for) }}
                                        </span>
                                        <span class="inline-flex items-center gap-1.5">
                                            <Timer class="size-3.5" />
                                            {{ appointment.duration_minutes }} دقيقة
                                        </span>
                                    </div>
                                    <p v-if="appointment.notes" class="mt-2 text-xs text-muted-foreground">
                                        {{ appointment.notes }}
                                    </p>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div v-if="actions.can_reschedule || actions.can_cancel" class="mt-4 flex flex-wrap gap-2 border-t border-border/50 pt-4">
                                <!-- Reschedule Form -->
                                <div v-if="actions.can_reschedule && appointment.status !== 'arrived'" class="flex items-center gap-2">
                                    <input
                                        v-model="newDate"
                                        type="datetime-local"
                                        class="pattern-field-clay h-8 text-xs"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-info-500/30 bg-info-500/10 px-3 text-xs font-semibold text-info-700 transition hover:bg-info-500/20 dark:text-info-300"
                                        :disabled="isRescheduling === appointment.id || !newDate"
                                        @click="handleReschedule(appointment.id)"
                                    >
                                        <Calendar class="size-3.5" />
                                        {{ isRescheduling === appointment.id ? 'جاري الحفظ...' : 'إعادة جدولة' }}
                                    </button>
                                </div>

                                <!-- Cancel Form -->
                                <div v-if="actions.can_cancel && appointment.status !== 'arrived'" class="flex items-center gap-2">
                                    <input
                                        v-model="cancelReason"
                                        type="text"
                                        placeholder="سبب الإلغاء"
                                        class="pattern-field-clay h-8 text-xs"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-destructive/30 bg-destructive/10 px-3 text-xs font-semibold text-destructive transition hover:bg-destructive/20"
                                        :disabled="isCancelling === appointment.id || !cancelReason.trim()"
                                        @click="handleCancel(appointment.id)"
                                    >
                                        <XCircle class="size-3.5" />
                                        {{ isCancelling === appointment.id ? 'جاري الإلغاء...' : 'إلغاء' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Past Appointments -->
            <div v-if="pastAppointments.length > 0">
                <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold tracking-tight text-muted-foreground">
                    <Calendar class="size-4" />
                    المواعيد السابقة
                </h3>
                <div class="space-y-3">
                    <div
                        v-for="appointment in pastAppointments"
                        :key="appointment.id"
                        class="rounded-xl border border-border/50 bg-background/40 p-4 opacity-70"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-sm">{{ appointment.appointment_number }}</span>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold"
                                    :class="getStatusConfig(appointment.status).class"
                                >
                                    <component :is="getStatusConfig(appointment.status).icon" class="size-3" />
                                    {{ getStatusConfig(appointment.status).label }}
                                </span>
                            </div>
                            <span class="text-xs text-muted-foreground">{{ formatDate(appointment.scheduled_for) }}</span>
                        </div>
                        <p v-if="appointment.cancel_reason" class="mt-2 text-xs text-destructive">
                            السبب: {{ appointment.cancel_reason }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
