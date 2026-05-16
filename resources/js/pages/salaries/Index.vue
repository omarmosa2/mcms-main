<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, Plus, DollarSign } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import SalaryController from '@/actions/App/Http/Controllers/Salaries/SalaryController';
import InputError from '@/components/InputError.vue';
import InternalPageHero from '@/components/InternalPageHero.vue';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import {
    FilterBar,
    FilterSearch,
    FilterSelect,
} from '@/components/ui/filter';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';

type Salary = {
    id: number;
    base_salary: number;
    allowances: number;
    deductions: number;
    net_salary: number;
    status: 'draft' | 'calculated' | 'approved' | 'paid';
    period_month: string;
    user: { id: number; name: string; email: string } | null;
    paid_at: string | null;
    notes: string | null;
    created_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginationMeta = {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

type PaginationNavigation = {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
};

type PaginatedResponse<T> = {
    data: T[];
    links: PaginationNavigation;
    meta: PaginationMeta;
};

type SalarySortField = 'period_month' | 'net_salary' | 'status';
type SortDirection = 'asc' | 'desc';

const { salaries, filters } = defineProps<{
    salaries: PaginatedResponse<Salary>;
    filters: {
        search: string | null;
        status: string | null;
        period_month: string | null;
        per_page: number;
        sort_by: SalarySortField | null;
        sort_direction: SortDirection | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Salaries',
                href: SalaryController.index(),
            },
        ],
    },
});

const { can } = usePermissions();
const { isOpen: isConfirmOpen, options: confirmOptions, confirm, handleConfirm: handleConfirmDelete, handleCancel: handleConfirmCancel } = useConfirm();
const toast = useToast();

const localSearch = ref<string>(filters.search ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(salaries.meta.current_page);
const localStatus = ref<string | null>(filters.status ?? null);
const localPeriodMonth = ref<string>(filters.period_month ?? '');

const allowedSortFields: SalarySortField[] = ['period_month', 'net_salary', 'status'];

const resolveInitialSortBy = (): SalarySortField => {
    const sortBy = filters.sort_by;

    if (sortBy !== null && allowedSortFields.includes(sortBy as SalarySortField)) {
        return sortBy;
    }

    return 'period_month';
};

const localSortBy = ref<SalarySortField>(resolveInitialSortBy());
const localSortDirection = ref<SortDirection>(
    filters.sort_direction === 'asc' ? 'asc' : 'desc',
);

const visibleSalaries = computed<Salary[]>(() => salaries.data);
const totalLocalPages = computed<number>(() => Math.max(1, salaries.meta.last_page));
const totalAmount = computed<number>(() =>
    visibleSalaries.value.reduce((sum, s) => sum + s.net_salary, 0)
);

let filtersDebounceTimeout: ReturnType<typeof setTimeout> | null = null;

const buildIndexQuery = (overrides: Partial<{
    search: string;
    status: string | null;
    period_month: string;
    per_page: number;
    page: number;
    sort_by: SalarySortField;
    sort_direction: SortDirection;
}> = {}) => ({
    search: (overrides.search ?? localSearch.value).trim(),
    status: overrides.status ?? localStatus.value ?? undefined,
    period_month: (overrides.period_month ?? localPeriodMonth.value) || undefined,
    per_page: overrides.per_page ?? localRowsPerPage.value,
    page: overrides.page ?? localPage.value,
    sort_by: overrides.sort_by ?? localSortBy.value,
    sort_direction: overrides.sort_direction ?? localSortDirection.value,
});

const reloadSalaries = (overrides = {}, debounce = false) => {
    const executeReload = () => {
        router.cancelAll();
        router.get(SalaryController.index.url(), buildIndexQuery(overrides), {
            only: ['salaries', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    if (debounce) {
        if (filtersDebounceTimeout !== null) {
clearTimeout(filtersDebounceTimeout);
}

        filtersDebounceTimeout = setTimeout(executeReload, 300);

        return;
    }

    executeReload();
};

const sortIconFor = (field: SalarySortField) => {
    if (localSortBy.value !== field) {
return ArrowUpDown;
}

    return localSortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

const toggleSort = (field: SalarySortField) => {
    if (localSortBy.value === field) {
        localSortDirection.value = localSortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        localSortBy.value = field;
        localSortDirection.value = 'desc';
    }
};

const resetLocalFilters = () => {
    localSearch.value = '';
    localStatus.value = null;
    localPeriodMonth.value = '';
    localRowsPerPage.value = 15;
    localSortBy.value = 'period_month';
    localSortDirection.value = 'desc';
    localPage.value = 1;
    reloadSalaries({ search: '', status: null, period_month: '', per_page: 15, page: 1, sort_by: 'period_month', sort_direction: 'desc' });
};

const goToPreviousPage = () => {
 if (localPage.value > 1) {
 localPage.value--; reloadSalaries({ page: localPage.value }); 
} 
};
const goToNextPage = () => {
 if (localPage.value < totalLocalPages.value) {
 localPage.value++; reloadSalaries({ page: localPage.value }); 
} 
};

watch([() => filters.search, () => filters.per_page], () => {
    localSearch.value = filters.search ?? '';
    localRowsPerPage.value = filters.per_page;
    localPage.value = salaries.meta.current_page;
}, { immediate: true });

watch(localSearch, () => {
 localPage.value = 1; reloadSalaries({ page: 1, search: localSearch.value.trim() }, true); 
});
watch(localRowsPerPage, () => {
 localPage.value = 1; reloadSalaries({ page: 1, per_page: localRowsPerPage.value }); 
});
watch([localSortBy, localSortDirection], () => {
 localPage.value = 1; reloadSalaries({ page: 1, sort_by: localSortBy.value, sort_direction: localSortDirection.value }); 
});
watch(localStatus, () => {
 localPage.value = 1; reloadSalaries({ page: 1, status: localStatus.value }); 
});

onBeforeUnmount(() => {
 if (filtersDebounceTimeout !== null) {
clearTimeout(filtersDebounceTimeout);
} 
});

const formatAmount = (amount: number | string): string => {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;

    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(num);
};

const statusClass = (status: string): string => {
    if (status === 'paid') {
        return 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/40 dark:bg-success-500/15 dark:text-success-100';
    }

    if (status === 'approved') {
        return 'border-info-300/70 bg-info-50 text-info-800 dark:border-info-500/40 dark:bg-info-500/15 dark:text-info-100';
    }

    if (status === 'calculated') {
        return 'border-warning-300/70 bg-warning-50 text-warning-800 dark:border-warning-500/40 dark:bg-warning-500/15 dark:text-warning-100';
    }

    return 'border-border/70 bg-background/80 text-muted-foreground dark:border-border/40 dark:bg-background/40 dark:text-muted-foreground';
};

const heroMetrics = computed(() => [
    { label: 'Total records', value: String(salaries.meta.total), hint: 'All salary records' },
    { label: 'Total amount', value: formatAmount(totalAmount.value), hint: 'On current page' },
]);

const activeFilters = computed(() => {
    const filterList: { key: string; label: string; value: string | null }[] = [];

    if (localSearch.value.trim()) {
        filterList.push({ key: 'search', label: 'Search', value: localSearch.value.trim() });
    }

    if (localStatus.value) {
        filterList.push({ key: 'status', label: 'الحالة', value: localStatus.value });
    }

    if (localPeriodMonth.value) {
        filterList.push({ key: 'period_month', label: 'الفترة', value: localPeriodMonth.value });
    }

    return filterList;
});

const statusOptions = [
    { label: 'الكل', value: '' },
    { label: 'مسودة', value: 'draft' },
    { label: 'محسوب', value: 'calculated' },
    { label: 'معتمد', value: 'approved' },
    { label: 'مدفوع', value: 'paid' },
];

const handleRemoveFilter = (key: string) => {
    if (key === 'search') {
        localSearch.value = '';
    } else if (key === 'status') {
        localStatus.value = null;
    } else if (key === 'period_month') {
        localPeriodMonth.value = '';
    }
};

const deleteSalary = async (salary: Salary) => {
    const confirmed = await confirm({
        title: 'حذف الراتب',
        description: `هل أنت متأكد من حذف الراتب الخاص بـ "${salary.user?.name || salary.id}"؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(SalaryController.destroy(salary.id), {
            onSuccess: () => {
                toast.success('تم حذف الراتب بنجاح');
            },
            onError: () => {
                toast.error('فشل حذف الراتب');
            },
        });
    }
};
</script>

<template>
    <Head title="الرواتب" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6">
        <InternalPageHero
            kicker="الرواتب"
            title="إدارة الرواتب"
            description="إدارة رواتب الموظفين والبدلات والخصومات ومعالجة الرواتب الشهرية."
            :metrics="heroMetrics"
        />

        <div class="grid gap-5 lg:grid-cols-3">
            <section v-if="can('salaries.create')" class="glass-panel-soft p-5 lg:col-span-1">
                <h3 class="pattern-typographic-title mb-4 text-[0.76rem]">راتب جديد</h3>
                <Form v-bind="SalaryController.store.form()" class="space-y-4" reset-on-success v-slot="{ errors, processing }">
                    <div class="grid gap-2">
                        <Label for="user_id">Employee</Label>
                        <Input id="user_id" name="user_id" type="number" required placeholder="User ID" class="pattern-field-clay" />
                        <InputError :message="errors.user_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="period_month">الفترة (YYYY-MM)</Label>
                        <Input id="period_month" name="period_month" required placeholder="2026-04" class="pattern-field-clay" />
                        <InputError :message="errors.period_month" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="base_salary">الراتب الأساسي</Label>
                        <Input id="base_salary" name="base_salary" type="number" step="0.01" required placeholder="0.00" class="pattern-field-clay" />
                        <InputError :message="errors.base_salary" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="allowances">البدلات</Label>
                        <Input id="allowances" name="allowances" type="number" step="0.01" placeholder="0.00" class="pattern-field-clay" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="deductions">الخصومات</Label>
                        <Input id="deductions" name="deductions" type="number" step="0.01" placeholder="0.00" class="pattern-field-clay" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="notes">ملاحظات</Label>
                        <textarea id="notes" name="notes" rows="2" class="pattern-field-clay" />
                    </div>
                    <Button :disabled="processing" variant="clay" class="w-full">
                        <Plus class="me-2 size-4" />إنشاء راتب
                    </Button>
                </Form>
            </section>

            <section :class="['glass-panel-soft p-5', can('salaries.create') ? 'lg:col-span-2' : 'lg:col-span-3']">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                    <h3 class="pattern-typographic-title text-[0.76rem]">قائمة الرواتب</h3>
                    <span class="text-xs text-muted-foreground">الإجمالي: {{ salaries.meta.total }}</span>
                </div>

                <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
                    <div class="grid gap-3 md:grid-cols-[repeat(3,minmax(0,1fr))] md:items-end">
                        <div class="grid gap-2">
                            <Label for="salaries_search">بحث</Label>
                            <FilterSearch id="salaries_search" v-model="localSearch" placeholder="Employee name..." />
                        </div>
                        <div class="grid gap-2">
                            <Label for="salaries_status">الحالة</Label>
                            <FilterSelect id="salaries_status" v-model="localStatus" :options="statusOptions" placeholder="الكل" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="salaries_per_page">صفوف</Label>
                            <select id="salaries_per_page" v-model.number="localRowsPerPage" class="pattern-field-clay h-9 px-3 py-1.5">
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                    </div>

                    <FilterBar v-if="activeFilters.length > 0" :active-filters="activeFilters" @remove="handleRemoveFilter" @clear-all="resetLocalFilters" />
                </div>

                <div class="ui-table-shell">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th class="px-3 py-2"><button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('period_month')">الفترة<component :is="sortIconFor('period_month')" class="size-3.5" /></button></th>
                                <th class="px-3 py-2">الموظف</th>
                                <th class="px-3 py-2">الأساسي</th>
                                <th class="px-3 py-2">البدلات</th>
                                <th class="px-3 py-2">الخصومات</th>
                                <th class="px-3 py-2"><button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('net_salary')">الصافي<component :is="sortIconFor('net_salary')" class="size-3.5" /></button></th>
                                <th class="px-3 py-2"><button type="button" class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground" @click="toggleSort('status')">الحالة<component :is="sortIconFor('status')" class="size-3.5" /></button></th>
                                <th class="px-3 py-2 text-right">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="salary in visibleSalaries" :key="salary.id" class="ui-table-row">
                                <td class="px-3 py-2" data-label="الفترة">{{ salary.period_month }}</td>
                                <td class="px-3 py-2 font-medium" data-label="الموظف">{{ salary.user?.name ?? '-' }}</td>
                                <td class="px-3 py-2 font-mono" data-label="الأساسي">{{ formatAmount(salary.base_salary) }}</td>
                                <td class="px-3 py-2 font-mono text-success-600 dark:text-success-400" data-label="البدلات">+{{ formatAmount(salary.allowances) }}</td>
                                <td class="px-3 py-2 font-mono text-destructive" data-label="الخصومات">-{{ formatAmount(salary.deductions) }}</td>
                                <td class="px-3 py-2 font-mono font-bold" data-label="الصافي">{{ formatAmount(salary.net_salary) }}</td>
                                <td class="px-3 py-2" data-label="الحالة">
                                    <span :class="statusClass(salary.status)" class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize">
                                        <span class="w-1.5 h-1.5 rounded-full" :class="salary.status === 'paid' ? 'bg-success-500' : salary.status === 'approved' ? 'bg-info-500' : salary.status === 'calculated' ? 'bg-warning-500' : 'bg-muted-foreground'"></span>
                                        {{ salary.status }}
                                    </span>
                                </td>
                                <td class="table-cell-actions px-3 py-2 md:text-right" data-label="الإجراءات">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <Form v-if="can('salaries.approve') && salary.status === 'draft'" :action="SalaryController.approve.url(salary.id)" method="post" v-slot="{ processing }" @success="() => toast.success('تم اعتماد الراتب بنجاح')" @error="() => toast.error('فشل في اعتماد الراتب')">
                                            <Button type="submit" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="processing">اعتماد</Button>
                                        </Form>
                                        <Form v-if="can('salaries.pay') && salary.status === 'approved'" :action="SalaryController.pay.url(salary.id)" method="post" v-slot="{ processing }" @success="() => toast.success('تم دفع الراتب بنجاح')" @error="() => toast.error('فشل في معالجة الدفع')">
                                            <Button type="submit" variant="clay" size="sm" class="h-8 px-3 text-xs" :disabled="processing"><DollarSign class="me-1 size-3" />دفع</Button>
                                        </Form>
                                        <Button v-if="can('salaries.delete') && salary.status !== 'paid'" type="button" variant="ghost" size="sm" class="h-8 px-3 text-xs text-destructive" @click="deleteSalary(salary)">حذف</Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="visibleSalaries.length === 0" class="table-empty-state">
                                <td :colspan="7" class="px-3 py-10 text-center text-muted-foreground">لا توجد رواتب.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2">
                    <p class="text-xs text-muted-foreground">عرض {{ salaries.meta.from ?? 0 }}-{{ salaries.meta.to ?? 0 }} من {{ salaries.meta.total }}</p>
                    <div class="flex items-center gap-2">
                        <Button type="button" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="localPage === 1" @click="goToPreviousPage">السابق</Button>
                        <span class="text-xs font-semibold text-foreground/85">صفحة {{ localPage }} / {{ totalLocalPages }}</span>
                        <Button type="button" variant="neumorphic" size="sm" class="h-8 px-3 text-xs" :disabled="localPage >= totalLocalPages" @click="goToNextPage">التالي</Button>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <ConfirmationDialog
        :open="isConfirmOpen"
        :options="confirmOptions"
        @confirm="handleConfirmDelete"
        @cancel="handleConfirmCancel"
        @update:open="handleConfirmCancel"
    />
</template>