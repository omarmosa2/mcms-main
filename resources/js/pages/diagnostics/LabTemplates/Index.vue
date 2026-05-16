<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import DiagnosticsController from '@/actions/App/Http/Controllers/Diagnostics/DiagnosticsController';
import InternalPageHero from '@/components/InternalPageHero.vue';
import { Button } from '@/components/ui/button';
import { FilterBar, FilterSearch } from '@/components/ui/filter';
import { Label } from '@/components/ui/label';

type LabTemplate = {
    id: number;
    name: string;
    code: string;
    category: string | null;
    unit: string | null;
    min_reference: number | null;
    max_reference: number | null;
    is_active: boolean;
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

type SortField = 'name' | 'code' | 'category' | 'created_at';
type SortDirection = 'asc' | 'desc';

const { templates, filters } = defineProps<{
    templates: PaginatedResponse<LabTemplate>;
    filters: {
        search: string | null;
        per_page: number;
        is_active: boolean | null;
        category: string | null;
        sort_by: SortField | null;
        sort_direction: SortDirection | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'قوالب الفحوصات المختبرية', href: DiagnosticsController.labTemplates() },
        ],
    },
});

const localSearch = ref<string>(filters.search ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(templates.meta.current_page);
const localSortBy = ref<SortField>((filters.sort_by as SortField) ?? 'created_at');
const localSortDirection = ref<SortDirection>(filters.sort_direction === 'asc' ? 'asc' : 'desc');
const localActive = ref<boolean | null>(filters.is_active);
const localCategory = ref<string | null>(filters.category);

const visibleTemplates = computed(() => templates.data);

const reload = (overrides: Record<string, any> = {}) => {
    router.get(DiagnosticsController.labTemplates.url(), {
        search: localSearch.value.trim(),
        per_page: localRowsPerPage.value,
        page: localPage.value,
        sort_by: localSortBy.value,
        sort_direction: localSortDirection.value,
        is_active: localActive.value !== null ? (localActive.value ? '1' : '0') : null,
        category: localCategory.value,
        ...overrides,
    }, { only: ['templates', 'filters'], preserveState: true, preserveScroll: true, replace: true });
};

watch(() => localSearch.value, () => {
 localPage.value = 1; reload({ page: 1 }); 
});
watch(() => localRowsPerPage.value, () => {
 localPage.value = 1; reload({ page: 1 }); 
});
watch(() => localActive.value, () => {
 localPage.value = 1; reload({ page: 1 }); 
});
watch(() => localCategory.value, () => {
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

    if (localActive.value !== null) {
list.push({ key: 'is_active', label: 'نشط', value: localActive.value ? 'نعم' : 'لا' });
}

    if (localCategory.value) {
list.push({ key: 'category', label: 'الفئة', value: localCategory.value });
}

    return list;
});

const resetFilters = () => {
    localSearch.value = '';
    localActive.value = null;
    localCategory.value = null;
    localPage.value = 1;
    reload({ page: 1, search: '', is_active: null, category: null });
};

const heroMetrics = computed(() => [
    { label: 'إجمالي القوالب', value: String(templates.meta.total), hint: 'جميع قوالب الفحوصات' },
    { label: 'نشط', value: String(visibleTemplates.value.filter(t => t.is_active).length), hint: 'القوالب النشطة' },
]);
</script>

<template>
    <Head title="قوالب الفحوصات المختبرية" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6">
        <InternalPageHero
            kicker="قوالب تشخيصية"
            title="قوالب الفحوصات المختبرية"
            description="إدارة تعريفات الفحوصات المختبرية والمراجع والوحدات."
            :metrics="heroMetrics"
        />

        <section class="glass-panel-soft p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                <h3 class="pattern-typographic-title text-[0.76rem]">قائمة القوالب</h3>
                <span class="text-xs text-muted-foreground">الإجمالي: {{ templates.meta.total }}</span>
            </div>

            <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
                <div class="grid gap-3 md:grid-cols-[repeat(3,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2">
                        <Label for="templates_search">بحث</Label>
                        <FilterSearch id="templates_search" v-model="localSearch" placeholder="Name, code, category" />
                    </div>
                    <div class="grid gap-2 md:max-w-44">
                        <Label for="templates_per_page">صفوف لكل صفحة</Label>
                        <select id="templates_per_page" v-model.number="localRowsPerPage" class="pattern-field-clay h-9 px-3 py-1.5">
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    <div class="grid gap-2 md:max-w-44">
                        <Label for="templates_active">الحالة</Label>
                        <select id="templates_active" v-model="localActive" class="pattern-field-clay h-9 px-3 py-1.5">
                            <option :value="null">الكل</option>
                            <option :value="true">نشط</option>
                            <option :value="false">غير نشط</option>
                        </select>
                    </div>
                </div>
                <FilterBar v-if="activeFilters.length > 0" :active-filters="activeFilters" @remove="(k) => { if(k==='search') localSearch=''; else if(k==='is_active') localActive=null; else if(k==='category') localCategory=null; reload(); }" @clear-all="resetFilters" />
            </div>

            <div class="ui-table-shell mt-4">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th class="px-3 py-2">
                                <button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('name')">
                                    الاسم <component :is="sortIconFor('name')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2">
                                <button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('code')">
                                    الكود <component :is="sortIconFor('code')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2">
                                <button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('category')">
                                    الفئة <component :is="sortIconFor('category')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2">الوحدة</th>
                            <th class="px-3 py-2">المرجع</th>
                            <th class="px-3 py-2">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="template in visibleTemplates" :key="template.id" class="ui-table-row">
                            <td class="px-3 py-2 font-medium">{{ template.name }}</td>
                            <td class="px-3 py-2 font-mono text-sm">{{ template.code }}</td>
                            <td class="px-3 py-2 text-sm">{{ template.category ?? '-' }}</td>
                            <td class="px-3 py-2 text-sm">{{ template.unit ?? '-' }}</td>
                            <td class="px-3 py-2 text-sm">
                                <span v-if="template.min_reference !== null || template.max_reference !== null">
                                    {{ template.min_reference ?? '' }} - {{ template.max_reference ?? '' }}
                                </span>
                                <span v-else class="text-muted-foreground">-</span>
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold" :class="template.is_active ? 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/40 dark:bg-success-500/15 dark:text-success-100' : 'border-border/70 bg-background/80 text-muted-foreground'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="template.is_active ? 'bg-success-500' : 'bg-muted-foreground'"></span>
                                    {{ template.is_active ? 'نشط' : 'غير نشط' }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="visibleTemplates.length === 0" class="table-empty-state">
                            <td colspan="6" class="px-3 py-10 text-center text-muted-foreground">لا توجد قوالب فحوصات مختبرية.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2">
                <p class="text-xs text-muted-foreground">عرض {{ templates.meta.from ?? 0 }}-{{ templates.meta.to ?? 0 }} من {{ templates.meta.total }}</p>
                <div class="flex items-center gap-2">
                    <Button type="button" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="localPage === 1" @click="localPage--; reload({ page: localPage })">السابق</Button>
                    <span class="text-xs font-semibold">صفحة {{ localPage }} / {{ templates.meta.last_page }}</span>
                    <Button type="button" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="localPage >= templates.meta.last_page" @click="localPage++; reload({ page: localPage })">التالي</Button>
                </div>
            </div>
        </section>
    </div>
</template>
