<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, Edit, PackagePlus, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import DrugController from '@/actions/App/Http/Controllers/Pharmacy/DrugController';
import InternalPageHero from '@/components/InternalPageHero.vue';
import { Button } from '@/components/ui/button';
import { FilterBar, FilterSearch } from '@/components/ui/filter';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useMoneyFormatter } from '@/lib/money';

type Drug = {
    id: number;
    trade_name: string;
    generic_name: string;
    code: string | null;
    barcode: string | null;
    category: string | null;
    form: string | null;
    unit: string | null;
    strength: string | null;
    manufacturer: string | null;
    unit_price: number;
    min_stock_level: number;
    current_stock: number;
    is_low_stock: boolean;
    expires_at: string | null;
    nearest_expiry: string | null;
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

const { drugs, filters } = defineProps<{
    drugs: PaginatedResponse<Drug>;
    filters: {
        search: string | null;
        per_page: number;
        low_stock_only: boolean;
        category: string | null;
        form: string | null;
        stock_status: string | null;
    };
}>();
const { formatMoney } = useMoneyFormatter();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'الأدوية', href: DrugController.index() },
        ],
    },
});

const localSearch = ref<string>(filters.search ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(drugs?.meta?.current_page ?? 1);
const lowStockOnly = ref<boolean>(filters.low_stock_only);
const localCategory = ref<string | null>(filters.category);
const localForm = ref<string | null>(filters.form);
const localStockStatus = ref<string | null>(filters.stock_status);

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

const reload = (overrides: Record<string, any> = {}) => {
    router.get(DrugController.index.url(), {
        search: localSearch.value.trim(),
        per_page: localRowsPerPage.value,
        page: localPage.value,
        low_stock_only: lowStockOnly.value ? '1' : null,
        category: localCategory.value,
        form: localForm.value,
        stock_status: localStockStatus.value,
        ...overrides,
    }, { only: ['drugs', 'filters'], preserveState: true, preserveScroll: true, replace: true });
};

watch(() => localSearch.value, () => { localPage.value = 1; reload({ page: 1 }); });
watch(() => localRowsPerPage.value, () => { localPage.value = 1; reload({ page: 1 }); });
watch(() => lowStockOnly.value, () => { localPage.value = 1; reload({ page: 1 }); });
watch(() => localCategory.value, () => { localPage.value = 1; reload({ page: 1 }); });
watch(() => localForm.value, () => { localPage.value = 1; reload({ page: 1 }); });
watch(() => localStockStatus.value, () => { localPage.value = 1; reload({ page: 1 }); });

const activeFilters = computed(() => {
    const list: { key: string; label: string; value: string | null }[] = [];
    if (localSearch.value.trim()) { list.push({ key: 'search', label: 'بحث', value: localSearch.value.trim() }); }
    if (lowStockOnly.value) { list.push({ key: 'low_stock_only', label: 'مخزون منخفض', value: 'نعم' }); }
    if (localCategory.value) { list.push({ key: 'category', label: 'التصنيف', value: localCategory.value }); }
    if (localForm.value) { list.push({ key: 'form', label: 'الشكل', value: localForm.value }); }
    if (localStockStatus.value) { list.push({ key: 'stock_status', label: 'المخزون', value: localStockStatus.value }); }
    return list;
});

const resetFilters = () => {
    localSearch.value = '';
    lowStockOnly.value = false;
    localCategory.value = null;
    localForm.value = null;
    localStockStatus.value = null;
    localPage.value = 1;
    reload({ page: 1, search: '', low_stock_only: null, category: null, form: null, stock_status: null });
};

const openAddDialog = () => {
    addForm.reset();
    showAddDialog.value = true;
};

const submitAdd = () => {
    addForm.post('/pharmacy/drugs', {
        preserveScroll: true,
        onSuccess: () => {
            showAddDialog.value = false;
            reload();
        },
    });
};

const openEditDialog = (drug: Drug) => {
    editingDrug.value = drug;
    editForm.trade_name = drug.trade_name;
    editForm.generic_name = drug.generic_name;
    editForm.code = drug.code ?? '';
    editForm.barcode = drug.barcode ?? '';
    editForm.category = drug.category ?? '';
    editForm.form = drug.form ?? '';
    editForm.unit = drug.unit ?? '';
    editForm.strength = drug.strength ?? '';
    editForm.manufacturer = drug.manufacturer ?? '';
    editForm.description = '';
    editForm.supplier_name = '';
    editForm.unit_price = drug.unit_price;
    editForm.min_stock_level = drug.min_stock_level;
    editForm.expires_at = drug.expires_at ?? '';
    editForm.is_active = true;
    showEditDialog.value = true;
};

const submitEdit = () => {
    if (!editingDrug.value) return;
    editForm.put(`/pharmacy/drugs/${editingDrug.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditDialog.value = false;
            reload();
        },
    });
};

const deleteDrug = (drug: Drug) => {
    if (!confirm(`هل أنت متأكد من حذف/تعطيل الدواء "${drug.trade_name}"؟`)) return;
    router.delete(`/pharmacy/drugs/${drug.id}`, {
        preserveScroll: true,
        onSuccess: () => reload(),
    });
};

const heroMetrics = computed(() => [
    { label: 'إجمالي الأدوية', value: String(drugs?.meta?.total ?? 0), hint: 'جميع أدوية الصيدلية' },
    { label: 'مخزون منخفض', value: String((drugs?.data ?? []).filter(d => d.is_low_stock).length), hint: 'أقل من الحد الأدنى' },
]);

const formLabel = (value: string | null): string => {
    if (!value) return '-';
    const found = formOptions.forms.find(f => f.value === value);
    return found?.label ?? value;
};
</script>

<template>
    <Head title="أدوية الصيدلية" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6">
        <InternalPageHero
            kicker="مخزون الصيدلية"
            title="الأدوية"
            description="إدارة مخزون الأدوية ومستويات التخزين والتسعير."
            :metrics="heroMetrics"
        />

        <section class="glass-panel-soft p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                <h3 class="pattern-typographic-title text-[0.76rem]">قائمة الأدوية</h3>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-muted-foreground">الإجمالي: {{ drugs?.meta?.total ?? 0 }}</span>
                    <Button type="button" size="sm" class="h-8 text-xs" @click="openAddDialog">
                        <Plus class="size-3.5" />
                        إضافة دواء
                    </Button>
                </div>
            </div>

            <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
                <div class="grid gap-3 md:grid-cols-[repeat(4,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2">
                        <Label for="drugs_search">بحث</Label>
                        <FilterSearch id="drugs_search" v-model="localSearch" placeholder="الاسم، الكود، الباركود" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="drugs_category">التصنيف</Label>
                        <input id="drugs_category" v-model="localCategory" type="text" class="pattern-field-clay h-9 px-3 py-1.5" placeholder="التصنيف" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="drugs_form">الشكل الدوائي</Label>
                        <select id="drugs_form" v-model="localForm" class="pattern-field-clay h-9 px-3 py-1.5">
                            <option :value="null">الكل</option>
                            <option v-for="f in formOptions.forms" :key="f.value" :value="f.value">{{ f.label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="drugs_stock">حالة المخزون</Label>
                        <select id="drugs_stock" v-model="localStockStatus" class="pattern-field-clay h-9 px-3 py-1.5">
                            <option :value="null">الكل</option>
                            <option value="available">متوفر</option>
                            <option value="low">منخفض</option>
                            <option value="out">نفد</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="lowStockOnly" type="checkbox" class="size-4 rounded border-border" />
                        المخزون المنخفض فقط
                    </label>
                    <div class="grid gap-2 md:max-w-36">
                        <Label for="drugs_per_page">صفوف</Label>
                        <select id="drugs_per_page" v-model.number="localRowsPerPage" class="pattern-field-clay h-9 px-3 py-1.5">
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>
                <FilterBar v-if="activeFilters.length > 0" :active-filters="activeFilters" @remove="(k) => { if(k==='search') localSearch=''; else if(k==='low_stock_only') lowStockOnly=false; else if(k==='category') localCategory=null; else if(k==='form') localForm=null; else if(k==='stock_status') localStockStatus=null; reload(); }" @clear-all="resetFilters" />
            </div>

            <div class="ui-table-shell mt-4">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th class="px-3 py-2">الكود</th>
                            <th class="px-3 py-2">الاسم التجاري</th>
                            <th class="px-3 py-2">الاسم العلمي</th>
                            <th class="px-3 py-2">الشكل</th>
                            <th class="px-3 py-2">التركيز</th>
                            <th class="px-3 py-2">المخزون</th>
                            <th class="px-3 py-2">الحد الأدنى</th>
                            <th class="px-3 py-2">أقرب انتهاء</th>
                            <th class="px-3 py-2">السعر</th>
                            <th class="px-3 py-2">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="drug in (drugs?.data ?? [])" :key="drug.id" class="ui-table-row">
                            <td class="px-3 py-2 font-mono text-xs">{{ drug.code ?? '-' }}</td>
                            <td class="px-3 py-2 font-medium">{{ drug.trade_name }}</td>
                            <td class="px-3 py-2 text-sm">{{ drug.generic_name }}</td>
                            <td class="px-3 py-2 text-sm">{{ formLabel(drug.form) }}</td>
                            <td class="px-3 py-2 text-sm">{{ drug.strength ?? '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="font-mono text-sm">{{ drug.current_stock }}</span>
                                    <span v-if="drug.is_low_stock" class="inline-flex items-center gap-1 rounded-full border border-destructive/70 bg-destructive/10 px-2 py-0.5 text-xs font-semibold text-destructive">
                                        <span class="size-1.5 rounded-full bg-destructive"></span>منخفض
                                    </span>
                                </span>
                            </td>
                            <td class="px-3 py-2 font-mono text-sm text-muted-foreground">{{ drug.min_stock_level }}</td>
                            <td class="px-3 py-2 text-xs text-muted-foreground">{{ drug.nearest_expiry ?? '-' }}</td>
                            <td class="px-3 py-2 font-mono text-sm">{{ formatMoney(drug.unit_price) }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-1">
                                    <Button type="button" variant="neumorphic" size="sm" class="size-7 p-0" title="تعديل" @click="openEditDialog(drug)">
                                        <Edit class="size-3.5" />
                                    </Button>
                                    <Button type="button" variant="neumorphic" size="sm" class="size-7 p-0 text-destructive hover:text-destructive" title="حذف/تعطيل" @click="deleteDrug(drug)">
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="(drugs?.data?.length ?? 0) === 0" class="table-empty-state">
                            <td colspan="10" class="px-3 py-10 text-center text-muted-foreground">لا توجد أدوية.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2">
                <p class="text-xs text-muted-foreground">عرض {{ drugs?.meta?.from ?? 0 }}-{{ drugs?.meta?.to ?? 0 }} من {{ drugs?.meta?.total ?? 0 }}</p>
                <div class="flex items-center gap-2">
                    <Button type="button" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="localPage === 1" @click="localPage--; reload({ page: localPage })">السابق</Button>
                    <span class="text-xs font-semibold">صفحة {{ localPage }} / {{ drugs?.meta?.last_page ?? 1 }}</span>
                    <Button type="button" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="localPage >= (drugs?.meta?.last_page ?? 1)" @click="localPage++; reload({ page: localPage })">التالي</Button>
                </div>
            </div>
        </section>

        <Dialog v-model:open="showAddDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>إضافة دواء جديد</DialogTitle>
                    <DialogDescription>أدخل بيانات الدواء الجديد.</DialogDescription>
                </DialogHeader>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1">
                        <Label for="add_trade_name">الاسم التجاري *</Label>
                        <input id="add_trade_name" v-model="addForm.trade_name" type="text" class="pattern-field-clay h-9 px-3 py-1.5" />
                        <p v-if="addForm.errors.trade_name" class="text-xs text-destructive">{{ addForm.errors.trade_name }}</p>
                    </div>
                    <div class="grid gap-1">
                        <Label for="add_generic_name">الاسم العلمي *</Label>
                        <input id="add_generic_name" v-model="addForm.generic_name" type="text" class="pattern-field-clay h-9 px-3 py-1.5" />
                        <p v-if="addForm.errors.generic_name" class="text-xs text-destructive">{{ addForm.errors.generic_name }}</p>
                    </div>
                    <div class="grid gap-1">
                        <Label for="add_code">الكود</Label>
                        <input id="add_code" v-model="addForm.code" type="text" class="pattern-field-clay h-9 px-3 py-1.5" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="add_barcode">الباركود</Label>
                        <input id="add_barcode" v-model="addForm.barcode" type="text" class="pattern-field-clay h-9 px-3 py-1.5" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="add_category">التصنيف</Label>
                        <input id="add_category" v-model="addForm.category" type="text" class="pattern-field-clay h-9 px-3 py-1.5" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="add_form">الشكل الدوائي</Label>
                        <select id="add_form" v-model="addForm.form" class="pattern-field-clay h-9 px-3 py-1.5">
                            <option value="">اختر</option>
                            <option v-for="f in formOptions.forms" :key="f.value" :value="f.value">{{ f.label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1">
                        <Label for="add_unit">الوحدة</Label>
                        <select id="add_unit" v-model="addForm.unit" class="pattern-field-clay h-9 px-3 py-1.5">
                            <option value="">اختر</option>
                            <option v-for="u in formOptions.units" :key="u.value" :value="u.value">{{ u.label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1">
                        <Label for="add_strength">التركيز</Label>
                        <input id="add_strength" v-model="addForm.strength" type="text" class="pattern-field-clay h-9 px-3 py-1.5" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="add_manufacturer">الشركة المصنعة</Label>
                        <input id="add_manufacturer" v-model="addForm.manufacturer" type="text" class="pattern-field-clay h-9 px-3 py-1.5" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="add_price">السعر</Label>
                        <input id="add_price" v-model.number="addForm.unit_price" type="number" min="0" step="0.01" class="pattern-field-clay h-9 px-3 py-1.5" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="add_min_stock">الحد الأدنى للمخزون</Label>
                        <input id="add_min_stock" v-model.number="addForm.min_stock_level" type="number" min="0" class="pattern-field-clay h-9 px-3 py-1.5" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="add_stock">المخزون الحالي</Label>
                        <input id="add_stock" v-model.number="addForm.current_stock" type="number" min="0" class="pattern-field-clay h-9 px-3 py-1.5" />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="neumorphic" @click="showAddDialog = false">إلغاء</Button>
                    <Button type="button" :disabled="addForm.processing" @click="submitAdd">إضافة</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showEditDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>تعديل الدواء</DialogTitle>
                    <DialogDescription>تعديل بيانات الدواء {{ editingDrug?.trade_name }}.</DialogDescription>
                </DialogHeader>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1">
                        <Label for="edit_trade_name">الاسم التجاري</Label>
                        <input id="edit_trade_name" v-model="editForm.trade_name" type="text" class="pattern-field-clay h-9 px-3 py-1.5" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="edit_generic_name">الاسم العلمي</Label>
                        <input id="edit_generic_name" v-model="editForm.generic_name" type="text" class="pattern-field-clay h-9 px-3 py-1.5" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="edit_code">الكود</Label>
                        <input id="edit_code" v-model="editForm.code" type="text" class="pattern-field-clay h-9 px-3 py-1.5" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="edit_form">الشكل الدوائي</Label>
                        <select id="edit_form" v-model="editForm.form" class="pattern-field-clay h-9 px-3 py-1.5">
                            <option value="">اختر</option>
                            <option v-for="f in formOptions.forms" :key="f.value" :value="f.value">{{ f.label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1">
                        <Label for="edit_strength">التركيز</Label>
                        <input id="edit_strength" v-model="editForm.strength" type="text" class="pattern-field-clay h-9 px-3 py-1.5" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="edit_price">السعر</Label>
                        <input id="edit_price" v-model.number="editForm.unit_price" type="number" min="0" step="0.01" class="pattern-field-clay h-9 px-3 py-1.5" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="edit_min_stock">الحد الأدنى</Label>
                        <input id="edit_min_stock" v-model.number="editForm.min_stock_level" type="number" min="0" class="pattern-field-clay h-9 px-3 py-1.5" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="edit_manufacturer">الشركة المصنعة</Label>
                        <input id="edit_manufacturer" v-model="editForm.manufacturer" type="text" class="pattern-field-clay h-9 px-3 py-1.5" />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="neumorphic" @click="showEditDialog = false">إلغاء</Button>
                    <Button type="button" :disabled="editForm.processing" @click="submitEdit">حفظ التعديلات</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
