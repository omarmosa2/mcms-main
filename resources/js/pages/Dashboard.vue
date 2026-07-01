<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    Banknote,
    BarChart3,
    CalendarClock,
    CheckCircle2,
    ChevronLeft,
    Clock,
    FileText,
    Plus,
    ReceiptText,
    ShieldCheck,
    Stethoscope,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import InvoiceController from '@/actions/App/Http/Controllers/Billing/InvoiceController';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import ReportController from '@/actions/App/Http/Controllers/Reports/ReportController';
import { usePermissions } from '@/composables/usePermissions';
import { useMoneyFormatter } from '@/lib/money';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

type DashboardStats = {
    patients_by_month: Record<string, number>;
    appointments_by_status: Record<string, number>;
    revenue_by_month: Record<string, number>;
    total_patients: number;
    today_new_patients: number;
    today_appointments: number;
    today_appointments_by_status: Record<string, number>;
    pending_invoices_today: number;
    pending_invoices_amount_today: number;
    upcoming_appointments: Array<{
        id: number;
        time: string;
        patient_name: string;
        doctor_name: string;
        status: string;
    }>;
    last_7_days_revenue: Record<string, number>;
    last_7_days_patients: Record<string, number>;
};

const { chartStats } = defineProps<{
    chartStats?: DashboardStats;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'لوحة التحكم', href: dashboard() },
        ],
    },
});

const { can } = usePermissions();
const page = usePage();
const { formatMoney } = useMoneyFormatter();

const auth = computed(() => page.props.auth as { user?: { name: string }; roles?: string[] } | undefined);

const roleNames = computed<string[]>(() => {
    return (
        (page.props.auth as { roles?: string[] } | undefined)?.roles ?? []
    ).filter((value): value is string => typeof value === 'string');
});

const isDoctor = computed(() => roleNames.value.includes('doctor'));
const isAdmin = computed(() => roleNames.value.some((role) => ['super_admin', 'admin', 'clinic_admin'].includes(role)));

const currentDate = computed(() => {
    const now = new Date();

    return now.toLocaleDateString('ar-SA', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
});

type ModuleCard = NavItem & {
    description: string;
    metric: string;
    permission?: string;
    anyPermissions?: string[];
};

const moduleCards: ModuleCard[] = [
    { title: 'المرضى', href: PatientController.index(), icon: Users, description: 'السجلات والملفات والتواصل.', metric: 'السجلات', permission: 'patient.view' },
    { title: 'المواعيد', href: AppointmentController.index(), icon: CalendarClock, description: 'جدولة اليوم والحضور والحالات.', metric: 'الجدولة', permission: 'appointment.view' },
    { title: 'الفواتير', href: InvoiceController.index(), icon: ReceiptText, description: 'الفواتير والمدفوعات والمتأخرات.', metric: 'الإيرادات', permission: 'billing.view' },
    { title: 'التقارير', href: ReportController.index(), icon: BarChart3, description: 'تقارير تشغيلية ومالية قابلة للتصدير.', metric: 'الرؤى', anyPermissions: ['reports.view', 'reports.financial'] },
];

const canAccessModule = (moduleCard: ModuleCard): boolean => {
    if (moduleCard.permission !== undefined && !can(moduleCard.permission)) {
        return false;
    }

    if (moduleCard.anyPermissions !== undefined) {
        return moduleCard.anyPermissions.some((permission) => can(permission));
    }

    return true;
};

const visibleModuleCards = computed<ModuleCard[]>(() => moduleCards.filter((moduleCard) => canAccessModule(moduleCard)));

const quickActions = computed(() => [
    { label: 'موعد جديد', icon: Plus, href: AppointmentController.index(), enabled: can('appointment.create'), primary: true },
    { label: 'مريض جديد', icon: Users, href: PatientController.index({ query: { create: '1' } }), enabled: can('patient.create') && !isDoctor.value, primary: false },
    { label: 'الفواتير', icon: ReceiptText, href: InvoiceController.index(), enabled: can('billing.view') && !isDoctor.value, primary: false },
    { label: 'التقارير', icon: FileText, href: ReportController.index(), enabled: (can('reports.view') || can('reports.financial')) && !isDoctor.value, primary: false },
].filter((action) => action.enabled));

const totalRevenue7Days = computed(() =>
    Object.values(chartStats?.last_7_days_revenue ?? {}).reduce((total, value) => total + Number(value), 0),
);

const totalNewPatients7Days = computed(() =>
    Object.values(chartStats?.last_7_days_patients ?? {}).reduce((total, value) => total + Number(value), 0),
);

const todayCompletedAppointments = computed(() => Number(chartStats?.today_appointments_by_status?.completed ?? 0));
const todayConfirmedAppointments = computed(() => Number(chartStats?.today_appointments_by_status?.confirmed ?? 0));
const todayAttentionAppointments = computed(() =>
    Number(chartStats?.today_appointments_by_status?.canceled ?? 0)
    + Number(chartStats?.today_appointments_by_status?.cancelled ?? 0)
    + Number(chartStats?.today_appointments_by_status?.no_show ?? 0),
);

const urgentAlerts = computed(() => {
    const alerts: Array<{ type: 'warning' | 'danger' | 'info'; message: string; detail: string; href?: string }> = [];

    if (!isDoctor.value && (chartStats?.pending_invoices_today ?? 0) > 0) {
        alerts.push({
            type: (chartStats?.pending_invoices_today ?? 0) > 5 ? 'warning' : 'info',
            message: `${chartStats?.pending_invoices_today ?? 0} فواتير معلقة اليوم`,
            detail: formatMoney(chartStats?.pending_invoices_amount_today ?? 0),
            href: InvoiceController.index.url(),
        });
    }

    if ((chartStats?.upcoming_appointments?.length ?? 0) === 0) {
        alerts.push({
            type: 'info',
            message: 'لا توجد مواعيد خلال الساعة القادمة',
            detail: 'الجدول مستقر الآن',
            href: AppointmentController.index.url(),
        });
    }

    return alerts;
});

const statusRows = computed(() => {
    const rows = Object.entries(chartStats?.appointments_by_status ?? {}).map(([status, count]) => ({
        status,
        count: Number(count),
    }));

    return rows.sort((first, second) => second.count - first.count);
});

const statusLabels: Record<string, string> = {
    scheduled: 'مجدول',
    confirmed: 'مؤكد',
    completed: 'مكتمل',
    canceled: 'ملغى',
    cancelled: 'ملغى',
    no_show: 'لم يحضر',
    arrived: 'حاضر',
};

const getStatusLabel = (status: string): string => statusLabels[status] ?? status;

const getStatusBadgeClass = (status: string): string => {
    const statusMap: Record<string, string> = {
        scheduled: 'bg-sky-500/10 text-sky-700 ring-sky-500/20',
        confirmed: 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/20',
        arrived: 'bg-teal-500/10 text-teal-700 ring-teal-500/20',
        completed: 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/20',
        canceled: 'bg-amber-500/10 text-amber-700 ring-amber-500/20',
        cancelled: 'bg-amber-500/10 text-amber-700 ring-amber-500/20',
        no_show: 'bg-rose-500/10 text-rose-700 ring-rose-500/20',
    };

    return statusMap[status] ?? 'bg-muted text-muted-foreground ring-border';
};

const getStatusDotClass = (status: string): string => {
    if (status === 'confirmed' || status === 'completed') {
        return 'bg-emerald-500';
    }

    if (status === 'arrived') {
        return 'bg-teal-500';
    }

    if (status === 'scheduled') {
        return 'bg-sky-500';
    }

    if (status === 'canceled' || status === 'cancelled' || status === 'no_show') {
        return 'bg-amber-500';
    }

    return 'bg-muted-foreground';
};

const alertClass = (type: 'warning' | 'danger' | 'info'): string => {
    if (type === 'danger') {
        return 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/25 dark:text-rose-200';
    }

    if (type === 'warning') {
        return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/25 dark:text-amber-200';
    }

    return 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900/50 dark:bg-sky-950/25 dark:text-sky-200';
};
</script>

<template>
    <Head title="لوحة التحكم" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                    <ShieldCheck class="size-3.5" />
                    لوحة الإدارة
                </div>
                <div>
                    <h1 class="text-3xl font-extrabold tracking-normal text-foreground">
                        مرحبا، {{ auth?.user?.name ?? 'مستخدم' }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ currentDate }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    v-for="action in quickActions"
                    :key="action.label"
                    :href="action.href"
                    class="inline-flex h-10 items-center gap-2 rounded-xl px-3.5 text-sm font-bold transition-colors"
                    :class="action.primary
                        ? 'bg-primary text-primary-foreground shadow-sm hover:bg-primary/90'
                        : 'border border-border bg-card text-foreground hover:bg-muted'"
                >
                    <component :is="action.icon" class="size-4" />
                    {{ action.label }}
                </Link>
            </div>
        </section>

        <section v-if="urgentAlerts.length > 0" class="grid gap-2 lg:grid-cols-2">
            <Link
                v-for="(alert, index) in urgentAlerts"
                :key="index"
                :href="alert.href ?? '#'"
                class="flex items-center justify-between rounded-xl border p-3.5 transition-colors hover:bg-card"
                :class="alertClass(alert.type)"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <AlertTriangle class="size-4 shrink-0" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold">{{ alert.message }}</p>
                        <p class="truncate text-xs opacity-75">{{ alert.detail }}</p>
                    </div>
                </div>
                <ChevronLeft class="size-4 shrink-0 opacity-60" />
            </Link>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <article class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold text-muted-foreground">مواعيد اليوم</p>
                        <p class="mt-1 text-2xl font-extrabold tabular-nums text-foreground">{{ chartStats?.today_appointments ?? 0 }}</p>
                    </div>
                    <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <CalendarClock class="size-5" />
                    </div>
                </div>
            </article>

            <article class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold text-muted-foreground">مؤكد ومكتمل</p>
                        <p class="mt-1 text-2xl font-extrabold tabular-nums text-foreground">{{ todayConfirmedAppointments + todayCompletedAppointments }}</p>
                    </div>
                    <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-700">
                        <CheckCircle2 class="size-5" />
                    </div>
                </div>
            </article>

            <article v-if="!isDoctor" class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold text-muted-foreground">مرضى جدد اليوم</p>
                        <p class="mt-1 text-2xl font-extrabold tabular-nums text-foreground">{{ chartStats?.today_new_patients ?? 0 }}</p>
                    </div>
                    <div class="flex size-10 items-center justify-center rounded-xl bg-sky-500/10 text-sky-700">
                        <Users class="size-5" />
                    </div>
                </div>
            </article>

            

            <article class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold text-muted-foreground">تحتاج متابعة</p>
                        <p class="mt-1 text-2xl font-extrabold tabular-nums text-foreground">{{ todayAttentionAppointments }}</p>
                    </div>
                    <div class="flex size-10 items-center justify-center rounded-xl bg-amber-500/10 text-amber-700">
                        <AlertTriangle class="size-5" />
                    </div>
                </div>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-[1.4fr_0.9fr]">
            <article class="rounded-xl border border-border bg-card shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                    <div class="flex items-center gap-2">
                        <Clock class="size-4 text-muted-foreground" />
                        <h2 class="text-sm font-extrabold text-foreground">المواعيد خلال الساعة القادمة</h2>
                    </div>
                    <Link
                        v-if="can('appointment.view')"
                        :href="AppointmentController.index()"
                        class="text-xs font-bold text-primary hover:underline"
                    >
                        عرض الكل
                    </Link>
                </div>

                <div v-if="chartStats?.upcoming_appointments && chartStats.upcoming_appointments.length > 0" class="divide-y divide-border">
                    <div
                        v-for="appointment in chartStats.upcoming_appointments.slice(0, 6)"
                        :key="appointment.id"
                        class="flex items-center justify-between gap-3 px-4 py-3"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-10 w-12 shrink-0 items-center justify-center rounded-lg border border-border bg-muted text-xs font-extrabold tabular-nums text-foreground">
                                {{ appointment.time }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-foreground">{{ appointment.patient_name || '-' }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ appointment.doctor_name || '-' }}</p>
                            </div>
                        </div>
                        <span
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ring-1"
                            :class="getStatusBadgeClass(appointment.status)"
                        >
                            <span class="size-1.5 rounded-full" :class="getStatusDotClass(appointment.status)"></span>
                            {{ getStatusLabel(appointment.status) }}
                        </span>
                    </div>
                </div>

                <div v-else class="px-4 py-10 text-center">
                    <CalendarClock class="mx-auto mb-3 size-10 text-muted-foreground/35" />
                    <p class="text-sm font-bold text-foreground">لا توجد مواعيد قادمة الآن</p>
                    <p class="mt-1 text-xs text-muted-foreground">سيظهر هنا أقرب المرضى المتوقع وصولهم.</p>
                </div>
            </article>

            <div class="space-y-5">
                <article v-if="!isDoctor" class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-extrabold text-foreground">المتابعة المالية</h2>
                            <p class="text-xs text-muted-foreground">فواتير اليوم غير المسددة</p>
                        </div>
                        <ReceiptText class="size-5 text-muted-foreground" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-muted p-3">
                            <p class="text-xs text-muted-foreground">العدد</p>
                            <p class="mt-1 text-xl font-extrabold tabular-nums">{{ chartStats?.pending_invoices_today ?? 0 }}</p>
                        </div>
                        <div class="rounded-lg bg-muted p-3">
                            <p class="text-xs text-muted-foreground">المبلغ</p>
                            <p class="mt-1 text-lg font-extrabold tabular-nums">{{ formatMoney(chartStats?.pending_invoices_amount_today ?? 0) }}</p>
                        </div>
                    </div>
                </article>

                <article class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-extrabold text-foreground">حالات آخر 30 يوم</h2>
                            <p class="text-xs text-muted-foreground">ملخص سريع دون مخططات</p>
                        </div>
                        <Stethoscope class="size-5 text-muted-foreground" />
                    </div>
                    <div v-if="statusRows.length > 0" class="space-y-2">
                        <div v-for="row in statusRows" :key="row.status" class="flex items-center justify-between rounded-lg bg-muted px-3 py-2">
                            <span class="inline-flex items-center gap-2 text-sm font-bold text-foreground">
                                <span class="size-2 rounded-full" :class="getStatusDotClass(row.status)"></span>
                                {{ getStatusLabel(row.status) }}
                            </span>
                            <span class="text-sm font-extrabold tabular-nums">{{ row.count }}</span>
                        </div>
                    </div>
                    <p v-else class="rounded-lg bg-muted px-3 py-5 text-center text-sm text-muted-foreground">
                        لا توجد حالات مسجلة ضمن الفترة.
                    </p>
                </article>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[0.9fr_1.4fr]">
            <article class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <div class="mb-4">
                    <h2 class="text-sm font-extrabold text-foreground">مؤشرات الإدارة</h2>
                    <p class="text-xs text-muted-foreground">أرقام تساعدك على قراءة اليوم بسرعة.</p>
                </div>
                <div class="space-y-2">
                    <div class="flex items-center justify-between rounded-lg bg-muted px-3 py-2">
                        <span class="text-sm text-muted-foreground">إجمالي المرضى</span>
                        <span class="font-extrabold tabular-nums">{{ chartStats?.total_patients ?? 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-muted px-3 py-2">
                        <span class="text-sm text-muted-foreground">مرضى جدد خلال 7 أيام</span>
                        <span class="font-extrabold tabular-nums">{{ totalNewPatients7Days }}</span>
                    </div>
                    <div v-if="isAdmin" class="flex items-center justify-between rounded-lg bg-muted px-3 py-2">
                        <span class="text-sm text-muted-foreground">الوصول الإداري</span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2 py-1 text-xs font-bold text-emerald-700">
                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                            مفعل
                        </span>
                    </div>
                </div>
            </article>

            <section>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-extrabold text-foreground">الوحدات المهمة</h2>
                    <span class="text-xs font-bold text-muted-foreground">{{ visibleModuleCards.length }} وحدات متاحة</span>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <Link
                        v-for="module in visibleModuleCards"
                        :key="module.title"
                        :href="module.href"
                        class="group rounded-xl border border-border bg-card p-4 shadow-sm transition-colors hover:border-primary/40 hover:bg-muted/40"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 gap-3">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                                    <component :is="module.icon" class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-extrabold text-foreground">{{ module.title }}</h3>
                                    <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-muted-foreground">{{ module.description }}</p>
                                </div>
                            </div>
                            <ArrowUpRight class="size-4 shrink-0 text-muted-foreground transition-colors group-hover:text-primary" />
                        </div>
                    </Link>
                </div>
            </section>
        </section>
    </div>
</template>
