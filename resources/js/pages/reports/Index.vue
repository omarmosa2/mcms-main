<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Calendar,
    DollarSign,
    Download,
    FileText,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AuditReportController from '@/actions/App/Http/Controllers/Reports/AuditReportController';
import ReportController from '@/actions/App/Http/Controllers/Reports/ReportController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type StatusCounts = Record<string, number>;

type OperationalSummary = {
    period: {
        from: string;
        to: string;
    };
    patients_total: number;
    appointments: {
        total: number;
        by_status: StatusCounts;
    };
    queue_entries: {
        total: number;
        by_status: StatusCounts;
    };
    visits: {
        total: number;
        by_status: StatusCounts;
    };
    snapshot: {
        waiting_queue_today: number;
        active_visits: number;
        arrived_appointments_today: number;
    };
};

type FinancialSummary = {
    period: {
        from: string;
        to: string;
    };
    invoices: {
        count: number;
        total_amount: number;
        issued_amount: number;
        outstanding_balance: number;
        overdue_count: number;
        by_status: StatusCounts;
    };
    payments: {
        count: number;
        gross_collections: number;
        refund_amount: number;
        net_collections: number;
    };
};

const props = defineProps<{
    filters: {
        from: string | null;
        to: string | null;
    };
    can_view_operational: boolean;
    can_view_financial: boolean;
    operational_summary: OperationalSummary | null;
    financial_summary: FinancialSummary | null;
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

const page = usePage();

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

    return rolePriority.find((role) => roleNames.value.includes(role)) ?? 'staff';
});

const roleLabels: Record<string, string> = {
    super_admin: 'مدير النظام',
    admin: 'مدير',
    clinic_admin: 'مدير العيادة',
    doctor: 'طبيب',
    receptionist: 'استقبال',
    accountant: 'محاسب',
    staff: 'موظف',
};

const activeRoleLabel = computed<string>(() => roleLabels[primaryRole.value] ?? roleLabels.staff);

const formatStatus = (status: string): string => {
    const labels: Record<string, string> = {
        scheduled: 'مجدول',
        confirmed: 'مؤكد',
        arrived: 'حاضر',
        completed: 'مكتمل',
        canceled: 'ملغي',
        no_show: 'لم يحضر',
        waiting: 'في الانتظار',
        in_progress: 'قيد التنفيذ',
        draft: 'مسودة',
        issued: 'صادرة',
        paid: 'مدفوعة',
        overdue: 'متأخرة',
    };

    return labels[status] ?? status.replace(/_/g, ' ');
};

const formatAmount = (amount: number): string => {
    return amount.toLocaleString('ar-SA', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const auditExportUrl = computed(() => {
    return AuditReportController.export.url({
        query: {
            from: props.filters.from ?? undefined,
            to: props.filters.to ?? undefined,
        },
    });
});
</script>

<template>
    <Head title="التقارير" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="page-title">التقارير</h1>
                    <p class="mt-1 text-sm text-muted-foreground">تقارير مالية وتشغيلية شاملة.</p>
                </div>
                <span class="inline-flex items-center rounded-full border border-border/60 bg-muted/40 px-2.5 py-0.5 text-[0.7rem] font-medium text-muted-foreground">
                    {{ activeRoleLabel }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <a
                    :href="auditExportUrl"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border/70 bg-background/80 px-3 text-xs font-semibold transition hover:bg-muted"
                >
                    <Download class="size-3.5" />
                    تصدير التدقيق
                </a>
            </div>
        </div>

        <section class="grid gap-3 md:grid-cols-4">
            <article class="rounded-xl border border-border/70 bg-card p-4">
                <div class="flex items-center justify-between">
                    <p class="section-label">المرضى</p>
                    <Users class="size-4 text-muted-foreground" />
                </div>
                <p class="card-value text-2xl">{{ operational_summary?.patients_total ?? 0 }}</p>
            </article>
            <article class="rounded-xl border border-border/70 bg-card p-4">
                <div class="flex items-center justify-between">
                    <p class="section-label">المواعيد</p>
                    <Calendar class="size-4 text-info-500" />
                </div>
                <p class="card-value text-2xl text-info-600 dark:text-info-400">{{ operational_summary?.appointments.total ?? 0 }}</p>
            </article>
            <article class="rounded-xl border border-border/70 bg-card p-4">
                <div class="flex items-center justify-between">
                    <p class="section-label">إجمالي الفواتير</p>
                    <FileText class="size-4 text-warning-500" />
                </div>
                <p class="card-value text-2xl text-warning-600 dark:text-warning-400">{{ financial_summary?.invoices.count ?? 0 }}</p>
            </article>
            <article class="rounded-xl border border-border/70 bg-card p-4">
                <div class="flex items-center justify-between">
                    <p class="section-label">صافي التحصيلات</p>
                    <DollarSign class="size-4 text-success-500" />
                </div>
                <p class="card-value text-2xl text-success-600 dark:text-success-400">{{ formatAmount(financial_summary?.payments.net_collections ?? 0) }}</p>
            </article>
        </section>

        <div class="glass-panel-soft p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                <h3 class="pattern-typographic-title text-[0.76rem]">
                    فلتر الفترة
                </h3>
                <span class="text-xs text-muted-foreground">
                    {{ filters.from ?? 'غير محدد' }} → {{ filters.to ?? 'غير محدد' }}
                </span>
            </div>

            <div class="rounded-2xl border border-border/70 bg-background/60 p-4">
                <Form v-bind="ReportController.index.form()" class="grid gap-3 md:grid-cols-4 md:items-end">
                    <div class="grid gap-2">
                        <Label for="from">من</Label>
                        <Input
                            class="pattern-field-clay"
                            id="from"
                            name="from"
                            type="date"
                            :default-value="filters.from ?? ''"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="to">إلى</Label>
                        <Input
                            class="pattern-field-clay"
                            id="to"
                            name="to"
                            type="date"
                            :default-value="filters.to ?? ''"
                        />
                    </div>

                    <div class="md:col-span-2 md:justify-self-end">
                        <div class="flex items-center gap-2">
                            <Button type="submit" variant="clay" size="sm" class="h-8 px-4 text-xs">
                                تطبيق
                            </Button>
                            <Link
                                :href="AuditReportController.index()"
                                class="inline-flex h-8 items-center justify-center rounded-lg border border-border/70 bg-background/80 px-3 text-xs font-semibold transition hover:bg-muted"
                            >
                                <BarChart3 class="me-1 size-3.5" />
                                سجل التدقيق
                            </Link>
                        </div>
                    </div>
                </Form>
            </div>
        </div>

        <div v-if="can_view_operational && operational_summary !== null" class="glass-panel-soft p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                <h3 class="pattern-typographic-title text-[0.76rem]">
                    التقرير التشغيلي
                </h3>
                <span class="text-xs text-muted-foreground">
                    {{ operational_summary.period.from }} - {{ operational_summary.period.to }}
                </span>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                    <p class="text-xs text-muted-foreground">المرضى</p>
                    <p class="mt-1 text-xl font-semibold">{{ operational_summary.patients_total }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                    <p class="text-xs text-muted-foreground">المواعيد</p>
                    <p class="mt-1 text-xl font-semibold">{{ operational_summary.appointments.total }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                    <p class="text-xs text-muted-foreground">قائمة الانتظار</p>
                    <p class="mt-1 text-xl font-semibold">{{ operational_summary.queue_entries.total }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                    <p class="text-xs text-muted-foreground">الزيارات</p>
                    <p class="mt-1 text-xl font-semibold">{{ operational_summary.visits.total }}</p>
                </div>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                    <h4 class="pattern-typographic-title mb-3 text-[0.72rem]">حالات المواعيد</h4>
                    <ul class="space-y-2 text-sm">
                        <li v-for="(count, status) in operational_summary.appointments.by_status" :key="status" class="flex items-center justify-between">
                            <span>{{ formatStatus(status) }}</span>
                            <span class="font-medium">{{ count }}</span>
                        </li>
                    </ul>
                </div>

                <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                    <h4 class="pattern-typographic-title mb-3 text-[0.72rem]">حالات الانتظار</h4>
                    <ul class="space-y-2 text-sm">
                        <li v-for="(count, status) in operational_summary.queue_entries.by_status" :key="status" class="flex items-center justify-between">
                            <span>{{ formatStatus(status) }}</span>
                            <span class="font-medium">{{ count }}</span>
                        </li>
                    </ul>
                </div>

                <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                    <h4 class="pattern-typographic-title mb-3 text-[0.72rem]">حالات الزيارات</h4>
                    <ul class="space-y-2 text-sm">
                        <li v-for="(count, status) in operational_summary.visits.by_status" :key="status" class="flex items-center justify-between">
                            <span>{{ formatStatus(status) }}</span>
                            <span class="font-medium">{{ count }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                    <p class="text-xs text-muted-foreground">في الانتظار اليوم</p>
                    <p class="mt-1 text-xl font-semibold">{{ operational_summary.snapshot.waiting_queue_today }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                    <p class="text-xs text-muted-foreground">زيارات نشطة</p>
                    <p class="mt-1 text-xl font-semibold">{{ operational_summary.snapshot.active_visits }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                    <p class="text-xs text-muted-foreground">مواعيد حاضرة اليوم</p>
                    <p class="mt-1 text-xl font-semibold">{{ operational_summary.snapshot.arrived_appointments_today }}</p>
                </div>
            </div>
        </div>

        <div v-if="can_view_financial && financial_summary !== null" class="glass-panel-soft p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                <h3 class="pattern-typographic-title text-[0.76rem]">
                    التقرير المالي
                </h3>
                <span class="text-xs text-muted-foreground">
                    {{ financial_summary.period.from }} - {{ financial_summary.period.to }}
                </span>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                    <p class="text-xs text-muted-foreground">إجمالي الفوترة</p>
                    <p class="mt-1 text-xl font-semibold">{{ formatAmount(financial_summary.invoices.total_amount) }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                    <p class="text-xs text-muted-foreground">إجمالي التحصيلات</p>
                    <p class="mt-1 text-xl font-semibold text-success-600 dark:text-success-400">{{ formatAmount(financial_summary.payments.gross_collections) }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                    <p class="text-xs text-muted-foreground">المبالغ المستردة</p>
                    <p class="mt-1 text-xl font-semibold text-destructive">{{ formatAmount(financial_summary.payments.refund_amount) }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                    <p class="text-xs text-muted-foreground">صافي التحصيلات</p>
                    <p class="mt-1 text-xl font-semibold text-success-600 dark:text-success-400">{{ formatAmount(financial_summary.payments.net_collections) }}</p>
                </div>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                    <h4 class="pattern-typographic-title mb-3 text-[0.72rem]">مقاييس الفواتير</h4>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-center justify-between">
                            <span>فواتير في الفترة</span>
                            <span class="font-medium">{{ financial_summary.invoices.count }}</span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span>المبلغ الصادر</span>
                            <span class="font-medium">{{ formatAmount(financial_summary.invoices.issued_amount) }}</span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span>الرصيد المستحق</span>
                            <span class="font-medium text-destructive">{{ formatAmount(financial_summary.invoices.outstanding_balance) }}</span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span>فواتير متأخرة</span>
                            <span class="font-medium text-destructive">{{ financial_summary.invoices.overdue_count }}</span>
                        </li>
                    </ul>
                </div>

                <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                    <h4 class="pattern-typographic-title mb-3 text-[0.72rem]">توزيع حالات الفواتير</h4>
                    <ul class="space-y-2 text-sm">
                        <li v-for="(count, status) in financial_summary.invoices.by_status" :key="status" class="flex items-center justify-between">
                            <span>{{ formatStatus(status) }}</span>
                            <span class="font-medium">{{ count }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
