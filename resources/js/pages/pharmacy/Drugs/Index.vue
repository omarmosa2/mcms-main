<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import DrugController from '@/actions/App/Http/Controllers/Pharmacy/DrugController';
import InternalPageHero from '@/components/InternalPageHero.vue';
import { Button } from '@/components/ui/button';
import { FilterBar, FilterSearch } from '@/components/ui/filter';
import { Label } from '@/components/ui/label';
import { useMoneyFormatter } from '@/lib/money';

type Drug = {
    id: number;
    trade_name: string;
    generic_name: string;
    unit_price: number;
    min_stock_level: number;
    current_stock: number;
    is_low_stock: boolean;
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

type SortField = 'trade_name' | 'generic_name' | 'current_stock' | 'unit_price';
type SortDirection = 'asc' | 'desc';

const { drugs, filters } = defineProps<{
    drugs: PaginatedResponse<Drug>;
    filters: {
        search: string | null;
        per_page: number;
        low_stock_only: boolean;
        sort_by: SortField | null;
        sort_direction: SortDirection | null;
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
const localPage = ref<number>(drugs.meta.current_page);
const localSortBy = ref<SortField>((filters.sort_by as SortField) ?? 'trade_name');
const localSortDirection = ref<SortDirection>(filters.sort_direction === 'asc' ? 'asc' : 'desc');
const lowStockOnly = ref<boolean>(filters.low_stock_only);

const visibleDrugs = computed(() => drugs.data);

const reload = (overrides: Record<string, any> = {}) => {
    router.get(DrugController.index.url(), {
        search: localSearch.value.trim(),
        per_page: localRowsPerPage.value,
        page: localPage.value,
        sort_by: localSortBy.value,
        sort_direction: localSortDirection.value,
        low_stock_only: lowStockOnly.value ? '1' : null,
        ...overrides,
    }, { only: ['drugs', 'filters'], preserveState: true, preserveScroll: true, replace: true });
};

watch(() => localSearch.value, () => {
 localPage.value = 1; reload({ page: 1 }); 
});
watch(() => localRowsPerPage.value, () => {
 localPage.value = 1; reload({ page: 1 }); 
});
watch(() => lowStockOnly.value, () => {
 localPage.value = 1; reload({ page: 1 }); 
});

const sortIconFor = (field: SortField) => {
    if (localSortBy.value !== field) {
return ArrowUpDown;
}

    return localSortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

const toggleSort = (field: SortField) => {
    if (localSortBy.value === field) {
        localSortDirection.value = localSortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        localSortBy.value = field;
        localSortDirection.value = 'asc';
    }

    localPage.value = 1;
    reload({ page: 1 });
};

const activeFilters = computed(() => {
    const list: { key: string; label: string; value: string | null }[] = [];

    if (localSearch.value.trim()) {
list.push({ key: 'search', label: 'بحث', value: localSearch.value.trim() });
}

    if (lowStockOnly.value) {
list.push({ key: 'low_stock_only', label: 'مخزون منخفض', value: 'Yes' });
}

    return list;
});

const resetFilters = () => {
    localSearch.value = '';
    lowStockOnly.value = false;
    localPage.value = 1;
    reload({ page: 1, search: '', low_stock_only: null });
};

const heroMetrics = computed(() => [
    { label: 'إجمالي الأدوية', value: String(drugs.meta.total), hint: 'جميع أدوية الصيدلية' },
    { label: 'مخزون منخفض', value: String(visibleDrugs.value.filter(d => d.is_low_stock).length), hint: 'أقل من الحد الأدنى' },
]);
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
                <span class="text-xs text-muted-foreground">الإجمالي: {{ drugs.meta.total }}</span>
            </div>

            <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
                <div class="grid gap-3 md:grid-cols-[repeat(3,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2">
                        <Label for="drugs_search">بحث</Label>
                        <FilterSearch id="drugs_search" v-model="localSearch" placeholder="Trade or generic name" />
                    </div>
                    <div class="grid gap-2 md:max-w-44">
                        <Label for="drugs_per_page">صفوف لكل صفحة</Label>
                        <select id="drugs_per_page" v-model.number="localRowsPerPage" class="pattern-field-clay h-9 px-3 py-1.5">
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input v-model="lowStockOnly" type="checkbox" class="size-4 rounded border-border" />
                            المخزون المنخفض فقط
                        </label>
                    </div>
                </div>
                <FilterBar v-if="activeFilters.length > 0" :active-filters="activeFilters" @remove="(k) => { if(k==='search') localSearch=''; else if(k==='low_stock_only') lowStockOnly=false; reload(); }" @clear-all="resetFilters" />
            </div>

            <div class="ui-table-shell mt-4">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th class="px-3 py-2">
                                <button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('trade_name')">
                                    الاسم التجاري <component :is="sortIconFor('trade_name')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2">
                                <button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('generic_name')">
                                    الاسم العلمي <component :is="sortIconFor('generic_name')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2">
                                <button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('unit_price')">
                                    السعر <component :is="sortIconFor('unit_price')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2">
                                <button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('current_stock')">
                                    المخزون <component :is="sortIconFor('current_stock')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2">الحد الأدنى</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="drug in visibleDrugs" :key="drug.id" class="ui-table-row">
                            <td class="px-3 py-2 font-medium">{{ drug.trade_name }}</td>
                            <td class="px-3 py-2 text-sm">{{ drug.generic_name }}</td>
                            <td class="px-3 py-2 font-mono text-sm">{{ formatMoney(drug.unit_price) }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="font-mono text-sm">{{ drug.current_stock }}</span>
                                    <span v-if="drug.is_low_stock" class="inline-flex items-center gap-1 rounded-full border border-destructive/70 bg-destructive/10 px-2 py-0.5 text-xs font-semibold text-destructive dark:border-destructive/40 dark:bg-destructive/15 dark:text-destructive-foreground"><span class="w-1.5 h-1.5 rounded-full bg-destructive"></span>منخفض</span>
                                </span>
                            </td>
                            <td class="px-3 py-2 font-mono text-sm text-muted-foreground">{{ drug.min_stock_level }}</td>
                        </tr>
                        <tr v-if="visibleDrugs.length === 0" class="table-empty-state">
                            <td colspan="5" class="px-3 py-10 text-center text-muted-foreground">لا توجد أدوية.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2">
                <p class="text-xs text-muted-foreground">عرض {{ drugs.meta.from ?? 0 }}-{{ drugs.meta.to ?? 0 }} من {{ drugs.meta.total }}</p>
                <div class="flex items-center gap-2">
                    <Button type="button" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="localPage === 1" @click="localPage--; reload({ page: localPage })">السابق</Button>
                    <span class="text-xs font-semibold">صفحة {{ localPage }} / {{ drugs.meta.last_page }}</span>
                    <Button type="button" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="localPage >= drugs.meta.last_page" @click="localPage++; reload({ page: localPage })">التالي</Button>
                </div>
            </div>
        </section>
    </div>
</template>
