<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InventoryController from '@/actions/App/Http/Controllers/Inventory/InventoryController';
import InternalPageHero from '@/components/InternalPageHero.vue';
import { Button } from '@/components/ui/button';
import { FilterBar, FilterSearch } from '@/components/ui/filter';
import { Label } from '@/components/ui/label';

type ReturnItem = {
    id: number;
    quantity: number;
    reason: string;
    returned_to_supplier: boolean;
    returned_at: string | null;
    drug?: { id: number; name: string } | null;
    supplier?: { id: number; name: string } | null;
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

type SortField = 'reason' | 'returned_at' | 'created_at';
type SortDirection = 'asc' | 'desc';

const { returns, filters } = defineProps<{
    returns: PaginatedResponse<ReturnItem>;
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
            { title: 'مرتجعات المخزون', href: InventoryController.returns() },
        ],
    },
});

const localSearch = ref<string>(filters.search ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(returns.meta.current_page);
const localSortBy = ref<SortField>((filters.sort_by as SortField) ?? 'returned_at');
const localSortDirection = ref<SortDirection>(filters.sort_direction === 'asc' ? 'asc' : 'desc');

const visibleReturns = computed(() => returns.data);

const reload = (overrides: Record<string, any> = {}) => {
    router.get(InventoryController.returns.url(), {
        search: localSearch.value.trim(),
        per_page: localRowsPerPage.value,
        page: localPage.value,
        sort_by: localSortBy.value,
        sort_direction: localSortDirection.value,
        ...overrides,
    }, { only: ['returns', 'filters'], preserveState: true, preserveScroll: true, replace: true });
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
    { label: 'إجمالي المرتجعات', value: String(returns.meta.total), hint: 'جميع مرتجعات المخزون' },
    { label: 'مرئي', value: String(visibleReturns.value.length), hint: 'الصفحة الحالية' },
]);
</script>

<template>
    <Head title="مرتجعات المخزون" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6">
        <InternalPageHero
            kicker="إدارة المرتجعات"
            title="مرتجعات المخزون"
            description="تتبع المخزون المرتجع والأسباب والمرتجعات إلى الموردين."
            :metrics="heroMetrics"
        />

        <section class="glass-panel-soft p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                <h3 class="pattern-typographic-title text-[0.76rem]">قائمة المرتجعات</h3>
                <span class="text-xs text-muted-foreground">الإجمالي: {{ returns.meta.total }}</span>
            </div>

            <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
                <div class="grid gap-3 md:grid-cols-[repeat(2,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2">
                        <Label for="returns_search">بحث</Label>
                        <FilterSearch id="returns_search" v-model="localSearch" placeholder="Reason, drug, supplier" />
                    </div>
                    <div class="grid gap-2 md:max-w-44">
                        <Label for="returns_per_page">صفوف لكل صفحة</Label>
                        <select id="returns_per_page" v-model.number="localRowsPerPage" class="pattern-field-clay h-9 px-3 py-1.5">
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
                                <button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('reason')">
                                    السبب <component :is="sortIconFor('reason')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2">الكمية</th>
                            <th class="px-3 py-2">المورد</th>
                            <th class="px-3 py-2">مرتجع للمورد</th>
                            <th class="px-3 py-2">
                                <button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('returned_at')">
                                    التاريخ <component :is="sortIconFor('returned_at')" class="size-3.5" />
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in visibleReturns" :key="item.id" class="ui-table-row">
                            <td class="px-3 py-2 font-medium">{{ item.drug?.name ?? '-' }}</td>
                            <td class="px-3 py-2 text-sm capitalize">{{ item.reason.replace('_', ' ') }}</td>
                            <td class="px-3 py-2 font-mono text-sm">{{ item.quantity }}</td>
                            <td class="px-3 py-2 text-sm">{{ item.supplier?.name ?? '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold" :class="item.returned_to_supplier ? 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/40 dark:bg-success-500/15 dark:text-success-100' : 'border-border/70 bg-background/80 text-muted-foreground'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="item.returned_to_supplier ? 'bg-success-500' : 'bg-muted-foreground'"></span>
                                    {{ item.returned_to_supplier ? 'مرتجع' : 'لم يتم الإرجاع' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-sm text-muted-foreground">
                                {{ item.returned_at ? new Date(item.returned_at).toLocaleString() : '-' }}
                            </td>
                        </tr>
                        <tr v-if="visibleReturns.length === 0" class="table-empty-state">
                            <td colspan="6" class="px-3 py-10 text-center text-muted-foreground">لا توجد مرتجعات.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2">
                <p class="text-xs text-muted-foreground">عرض {{ returns.meta.from ?? 0 }}-{{ returns.meta.to ?? 0 }} من {{ returns.meta.total }}</p>
                <div class="flex items-center gap-2">
                    <Button type="button" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="localPage === 1" @click="localPage--; reload({ page: localPage })">السابق</Button>
                    <span class="text-xs font-semibold">صفحة {{ localPage }} / {{ returns.meta.last_page }}</span>
                    <Button type="button" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="localPage >= returns.meta.last_page" @click="localPage++; reload({ page: localPage })">التالي</Button>
                </div>
            </div>
        </section>
    </div>
</template>
