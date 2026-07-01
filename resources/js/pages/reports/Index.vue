<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    Banknote,
    BarChart3,
    CalendarDays,
    CheckCircle2,
    Clock,
    Download,
    FileSpreadsheet,
    FileText,
    Filter,
    Pill,
    Printer,
    Receipt,
    Stethoscope,
    TrendingDown,
    TrendingUp,
    Users,
    Wallet,
    Wifi,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AuditReportController from '@/actions/App/Http/Controllers/Reports/AuditReportController';
import ReportController from '@/actions/App/Http/Controllers/Reports/ReportController';
import Chart from '@/components/Chart.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useMoneyFormatter } from '@/lib/money';

type Option = { id: number; name: string };
type TableRow = Record<string, string | number | null>;
type ChartDataset = {
    labels: string[];
    datasets: {
        label: string;
        data: number[];
        backgroundColor?: string | string[];
        borderColor?: string | string[];
        borderWidth?: number;
    }[];
};
type ChartData = {
    daily_income?: { date: string; amount: number }[];
    income_by_clinic?: { clinic_name: string; amount: number }[];
    income_by_doctor?: { doctor_name: string; amount: number }[];
    payment_status?: { status: string; count: number }[];
    appointments_by_day?: { date: string; count: number }[];
    appointments_by_status?: { status: string; count: number }[];
    expenses_by_category?: { category_name: string; amount: number }[];
    monthly_profit?: {
        month: string;
        income: number;
        expenses?: number;
        payroll?: number;
        outflow?: number;
        profit: number;
    }[];
};
type ReportData = {
    overview?: Record<string, string | number>;
    financial?: Record<string, number>;
    payroll?: Record<string, unknown> & { rows?: TableRow[] };
    clinics?: { rows?: TableRow[]; active_count?: number };
    doctors?: { rows?: TableRow[]; active_count?: number };
    appointments?: { rows?: TableRow[] };
    patients?: { rows?: TableRow[] };
    expenses?: { rows?: TableRow[]; total?: number };
    pharmacy?: Record<string, string | number | boolean>;
};

const props = defineProps<{
    filters: {
        from: string | null;
        to: string | null;
        month: string | null;
        clinic_id: number | null;
        doctor_id: number | null;
        report_type: string | null;
    };
    can_view_operational: boolean;
    can_view_financial: boolean;
    report_data?: ReportData;
    chart_data?: ChartData;
    clinics: Option[];
    doctors: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'التقارير',
                href: ReportController.index(),
            },
        ],
    },
});

const { formatMoney } = useMoneyFormatter();

const month = ref(props.filters.month ?? new Date().toISOString().slice(0, 7));
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
const clinicId = ref(
    props.filters.clinic_id !== null ? String(props.filters.clinic_id) : '',
);
const doctorId = ref(
    props.filters.doctor_id !== null ? String(props.filters.doctor_id) : '',
);
const activeTab = ref(props.filters.report_type ?? 'overview');

const reportData = computed<ReportData>(() => props.report_data ?? {});
const chartData = computed<ChartData>(() => props.chart_data ?? {});
const overview = computed(() => reportData.value.overview ?? {});
const financial = computed(() => reportData.value.financial ?? {});

const query = computed(() => ({
    month: month.value || undefined,
    from: from.value || undefined,
    to: to.value || undefined,
    clinic_id: clinicId.value || undefined,
    doctor_id: doctorId.value || undefined,
    report_type: activeTab.value || undefined,
}));

const reload = (): void => {
    router.get(ReportController.index.url(), query.value, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        only: ['filters', 'report_data', 'chart_data', 'doctors'],
    });
};

watch([month, from, to, clinicId, doctorId, activeTab], reload);

const labels: Record<string, string> = {
    appointment_id: '#',
    appointments_count: 'عدد المواعيد',
    appointments_total: 'إجمالي المواعيد',
    category_name: 'التصنيف',
    clinic_name: 'العيادة',
    completed: 'مكتمل',
    completed_count: 'مكتملة',
    cost: 'التكلفة',
    date: 'التاريخ',
    doctor: 'طبيب',
    doctor_name: 'الطبيب',
    due_amount: 'المستحق',
    employee: 'موظف',
    expense_id: '#',
    file_number: 'رقم الملف',
    fixed_monthly: 'شهري ثابت',
    fixed_weekly: 'أسبوعي ثابت',
    free: 'مجاني',
    paid: 'مدفوع',
    paid_amount: 'المدفوع',
    partially_paid: 'مدفوع جزئيا',
    patient_name: 'المريض',
    payment_status: 'حالة الدفع',
    payment_type: 'نوع الأجر',
    percentage: 'نسبة',
    remaining_amount: 'المتبقي',
    revenue_amount: 'الإيراد',
    status: 'الحالة',
    unpaid: 'غير مدفوع',
};

const tabs = [
    { key: 'overview', title: 'التقرير الشامل', subtitle: 'نظرة شاملة على أداء العيادة', icon: Activity },
    { key: 'financial', title: 'المالية', subtitle: 'الدخل والمصاريف والسيولة', icon: Wallet },
    { key: 'patients', title: 'المرضى', subtitle: 'المرضى الجدد وحركة الزيارات', icon: Users },
    { key: 'appointments', title: 'المواعيد', subtitle: 'الحجوزات وحالاتها', icon: CalendarDays },
    { key: 'doctors', title: 'الأطباء', subtitle: 'تقارير الأطباء والإيرادات', icon: Stethoscope },
    { key: 'payroll', title: 'الرواتب', subtitle: 'مستحقات الأطباء والموظفين', icon: Banknote },
    { key: 'expenses', title: 'المصاريف', subtitle: 'تصنيف المصاريف وسجلها', icon: Receipt },
    { key: 'clinics', title: 'العيادات', subtitle: 'أداء العيادات والمؤشرات العامة', icon: BarChart3 },
    { key: 'pharmacy', title: 'المخزون', subtitle: 'الصيدلية وتنبيهات المخزون', icon: Pill },
];

const activeTabMeta = computed(() => {
    return tabs.find((tab) => tab.key === activeTab.value) ?? tabs[0];
});

const labelFor = (value: unknown): string => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const key = String(value);

    return labels[key] ?? key.replace(/_/g, ' ');
};

const moneyValue = (value: unknown): string => formatMoney(Number(value ?? 0));

const colors = [
    'rgba(14, 165, 233, 0.62)',
    'rgba(16, 185, 129, 0.58)',
    'rgba(245, 158, 11, 0.58)',
    'rgba(239, 68, 68, 0.56)',
    'rgba(139, 92, 246, 0.56)',
    'rgba(20, 184, 166, 0.56)',
];

const solidColors = colors.map((color) =>
    color.replace('0.56', '1').replace('0.58', '1').replace('0.62', '1'),
);

const buildChart = (
    rows: unknown[] | undefined,
    labelKey: string,
    valueKey: string,
    title: string,
): ChartDataset => {
    const items = (rows ?? []) as Record<string, string | number>[];

    return {
        labels: items.map((item) => labelFor(item[labelKey])),
        datasets: [
            {
                label: title,
                data: items.map((item) => Number(item[valueKey] ?? 0)),
                backgroundColor: colors,
                borderColor: solidColors,
                borderWidth: 1,
            },
        ],
    };
};

const dailyIncomeChart = computed(() =>
    buildChart(chartData.value.daily_income, 'date', 'amount', 'الدخل اليومي'),
);
const clinicIncomeChart = computed(() =>
    buildChart(chartData.value.income_by_clinic, 'clinic_name', 'amount', 'الدخل حسب العيادة'),
);
const doctorIncomeChart = computed(() =>
    buildChart(chartData.value.income_by_doctor, 'doctor_name', 'amount', 'الدخل حسب الطبيب'),
);
const paymentStatusChart = computed(() =>
    buildChart(chartData.value.payment_status, 'status', 'count', 'حالات الدفع'),
);
const appointmentsDayChart = computed(() =>
    buildChart(chartData.value.appointments_by_day, 'date', 'count', 'المواعيد اليومية'),
);
const appointmentsStatusChart = computed(() =>
    buildChart(chartData.value.appointments_by_status, 'status', 'count', 'حالات المواعيد'),
);
const expenseCategoryChart = computed(() =>
    buildChart(chartData.value.expenses_by_category, 'category_name', 'amount', 'المصاريف حسب التصنيف'),
);
const monthlyProfitChart = computed(() => {
    const rows = chartData.value.monthly_profit ?? [];

    return {
        labels: rows.map((row) => row.month),
        datasets: [
            {
                label: 'الدخل',
                data: rows.map((row) => row.income),
                backgroundColor: 'rgba(16, 185, 129, 0.55)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 1,
            },
            {
                label: 'المصاريف',
                data: rows.map((row) => row.outflow ?? Number(row.expenses ?? 0) + Number(row.payroll ?? 0)),
                backgroundColor: 'rgba(239, 68, 68, 0.52)',
                borderColor: 'rgba(239, 68, 68, 1)',
                borderWidth: 1,
            },
            {
                label: 'الصافي',
                data: rows.map((row) => row.profit),
                backgroundColor: 'rgba(14, 165, 233, 0.56)',
                borderColor: 'rgba(14, 165, 233, 1)',
                borderWidth: 1,
            },
        ],
    };
});

const currentRows = computed<TableRow[]>(() => {
    if (activeTab.value === 'payroll') {
        return reportData.value.payroll?.rows ?? [];
    }

    if (activeTab.value === 'clinics') {
        return reportData.value.clinics?.rows ?? [];
    }

    if (activeTab.value === 'doctors') {
        return reportData.value.doctors?.rows ?? [];
    }

    if (activeTab.value === 'appointments') {
        return reportData.value.appointments?.rows ?? [];
    }

    if (activeTab.value === 'patients') {
        return reportData.value.patients?.rows ?? [];
    }

    if (activeTab.value === 'expenses') {
        return reportData.value.expenses?.rows ?? [];
    }

    return [];
});

const tableColumnsByTab: Record<string, string[]> = {
    payroll: ['type', 'name', 'role', 'period', 'due_amount', 'paid_amount', 'remaining_amount', 'status'],
    clinics: ['clinic_name', 'appointments_count', 'patients_count', 'doctors_count', 'income_amount'],
    doctors: ['doctor_name', 'clinic_name', 'payment_type', 'appointments_count', 'completed_count', 'revenue_amount', 'due_amount', 'paid_amount', 'remaining_amount'],
    appointments: ['date', 'patient_name', 'file_number', 'clinic_name', 'doctor_name', 'appointment_type', 'status', 'cost', 'paid_amount', 'remaining_amount', 'payment_status'],
    patients: ['patient_name', 'file_number', 'phone', 'created_at', 'appointments_count'],
    expenses: ['date', 'description', 'category_name', 'clinic_name', 'status', 'payment_method', 'amount'],
};

const tableColumns = computed(() => {
    const first = currentRows.value[0];

    return tableColumnsByTab[activeTab.value] ?? (first ? Object.keys(first) : []);
});

const isMoneyColumn = (column: string): boolean => {
    return column.includes('amount') || ['cost', 'paid_amount', 'remaining_amount'].includes(column);
};

const exportExcelUrl = computed(() => ReportController.exportExcel.url({ query: query.value }));
const exportPdfUrl = computed(() => ReportController.exportPdf.url({ query: query.value }));
const auditExportUrl = computed(() => AuditReportController.export.url({ query: query.value }));
</script>

<template>
    <Head title="التقارير والتحليلات" />

    <div class="mx-auto w-full max-w-[1680px] space-y-6 p-4 md:p-6" dir="rtl">
        <!-- Header -->
        <section class="space-y-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="space-y-2 text-right">
                    <div class="inline-flex items-center gap-2 rounded-full bg-emerald-500/12 px-3 py-1 text-xs font-bold text-emerald-700">
                        <Wifi class="size-3.5" />
                        متصل
                    </div>
                    <h1 class="text-3xl font-extrabold tracking-tight text-foreground">
                        التقارير والتحليلات
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        تقارير شاملة ومفصلة لجميع جوانب العيادة - تحديث تلقائي في الوقت الفعلي
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a :href="exportExcelUrl" class="inline-flex h-10 items-center gap-2 rounded-xl border border-border bg-background px-4 text-sm font-semibold shadow-sm hover:bg-muted">
                        <FileSpreadsheet class="size-4" />
                        Excel
                    </a>
                    <a :href="exportPdfUrl" class="inline-flex h-10 items-center gap-2 rounded-xl border border-border bg-background px-4 text-sm font-semibold shadow-sm hover:bg-muted">
                        <Printer class="size-4" />
                        PDF
                    </a>
                    <a :href="auditExportUrl" class="inline-flex h-10 items-center gap-2 rounded-xl border border-border bg-background px-4 text-sm font-semibold shadow-sm hover:bg-muted">
                        <Download class="size-4" />
                        تصدير
                    </a>
                </div>
            </div>

            <!-- Tabs -->
            <div class="overflow-x-auto rounded-2xl bg-muted/60 p-1.5 [scrollbar-width:none]">
                <div class="flex min-w-max items-center gap-1">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="inline-flex h-10 min-w-32 items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold transition-all"
                        :class="activeTab === tab.key
                            ? 'bg-background text-foreground shadow-sm ring-1 ring-border'
                            : 'text-muted-foreground hover:bg-background/60 hover:text-foreground'"
                        @click="activeTab = tab.key"
                    >
                        <component :is="tab.icon" class="size-4" />
                        {{ tab.title }}
                    </button>
                </div>
            </div>
        </section>

        <!-- Page Title -->
        <section class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div class="space-y-1">
                <h2 class="text-2xl font-bold text-foreground">
                    {{ activeTabMeta.title }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ activeTabMeta.subtitle }}
                </p>
            </div>
            <Link :href="AuditReportController.index()" class="inline-flex h-10 w-fit items-center gap-2 rounded-xl border border-border bg-background px-4 text-sm font-semibold shadow-sm hover:bg-muted">
                <FileText class="size-4" />
                سجل التدقيق
            </Link>
        </section>

        <!-- Filters -->
        <section class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
            <div class="mb-4 flex items-center gap-2">
                <Filter class="size-5 text-primary" />
                <div>
                    <h3 class="text-base font-bold text-foreground">فلترة التقرير</h3>
                    <p class="text-xs text-muted-foreground">اختر الفترة والعيادة والطبيب لعرض البيانات</p>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-5">
                <div class="grid gap-1.5">
                    <Label>الشهر</Label>
                    <Input v-model="month" type="month" class="pattern-field-clay" />
                </div>
                <div class="grid gap-1.5">
                    <Label>من تاريخ</Label>
                    <Input v-model="from" type="date" class="pattern-field-clay" />
                </div>
                <div class="grid gap-1.5">
                    <Label>إلى تاريخ</Label>
                    <Input v-model="to" type="date" class="pattern-field-clay" />
                </div>
                <div class="grid gap-1.5">
                    <Label>العيادة</Label>
                    <select v-model="clinicId" class="pattern-field-clay h-10">
                        <option value="">العيادة الحالية</option>
                        <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">{{ clinic.name }}</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>الطبيب</Label>
                    <select v-model="doctorId" class="pattern-field-clay h-10">
                        <option value="">كل الأطباء</option>
                        <option v-for="doctor in doctors" :key="doctor.id" :value="doctor.id">{{ doctor.name }}</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- Overview Tab -->
        <section v-if="activeTab === 'overview'" class="space-y-6">
            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm hover-lift">
                    <div class="flex items-start justify-between">
                        <div class="space-y-2">
                            <p class="text-xs font-semibold text-muted-foreground">إجمالي الدخل</p>
                            <p class="text-2xl font-bold text-emerald-600">{{ moneyValue(overview.total_income) }}</p>
                        </div>
                        <div class="flex size-12 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-600">
                            <TrendingUp class="size-5" />
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm hover-lift">
                    <div class="flex items-start justify-between">
                        <div class="space-y-2">
                            <p class="text-xs font-semibold text-muted-foreground">المقبوض</p>
                            <p class="text-2xl font-bold text-sky-600">{{ moneyValue(overview.total_collected) }}</p>
                        </div>
                        <div class="flex size-12 items-center justify-center rounded-2xl bg-sky-500/15 text-sky-600">
                            <Wallet class="size-5" />
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm hover-lift">
                    <div class="flex items-start justify-between">
                        <div class="space-y-2">
                            <p class="text-xs font-semibold text-muted-foreground">إجمالي المواعيد</p>
                            <p class="text-2xl font-bold text-foreground">{{ overview.appointments_total ?? 0 }}</p>
                        </div>
                        <div class="flex size-12 items-center justify-center rounded-2xl bg-violet-500/15 text-violet-600">
                            <CalendarDays class="size-5" />
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm hover-lift">
                    <div class="flex items-start justify-between">
                        <div class="space-y-2">
                            <p class="text-xs font-semibold text-muted-foreground">صافي الربح</p>
                            <p class="text-2xl font-bold" :class="Number(overview.net_profit ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600'">
                                {{ moneyValue(overview.net_profit) }}
                            </p>
                        </div>
                        <div class="flex size-12 items-center justify-center rounded-2xl" :class="Number(overview.net_profit ?? 0) >= 0 ? 'bg-emerald-500/15 text-emerald-600' : 'bg-red-500/15 text-red-600'">
                            <component :is="Number(overview.net_profit ?? 0) >= 0 ? TrendingUp : TrendingDown" class="size-5" />
                        </div>
                    </div>
                </article>
            </div>

            <!-- Additional Stats -->
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div class="space-y-2">
                            <p class="text-xs font-semibold text-muted-foreground">المرضى الجدد</p>
                            <p class="text-2xl font-bold text-foreground">{{ overview.new_patients ?? 0 }}</p>
                        </div>
                        <div class="flex size-12 items-center justify-center rounded-2xl bg-amber-500/15 text-amber-600">
                            <Users class="size-5" />
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div class="space-y-2">
                            <p class="text-xs font-semibold text-muted-foreground">مواعيد اليوم</p>
                            <p class="text-2xl font-bold text-foreground">{{ overview.today_appointments ?? 0 }}</p>
                        </div>
                        <div class="flex size-12 items-center justify-center rounded-2xl bg-blue-500/15 text-blue-600">
                            <Clock class="size-5" />
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div class="space-y-2">
                            <p class="text-xs font-semibold text-muted-foreground">إجمالي المصاريف</p>
                            <p class="text-2xl font-bold text-red-600">{{ moneyValue(overview.total_expenses) }}</p>
                        </div>
                        <div class="flex size-12 items-center justify-center rounded-2xl bg-red-500/15 text-red-600">
                            <Receipt class="size-5" />
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div class="space-y-2">
                            <p class="text-xs font-semibold text-muted-foreground">المتبقي</p>
                            <p class="text-2xl font-bold text-amber-600">{{ moneyValue(overview.total_remaining) }}</p>
                        </div>
                        <div class="flex size-12 items-center justify-center rounded-2xl bg-amber-500/15 text-amber-600">
                            <AlertCircle class="size-5" />
                        </div>
                    </div>
                </article>
            </div>

            <!-- Charts -->
            <div class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <Chart type="line" v-bind="dailyIncomeChart" title="الدخل اليومي" />
                </div>
                <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <Chart v-bind="monthlyProfitChart" title="الدخل والمصاريف وصافي الربح" />
                </div>
                <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <Chart type="pie" v-bind="appointmentsStatusChart" title="حالات المواعيد" />
                </div>
                <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <Chart type="pie" v-bind="paymentStatusChart" title="حالات الدفع" />
                </div>
            </div>
        </section>

        <!-- Financial Tab -->
        <section v-else-if="activeTab === 'financial'" class="space-y-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div v-for="(value, key) in financial" :key="key" class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm hover-lift">
                    <p class="text-xs font-semibold text-muted-foreground">{{ labelFor(key) }}</p>
                    <p class="mt-2 text-2xl font-bold text-foreground">{{ moneyValue(value) }}</p>
                </div>
            </div>
            <div class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <Chart type="bar" v-bind="dailyIncomeChart" title="الدخل اليومي" />
                </div>
                <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <Chart type="bar" v-bind="clinicIncomeChart" title="الدخل حسب العيادة" />
                </div>
                <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <Chart type="bar" v-bind="doctorIncomeChart" title="الدخل حسب الطبيب" />
                </div>
                <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <Chart type="bar" v-bind="expenseCategoryChart" title="المصاريف حسب التصنيف" />
                </div>
                <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm xl:col-span-2">
                    <Chart type="line" v-bind="monthlyProfitChart" title="صافي الربح شهريا (آخر 6 أشهر)" />
                </div>
            </div>
        </section>

        <!-- Pharmacy Tab -->
        <section v-else-if="activeTab === 'pharmacy'" class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm hover-lift">
                <div class="flex items-start justify-between">
                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-muted-foreground">الأدوية</p>
                        <p class="text-3xl font-bold text-foreground">{{ reportData.pharmacy?.drugs_count ?? 0 }}</p>
                    </div>
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-blue-500/15 text-blue-600">
                        <Pill class="size-5" />
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm hover-lift">
                <div class="flex items-start justify-between">
                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-muted-foreground">عمليات الصرف</p>
                        <p class="text-3xl font-bold text-foreground">{{ reportData.pharmacy?.dispenses_count ?? 0 }}</p>
                    </div>
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-600">
                        <Activity class="size-5" />
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm hover-lift">
                <div class="flex items-start justify-between">
                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-muted-foreground">تنبيهات المخزون</p>
                        <p class="text-3xl font-bold text-amber-600">{{ reportData.pharmacy?.low_stock_count ?? 0 }}</p>
                    </div>
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-amber-500/15 text-amber-600">
                        <AlertCircle class="size-5" />
                    </div>
                </div>
            </div>
        </section>

        <!-- Other Tabs -->
        <section v-else class="space-y-6">
            <!-- Charts -->
            <div class="grid gap-4 xl:grid-cols-2">
                <div v-if="activeTab === 'doctors'" class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <Chart v-bind="doctorIncomeChart" title="الدخل حسب الطبيب" />
                </div>
                <div v-if="activeTab === 'appointments'" class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <Chart type="line" v-bind="appointmentsDayChart" title="المواعيد اليومية" />
                </div>
                <div v-if="activeTab === 'appointments'" class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <Chart type="pie" v-bind="appointmentsStatusChart" title="حالات المواعيد" />
                </div>
                <div v-if="activeTab === 'expenses'" class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <Chart type="pie" v-bind="expenseCategoryChart" title="المصاريف حسب التصنيف" />
                </div>
                <div v-if="activeTab === 'clinics'" class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <Chart v-bind="clinicIncomeChart" title="الدخل حسب العيادة" />
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-2xl border border-border/70 bg-card shadow-sm">
                <table class="w-full min-w-[1080px] border-separate border-spacing-0 text-sm">
                    <thead>
                        <tr class="bg-secondary/50">
                            <th v-for="column in tableColumns" :key="column" class="border-b border-border px-4 py-3 text-right text-[0.72rem] font-bold text-muted-foreground">
                                {{ labelFor(column) }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in currentRows" :key="index" class="transition-colors hover:bg-primary/[0.03]">
                            <td v-for="column in tableColumns" :key="column" class="border-b border-border/60 px-4 py-3">
                                <span v-if="isMoneyColumn(column)" class="font-semibold tabular-nums">{{ moneyValue(row[column]) }}</span>
                                <span v-else>{{ labelFor(row[column]) }}</span>
                            </td>
                        </tr>
                        <tr v-if="currentRows.length === 0">
                            <td :colspan="Math.max(tableColumns.length, 1)" class="px-4 py-14 text-center text-muted-foreground">
                                لا توجد بيانات ضمن الفلاتر الحالية.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
