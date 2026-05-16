<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import DiagnosticsController from '@/actions/App/Http/Controllers/Diagnostics/DiagnosticsController';
import InternalPageHero from '@/components/InternalPageHero.vue';
import { Button } from '@/components/ui/button';
import { FilterBar, FilterSearch } from '@/components/ui/filter';
import { Label } from '@/components/ui/label';

type RadiologyStudyType = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    requires_contrast: boolean;
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

type SortField = 'name' | 'code' | 'created_at';
type SortDirection = 'asc' | 'desc';

const { studyTypes, filters } = defineProps<{
    studyTypes: PaginatedResponse<RadiologyStudyType>;
    filters: {
        search: string | null;
        per_page: number;
        is_active: boolean | null;
        sort_by: SortField | null;
        sort_direction: SortDirection | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'أنواع دراسات الأشعة', href: DiagnosticsController.radiologyStudyTypes() },
        ],
    },
});

const localSearch = ref<string>(filters.search ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(studyTypes.meta.current_page);
const localSortBy = ref<SortField>((filters.sort_by as SortField) ?? 'created_at');
const localSortDirection = ref<SortDirection>(filters.sort_direction === 'asc' ? 'asc' : 'desc');
const localActive = ref<boolean | null>(filters.is_active);

const visibleStudyTypes = computed(() => studyTypes.data);

const reload = (overrides: Record<string, any> = {}) => {
    router.get(DiagnosticsController.radiologyStudyTypes.url(), {
        search: localSearch.value.trim(),
        per_page: localRowsPerPage.value,
        page: localPage.value,
        sort_by: localSortBy.value,
        sort_direction: localSortDirection.value,
        is_active: localActive.value !== null ? (localActive.value ? '1' : '0') : null,
        ...overrides,
    }, { only: ['studyTypes', 'filters'], preserveState: true, preserveScroll: true, replace: true });
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

    return list;
});

const resetFilters = () => {
    localSearch.value = '';
    localActive.value = null;
    localPage.value = 1;
    reload({ page: 1, search: '', is_active: null });
};

const heroMetrics = computed(() => [
    { label: 'إجمالي الأنواع', value: String(studyTypes.meta.total), hint: 'جميع أنواع دراسات الأشعة' },
    { label: 'نشط', value: String(visibleStudyTypes.value.filter(t => t.is_active).length), hint: 'الأنواع النشطة' },
]);
</script>

<template>
    <Head title="أنواع دراسات الأشعة" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6">
        <InternalPageHero
            kicker="كتالوج التصوير"
            title="أنواع دراسات الأشعة"
            description="إدارة تعريفات دراسات التصوير ومتطلبات التباين والرموز."
            :metrics="heroMetrics"
        />

        <section class="glass-panel-soft p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                <h3 class="pattern-typographic-title text-[0.76rem]">قائمة أنواع الدراسات</h3>
                <span class="text-xs text-muted-foreground">الإجمالي: {{ studyTypes.meta.total }}</span>
            </div>

            <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
                <div class="grid gap-3 md:grid-cols-[repeat(3,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2">
                        <Label for="types_search">بحث</Label>
                        <FilterSearch id="types_search" v-model="localSearch" placeholder="Name, code" />
                    </div>
                    <div class="grid gap-2 md:max-w-44">
                        <Label for="types_per_page">صفوف لكل صفحة</Label>
                        <select id="types_per_page" v-model.number="localRowsPerPage" class="pattern-field-clay h-9 px-3 py-1.5">
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    <div class="grid gap-2 md:max-w-44">
                        <Label for="types_active">الحالة</Label>
                        <select id="types_active" v-model="localActive" class="pattern-field-clay h-9 px-3 py-1.5">
                            <option :value="null">الكل</option>
                            <option :value="true">نشط</option>
                            <option :value="false">غير نشط</option>
                        </select>
                    </div>
                </div>
                <FilterBar v-if="activeFilters.length > 0" :active-filters="activeFilters" @remove="(k) => { if(k==='search') localSearch=''; else if(k==='is_active') localActive=null; reload(); }" @clear-all="resetFilters" />
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
                            <th class="px-3 py-2">الوصف</th>
                            <th class="px-3 py-2">التباين</th>
                            <th class="px-3 py-2">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="type in visibleStudyTypes" :key="type.id" class="ui-table-row">
                            <td class="px-3 py-2 font-medium">{{ type.name }}</td>
                            <td class="px-3 py-2 font-mono text-sm">{{ type.code }}</td>
                            <td class="px-3 py-2 text-sm">{{ type.description ?? '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold" :class="type.requires_contrast ? 'border-amber-300/70 bg-amber-100/80 text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-100' : 'border-border/70 bg-background/80 text-muted-foreground'">
                                    {{ type.requires_contrast ? 'مطلوب' : 'غير مطلوب' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold" :class="type.is_active ? 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/40 dark:bg-success-500/15 dark:text-success-100' : 'border-border/70 bg-background/80 text-muted-foreground'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="type.is_active ? 'bg-success-500' : 'bg-muted-foreground'"></span>
                                    {{ type.is_active ? 'نشط' : 'غير نشط' }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="visibleStudyTypes.length === 0" class="table-empty-state">
                            <td colspan="5" class="px-3 py-10 text-center text-muted-foreground">لا توجد أنواع دراسات أشعة.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2">
                <p class="text-xs text-muted-foreground">عرض {{ studyTypes.meta.from ?? 0 }}-{{ studyTypes.meta.to ?? 0 }} من {{ studyTypes.meta.total }}</p>
                <div class="flex items-center gap-2">
                    <Button type="button" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="localPage === 1" @click="localPage--; reload({ page: localPage })">السابق</Button>
                    <span class="text-xs font-semibold">صفحة {{ localPage }} / {{ studyTypes.meta.last_page }}</span>
                    <Button type="button" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="localPage >= studyTypes.meta.last_page" @click="localPage++; reload({ page: localPage })">التالي</Button>
                </div>
            </div>
        </section>
    </div>
</template>
