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
    CalendarDays,
    TrendingUp,
    Activity,
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
    total_patients: number;
    today_new_patients: number;
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
        queue_number: number;
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
            { title: 'لوحة التحكم', href: dashboard() },
        ],
    },
});

const { can } = usePermissions();
const page = usePage();

const auth = computed(() => page.props.auth as { user?: { name: string } } | undefined);

const currentDate = computed(() => {
    const now = new Date();

    return now.toLocaleDateString('ar-SA', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    });
});

type ModuleCard = NavItem & {
    description: string;
    metric: string;
    permission?: string;
    anyPermissions?: string[];
};

const moduleCards: ModuleCard[] = [
    { title: 'المرضى', href: PatientController.index(), icon: Users, description: 'سجلات المرضى والبيانات الديموغرافية.', metric: 'السجلات', permission: 'patient.view' },
    { title: 'المواعيد', href: AppointmentController.index(), icon: CalendarClock, description: 'الجداول اليومية والحضور وعدم الحضور.', metric: 'الجدولة', permission: 'appointment.view' },
    { title: 'قائمة الانتظار', href: QueueEntryController.index(), icon: ListOrdered, description: 'سير الانتظار الفعلي والتريج واستدعاء التالي.', metric: 'مباشر', permission: 'queue.view' },
    { title: 'الزيارات', href: VisitController.index(), icon: Stethoscope, description: 'حالة الاستشارات والملاحظات الطبية والتقدم.', metric: 'سريري', anyPermissions: ['visit.start', 'visit.update', 'visit.complete'] },
    { title: 'الفواتير', href: InvoiceController.index(), icon: ReceiptText, description: 'الفواتير والمدفوعات وتتبع الحسابات غير المسددة.', metric: 'الإيرادات', permission: 'billing.view' },
    { title: 'التقارير', href: ReportController.index(), icon: BarChart3, description: 'رؤى تشغيلية ومالية مع بيانات جاهزة للتصدير.', metric: 'الرؤى', anyPermissions: ['reports.view', 'reports.financial'] },
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
    { label: 'مريض جديد', icon: Users, href: PatientController.index(), enabled: can('patient.create'), primary: false },
    { label: 'فاتورة', icon: ReceiptText, href: InvoiceController.index(), enabled: can('billing.create'), primary: false },
    { label: 'تقرير', icon: FileText, href: ReportController.index(), enabled: can('reports.view') || can('reports.financial'), primary: false },
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
        scheduled: 'bg-[#EFF6FF] text-[#1D4ED8]',
        confirmed: 'bg-[#EAF7FE] text-[#075985]',
        completed: 'bg-[#EAF7FE] text-[#075985]',
        cancelled: 'bg-[#FEF3C7] text-[#B45309]',
        no_show: 'bg-[#FEF3C7] text-[#B45309]',
    };

    return statusMap[status] ?? 'bg-[#F4F9FD] text-[#6C7F95]';
};

const getStatusDotClass = (status: string): string => {
    if (status === 'confirmed' || status === 'completed') {
return 'bg-[#0EA5E9]';
}

    if (status === 'scheduled') {
return 'bg-[#3B82F6]';
}

    if (status === 'cancelled' || status === 'no_show') {
return 'bg-[#F59E0B]';
}

    return 'bg-slate-400';
};

const getStatusLabel = (status: string) => {
    const statusMap: Record<string, string> = {
        scheduled: 'مجدول', confirmed: 'مؤكد', completed: 'مكتمل', cancelled: 'ملغي', no_show: 'لم يحضر',
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
    backgroundColor: 'rgba(14, 165, 233, 0.08)',
    borderColor: '#0EA5E9',
    fill: true,
    borderWidth: 2,
    tension: 0.4,
    pointRadius: 2,
    pointBackgroundColor: '#0EA5E9',
    pointBorderColor: '#FFFFFF',
    pointBorderWidth: 2,
}]);

const patientsChartLabels = computed(() => Object.keys(chartStats?.last_7_days_patients ?? {}));
const patientsChartDatasets = computed(() => [{
    label: 'مرضى جدد',
    data: Object.values(chartStats?.last_7_days_patients ?? {}),
    backgroundColor: '#0EA5E9',
    borderColor: '#0EA5E9',
    borderWidth: 0,
    borderRadius: 4,
}]);

const appointmentsByStatusLabels = computed(() => Object.keys(chartStats?.appointments_by_status ?? {}));
const appointmentsByStatusDatasets = computed(() => [{
    label: 'المواعيد',
    data: Object.values(chartStats?.appointments_by_status ?? {}),
    backgroundColor: ['#0EA5E9', '#8B5CF6', '#F59E0B', '#EF4444'],
    borderWidth: 0,
    borderRadius: 4,
}]);

const visitsByMonthLabels = computed(() => Object.keys(chartStats?.visits_by_month ?? {}));
const visitsByMonthDatasets = computed(() => [{
    label: 'الزيارات',
    data: Object.values(chartStats?.visits_by_month ?? {}),
    backgroundColor: 'rgba(14, 165, 233, 0.08)',
    borderColor: '#0EA5E9',
    fill: true,
    borderWidth: 2,
    tension: 0.4,
    pointRadius: 2,
    pointBackgroundColor: '#0EA5E9',
    pointBorderColor: '#FFFFFF',
    pointBorderWidth: 2,
}]);
</script>

<template>
    <Head title="لوحة التحكم" />

    <div class="container-modern space-y-8 py-6" dir="rtl">
        <!-- Welcome header -->
        <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-normal text-[#111827] md:text-[2.2rem]">
                    مرحباً، {{ auth?.user?.name ?? 'مستخدم' }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ currentDate }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    v-for="action in quickActions"
                    :key="action.label"
                    :href="action.href"
                    class="inline-flex items-center gap-1.5 rounded-2xl px-3.5 py-2 text-sm font-medium transition-all duration-200"
                    :class="action.primary
                        ? 'bg-[#0EA5E9] text-white shadow-[0_10px_24px_-16px_rgb(14_165_233_/_0.75)] hover:bg-[#0284C7]'
                        : 'border border-[#DDE9F3] bg-white text-[#47677F] hover:bg-[#F7FBFE] hover:text-[#075985]'"
                >
                    <component :is="action.icon" class="size-4" />
                    {{ action.label }}
                </Link>
            </div>
        </section>

        <!-- Alerts -->
        <section v-if="urgentAlerts.length > 0" class="space-y-2">
            <Link
                v-for="(alert, index) in urgentAlerts"
                :key="index"
                :href="alert.href ?? '#'"
                class="flex items-center justify-between rounded-xl border p-3.5 transition-all duration-200 hover:shadow-sm"
                :class="alert.type === 'danger'
                    ? 'border-red-200/80 bg-red-50/50 text-red-700'
                    : 'border-amber-200/80 bg-amber-50/50 text-amber-700'"
            >
                <div class="flex items-center gap-2.5">
                    <AlertTriangle class="size-4" />
                    <p class="text-sm font-medium">{{ alert.message }}</p>
                </div>
                <ChevronLeft class="size-4 shrink-0 opacity-60" />
            </Link>
        </section>

        <!-- Stats cards -->
        <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <!-- Today's Appointments -->
            <div class="card-float card-hover">
                <div class="flex items-center gap-3">
                    <div class="icon-container bg-[#EAF7FE] text-[#0EA5E9]">
                        <CalendarClock class="size-5" />
                    </div>
                    <div>
                        <p class="metric-label">مواعيد اليوم</p>
                        <p class="metric-value mt-0.5">{{ chartStats?.today_appointments ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <!-- New Patients -->
            <div class="card-float card-hover">
                <div class="flex items-center gap-3">
                    <div class="icon-container bg-[#EFF6FF] text-[#3B82F6]">
                        <Users class="size-5" />
                    </div>
                    <div>
                        <p class="metric-label">مرضى جدد</p>
                        <p class="metric-value mt-0.5">{{ chartStats?.today_new_patients ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <!-- Queue -->
            <div class="card-float card-hover">
                <div class="flex items-center gap-3">
                    <div class="icon-container bg-[#FEF3C7] text-[#F59E0B]">
                        <ListOrdered class="size-5" />
                    </div>
                    <div>
                        <p class="metric-label">في الانتظار</p>
                        <p class="metric-value mt-0.5">{{ chartStats?.pending_queue ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <!-- Active Visits -->
            <div class="card-float card-hover">
                <div class="flex items-center gap-3">
                    <div class="icon-container bg-[#F0FDF4] text-[#22C55E]">
                        <Stethoscope class="size-5" />
                    </div>
                    <div>
                        <p class="metric-label">زيارات نشطة</p>
                        <p class="metric-value mt-0.5">{{ chartStats?.active_visits ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main content grid -->
        <section class="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
            <!-- Right column -->
            <div class="flex flex-col gap-6">
                <!-- Upcoming appointments -->
                <article class="card-float">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Clock class="size-4 text-slate-400" />
                            <h2 class="text-sm font-semibold text-slate-900">المواعيد القادمة</h2>
                        </div>
                        <Link
                            :href="AppointmentController.index()"
                            class="text-xs font-medium text-[#0284C7] transition hover:underline"
                            v-if="can('appointment.view')"
                        >
                            عرض الكل
                        </Link>
                    </div>

                    <div v-if="chartStats?.upcoming_appointments && chartStats.upcoming_appointments.length > 0" class="space-y-2">
                        <div
                            v-for="apt in chartStats.upcoming_appointments.slice(0, 5)"
                            :key="apt.id"
                            class="flex items-center justify-between rounded-xl border border-[#E2ECF6] bg-[#F7FAFD] p-3 transition-colors hover:bg-[#EAF7FE]"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl border border-[#E2ECF6] bg-white text-xs font-semibold tabular-nums text-[#47677F]">
                                    {{ apt.time }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-900">{{ apt.patient_name }}</p>
                                    <p class="text-xs text-slate-500">{{ apt.doctor_name }}</p>
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="getStatusBadgeClass(apt.status)"
                            >
                                <span class="size-1.5 rounded-full" :class="getStatusDotClass(apt.status)"></span>
                                {{ getStatusLabel(apt.status) }}
                            </span>
                        </div>
                    </div>
                    <div v-else class="py-10 text-center">
                        <CalendarClock class="mx-auto size-10 text-slate-200 mb-3" />
                        <p class="text-sm text-slate-400">لا توجد مواعيد قادمة خلال الساعة القادمة</p>
                    </div>
                </article>

                <!-- Revenue chart -->
                <article v-if="chartStats?.last_7_days_revenue" class="card-float">
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-900">الإيرادات (آخر 7 أيام)</h2>
                        <div class="flex items-center gap-1.5 text-[#0284C7]">
                            <TrendingUp class="size-4" />
                            <span class="text-xs font-semibold tabular-nums">{{ totalRevenue7Days.toLocaleString('ar-SA') }}</span>
                        </div>
                    </div>
                    <Chart type="line" :labels="revenueChartLabels" :datasets="revenueChartDatasets" />
                </article>
            </div>

            <!-- Left column -->
            <div class="flex flex-col gap-6">
                <!-- Queue status -->
                <article class="card-float">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <ListOrdered class="size-4 text-slate-400" />
                            <h2 class="text-sm font-semibold text-slate-900">حالة الانتظار</h2>
                        </div>
                        <Link
                            :href="QueueEntryController.index()"
                            class="text-xs font-medium text-[#0284C7] transition hover:underline"
                            v-if="can('queue.view')"
                        >
                            عرض القائمة
                        </Link>
                    </div>
                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between rounded-xl bg-[#F7FAFD] p-3.5">
                            <span class="text-sm text-slate-600">مرضى في الانتظار</span>
                            <span class="text-xl font-bold text-slate-900 tabular-nums">{{ chartStats?.pending_queue ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-xl bg-[#F7FAFD] p-3.5">
                            <span class="text-sm text-slate-600">انتظار أكثر من 30 دقيقة</span>
                            <span class="text-xl font-bold text-slate-900 tabular-nums">{{ chartStats?.long_waiting_patients?.length ?? 0 }}</span>
                        </div>
                    </div>
                </article>

                <!-- Patients chart -->
                <article v-if="chartStats?.last_7_days_patients" class="card-float">
                    <h2 class="mb-5 text-sm font-semibold text-slate-900">مرضى جدد (آخر 7 أيام)</h2>
                    <Chart type="bar" :labels="patientsChartLabels" :datasets="patientsChartDatasets" />
                </article>
            </div>
        </section>

        <!-- Modules -->
        <section>
            <h2 class="section-label mb-3">الوحدات</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="module in visibleModuleCards"
                    :key="module.title"
                    :href="module.href"
                    class="group flex items-start gap-3.5 rounded-2xl border border-[#E2ECF6] bg-white p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-[#BFE3F5] hover:shadow-card-hover"
                >
                    <div class="icon-container-sm shrink-0 bg-[#EAF7FE] text-[#0EA5E9] transition-all duration-200 group-hover:bg-[#0EA5E9] group-hover:text-white">
                        <component :is="module.icon" class="size-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">{{ module.title }}</h3>
                        <p class="mt-0.5 text-xs text-slate-500 leading-relaxed">{{ module.description }}</p>
                    </div>
                </Link>
            </div>
        </section>

        <!-- Bottom charts -->
        <section v-if="chartStats" class="grid gap-4 md:grid-cols-2">
            <article class="card-float">
                <h2 class="mb-5 text-sm font-semibold text-slate-900">المواعيد حسب الحالة (آخر 30 يوم)</h2>
                <Chart type="bar" :labels="appointmentsByStatusLabels" :datasets="appointmentsByStatusDatasets" />
            </article>
            <article class="card-float">
                <h2 class="mb-5 text-sm font-semibold text-slate-900">الزيارات (آخر 6 أشهر)</h2>
                <Chart type="line" :labels="visitsByMonthLabels" :datasets="visitsByMonthDatasets" />
            </article>
        </section>
    </div>
</template>
