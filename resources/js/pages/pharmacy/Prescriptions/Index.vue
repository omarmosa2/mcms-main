<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Eye, FlaskConical, FileText, PackageCheck, XCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PrescriptionController from '@/actions/App/Http/Controllers/Pharmacy/PrescriptionController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FilterBar, FilterSearch } from '@/components/ui/filter';
import { Label } from '@/components/ui/label';

type PrescriptionItem = {
    id: number;
    medication_name: string;
    dosage: string;
    frequency: string;
    duration: string | null;
    quantity: number;
    quantity_dispensed: number;
    remaining_quantity: number;
    instructions: string | null;
    status: string;
    substitution_allowed: boolean;
    drug: {
        id: number;
        trade_name: string;
        generic_name: string;
        current_stock: number;
        form: string | null;
        unit: string | null;
        strength: string | null;
    } | null;
    available_batches: {
        id: number;
        batch_number: string;
        quantity: number;
        expiry_date: string | null;
        remaining_days: number;
    }[];
};

type Prescription = {
    id: number;
    prescription_number: string;
    status: string;
    issued_at: string | null;
    sent_to_pharmacy_at: string | null;
    dispensed_at: string | null;
    diagnosis: string | null;
    notes: string | null;
    patient?: { id: number; full_name: string; first_name: string; last_name: string; file_number: string; phone: string | null } | null;
    prescriber?: { id: number; name: string; specialty?: string; license_number?: string } | null;
    items?: PrescriptionItem[];
    items_count?: number;
};

type PaginationMeta = {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

type PaginatedResponse<T> = {
    data: T[];
    meta: PaginationMeta;
};

const { prescriptions, filters } = defineProps<{
    prescriptions: PaginatedResponse<Prescription>;
    filters: {
        search: string | null;
        per_page: number;
        status: string | null;
        doctor_id: number | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'الوصفات الطبية', href: PrescriptionController.index() },
        ],
    },
});

const localSearch = ref<string>(filters.search ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(prescriptions?.meta?.current_page ?? 1);
const localStatus = ref<string | null>(filters.status);

const visiblePrescriptions = computed(() => prescriptions?.data ?? []);

const showDetailDialog = ref(false);
const showDispenseDialog = ref(false);
const selectedPrescription = ref<Prescription | null>(null);
const selectedPrescriptionDetail = ref<Prescription | null>(null);
const dispenseItems = ref<{ prescription_item_id: number; quantity: number; batch_id: number | null }[]>([]);
const dispenseNotes = ref('');

const statusLabel = (status: string): string => {
    const map: Record<string, string> = {
        sent_to_pharmacy: 'مرسلة',
        received: 'مستلمة',
        preparing: 'قيد التحضير',
        ready: 'جاهزة',
        dispensed: 'مصروفة',
        partially_dispensed: 'مصروفة جزئياً',
        canceled: 'ملغية',
        issued: 'صادرة',
        draft: 'مسودة',
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
        issued: 'border-border/70 bg-background/80 text-muted-foreground',
        draft: 'border-border/70 bg-background/80 text-muted-foreground',
    };

    return map[status] ?? 'border-border/70 bg-background/80 text-muted-foreground';
};

const statusDotClass = (status: string): string => {
    const map: Record<string, string> = {
        sent_to_pharmacy: 'bg-blue-500',
        received: 'bg-indigo-500',
        preparing: 'bg-amber-500',
        ready: 'bg-emerald-500',
        dispensed: 'bg-success-500',
        partially_dispensed: 'bg-warning-500',
        canceled: 'bg-destructive',
    };

    return map[status] ?? 'bg-muted-foreground';
};

const reload = (overrides: Record<string, any> = {}) => {
    router.get(PrescriptionController.index.url(), {
        search: localSearch.value.trim(),
        per_page: localRowsPerPage.value,
        page: localPage.value,
        status: localStatus.value,
        ...overrides,
    }, { only: ['prescriptions', 'filters'], preserveState: true, preserveScroll: true, replace: true });
};

watch(() => localSearch.value, () => {
 localPage.value = 1; reload({ page: 1 }); 
});
watch(() => localRowsPerPage.value, () => {
 localPage.value = 1; reload({ page: 1 }); 
});
watch(() => localStatus.value, () => {
 localPage.value = 1; reload({ page: 1 }); 
});

const viewPrescription = (rx: Prescription) => {
    selectedPrescription.value = rx;
    fetchPrescriptionDetail(rx.id);
    showDetailDialog.value = true;
};

const fetchPrescriptionDetail = (id: number) => {
    router.get(`/pharmacy/prescriptions/${id}`, {}, {
        preserveState: true,
        preserveScroll: true,
        only: [] as string[],
        onSuccess: (page) => {
            const data = page.props as unknown as { data?: Prescription };

            if (data?.data) {
                selectedPrescriptionDetail.value = data.data;
            }
        },
    });
};

const startPreparing = (rx: Prescription) => {
    router.patch(`/pharmacy/prescriptions/${rx.id}/status`, {
        status: 'preparing',
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['prescriptions'],
    });
};

const markReady = (rx: Prescription) => {
    router.patch(`/pharmacy/prescriptions/${rx.id}/status`, {
        status: 'ready',
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['prescriptions'],
    });
};

const openDispenseDialog = (rx: Prescription) => {
    selectedPrescription.value = rx;

    if (rx.items) {
        dispenseItems.value = rx.items
            .filter(item => item.status !== 'dispensed' || item.remaining_quantity > 0)
            .map(item => ({
                prescription_item_id: item.id,
                quantity: item.remaining_quantity,
                batch_id: item.available_batches.length > 0 ? item.available_batches[0].id : null,
            }));
    }

    dispenseNotes.value = '';
    showDispenseDialog.value = true;
};

const confirmDispense = () => {
    if (!selectedPrescription.value) {
return;
}

    router.post(`/pharmacy/prescriptions/${selectedPrescription.value.id}/dispense`, {
        items: dispenseItems.value,
        notes: dispenseNotes.value || null,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['prescriptions'],
        onSuccess: () => {
            showDispenseDialog.value = false;
        },
    });
};

const cancelPrescription = (rx: Prescription) => {
    if (!confirm('هل أنت متأكد من إلغاء هذه الوصفة؟')) {
return;
}

    router.patch(`/pharmacy/prescriptions/${rx.id}/status`, {
        status: 'canceled',
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['prescriptions'],
    });
};

const activeFilters = computed(() => {
    const list: { key: string; label: string; value: string | null }[] = [];

    if (localSearch.value.trim()) {
        list.push({ key: 'search', label: 'بحث', value: localSearch.value.trim() });
    }

    if (localStatus.value) {
        list.push({ key: 'status', label: 'الحالة', value: statusLabel(localStatus.value) });
    }

    return list;
});

const resetFilters = () => {
    localSearch.value = '';
    localStatus.value = null;
    localPage.value = 1;
    reload({ page: 1, search: '', status: null });
};

const rxStatCards = computed(() => [
    {
        label: 'إجمالي الوصفات',
        value: String(prescriptions?.meta?.total ?? 0),
        hint: 'جميع الوصفات',
        tone: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900/50 dark:bg-sky-950/25 dark:text-sky-200',
    },
    {
        label: 'مرئية',
        value: String(visiblePrescriptions.value.length),
        hint: 'الصفحة الحالية',
        tone: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/25 dark:text-emerald-200',
    },
    {
        label: 'معلقة',
        value: String((prescriptions?.data ?? []).filter(rx => ['sent_to_pharmacy', 'received', 'preparing'].includes(rx.status)).length),
        hint: 'بانتظار التجهيز',
        tone: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/25 dark:text-amber-200',
    },
    {
        label: 'مصروفة',
        value: String((prescriptions?.data ?? []).filter(rx => rx.status === 'dispensed').length),
        hint: 'تم صرفها',
        tone: 'border-purple-200 bg-purple-50 text-purple-800 dark:border-purple-900/50 dark:bg-purple-950/25 dark:text-purple-200',
    },
]);
</script>

<template>
    <Head title="الوصفات الطبية - الصيدلية" />

    <div class="mx-auto w-full max-w-[1680px] space-y-7 p-4 md:p-6" dir="rtl">
        <section class="glass-panel-soft overflow-hidden">
            <div class="flex flex-col gap-4 border-b border-border/70 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-1">
                    <div class="inline-flex items-center gap-2 rounded-full bg-accent px-3 py-1 text-xs font-semibold text-accent-foreground">
                        <FileText class="size-4" />
                        وصفات طبية
                    </div>
                    <h1 class="page-title mt-2">الوصفات الواردة</h1>
                    <p class="page-subtitle max-w-3xl">
                        إدارة الوصفات الطبية الواردة من الأطباء وتجهيزها وصرفها.
                    </p>
                </div>
            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <article
                    v-for="card in rxStatCards"
                    :key="card.label"
                    class="rounded-2xl border p-4 shadow-sm"
                    :class="card.tone"
                >
                    <p class="text-xs font-semibold text-muted-foreground">{{ card.label }}</p>
                    <p class="mt-2 min-h-8 text-xl font-black tabular-nums leading-tight">
                        {{ card.value }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ card.hint }}</p>
                </article>
            </div>
        </section>

        <section class="glass-panel-soft overflow-hidden p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-border/70 pb-3">
                <h3 class="pattern-typographic-title text-[0.76rem]">قائمة الوصفات</h3>
                <span class="text-xs text-muted-foreground">الإجمالي: {{ prescriptions?.meta?.total ?? 0 }}</span>
            </div>

            <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
                <div class="grid gap-3 md:grid-cols-[repeat(3,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2">
                        <Label for="rx_search">بحث</Label>
                        <FilterSearch id="rx_search" v-model="localSearch" placeholder="رقم الوصفة، اسم المريض" />
                    </div>
                    <div class="grid gap-2 md:max-w-44">
                        <Label for="rx_per_page">صفوف لكل صفحة</Label>
                        <select id="rx_per_page" v-model.number="localRowsPerPage" class="pattern-field-clay h-9 px-3 py-1.5">
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="rx_status">الحالة</Label>
                        <select id="rx_status" v-model="localStatus" class="pattern-field-clay h-9 px-3 py-1.5">
                            <option :value="null">الكل</option>
                            <option value="sent_to_pharmacy">مرسلة</option>
                            <option value="received">مستلمة</option>
                            <option value="preparing">قيد التحضير</option>
                            <option value="ready">جاهزة</option>
                            <option value="dispensed">مصروفة</option>
                            <option value="partially_dispensed">مصروفة جزئياً</option>
                            <option value="canceled">ملغية</option>
                        </select>
                    </div>
                </div>
                <FilterBar v-if="activeFilters.length > 0" :active-filters="activeFilters" @remove="(k) => { if(k==='search') localSearch=''; else if(k==='status') localStatus=null; reload(); }" @clear-all="resetFilters" />
            </div>

            <div class="ui-table-shell mt-4">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th class="px-3 py-2">رقم الوصفة</th>
                            <th class="px-3 py-2">المريض</th>
                            <th class="px-3 py-2">الطبيب</th>
                            <th class="px-3 py-2">العناصر</th>
                            <th class="px-3 py-2">الحالة</th>
                            <th class="px-3 py-2">التاريخ</th>
                            <th class="px-3 py-2 text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="rx in (prescriptions?.data ?? [])" :key="rx.id" class="ui-table-row">
                            <td class="px-3 py-2 font-mono text-sm font-medium" data-label="رقم الوصفة">{{ rx.prescription_number }}</td>
                            <td class="px-3 py-2" data-label="المريض">{{ rx.patient?.full_name ?? rx.patient?.first_name ?? '-' }}</td>
                            <td class="px-3 py-2" data-label="الطبيب">{{ rx.prescriber?.name ?? '-' }}</td>
                            <td class="px-3 py-2 text-sm" data-label="العناصر">{{ rx.items_count ?? rx.items?.length ?? '-' }}</td>
                            <td class="px-3 py-2" data-label="الحالة">
                                <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold" :class="statusClass(rx.status)">
                                    <span class="size-1.5 rounded-full" :class="statusDotClass(rx.status)"></span>
                                    {{ statusLabel(rx.status) }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-sm text-muted-foreground" data-label="التاريخ">
                                {{ rx.sent_to_pharmacy_at ? new Date(rx.sent_to_pharmacy_at).toLocaleString() : rx.issued_at ? new Date(rx.issued_at).toLocaleString() : '-' }}
                            </td>
                            <td class="px-3 py-2 text-end" data-label="الإجراءات">
                                <div class="flex items-center justify-end gap-1">
                                    <Button type="button" variant="neumorphic" size="icon" class="size-7" title="عرض" @click="viewPrescription(rx)">
                                        <Eye class="size-3.5" />
                                    </Button>
                                    <Button
                                        v-if="['sent_to_pharmacy', 'received'].includes(rx.status)"
                                        type="button"
                                        variant="clay"
                                        size="sm"
                                        class="h-7 px-2 text-xs"
                                        @click="startPreparing(rx)"
                                    >
                                        <FlaskConical class="size-3.5" />
                                        بدء التحضير
                                    </Button>
                                    <Button
                                        v-if="rx.status === 'preparing'"
                                        type="button"
                                        variant="clay"
                                        size="sm"
                                        class="h-7 px-2 text-xs"
                                        @click="markReady(rx)"
                                    >
                                        <PackageCheck class="size-3.5" />
                                        تجهيز
                                    </Button>
                                    <Button
                                        v-if="['ready', 'preparing', 'partially_dispensed'].includes(rx.status)"
                                        type="button"
                                        variant="clay"
                                        size="sm"
                                        class="h-7 px-2 text-xs"
                                        @click="openDispenseDialog(rx)"
                                    >
                                        صرف
                                    </Button>
                                    <Button
                                        v-if="!['dispensed', 'canceled'].includes(rx.status)"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-7 text-destructive hover:text-destructive"
                                        title="إلغاء"
                                        @click="cancelPrescription(rx)"
                                    >
                                        <XCircle class="size-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="(prescriptions?.data?.length ?? 0) === 0" class="table-empty-state">
                            <td colspan="7" class="px-3 py-10 text-center text-muted-foreground">لا توجد وصفات طبية.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2">
                <p class="text-xs text-muted-foreground">عرض {{ prescriptions?.meta?.from ?? 0 }}-{{ prescriptions?.meta?.to ?? 0 }} من {{ prescriptions?.meta?.total ?? 0 }}</p>
                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" class="h-8 rounded-lg px-3 text-xs" :disabled="localPage === 1" @click="localPage--; reload({ page: localPage })">السابق</Button>
                    <span class="text-xs font-semibold">صفحة {{ localPage }} / {{ prescriptions?.meta?.last_page ?? 1 }}</span>
                    <Button type="button" variant="outline" size="sm" class="h-8 rounded-lg px-3 text-xs" :disabled="localPage >= (prescriptions?.meta?.last_page ?? 1)" @click="localPage++; reload({ page: localPage })">التالي</Button>
                </div>
            </div>
        </section>

        <Dialog v-model:open="showDetailDialog">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>تفاصيل الوصفة {{ selectedPrescription?.prescription_number }}</DialogTitle>
                    <DialogDescription>
                        الحالة: {{ selectedPrescription ? statusLabel(selectedPrescription.status) : '' }}
                    </DialogDescription>
                </DialogHeader>
                <div v-if="selectedPrescription" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="text-muted-foreground">المريض:</span>
                            <span class="ms-1 font-medium">{{ selectedPrescription.patient?.full_name ?? selectedPrescription.patient?.first_name ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground">الطبيب:</span>
                            <span class="ms-1 font-medium">{{ selectedPrescription.prescriber?.name ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground">التشخيص:</span>
                            <span class="ms-1">{{ (selectedPrescription as any).diagnosis ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground">التاريخ:</span>
                            <span class="ms-1">{{ selectedPrescription.sent_to_pharmacy_at ? new Date(selectedPrescription.sent_to_pharmacy_at).toLocaleString() : '-' }}</span>
                        </div>
                    </div>
                    <div v-if="selectedPrescription.items && selectedPrescription.items.length > 0">
                        <h4 class="mb-2 text-sm font-semibold">الأدوية</h4>
                        <div class="space-y-2">
                            <div v-for="item in selectedPrescription.items" :key="item.id" class="rounded-lg border p-3">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium">{{ item.medication_name }}</span>
                                    <span class="text-xs text-muted-foreground">{{ item.status }}</span>
                                </div>
                                <div class="mt-1 grid grid-cols-2 gap-2 text-xs text-muted-foreground">
                                    <span>الجرعة: {{ item.dosage }}</span>
                                    <span>التكرار: {{ item.frequency }}</span>
                                    <span>المدة: {{ item.duration ?? '-' }}</span>
                                    <span>الكمية: {{ item.quantity }} (مصروف: {{ item.quantity_dispensed }})</span>
                                </div>
                                <p v-if="item.instructions" class="mt-1 text-xs italic">{{ item.instructions }}</p>
                                <div v-if="item.drug" class="mt-1 text-xs">
                                    <span class="text-muted-foreground">المخزون المتاح:</span>
                                    <span class="ms-1 font-mono font-bold" :class="item.drug.current_stock > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'">{{ item.drug.current_stock }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showDispenseDialog">
            <DialogContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>صرف الوصفة {{ selectedPrescription?.prescription_number }}</DialogTitle>
                    <DialogDescription>تحقق من الكميات واختر الدفعة المناسبة لكل دواء.</DialogDescription>
                </DialogHeader>
                <div v-if="selectedPrescription?.items" class="space-y-3">
                    <div v-for="(item, idx) in selectedPrescription.items" :key="item.id" class="rounded-lg border p-3">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{ item.medication_name }}</span>
                            <span class="text-xs text-muted-foreground">المطلوب: {{ item.quantity }}</span>
                        </div>
                        <div v-if="item.drug" class="mt-2 grid grid-cols-3 gap-2 text-xs">
                            <div>
                                <Label :for="`qty_${idx}`">الكمية</Label>
                                <input
                                    :id="`qty_${idx}`"
                                    v-model.number="dispenseItems[idx].quantity"
                                    type="number"
                                    min="0"
                                    :max="item.quantity"
                                    class="pattern-field-clay mt-1 h-8 w-full px-2 text-sm"
                                />
                            </div>
                            <div>
                                <Label :for="`batch_${idx}`">الدفعة</Label>
                                <select :id="`batch_${idx}`" v-model="dispenseItems[idx].batch_id" class="pattern-field-clay mt-1 h-8 w-full px-2 text-sm">
                                    <option :value="null">تلقائي (FEFO)</option>
                                    <option v-for="batch in item.available_batches" :key="batch.id" :value="batch.id">
                                        {{ batch.batch_number }} ({{ batch.quantity }} متاح - تنتهي {{ batch.expiry_date }})
                                    </option>
                                </select>
                            </div>
                            <div>
                                <span class="text-muted-foreground">المتاح:</span>
                                <span class="ms-1 font-mono font-bold" :class="item.drug.current_stock > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'">{{ item.drug.current_stock }}</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <Label for="dispense_notes">ملاحظات</Label>
                        <textarea id="dispense_notes" v-model="dispenseNotes" rows="2" class="pattern-field-clay mt-1 w-full px-3 py-2 text-sm" placeholder="ملاحظات صرف (اختياري)"></textarea>
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="ghost" @click="showDispenseDialog = false">إلغاء</Button>
                    <Button type="button" variant="clay" @click="confirmDispense">تأكيد الصرف</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
