<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PrescriptionController from '@/actions/App/Http/Controllers/Pharmacy/PrescriptionController';
import InternalPageHero from '@/components/InternalPageHero.vue';
import { Button } from '@/components/ui/button';
import { FilterBar, FilterSearch } from '@/components/ui/filter';
import { Label } from '@/components/ui/label';

type Prescription = {
    id: number;
    prescription_number: string;
    status: string;
    issued_at: string | null;
    items_count: number;
    patient?: { id: number; full_name: string } | null;
    visit?: { id: number; visit_number: string } | null;
    prescriber?: { id: number; name: string } | null;
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

type SortField = 'prescription_number' | 'status' | 'issued_at' | 'created_at';
type SortDirection = 'asc' | 'desc';

const { prescriptions, filters } = defineProps<{
    prescriptions: PaginatedResponse<Prescription>;
    filters: {
        search: string | null;
        per_page: number;
        status: string | null;
        sort_by: SortField | null;
        sort_direction: SortDirection | null;
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
const localPage = ref<number>(prescriptions.meta.current_page);
const localSortBy = ref<SortField>((filters.sort_by as SortField) ?? 'created_at');
const localSortDirection = ref<SortDirection>(filters.sort_direction === 'asc' ? 'asc' : 'desc');
const localStatus = ref<string | null>(filters.status);

const visiblePrescriptions = computed(() => prescriptions.data);

const statusClass = (status: string): string => {
    const map: Record<string, string> = {
        issued: 'border-warning-300/70 bg-warning-50 text-warning-800 dark:border-warning-500/40 dark:bg-warning-500/15 dark:text-warning-100',
        dispensed: 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/40 dark:bg-success-500/15 dark:text-success-100',
        cancelled: 'border-destructive/70 bg-destructive/10 text-destructive dark:border-destructive/40 dark:bg-destructive/15 dark:text-destructive-foreground',
    };

    return map[status] ?? 'border-border/70 bg-background/80 text-muted-foreground';
};

const statusDotClass = (status: string): string => {
    const map: Record<string, string> = {
        issued: 'bg-warning-500',
        dispensed: 'bg-success-500',
        cancelled: 'bg-destructive',
    };

    return map[status] ?? 'bg-muted-foreground';
};

const reload = (overrides: Record<string, any> = {}) => {
    router.get(PrescriptionController.index.url(), {
        search: localSearch.value.trim(),
        per_page: localRowsPerPage.value,
        page: localPage.value,
        sort_by: localSortBy.value,
        sort_direction: localSortDirection.value,
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

    if (localStatus.value) {
list.push({ key: 'status', label: 'الحالة', value: localStatus.value });
}

    return list;
});

const resetFilters = () => {
    localSearch.value = '';
    localStatus.value = null;
    localPage.value = 1;
    reload({ page: 1, search: '', status: null });
};

const heroMetrics = computed(() => [
    { label: 'إجمالي الوصفات', value: String(prescriptions.meta.total), hint: 'جميع الوصفات' },
    { label: 'مرئي', value: String(visiblePrescriptions.value.length), hint: 'الصفحة الحالية' },
]);
</script>

<template>
    <Head title="الوصفات الطبية" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6">
        <InternalPageHero
            kicker="وصفات طبية"
            title="الوصفات الطبية"
            description="إدارة الوصفات الطبية للمرضى والعناصر وحالة الصرف."
            :metrics="heroMetrics"
        />

        <section class="glass-panel-soft p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                <h3 class="pattern-typographic-title text-[0.76rem]">قائمة الوصفات</h3>
                <span class="text-xs text-muted-foreground">الإجمالي: {{ prescriptions.meta.total }}</span>
            </div>

            <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
                <div class="grid gap-3 md:grid-cols-[repeat(3,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2">
                        <Label for="prescriptions_search">بحث</Label>
                        <FilterSearch id="prescriptions_search" v-model="localSearch" placeholder="Number, patient" />
                    </div>
                    <div class="grid gap-2 md:max-w-44">
                        <Label for="prescriptions_per_page">صفوف لكل صفحة</Label>
                        <select id="prescriptions_per_page" v-model.number="localRowsPerPage" class="pattern-field-clay h-9 px-3 py-1.5">
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="prescriptions_status">الحالة</Label>
                        <select id="prescriptions_status" v-model="localStatus" class="pattern-field-clay h-9 px-3 py-1.5">
                            <option :value="null">الكل</option>
                            <option value="issued">صادر</option>
                            <option value="dispensed">مصروف</option>
                            <option value="cancelled">ملغي</option>
                        </select>
                    </div>
                </div>
                <FilterBar v-if="activeFilters.length > 0" :active-filters="activeFilters" @remove="(k) => { if(k==='search') localSearch=''; else if(k==='status') localStatus=null; reload(); }" @clear-all="resetFilters" />
            </div>

            <div class="ui-table-shell mt-4">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th class="px-3 py-2">
                                <button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('prescription_number')">
                                    الرقم <component :is="sortIconFor('prescription_number')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2">المريض</th>
                            <th class="px-3 py-2">الطبيب المصروف</th>
                            <th class="px-3 py-2">العناصر</th>
                            <th class="px-3 py-2">
                                <button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('status')">
                                    الحالة <component :is="sortIconFor('status')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2">
                                <button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('issued_at')">
                                    التاريخ <component :is="sortIconFor('issued_at')" class="size-3.5" />
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="prescription in visiblePrescriptions" :key="prescription.id" class="ui-table-row">
                            <td class="px-3 py-2 font-mono text-sm font-medium">{{ prescription.prescription_number }}</td>
                            <td class="px-3 py-2">{{ prescription.patient?.full_name ?? '-' }}</td>
                            <td class="px-3 py-2">{{ prescription.prescriber?.name ?? '-' }}</td>
                            <td class="px-3 py-2 text-sm">{{ prescription.items_count }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize" :class="statusClass(prescription.status)">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="statusDotClass(prescription.status)"></span>
                                    {{ prescription.status }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-sm text-muted-foreground">
                                {{ prescription.issued_at ? new Date(prescription.issued_at).toLocaleString() : '-' }}
                            </td>
                        </tr>
                        <tr v-if="visiblePrescriptions.length === 0" class="table-empty-state">
                            <td colspan="6" class="px-3 py-10 text-center text-muted-foreground">لا توجد وصفات طبية.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2">
                <p class="text-xs text-muted-foreground">عرض {{ prescriptions.meta.from ?? 0 }}-{{ prescriptions.meta.to ?? 0 }} من {{ prescriptions.meta.total }}</p>
                <div class="flex items-center gap-2">
                    <Button type="button" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="localPage === 1" @click="localPage--; reload({ page: localPage })">السابق</Button>
                    <span class="text-xs font-semibold">صفحة {{ localPage }} / {{ prescriptions.meta.last_page }}</span>
                    <Button type="button" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="localPage >= prescriptions.meta.last_page" @click="localPage++; reload({ page: localPage })">التالي</Button>
                </div>
            </div>
        </section>
    </div>
</template>
