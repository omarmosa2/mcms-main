<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    BarChart3,
    CalendarClock,
    Clock,
    FileText,
    ListOrdered,
    Plus,
    ReceiptText,
    Stethoscope,
    Users,
    AlertTriangle,
    ChevronLeft,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import InvoiceController from '@/actions/App/Http/Controllers/Billing/InvoiceController';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import QueueEntryController from '@/actions/App/Http/Controllers/Queue/QueueEntryController';
import ReportController from '@/actions/App/Http/Controllers/Reports/ReportController';
import VisitController from '@/actions/App/Http/Controllers/Visits/VisitController';
import Chart from '@/components/Chart.vue';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

type ChartStats = {
    patients_by_month: Record<string, number>;
    appointments_by_status: Record<string, number>;
    revenue_by_month: Record<string, number>;
    visits_by_month: Record<string, number>;
    today_appointments: number;
    today_appointments_by_status: Record<string, number>;
    pending_queue: number;
    active_visits: number;
    pending_invoices_today: number;
    pending_invoices_amount_today: number;
    upcoming_appointments: Array<{
        id: number;
        time: string;
        patient_name: string;
        doctor_name: string;
        status: string;
    }>;
    long_waiting_patients: Array<{
        id: number;
        queue_number: string;
        patient_name: string;
        waiting_minutes: number;
    }>;
    last_7_days_revenue: Record<string, number>;
    last_7_days_patients: Record<string, number>;
};

const { chartStats } = defineProps<{
    chartStats?: ChartStats;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'لوحة التحكم',
                href: dashboard(),
            },
        ],
    },
});

const { can } = usePermissions();
const page = usePage();

const auth = computed(() => page.props.auth as { user?: { name: string } } | undefined);

const currentDate = computed(() => {
    const now = new Date();
    const options: Intl.DateTimeFormatOptions = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    };

    return now.toLocaleDateString('ar-SA', options);
});

const roleNames = computed<string[]>(() => {
    return (
        ((page.props.auth as { roles?: string[] } | undefined)?.roles ?? [])
            .filter((value): value is string => typeof value === 'string')
    );
});

const primaryRole = computed<string>(() => {
    const rolePriority = [
        'super_admin',
        'admin',
        'clinic_admin',
        'doctor',
        'receptionist',
        'accountant',
    ];

    const matchedRole = rolePriority.find((role) => roleNames.value.includes(role));

    return matchedRole ?? roleNames.value[0] ?? 'staff';
});

const roleProfiles: Record<
    string,
    {
        label: string;
        title: string;
        description: string;
    }
> = {
    super_admin: {
        label: 'مدير النظام',
        title: 'التحكم الكامل بالنظام',
        description: 'لديك وصول كامل لجميع وحدات النظام.',
    },
    admin: {
        label: 'مدير',
        title: 'إدارة العمليات',
        description: 'إدارة العمليات اليومية وسير العمل والفواتير والتقارير.',
    },
    clinic_admin: {
        label: 'مدير العيادة',
        title: 'إدارة العيادة',
        description: 'إدارة العمليات اليومية وسير العمل والفواتير والتقارير.',
    },
    receptionist: {
        label: 'موظف استقبال',
        title: 'مكتب الاستقبال',
        description: 'تسجيل الوصول وسجلات المرضى والمواعيد.',
    },
    doctor: {
        label: 'طبيب',
        title: 'الحالات المسندة',
        description: 'مراجعة قائمة الانتظار والزيارات المسندة وإكمال الملاحظات الطبية.',
    },
    accountant: {
        label: 'محاسب',
        title: 'الإدارة المالية',
        description: 'إدارة الفواتير والمدفوعات والتقارير المالية.',
    },
    staff: {
        label: 'موظف',
        title: 'مساحة العمل',
        description: 'لوحة التحكم مفلترة حسب صلاحياتك.',
    },
};

const activeRoleProfile = computed(() => roleProfiles[primaryRole.value] ?? roleProfiles.staff);

type ModuleCard = NavItem & {
    description: string;
    metric: string;
    permission?: string;
    anyPermissions?: string[];
};

const moduleCards: ModuleCard[] = [
    {
        title: 'المرضى',
        href: PatientController.index(),
        icon: Users,
        description: 'سجلات المرضى والبيانات الديموغرافية.',
        metric: 'السجلات',
        permission: 'patient.view',
    },
    {
        title: 'المواعيد',
        href: AppointmentController.index(),
        icon: CalendarClock,
        description: 'الجداول اليومية والحضور وعدم الحضور.',
        metric: 'الجدولة',
        permission: 'appointment.view',
    },
    {
        title: 'قائمة الانتظار',
        href: QueueEntryController.index(),
        icon: ListOrdered,
        description: 'سير الانتظار الفعلي والتريج واستدعاء التالي.',
        metric: 'مباشر',
        permission: 'queue.view',
    },
    {
        title: 'الزيارات',
        href: VisitController.index(),
        icon: Stethoscope,
        description: 'حالة الاستشارات والملاحظات الطبية والتقدم.',
        metric: 'سريري',
        anyPermissions: ['visit.start', 'visit.update', 'visit.complete'],
    },
    {
        title: 'الفواتير',
        href: InvoiceController.index(),
        icon: ReceiptText,
        description: 'الفواتير والمدفوعات وتتبع الحسابات غير المسددة.',
        metric: 'الإيرادات',
        permission: 'billing.view',
    },
    {
        title: 'التقارير',
        href: ReportController.index(),
        icon: BarChart3,
        description: 'رؤى تشغيلية ومالية مع بيانات جاهزة للتصدير.',
        metric: 'الرؤى',
        anyPermissions: ['reports.view', 'reports.financial'],
    },
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

const visibleModuleCards = computed<ModuleCard[]>(() =>
    moduleCards.filter((moduleCard) => canAccessModule(moduleCard)),
);

const quickActions = computed(() => [
    {
        label: 'موعد جديد',
        icon: Plus,
        href: AppointmentController.index(),
        enabled: can('appointment.create'),
    },
    {
        label: 'مريض جديد',
        icon: Users,
        href: PatientController.index(),
        enabled: can('patient.create'),
    },
    {
        label: 'فاتورة',
        icon: ReceiptText,
        href: InvoiceController.index(),
        enabled: can('billing.create'),
    },
    {
        label: 'تقرير',
        icon: FileText,
        href: ReportController.index(),
        enabled: can('reports.view') || can('reports.financial'),
    },
].filter(action => action.enabled));

const urgentAlerts = computed(() => {
    const alerts: Array<{ type: 'warning' | 'danger'; message: string; href?: string }> = [];

    if (chartStats?.long_waiting_patients && chartStats.long_waiting_patients.length > 0) {
        chartStats.long_waiting_patients.forEach(patient => {
            alerts.push({
                type: 'danger',
                message: `${patient.patient_name} ينتظر منذ ${patient.waiting_minutes} دقيقة`,
                href: QueueEntryController.index.url(),
            });
        });
    }

    if ((chartStats?.pending_invoices_today ?? 0) > 5) {
        alerts.push({
            type: 'warning',
            message: `${chartStats?.pending_invoices_today} فواتير معلقة اليوم`,
            href: InvoiceController.index.url(),
        });
    }

    return alerts;
});

const getStatusBadgeClass = (status: string) => {
    const statusMap: Record<string, string> = {
        scheduled: 'border-[var(--border-soft)] bg-[var(--accent-teal-soft)] text-[var(--accent-teal-strong)]',
        confirmed: 'border-[var(--border-soft)] bg-[var(--accent-mint-soft)] text-[var(--accent-mint-strong)]',
        completed: 'border-[var(--border-soft)] bg-[var(--accent-mint-soft)] text-[var(--accent-mint-strong)]',
        cancelled: 'border-[var(--border-soft)] bg-[var(--accent-coral-soft)] text-[var(--accent-coral-strong)]',
        no_show: 'border-[var(--border-soft)] bg-[var(--accent-coral-soft)] text-[var(--accent-coral-strong)]',
    };

    return statusMap[status] ?? 'border-[var(--border-soft)] bg-[var(--surface-secondary)] text-[var(--surface-contrast-soft)]';
};

const getStatusDotClass = (status: string): string => {
    if (status === 'confirmed' || status === 'completed') {
        return 'bg-[var(--accent-mint)]';
    }

    if (status === 'scheduled') {
        return 'bg-[var(--accent-teal)]';
    }

    if (status === 'cancelled') {
        return 'bg-[var(--accent-coral)]';
    }

    if (status === 'no_show') {
        return 'bg-[var(--accent-coral)]';
    }

    return 'bg-[var(--surface-contrast-soft)]';
};

const getStatusLabel = (status: string) => {
    const statusMap: Record<string, string> = {
        scheduled: 'مجدول',
        confirmed: 'مؤكد',
        completed: 'مكتمل',
        cancelled: 'ملغي',
        no_show: 'لم يحضر',
    };

    return statusMap[status] ?? status;
};

const totalRevenue7Days = computed(() =>
    Object.values(chartStats?.last_7_days_revenue ?? {}).reduce((a, b) => a + b, 0),
);

const revenueChartLabels = computed(() => Object.keys(chartStats?.last_7_days_revenue ?? {}));
const revenueChartDatasets = computed(() => [{
    label: 'الإيرادات',
    data: Object.values(chartStats?.last_7_days_revenue ?? {}),
    backgroundColor: 'var(--chart-1-bg)',
    borderColor: 'var(--chart-1)',
    fill: true,
}]);

const patientsChartLabels = computed(() => Object.keys(chartStats?.last_7_days_patients ?? {}));
const patientsChartDatasets = computed(() => [{
    label: 'مرضى جدد',
    data: Object.values(chartStats?.last_7_days_patients ?? {}),
    backgroundColor: 'var(--chart-2-bg)',
    borderColor: 'var(--chart-2)',
}]);

const appointmentsByStatusLabels = computed(() => Object.keys(chartStats?.appointments_by_status ?? {}));
const appointmentsByStatusDatasets = computed(() => [{
    label: 'المواعيد',
    data: Object.values(chartStats?.appointments_by_status ?? {}),
    backgroundColor: [
        'var(--chart-4-bg)',
        'var(--chart-1-bg)',
        'var(--chart-2-bg)',
        'var(--chart-5-bg)',
    ],
    borderColor: [
        'var(--chart-4)',
        'var(--chart-1)',
        'var(--chart-2)',
        'var(--chart-5)',
    ],
}]);

const visitsByMonthLabels = computed(() => Object.keys(chartStats?.visits_by_month ?? {}));
const visitsByMonthDatasets = computed(() => [{
    label: 'الزيارات',
    data: Object.values(chartStats?.visits_by_month ?? {}),
    backgroundColor: 'var(--chart-3-bg)',
    borderColor: 'var(--chart-3)',
}]);
</script>

<template>
    <Head title="لوحة التحكم" />

    <div
        class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6"
        dir="rtl"
    >
        <!-- رأس الصفحة -->
        <section class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="page-title">
                    مرحباً، {{ auth?.user?.name ?? 'مستخدم' }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ currentDate }}
                    <span class="mx-1">·</span>
                    <span class="inline-flex items-center rounded-full border border-border/60 bg-muted/40 px-2 py-0.5 text-[0.65rem] font-medium text-muted-foreground">
                        {{ activeRoleProfile.label }}
                    </span>
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    v-for="action in quickActions"
                    :key="action.label"
                    :href="action.href"
                    class="inline-flex items-center gap-2 rounded-lg border border-border/80 bg-card px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted min-h-[44px]"
                >
                    <component
                        :is="action.icon"
                        class="size-4 text-[var(--accent-mint)]"
                    />
                    {{ action.label }}
                </Link>
            </div>
        </section>

        <!-- ملخص اليوم -->
        <section class="rounded-xl border border-border/70 bg-card px-4 py-3">
            <div class="flex flex-wrap items-center gap-4 md:gap-6">
                <div class="flex items-center gap-2">
                    <CalendarClock class="size-4 text-muted-foreground" />
                    <span class="text-sm text-muted-foreground">اليوم</span>
                    <span class="text-lg font-bold tabular-nums text-foreground">{{ chartStats?.today_appointments ?? 0 }}</span>
                </div>
                <div class="hidden h-5 w-px bg-border/60 md:block" aria-hidden="true"></div>
                <div class="flex items-center gap-2">
                    <Users class="size-4 text-muted-foreground" />
                    <span class="text-sm text-muted-foreground">مرضى جدد اليوم</span>
                    <span class="text-lg font-bold tabular-nums text-[var(--accent-mint-strong)]">{{ chartStats?.today_new_patients ?? 0 }}</span>
                </div>
                <div class="hidden h-5 w-px bg-border/60 md:block" aria-hidden="true"></div>
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-[var(--accent-teal)]" aria-hidden="true"></span>
                    <span class="text-sm text-muted-foreground">في الانتظار</span>
                    <span class="text-lg font-bold tabular-nums text-[var(--accent-teal-strong)]">{{ chartStats?.pending_queue ?? 0 }}</span>
                </div>
                <div class="hidden h-5 w-px bg-border/60 md:block" aria-hidden="true"></div>
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-[var(--accent-mint)]" aria-hidden="true"></span>
                    <span class="text-sm text-muted-foreground">زيارة نشطة</span>
                    <span class="text-lg font-bold tabular-nums text-[var(--accent-mint-strong)]">{{ chartStats?.active_visits ?? 0 }}</span>
                </div>
                <div v-if="(chartStats?.pending_invoices_today ?? 0) > 0" class="hidden h-5 w-px bg-border/60 md:block" aria-hidden="true"></div>
                <div v-if="(chartStats?.pending_invoices_today ?? 0) > 0" class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-[var(--accent-coral)]" aria-hidden="true"></span>
                    <span class="text-sm text-muted-foreground">فاتورة معلقة</span>
                    <span class="text-lg font-bold tabular-nums text-[var(--accent-coral-strong)]">{{ chartStats?.pending_invoices_today }}</span>
                </div>
            </div>
        </section>

        <!-- تنبيهات عاجلة -->
        <section v-if="urgentAlerts.length > 0" class="space-y-2">
            <Link
                v-for="(alert, index) in urgentAlerts"
                :key="index"
                :href="alert.href ?? '#'"
                class="flex items-center justify-between rounded-xl border p-3 transition-colors hover:bg-muted/50 min-h-[44px]"
                :class="alert.type === 'danger'
                    ? 'border-[var(--accent-coral-soft)] bg-[var(--accent-coral-soft)]/30 text-[var(--accent-coral-strong)]'
                    : 'border-[var(--accent-coral-soft)] bg-[var(--accent-coral-soft)]/20 text-[var(--accent-coral-strong)]'"
            >
                <div class="flex items-center gap-2">
                    <AlertTriangle class="size-4" />
                    <p class="text-sm font-medium">
                        {{ alert.message }}
                    </p>
                </div>
                <ChevronLeft class="size-4 shrink-0 opacity-60" />
            </Link>
        </section>

        <!-- المحتوى الرئيسي -->
        <section class="grid gap-6 xl:grid-cols-[1.6fr_0.9fr]">
            <!-- العمود الأيمن -->
            <div class="flex flex-col gap-6">
                <!-- المواعيد القادمة -->
                <article class="rounded-xl border border-border/70 bg-card p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Clock class="size-4 text-muted-foreground" />
                            <h2 class="text-sm font-semibold tracking-tight">
                                المواعيد القادمة
                            </h2>
                        </div>
                        <Link
                            :href="AppointmentController.index()"
                            class="text-xs font-medium text-[var(--accent-teal-strong)] transition hover:underline"
                            v-if="can('appointment.view')"
                        >
                            عرض الكل
                        </Link>
                    </div>

                    <div v-if="chartStats?.upcoming_appointments && chartStats.upcoming_appointments.length > 0" class="space-y-2">
                        <div
                            v-for="apt in chartStats.upcoming_appointments.slice(0, 5)"
                            :key="apt.id"
                            class="flex items-center justify-between rounded-lg border border-border/50 bg-background/50 p-3"
                        >
                            <div class="flex items-center gap-3">
                                <div class="rounded-lg bg-muted px-3 py-2 text-sm font-bold tabular-nums">
                                    {{ apt.time }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium">
                                        {{ apt.patient_name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ apt.doctor_name }}
                                    </p>
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium"
                                :class="getStatusBadgeClass(apt.status)"
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :class="getStatusDotClass(apt.status)"
                                ></span>
                                {{ getStatusLabel(apt.status) }}
                            </span>
                        </div>
                    </div>
                    <div v-else class="py-8 text-center text-sm text-muted-foreground">
                        لا توجد مواعيد قادمة خلال الساعة القادمة
                    </div>
                </article>

                <!-- الرسم البياني للإيرادات -->
                <article v-if="chartStats?.last_7_days_revenue" class="rounded-xl border border-border/70 bg-card p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-semibold tracking-tight">
                            الإيرادات (آخر 7 أيام)
                        </h2>
                        <div class="flex items-center gap-1.5 text-[var(--accent-mint-strong)]">
                            <ArrowUpRight class="size-4" />
                            <span class="text-xs font-medium tabular-nums">
                                {{ totalRevenue7Days.toLocaleString('ar-SA') }}
                            </span>
                        </div>
                    </div>
                    <Chart
                        type="line"
                        :labels="revenueChartLabels"
                        :datasets="revenueChartDatasets"
                    />
                </article>
            </div>

            <!-- العمود الأيسر -->
            <div class="flex flex-col gap-6">
                <!-- حالة قائمة الانتظار -->
                <article class="rounded-xl border border-border/70 bg-card p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <ListOrdered class="size-4 text-muted-foreground" />
                            <h2 class="text-sm font-semibold tracking-tight">
                                حالة الانتظار
                            </h2>
                        </div>
                        <Link
                            :href="QueueEntryController.index()"
                            class="text-xs font-medium text-[var(--accent-teal-strong)] transition hover:underline"
                            v-if="can('queue.view')"
                        >
                            عرض القائمة
                        </Link>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between rounded-lg bg-muted/40 p-3">
                            <span class="text-sm text-muted-foreground">مرضى في الانتظار</span>
                            <span class="text-lg font-bold tabular-nums text-[var(--accent-coral-strong)]">
                                {{ chartStats?.pending_queue ?? 0 }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-muted/40 p-3">
                            <span class="text-sm text-muted-foreground">انتظار أكثر من 30 دقيقة</span>
                            <span class="text-lg font-bold tabular-nums text-[var(--accent-coral-strong)]">
                                {{ chartStats?.long_waiting_patients?.length ?? 0 }}
                            </span>
                        </div>
                    </div>
                </article>

                <!-- رسوم بيانية مصغرة -->
                <article v-if="chartStats?.last_7_days_patients" class="rounded-xl border border-border/70 bg-card p-5">
                    <h2 class="mb-4 text-sm font-semibold tracking-tight">
                        مرضى جدد (آخر 7 أيام)
                    </h2>
                    <Chart
                        type="bar"
                        :labels="patientsChartLabels"
                        :datasets="patientsChartDatasets"
                    />
                </article>
            </div>
        </section>

        <!-- الوصول السريع للوحدات -->
        <section>
            <h2 class="mb-3 text-sm font-semibold text-muted-foreground uppercase tracking-wider">
                الوحدات
            </h2>
            <div class="flex flex-wrap gap-3">
                <Link
                    v-for="module in visibleModuleCards"
                    :key="module.title"
                    :href="module.href"
                    class="group inline-flex items-center gap-3 rounded-xl border border-border/70 bg-card px-4 py-3 transition-colors hover:border-[var(--accent-mint-soft)] hover:bg-[var(--accent-mint-soft)]/20"
                >
                    <div
                        class="rounded-lg bg-muted p-2 text-muted-foreground transition-colors group-hover:text-[var(--accent-mint)]"
                    >
                        <component
                            :is="module.icon"
                            class="size-4"
                        />
                    </div>
                    <div>
                        <h3 class="text-sm font-medium">
                            {{ module.title }}
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            {{ module.description }}
                        </p>
                    </div>
                </Link>
            </div>
        </section>

        <!-- رسوم بيانية إضافية -->
        <section v-if="chartStats" class="grid gap-4 md:grid-cols-2">
            <article class="rounded-xl border border-border/70 bg-card p-5">
                <h2 class="mb-4 text-sm font-semibold tracking-tight">
                    المواعيد حسب الحالة (آخر 30 يوم)
                </h2>
                <Chart
                    type="bar"
                    :labels="appointmentsByStatusLabels"
                    :datasets="appointmentsByStatusDatasets"
                />
            </article>

            <article class="rounded-xl border border-border/70 bg-card p-5">
                <h2 class="mb-4 text-sm font-semibold tracking-tight">
                    الزيارات (آخر 6 أشهر)
                </h2>
                <Chart
                    type="line"
                    :labels="visitsByMonthLabels"
                    :datasets="visitsByMonthDatasets"
                />
            </article>
        </section>
    </div>
</template>
