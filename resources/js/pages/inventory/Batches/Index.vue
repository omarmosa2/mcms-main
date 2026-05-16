<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InventoryController from '@/actions/App/Http/Controllers/Inventory/InventoryController';
import InternalPageHero from '@/components/InternalPageHero.vue';
import { Button } from '@/components/ui/button';
import { FilterBar, FilterSearch } from '@/components/ui/filter';
import { Label } from '@/components/ui/label';

type Batch = {
    id: number;
    batch_number: string;
    quantity: number;
    expiry_date: string | null;
    drug?: { id: number; trade_name: string } | null;
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

type SortField = 'batch_number' | 'expiry_date' | 'created_at';
type SortDirection = 'asc' | 'desc';

const { batches, filters } = defineProps<{
    batches: PaginatedResponse<Batch>;
    filters: {
        search: string | null;
        per_page: number;
        sort_by: SortField | null;
        sort_direction: SortDirection | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'دفعات المخزون', href: InventoryController.index() },
        ],
    },
});

const localSearch = ref<string>(filters.search ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(batches.meta.current_page);
const localSortBy = ref<SortField>((filters.sort_by as SortField) ?? 'expiry_date');
const localSortDirection = ref<SortDirection>(filters.sort_direction === 'asc' ? 'asc' : 'desc');

const visibleBatches = computed(() => batches.data);

const reload = (overrides: Record<string, any> = {}) => {
    router.get(InventoryController.index.url(), {
        search: localSearch.value.trim(),
        per_page: localRowsPerPage.value,
        page: localPage.value,
        sort_by: localSortBy.value,
        sort_direction: localSortDirection.value,
        ...overrides,
    }, { only: ['batches', 'filters'], preserveState: true, preserveScroll: true, replace: true });
};

watch(() => localSearch.value, () => {
 localPage.value = 1; reload({ page: 1 }); 
});
watch(() => localRowsPerPage.value, () => {
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

    return list;
});

const resetFilters = () => {
    localSearch.value = '';
    localPage.value = 1;
    reload({ page: 1, search: '' });
};

const heroMetrics = computed(() => [
    { label: 'إجمالي الدفعات', value: String(batches.meta.total), hint: 'جميع دفعات المخزون' },
    { label: 'مرئي', value: String(visibleBatches.value.length), hint: 'الصفحة الحالية' },
]);
</script>

<template>
    <Head title="دفعات المخزون" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6">
        <InternalPageHero
            kicker="إدارة المخزون"
            title="دفعات المخزون"
            description="تتبع دفعات الأدوية وتواريخ انتهاء الصلاحية والكميات."
            :metrics="heroMetrics"
        />

        <section class="glass-panel-soft p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                <h3 class="pattern-typographic-title text-[0.76rem]">قائمة الدفعات</h3>
                <span class="text-xs text-muted-foreground">الإجمالي: {{ batches.meta.total }}</span>
            </div>

            <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
                <div class="grid gap-3 md:grid-cols-[repeat(2,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2">
                        <Label for="batches_search">بحث</Label>
                        <FilterSearch id="batches_search" v-model="localSearch" placeholder="Batch number, drug" />
                    </div>
                    <div class="grid gap-2 md:max-w-44">
                        <Label for="batches_per_page">صفوف لكل صفحة</Label>
                        <select id="batches_per_page" v-model.number="localRowsPerPage" class="pattern-field-clay h-9 px-3 py-1.5">
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>
                <FilterBar v-if="activeFilters.length > 0" :active-filters="activeFilters" @remove="(k) => { if(k==='search') localSearch=''; reload(); }" @clear-all="resetFilters" />
            </div>

            <div class="ui-table-shell mt-4">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th class="px-3 py-2">الدواء</th>
                            <th class="px-3 py-2">
                                <button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('batch_number')">
                                    رقم الدفعة <component :is="sortIconFor('batch_number')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2">الكمية</th>
                            <th class="px-3 py-2">
                                <button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('expiry_date')">
                                    الانتهاء <component :is="sortIconFor('expiry_date')" class="size-3.5" />
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="batch in visibleBatches" :key="batch.id" class="ui-table-row">
                            <td class="px-3 py-2 font-medium">{{ batch.drug?.trade_name ?? '-' }}</td>
                            <td class="px-3 py-2 font-mono text-sm">{{ batch.batch_number }}</td>
                            <td class="px-3 py-2 font-mono text-sm">{{ batch.quantity }}</td>
                            <td class="px-3 py-2 text-sm" :class="batch.expiry_date && new Date(batch.expiry_date) < new Date() ? 'text-destructive' : 'text-muted-foreground'">
                                {{ batch.expiry_date ? new Date(batch.expiry_date).toLocaleDateString() : '-' }}
                            </td>
                        </tr>
                        <tr v-if="visibleBatches.length === 0" class="table-empty-state">
                            <td colspan="4" class="px-3 py-10 text-center text-muted-foreground">لا توجد دفعات.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2">
                <p class="text-xs text-muted-foreground">عرض {{ batches.meta.from ?? 0 }}-{{ batches.meta.to ?? 0 }} من {{ batches.meta.total }}</p>
                <div class="flex items-center gap-2">
                    <Button type="button" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="localPage === 1" @click="localPage--; reload({ page: localPage })">السابق</Button>
                    <span class="text-xs font-semibold">صفحة {{ localPage }} / {{ batches.meta.last_page }}</span>
                    <Button type="button" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="localPage >= batches.meta.last_page" @click="localPage++; reload({ page: localPage })">التالي</Button>
                </div>
            </div>
        </section>
    </div>
</template>
