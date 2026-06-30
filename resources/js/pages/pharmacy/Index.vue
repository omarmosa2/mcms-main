<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Clock,
    Edit,
    FileText,
    Package,
    PackageCheck,
    PackageX,
    Pill,
    Plus,
    RefreshCw,
    Trash2,
    Truck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InternalPageHero from '@/components/InternalPageHero.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';
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

const { can } = usePermissions();
const { formatMoney } = useMoneyFormatter();

const activeTab = ref<string>('dashboard');

const tabs = computed(() => [
    { key: 'dashboard', label: 'لوحة الصيدلية', icon: Pill },
    { key: 'prescriptions', label: 'الوصفات الواردة', icon: FileText, href: '/pharmacy/prescriptions' },
    { key: 'drugs', label: 'الأدوية', icon: Package },
    { key: 'movements', label: 'حركات المخزون', icon: RefreshCw, href: '/pharmacy/stock-movements' },
]);

const statCards = computed(() => [
    {
        label: 'الوصفات الواردة اليوم',
        value: summary.prescriptions_today,
        icon: FileText,
        color: 'text-blue-600 bg-blue-50 dark:text-blue-400 dark:bg-blue-950/30',
    },
    {
        label: 'وصفات قيد التحضير',
        value: summary.prescriptions_preparing + summary.prescriptions_ready,
        icon: Clock,
        color: 'text-amber-600 bg-amber-50 dark:text-amber-400 dark:bg-amber-950/30',
    },
    {
        label: 'وصفات مصروفة اليوم',
        value: summary.prescriptions_dispensed_today,
        icon: PackageCheck,
        color: 'text-emerald-600 bg-emerald-50 dark:text-emerald-400 dark:bg-emerald-950/30',
    },
    {
        label: 'وصفات معلقة',
        value: summary.prescriptions_pending,
        icon: Package,
        color: 'text-orange-600 bg-orange-50 dark:text-orange-400 dark:bg-orange-950/30',
    },
    {
        label: 'أدوية منخفضة المخزون',
        value: summary.low_stock_total,
        icon: AlertTriangle,
        color: 'text-red-600 bg-red-50 dark:text-red-400 dark:bg-red-950/30',
    },
    {
        label: 'أدوية قريبة الانتهاء',
        value: summary.near_expiry_total,
        icon: Truck,
        color: 'text-purple-600 bg-purple-50 dark:text-purple-400 dark:bg-purple-950/30',
    },
    {
        label: 'إجمالي الأدوية النشطة',
        value: summary.drugs_total,
        icon: Pill,
        color: 'text-teal-600 bg-teal-50 dark:text-teal-400 dark:bg-teal-950/30',
    },
    {
        label: 'تنبيهات مفتوحة',
        value: summary.open_alerts_total,
        icon: AlertTriangle,
        color: 'text-rose-600 bg-rose-50 dark:text-rose-400 dark:bg-rose-950/30',
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
    if (!editingDrug.value) return;
    editForm.put(`/pharmacy/drugs/${editingDrug.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditDialog.value = false;
            router.reload({ only: ['drugs', 'summary'] });
        },
    });
};

const deleteDrug = (drug: Drug) => {
    if (!confirm(`هل أنت متأكد من حذف/تعطيل الدواء "${drug.trade_name}"؟`)) return;
    router.delete(`/pharmacy/drugs/${drug.id}`, {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['drugs', 'summary'] }),
    });
};

const formLabel = (value: string | null): string => {
    if (!value) return '-';
    const found = formOptions.forms.find(f => f.value === value);
    return found?.label ?? value;
};

const unitLabel = (value: string | null): string => {
    if (!value) return '-';
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

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6">
        <InternalPageHero
            kicker="مساحة عمل"
            title="الصيدلية"
            description="إدارة الأدوية والوصفات الطبية والمخزون وتنبيهات الصلاحية."
            :metrics="[
                { label: 'الوصفات المعلقة', value: String(summary.prescriptions_pending), hint: 'بانتظار التجهيز' },
                { label: 'مخزون منخفض', value: String(summary.low_stock_total), hint: 'يحتاج إعادة طلب' },
                { label: 'الأدوية النشطة', value: String(summary.drugs_total), hint: 'إجمالي الأدوية' },
            ]"
        />

        <div class="flex gap-2 overflow-x-auto pb-1">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-medium transition-all"
                :class="activeTab === tab.key ? 'border-primary/50 bg-primary/10 text-primary shadow-sm' : 'border-border/60 bg-background/60 text-muted-foreground hover:bg-accent/50 hover:text-foreground'"
                @click="tab.href ? navigateTo(tab.href) : activeTab = tab.key"
            >
                <component :is="tab.icon" class="size-4" />
                {{ tab.label }}
            </button>
        </div>

        <div v-if="activeTab === 'dashboard'" class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="card in statCards"
                    :key="card.label"
                    class="glass-panel-soft flex items-center gap-4 p-4"
                >
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl" :class="card.color">
                        <component :is="card.icon" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-xs text-muted-foreground">{{ card.label }}</p>
                        <p class="text-xl font-bold tracking-tight">{{ card.value }}</p>
                    </div>
                </div>
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
            <section class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm">
                <div class="flex items-center justify-between border-b border-border/60 px-4 py-3">
                    <h3 class="text-sm font-semibold text-foreground">قائمة الأدوية</h3>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-muted-foreground">الإجمالي: {{ drugs.length }}</span>
                        <Button type="button" size="sm" class="h-9 rounded-lg px-4 text-xs" @click="openAddDialog">
                            <Plus class="size-3.5" />
                            إضافة دواء
                        </Button>
                    </div>
                </div>

                <div class="w-full overflow-x-auto">
                    <table class="w-full border-separate border-spacing-0">
                        <thead>
                            <tr class="border-b border-border/60 bg-muted/40">
                                <th class="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground">#</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground">الكود</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground">الاسم التجاري</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground">الاسم العلمي</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground">الشكل</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground">التركيز</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground">الوحدة</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground">المخزون</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground">السعر</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(drug, index) in drugs" :key="drug.id" class="group border-b border-border/40 transition-colors last:border-b-0 hover:bg-muted/30">
                                <td class="px-3 py-2.5 text-sm font-medium text-muted-foreground">{{ index + 1 }}</td>
                                <td class="px-3 py-2.5 text-sm text-muted-foreground">{{ drug.code ?? '-' }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                                            {{ drug.trade_name?.charAt(0) ?? '?' }}
                                        </span>
                                        <span class="truncate text-sm font-medium text-foreground">{{ drug.trade_name }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-sm text-muted-foreground">{{ drug.generic_name }}</td>
                                <td class="px-3 py-2.5 text-sm text-muted-foreground">{{ formLabel(drug.form) }}</td>
                                <td class="px-3 py-2.5 text-sm text-muted-foreground">{{ drug.strength ?? '-' }}</td>
                                <td class="px-3 py-2.5 text-sm text-muted-foreground">{{ unitLabel(drug.unit) }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-sm font-medium text-foreground">{{ drug.current_stock }}</span>
                                        <span v-if="drug.is_low_stock" class="inline-flex items-center gap-1 rounded-full border border-destructive/70 bg-destructive/10 px-2 py-0.5 text-[10px] font-semibold text-destructive">
                                            <span class="size-1.5 rounded-full bg-destructive"></span>منخفض
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-sm font-mono text-muted-foreground">{{ formatMoney(drug.unit_price) }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" class="inline-flex size-7 items-center justify-center rounded-md text-primary transition-colors hover:bg-primary/10 dark:text-sky-300 dark:hover:bg-primary/20" title="تعديل" @click="openEditDialog(drug)">
                                            <Edit class="size-3.5" />
                                        </button>
                                        <button type="button" class="inline-flex size-7 items-center justify-center rounded-md text-destructive transition-colors hover:bg-destructive/10 dark:text-red-400 dark:hover:bg-destructive/20" title="حذف/تعطيل" @click="deleteDrug(drug)">
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="drugs.length === 0">
                                <td colspan="10" class="px-5">
                                    <div class="py-16 text-center">
                                        <Package class="mx-auto mb-3 size-12 text-muted-foreground/40" />
                                        <h3 class="mb-1 text-sm font-semibold text-foreground">لا توجد أدوية</h3>
                                        <p class="mb-4 text-xs text-muted-foreground">اضغط "إضافة دواء" للبدء</p>
                                        <Button variant="default" size="sm" class="h-9 rounded-lg px-4 text-xs" @click="openAddDialog">
                                            <Plus class="size-3.5" />
                                            إضافة أول دواء
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <Dialog v-model:open="showAddDialog">
            <DialogContent class="max-w-[520px] bg-card rounded-xl">
                <DialogHeader class="p-6 pb-4 border-b border-border">
                    <DialogTitle class="text-base font-medium text-foreground">إضافة دواء جديد</DialogTitle>
                </DialogHeader>

                <DialogBody class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label for="add_trade_name" class="text-sm font-medium text-foreground">الاسم التجاري *</Label>
                            <Input
                                id="add_trade_name"
                                v-model="addForm.trade_name"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                            <p v-if="addForm.errors.trade_name" class="text-xs text-destructive">{{ addForm.errors.trade_name }}</p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="add_generic_name" class="text-sm font-medium text-foreground">الاسم العلمي *</Label>
                            <Input
                                id="add_generic_name"
                                v-model="addForm.generic_name"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                            <p v-if="addForm.errors.generic_name" class="text-xs text-destructive">{{ addForm.errors.generic_name }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label for="add_code" class="text-sm font-medium text-foreground">الكود</Label>
                            <Input
                                id="add_code"
                                v-model="addForm.code"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="add_barcode" class="text-sm font-medium text-foreground">الباركود</Label>
                            <Input
                                id="add_barcode"
                                v-model="addForm.barcode"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label for="add_category" class="text-sm font-medium text-foreground">التصنيف</Label>
                            <Input
                                id="add_category"
                                v-model="addForm.category"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="add_form" class="text-sm font-medium text-foreground">الشكل الدوائي</Label>
                            <select
                                id="add_form"
                                v-model="addForm.form"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors appearance-none cursor-pointer"
                            >
                                <option value="">اختر</option>
                                <option v-for="f in formOptions.forms" :key="f.value" :value="f.value">{{ f.label }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label for="add_unit" class="text-sm font-medium text-foreground">الوحدة</Label>
                            <select
                                id="add_unit"
                                v-model="addForm.unit"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors appearance-none cursor-pointer"
                            >
                                <option value="">اختر</option>
                                <option v-for="u in formOptions.units" :key="u.value" :value="u.value">{{ u.label }}</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="add_strength" class="text-sm font-medium text-foreground">التركيز</Label>
                            <Input
                                id="add_strength"
                                v-model="addForm.strength"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label for="add_manufacturer" class="text-sm font-medium text-foreground">الشركة المصنعة</Label>
                            <Input
                                id="add_manufacturer"
                                v-model="addForm.manufacturer"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="add_price" class="text-sm font-medium text-foreground">السعر</Label>
                            <Input
                                id="add_price"
                                v-model.number="addForm.unit_price"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label for="add_min_stock" class="text-sm font-medium text-foreground">الحد الأدنى للمخزون</Label>
                            <Input
                                id="add_min_stock"
                                v-model.number="addForm.min_stock_level"
                                type="number"
                                min="0"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="add_stock" class="text-sm font-medium text-foreground">المخزون الحالي</Label>
                            <Input
                                id="add_stock"
                                v-model.number="addForm.current_stock"
                                type="number"
                                min="0"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                        </div>
                    </div>
                </DialogBody>

                <DialogFooter class="flex items-center justify-between p-6 pt-4 gap-2">
                    <Button type="button" variant="ghost" class="h-9 px-4 rounded-lg text-muted-foreground text-sm font-medium hover:bg-muted hover:text-foreground transition-colors duration-150 active:scale-[0.98]" :disabled="addForm.processing" @click="showAddDialog = false">إلغاء</Button>
                    <Button
                        type="button"
                        variant="default"
                        :disabled="addForm.processing"
                        @click="submitAdd"
                    >
                        {{ addForm.processing ? 'جارٍ الإضافة...' : 'إضافة' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showEditDialog">
            <DialogContent class="max-w-[520px] bg-card rounded-xl">
                <DialogHeader class="p-6 pb-4 border-b border-border">
                    <DialogTitle class="text-base font-medium text-foreground">تعديل الدواء</DialogTitle>
                    <DialogDescription class="text-sm text-muted-foreground mt-0.5">تعديل بيانات الدواء {{ editingDrug?.trade_name }}</DialogDescription>
                </DialogHeader>

                <DialogBody class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label for="edit_trade_name" class="text-sm font-medium text-foreground">الاسم التجاري</Label>
                            <Input
                                id="edit_trade_name"
                                v-model="editForm.trade_name"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="edit_generic_name" class="text-sm font-medium text-foreground">الاسم العلمي</Label>
                            <Input
                                id="edit_generic_name"
                                v-model="editForm.generic_name"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label for="edit_code" class="text-sm font-medium text-foreground">الكود</Label>
                            <Input
                                id="edit_code"
                                v-model="editForm.code"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="edit_form" class="text-sm font-medium text-foreground">الشكل الدوائي</Label>
                            <select
                                id="edit_form"
                                v-model="editForm.form"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors appearance-none cursor-pointer"
                            >
                                <option value="">اختر</option>
                                <option v-for="f in formOptions.forms" :key="f.value" :value="f.value">{{ f.label }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label for="edit_strength" class="text-sm font-medium text-foreground">التركيز</Label>
                            <Input
                                id="edit_strength"
                                v-model="editForm.strength"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="edit_price" class="text-sm font-medium text-foreground">السعر</Label>
                            <Input
                                id="edit_price"
                                v-model.number="editForm.unit_price"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label for="edit_min_stock" class="text-sm font-medium text-foreground">الحد الأدنى</Label>
                            <Input
                                id="edit_min_stock"
                                v-model.number="editForm.min_stock_level"
                                type="number"
                                min="0"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="edit_manufacturer" class="text-sm font-medium text-foreground">الشركة المصنعة</Label>
                            <Input
                                id="edit_manufacturer"
                                v-model="editForm.manufacturer"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                            />
                        </div>
                    </div>
                </DialogBody>

                <DialogFooter class="flex items-center justify-between p-6 pt-4 gap-2">
                    <Button type="button" variant="ghost" class="h-9 px-4 rounded-lg text-muted-foreground text-sm font-medium hover:bg-muted hover:text-foreground transition-colors duration-150 active:scale-[0.98]" :disabled="editForm.processing" @click="showEditDialog = false">إلغاء</Button>
                    <Button
                        type="button"
                        variant="default"
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
