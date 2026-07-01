<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Clock,
    Edit,
    FileText,
    Package,
    PackageCheck,
    Pill,
    Plus,
    Trash2,
    Truck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useMoneyFormatter } from '@/lib/money';

type Summary = {
    drugs_total: number;
    low_stock_total: number;
    expired_drugs_total: number;
    near_expiry_total: number;
    prescriptions_today: number;
    prescriptions_pending: number;
    prescriptions_preparing: number;
    prescriptions_ready: number;
    prescriptions_dispensed_today: number;
    open_alerts_total: number;
    pending_purchase_orders_total: number;
};

type Drug = {
    id: number;
    trade_name: string;
    generic_name: string;
    code: string | null;
    form: string | null;
    unit: string | null;
    strength: string | null;
    manufacturer: string | null;
    current_stock: number;
    min_stock_level: number;
    unit_price: number;
    is_low_stock: boolean;
};

type LowStockItem = {
    id: number;
    trade_name: string;
    generic_name: string;
    code: string | null;
    current_stock: number;
    min_stock_level: number;
    expires_at: string | null;
};

type RecentAlert = {
    id: number;
    type: string;
    severity: string;
    status: string;
    message: string;
    drug_name: string | null;
    detected_at: string | null;
    resolved_at: string | null;
};

type RecentPrescription = {
    id: number;
    prescription_number: string;
    status: string;
    patient_name: string | null;
    doctor_name: string | null;
    items_count: number;
    created_at: string | null;
    sent_to_pharmacy_at: string | null;
};

type NearExpiryBatch = {
    id: number;
    drug_name: string | null;
    batch_number: string;
    quantity: number;
    expiry_date: string | null;
    remaining_days: number;
};

const { summary, low_stock_items, recent_alerts, recent_prescriptions, near_expiry_batches, drugs } = defineProps<{
    summary: Summary;
    low_stock_items: LowStockItem[];
    recent_alerts: RecentAlert[];
    recent_prescriptions: RecentPrescription[];
    near_expiry_batches: NearExpiryBatch[];
    drugs: Drug[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'الصيدلية', href: '/pharmacy' },
        ],
    },
});

const { formatMoney } = useMoneyFormatter();

const activeTab = ref<string>('dashboard');

const tabs = computed(() => [
    { key: 'prescriptions', label: 'الوصفات الواردة', icon: FileText, href: '/pharmacy/prescriptions' },
    { key: 'drugs', label: 'الأدوية', icon: Package }
]);

const statCards = computed(() => [
    {
        label: 'الوصفات الواردة اليوم',
        value: summary.prescriptions_today,
        icon: FileText,
        tone: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900/50 dark:bg-sky-950/25 dark:text-sky-200',
    },
    {
        label: 'وصفات قيد التحضير',
        value: summary.prescriptions_preparing + summary.prescriptions_ready,
        icon: Clock,
        tone: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/25 dark:text-amber-200',
    },
    {
        label: 'وصفات مصروفة اليوم',
        value: summary.prescriptions_dispensed_today,
        icon: PackageCheck,
        tone: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/25 dark:text-emerald-200',
    },
    {
        label: 'وصفات معلقة',
        value: summary.prescriptions_pending,
        icon: Package,
        tone: 'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-900/50 dark:bg-orange-950/25 dark:text-orange-200',
    },
    {
        label: 'أدوية منخفضة المخزون',
        value: summary.low_stock_total,
        icon: AlertTriangle,
        tone: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/25 dark:text-rose-200',
    },
    {
        label: 'أدوية قريبة الانتهاء',
        value: summary.near_expiry_total,
        icon: Truck,
        tone: 'border-purple-200 bg-purple-50 text-purple-800 dark:border-purple-900/50 dark:bg-purple-950/25 dark:text-purple-200',
    },
    {
        label: 'إجمالي الأدوية النشطة',
        value: summary.drugs_total,
        icon: Pill,
        tone: 'border-teal-200 bg-teal-50 text-teal-800 dark:border-teal-900/50 dark:bg-teal-950/25 dark:text-teal-200',
    },
    {
        label: 'تنبيهات مفتوحة',
        value: summary.open_alerts_total,
        icon: AlertTriangle,
        tone: 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-950/25 dark:text-red-200',
    },
]);

const formOptions = {
    forms: [
        { value: 'tablet', label: 'قرص' },
        { value: 'capsule', label: 'كبسولة' },
        { value: 'syrup', label: 'شراب' },
        { value: 'injection', label: 'حقن' },
        { value: 'cream', label: 'كريم' },
        { value: 'drops', label: 'قطرات' },
        { value: 'inhaler', label: 'بخاخ' },
        { value: 'other', label: 'أخرى' },
    ],
    units: [
        { value: 'box', label: 'علبة' },
        { value: 'strip', label: 'شريط' },
        { value: 'tablet', label: 'قرص' },
        { value: 'bottle', label: 'زجاجة' },
        { value: 'ampoule', label: 'أمبول' },
        { value: 'vial', label: 'قارورة' },
        { value: 'tube', label: 'أنبوب' },
    ],
};

const showAddDialog = ref(false);
const showEditDialog = ref(false);
const editingDrug = ref<Drug | null>(null);

const addForm = useForm({
    trade_name: '',
    generic_name: '',
    code: '',
    barcode: '',
    category: '',
    form: '',
    unit: '',
    strength: '',
    manufacturer: '',
    description: '',
    supplier_name: '',
    unit_price: 0,
    min_stock_level: 0,
    current_stock: 0,
    expires_at: '',
    is_active: true,
});

const editForm = useForm({
    trade_name: '',
    generic_name: '',
    code: '',
    barcode: '',
    category: '',
    form: '',
    unit: '',
    strength: '',
    manufacturer: '',
    description: '',
    supplier_name: '',
    unit_price: 0,
    min_stock_level: 0,
    expires_at: '',
    is_active: true,
});

const openAddDialog = () => {
    addForm.reset();
    showAddDialog.value = true;
};

const submitAdd = () => {
    addForm.post('/pharmacy/drugs', {
        preserveScroll: true,
        onSuccess: () => {
            showAddDialog.value = false;
            router.reload({ only: ['drugs', 'summary'] });
        },
    });
};

const openEditDialog = (drug: Drug) => {
    editingDrug.value = drug;
    editForm.trade_name = drug.trade_name;
    editForm.generic_name = drug.generic_name;
    editForm.code = drug.code ?? '';
    editForm.barcode = '';
    editForm.category = '';
    editForm.form = drug.form ?? '';
    editForm.unit = drug.unit ?? '';
    editForm.strength = drug.strength ?? '';
    editForm.manufacturer = drug.manufacturer ?? '';
    editForm.description = '';
    editForm.supplier_name = '';
    editForm.unit_price = drug.unit_price;
    editForm.min_stock_level = drug.min_stock_level;
    editForm.expires_at = '';
    editForm.is_active = true;
    showEditDialog.value = true;
};

const submitEdit = () => {
    if (!editingDrug.value) {
return;
}

    editForm.put(`/pharmacy/drugs/${editingDrug.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditDialog.value = false;
            router.reload({ only: ['drugs', 'summary'] });
        },
    });
};

const deleteDrug = (drug: Drug) => {
    if (!confirm(`هل أنت متأكد من حذف/تعطيل الدواء "${drug.trade_name}"؟`)) {
return;
}

    router.delete(`/pharmacy/drugs/${drug.id}`, {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['drugs', 'summary'] }),
    });
};

const formLabel = (value: string | null): string => {
    if (!value) {
return '-';
}

    const found = formOptions.forms.find(f => f.value === value);

    return found?.label ?? value;
};

const unitLabel = (value: string | null): string => {
    if (!value) {
return '-';
}

    const found = formOptions.units.find(u => u.value === value);

    return found?.label ?? value;
};

const statusLabel = (status: string): string => {
    const map: Record<string, string> = {
        sent_to_pharmacy: 'مرسلة',
        received: 'مستلمة',
        preparing: 'قيد التحضير',
        ready: 'جاهزة',
        dispensed: 'مصروفة',
        partially_dispensed: 'مصروفة جزئياً',
        canceled: 'ملغية',
    };

    return map[status] ?? status;
};

const statusClass = (status: string): string => {
    const map: Record<string, string> = {
        sent_to_pharmacy: 'border-blue-300/70 bg-blue-50 text-blue-800 dark:border-blue-500/40 dark:bg-blue-500/15 dark:text-blue-100',
        received: 'border-indigo-300/70 bg-indigo-50 text-indigo-800 dark:border-indigo-500/40 dark:bg-indigo-500/15 dark:text-indigo-100',
        preparing: 'border-amber-300/70 bg-amber-50 text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-100',
        ready: 'border-emerald-300/70 bg-emerald-50 text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-100',
        dispensed: 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/40 dark:bg-success-500/15 dark:text-success-100',
        partially_dispensed: 'border-warning-300/70 bg-warning-50 text-warning-800 dark:border-warning-500/40 dark:bg-warning-500/15 dark:text-warning-100',
        canceled: 'border-destructive/70 bg-destructive/10 text-destructive dark:border-destructive/40 dark:bg-destructive/15 dark:text-destructive-foreground',
    };

    return map[status] ?? 'border-border/70 bg-background/80 text-muted-foreground';
};

const alertTypeLabel = (type: string): string => {
    const map: Record<string, string> = {
        low_stock: 'مخزون منخفض',
        near_expiry: 'قريب الانتهاء',
        expired: 'منتهي الصلاحية',
    };

    return map[type] ?? type;
};

const alertSeverityClass = (severity: string): string => {
    const map: Record<string, string> = {
        low: 'border-border/70 bg-background/80 text-muted-foreground',
        medium: 'border-warning-300/70 bg-warning-50 text-warning-800 dark:border-warning-500/40 dark:bg-warning-500/15 dark:text-warning-100',
        high: 'border-destructive/70 bg-destructive/10 text-destructive dark:border-destructive/40 dark:bg-destructive/15 dark:text-destructive-foreground',
    };

    return map[severity] ?? 'border-border/70 bg-background/80 text-muted-foreground';
};

const navigateTo = (href: string) => {
    router.visit(href);
};
</script>

<template>
    <Head title="الصيدلية" />

    <div class="mx-auto w-full max-w-[1680px] space-y-7 p-4 md:p-6" dir="rtl">
        <section class="glass-panel-soft overflow-hidden">
            <div class="flex flex-col gap-4 border-b border-border/70 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-1">
                    <h1 class="page-title mt-2">الصيدلية</h1>
                    <p class="page-subtitle max-w-3xl">
                        إدارة الأدوية والوصفات الطبية والمخزون وتنبيهات الصلاحية.
                    </p>
                </div>
            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <article
                    class="rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm dark:border-sky-900/50 dark:bg-sky-950/25"
                >
                    <p class="text-xs font-semibold text-muted-foreground">الوصفات المعلقة</p>
                    <p class="mt-2 min-h-8 text-xl font-black tabular-nums leading-tight text-sky-800 dark:text-sky-200">
                        {{ summary.prescriptions_pending }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">بانتظار التجهيز</p>
                </article>
                <article
                    class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm dark:border-rose-900/50 dark:bg-rose-950/25"
                >
                    <p class="text-xs font-semibold text-muted-foreground">مخزون منخفض</p>
                    <p class="mt-2 min-h-8 text-xl font-black tabular-nums leading-tight text-rose-800 dark:text-rose-200">
                        {{ summary.low_stock_total }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">يحتاج إعادة طلب</p>
                </article>
                <article
                    class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm dark:border-emerald-900/50 dark:bg-emerald-950/25"
                >
                    <p class="text-xs font-semibold text-muted-foreground">الأدوية النشطة</p>
                    <p class="mt-2 min-h-8 text-xl font-black tabular-nums leading-tight text-emerald-800 dark:text-emerald-200">
                        {{ summary.drugs_total }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">إجمالي الأدوية</p>
                </article>
                <article
                    class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/25"
                >
                    <p class="text-xs font-semibold text-muted-foreground">وصفات اليوم</p>
                    <p class="mt-2 min-h-8 text-xl font-black tabular-nums leading-tight text-amber-800 dark:text-amber-200">
                        {{ summary.prescriptions_today }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">الوصفات الواردة اليوم</p>
                </article>
            </div>
        </section>

        <section class="glass-panel-soft p-3">
            <div class="inline-flex w-fit rounded-xl border border-border bg-muted/70 p-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-lg px-4 text-sm font-bold transition-colors"
                    :class="
                        activeTab === tab.key
                            ? 'bg-primary text-primary-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="tab.href ? navigateTo(tab.href) : activeTab = tab.key"
                >
                    <component :is="tab.icon" class="size-4" />
                    {{ tab.label }}
                </button>
            </div>
        </section>

        <div v-if="activeTab === 'dashboard'" class="space-y-5">
            <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <article
                    v-for="card in statCards"
                    :key="card.label"
                    class="rounded-2xl border p-4 shadow-sm"
                    :class="card.tone"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/60 dark:bg-black/20">
                            <component :is="card.icon" class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-xs font-semibold text-muted-foreground">{{ card.label }}</p>
                            <p class="mt-1 text-xl font-black tabular-nums leading-tight">
                                {{ card.value }}
                            </p>
                        </div>
                    </div>
                </article>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <section class="glass-panel-soft p-5">
                    <div class="mb-4 flex items-center justify-between border-b pb-3">
                        <h3 class="pattern-typographic-title text-[0.76rem]">آخر الوصفات الواردة</h3>
                        <Button type="button" variant="neumorphic" size="sm" class="h-7 text-xs" @click="navigateTo('/pharmacy/prescriptions')">
                            عرض الكل
                        </Button>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="rx in recent_prescriptions"
                            :key="rx.id"
                            class="flex items-center justify-between rounded-xl border border-border/50 bg-background/40 p-3 transition hover:bg-accent/30"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ rx.prescription_number }}</p>
                                <p class="truncate text-xs text-muted-foreground">
                                    {{ rx.patient_name ?? 'مريض غير محدد' }} &middot; {{ rx.doctor_name ?? 'طبيب غير محدد' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-muted-foreground">{{ rx.items_count }} عناصر</span>
                                <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold" :class="statusClass(rx.status)">
                                    {{ statusLabel(rx.status) }}
                                </span>
                            </div>
                        </div>
                        <p v-if="recent_prescriptions.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                            لا توجد وصفات واردة حالياً.
                        </p>
                    </div>
                </section>

                <section class="glass-panel-soft p-5">
                    <div class="mb-4 flex items-center justify-between border-b pb-3">
                        <h3 class="pattern-typographic-title text-[0.76rem]">أدوية منخفضة المخزون</h3>
                        <Button type="button" variant="neumorphic" size="sm" class="h-7 text-xs" @click="activeTab = 'drugs'">
                            عرض الكل
                        </Button>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="drug in low_stock_items"
                            :key="drug.id"
                            class="flex items-center justify-between rounded-xl border border-destructive/20 bg-destructive/5 p-3"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ drug.trade_name }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ drug.generic_name }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm font-bold text-destructive">{{ drug.current_stock }}</span>
                                <span class="text-xs text-muted-foreground">/ {{ drug.min_stock_level }}</span>
                            </div>
                        </div>
                        <p v-if="low_stock_items.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                            جميع الأدوية بمستوى مخزون كافٍ.
                        </p>
                    </div>
                </section>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <section class="glass-panel-soft p-5">
                    <div class="mb-4 flex items-center justify-between border-b pb-3">
                        <h3 class="pattern-typographic-title text-[0.76rem]">تنبيهات الصيدلية</h3>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="alert in recent_alerts"
                            :key="alert.id"
                            class="flex items-center justify-between rounded-xl border p-3"
                            :class="alertSeverityClass(alert.severity)"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ alert.message }}</p>
                                <p class="truncate text-xs opacity-70">
                                    {{ alertTypeLabel(alert.type) }} &middot; {{ alert.drug_name ?? '-' }}
                                </p>
                            </div>
                            <Badge v-if="alert.status === 'open'" variant="destructive" class="text-[10px]">مفتوح</Badge>
                            <Badge v-else variant="secondary" class="text-[10px]">محلول</Badge>
                        </div>
                        <p v-if="recent_alerts.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                            لا توجد تنبيهات.
                        </p>
                    </div>
                </section>

                <section class="glass-panel-soft p-5">
                    <div class="mb-4 flex items-center justify-between border-b pb-3">
                        <h3 class="pattern-typographic-title text-[0.76rem]">دفعات قريبة من الانتهاء (30 يوم)</h3>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="batch in near_expiry_batches"
                            :key="batch.id"
                            class="flex items-center justify-between rounded-xl border border-warning-300/30 bg-warning-50/50 p-3 dark:border-warning-500/20 dark:bg-warning-500/5"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ batch.drug_name ?? '-' }}</p>
                                <p class="truncate text-xs text-muted-foreground">
                                    تشغيلة: {{ batch.batch_number }} &middot; الكمية: {{ batch.quantity }}
                                </p>
                            </div>
                            <div class="text-left">
                                <p class="text-xs font-medium text-warning-700 dark:text-warning-300">{{ batch.remaining_days }} يوم</p>
                                <p class="text-[10px] text-muted-foreground">{{ batch.expiry_date }}</p>
                            </div>
                        </div>
                        <p v-if="near_expiry_batches.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                            لا توجد دفعات قريبة من الانتهاء.
                        </p>
                    </div>
                </section>
            </div>
        </div>

        <div v-if="activeTab === 'drugs'" class="space-y-4">
            <section class="glass-panel-soft overflow-hidden p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-border/70 pb-3">
                    <h3 class="pattern-typographic-title text-[0.76rem]">قائمة الأدوية</h3>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-muted-foreground">الإجمالي: {{ drugs.length }}</span>
                        <Button type="button" variant="clay" size="sm" class="h-9 rounded-xl px-4 text-xs" @click="openAddDialog">
                            <Plus class="size-3.5" />
                            إضافة دواء
                        </Button>
                    </div>
                </div>

                <div class="ui-table-shell">
                    <table class="ui-table w-full">
                        <thead>
                            <tr>
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2">الكود</th>
                                <th class="px-3 py-2">الاسم التجاري</th>
                                <th class="px-3 py-2">الاسم العلمي</th>
                                <th class="px-3 py-2">الشكل</th>
                                <th class="px-3 py-2">التركيز</th>
                                <th class="px-3 py-2">الوحدة</th>
                                <th class="px-3 py-2">المخزون</th>
                                <th class="px-3 py-2">السعر</th>
                                <th class="px-3 py-2 text-end">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(drug, index) in drugs" :key="drug.id" class="ui-table-row">
                                <td class="px-3 py-2 text-sm font-medium text-muted-foreground" data-label="#">
                                    {{ index + 1 }}
                                </td>
                                <td class="px-3 py-2 text-sm text-muted-foreground" data-label="الكود">
                                    {{ drug.code ?? '-' }}
                                </td>
                                <td class="px-3 py-2" data-label="الاسم التجاري">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                                            {{ drug.trade_name?.charAt(0) ?? '?' }}
                                        </span>
                                        <span class="truncate text-sm font-medium text-foreground">{{ drug.trade_name }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-sm text-muted-foreground" data-label="الاسم العلمي">
                                    {{ drug.generic_name }}
                                </td>
                                <td class="px-3 py-2 text-sm text-muted-foreground" data-label="الشكل">
                                    {{ formLabel(drug.form) }}
                                </td>
                                <td class="px-3 py-2 text-sm text-muted-foreground" data-label="التركيز">
                                    {{ drug.strength ?? '-' }}
                                </td>
                                <td class="px-3 py-2 text-sm text-muted-foreground" data-label="الوحدة">
                                    {{ unitLabel(drug.unit) }}
                                </td>
                                <td class="px-3 py-2" data-label="المخزون">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-sm font-medium text-foreground">{{ drug.current_stock }}</span>
                                        <span v-if="drug.is_low_stock" class="inline-flex items-center gap-1 rounded-full border border-destructive/70 bg-destructive/10 px-2 py-0.5 text-[10px] font-semibold text-destructive">
                                            <span class="size-1.5 rounded-full bg-destructive"></span>منخفض
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-sm font-mono text-muted-foreground" data-label="السعر">
                                    {{ formatMoney(drug.unit_price) }}
                                </td>
                                <td class="px-3 py-2 text-end" data-label="الإجراءات">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            variant="neumorphic"
                                            size="icon"
                                            class="size-7"
                                            title="تعديل"
                                            @click="openEditDialog(drug)"
                                        >
                                            <Edit class="size-3.5" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 text-destructive hover:text-destructive"
                                            title="حذف/تعطيل"
                                            @click="deleteDrug(drug)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="drugs.length === 0" class="table-empty-state">
                                <td colspan="10" class="px-3 py-10 text-center text-muted-foreground">
                                    لا توجد أدوية مسجلة.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <Dialog v-model:open="showAddDialog">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>إضافة دواء جديد</DialogTitle>
                    <DialogDescription>أدخل بيانات الدواء الجديد لإضافته إلى المخزون.</DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="add_trade_name">الاسم التجاري *</Label>
                        <Input
                            id="add_trade_name"
                            v-model="addForm.trade_name"
                            class="pattern-field-clay h-10"
                        />
                        <p v-if="addForm.errors.trade_name" class="text-xs text-destructive">{{ addForm.errors.trade_name }}</p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="add_generic_name">الاسم العلمي *</Label>
                        <Input
                            id="add_generic_name"
                            v-model="addForm.generic_name"
                            class="pattern-field-clay h-10"
                        />
                        <p v-if="addForm.errors.generic_name" class="text-xs text-destructive">{{ addForm.errors.generic_name }}</p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="add_code">الكود</Label>
                        <Input
                            id="add_code"
                            v-model="addForm.code"
                            class="pattern-field-clay h-10"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="add_barcode">الباركود</Label>
                        <Input
                            id="add_barcode"
                            v-model="addForm.barcode"
                            class="pattern-field-clay h-10"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="add_category">التصنيف</Label>
                        <Input
                            id="add_category"
                            v-model="addForm.category"
                            class="pattern-field-clay h-10"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="add_form">الشكل الدوائي</Label>
                        <select
                            id="add_form"
                            v-model="addForm.form"
                            class="pattern-field-clay h-10"
                        >
                            <option value="">اختر</option>
                            <option v-for="f in formOptions.forms" :key="f.value" :value="f.value">{{ f.label }}</option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="add_unit">الوحدة</Label>
                        <select
                            id="add_unit"
                            v-model="addForm.unit"
                            class="pattern-field-clay h-10"
                        >
                            <option value="">اختر</option>
                            <option v-for="u in formOptions.units" :key="u.value" :value="u.value">{{ u.label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="add_strength">التركيز</Label>
                        <Input
                            id="add_strength"
                            v-model="addForm.strength"
                            class="pattern-field-clay h-10"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="add_manufacturer">الشركة المصنعة</Label>
                        <Input
                            id="add_manufacturer"
                            v-model="addForm.manufacturer"
                            class="pattern-field-clay h-10"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="add_price">السعر</Label>
                        <Input
                            id="add_price"
                            v-model.number="addForm.unit_price"
                            type="number"
                            min="0"
                            step="0.01"
                            class="pattern-field-clay h-10"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="add_min_stock">الحد الأدنى للمخزون</Label>
                        <Input
                            id="add_min_stock"
                            v-model.number="addForm.min_stock_level"
                            type="number"
                            min="0"
                            class="pattern-field-clay h-10"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="add_stock">المخزون الحالي</Label>
                        <Input
                            id="add_stock"
                            v-model.number="addForm.current_stock"
                            type="number"
                            min="0"
                            class="pattern-field-clay h-10"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" :disabled="addForm.processing" @click="showAddDialog = false">إلغاء</Button>
                    <Button
                        type="button"
                        variant="clay"
                        :disabled="addForm.processing"
                        @click="submitAdd"
                    >
                        {{ addForm.processing ? 'جارٍ الإضافة...' : 'إضافة' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showEditDialog">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>تعديل الدواء</DialogTitle>
                    <DialogDescription>تعديل بيانات الدواء {{ editingDrug?.trade_name }}</DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="edit_trade_name">الاسم التجاري</Label>
                        <Input
                            id="edit_trade_name"
                            v-model="editForm.trade_name"
                            class="pattern-field-clay h-10"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit_generic_name">الاسم العلمي</Label>
                        <Input
                            id="edit_generic_name"
                            v-model="editForm.generic_name"
                            class="pattern-field-clay h-10"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_code">الكود</Label>
                        <Input
                            id="edit_code"
                            v-model="editForm.code"
                            class="pattern-field-clay h-10"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit_form">الشكل الدوائي</Label>
                        <select
                            id="edit_form"
                            v-model="editForm.form"
                            class="pattern-field-clay h-10"
                        >
                            <option value="">اختر</option>
                            <option v-for="f in formOptions.forms" :key="f.value" :value="f.value">{{ f.label }}</option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_strength">التركيز</Label>
                        <Input
                            id="edit_strength"
                            v-model="editForm.strength"
                            class="pattern-field-clay h-10"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit_price">السعر</Label>
                        <Input
                            id="edit_price"
                            v-model.number="editForm.unit_price"
                            type="number"
                            min="0"
                            step="0.01"
                            class="pattern-field-clay h-10"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_min_stock">الحد الأدنى</Label>
                        <Input
                            id="edit_min_stock"
                            v-model.number="editForm.min_stock_level"
                            type="number"
                            min="0"
                            class="pattern-field-clay h-10"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit_manufacturer">الشركة المصنعة</Label>
                        <Input
                            id="edit_manufacturer"
                            v-model="editForm.manufacturer"
                            class="pattern-field-clay h-10"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" :disabled="editForm.processing" @click="showEditDialog = false">إلغاء</Button>
                    <Button
                        type="button"
                        variant="clay"
                        :disabled="editForm.processing"
                        @click="submitEdit"
                    >
                        {{ editForm.processing ? 'جارٍ الحفظ...' : 'حفظ التعديلات' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
