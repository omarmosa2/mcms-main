<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, Plus, Download, FileSpreadsheet } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ExpenseController from '@/actions/App/Http/Controllers/Expenses/ExpenseController';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';
import { useMoneyFormatter } from '@/lib/money';
import ExpenseViewDialog from './components/ExpenseViewDialog.vue';
import ExpenseEditDialog from './components/ExpenseEditDialog.vue';
import ExpenseCreateSheet from './components/ExpenseCreateSheet.vue';
import ExpenseTable from './components/ExpenseTable.vue';

type ExpenseCategory = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
};

type Clinic = {
    id: number;
    name: string;
};

type Expense = {
    id: number;
    expense_number: string | null;
    title: string;
    description: string | null;
    amount: number;
    expense_date: string | null;
    status: 'pending' | 'paid' | 'cancelled';
    payment_method: string | null;
    paid_to: string | null;
    reference_number: string | null;
    attachment_path: string | null;
    category: {
        id: number;
        name: string;
    } | null;
    clinic: {
        id: number;
        name: string;
    } | null;
    user: {
        id: number;
        name: string;
    } | null;
    creator: {
        id: number;
        name: string;
    } | null;
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

type ExpenseSortField = 'amount' | 'expense_date' | 'status' | 'created_at';
type SortDirection = 'asc' | 'desc';
type StatusFilter = 'all' | 'pending' | 'paid' | 'cancelled';

const { expenses, categories, filters, stats, clinics } = defineProps<{
    expenses: PaginatedResponse<Expense>;
    categories: ExpenseCategory[];
    filters: {
        search: string | null;
        status: string | null;
        category_id: number | null;
        clinic_id: number | null;
        date_from: string | null;
        date_to: string | null;
        payment_method: string | null;
        per_page: number;
        sort_by: ExpenseSortField | null;
        sort_direction: SortDirection | null;
    };
    stats: {
        total_expenses: number;
        monthly_expenses: number;
        paid_expenses: number;
        pending_expenses: number;
        expenses_count: number;
        top_category: { name: string; total: number } | null;
    };
    clinics: Clinic[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'المصاريف',
                href: ExpenseController.index(),
            },
        ],
    },
});

const { can } = usePermissions();
const { isOpen: isConfirmOpen, options: confirmOptions, confirm, close: closeConfirm, handleConfirm: handleConfirmDelete, handleCancel: handleConfirmCancel } = useConfirm();
const toast = useToast();
const { formatMoney } = useMoneyFormatter();

const selectedExpenseIds = ref<number[]>([]);
const viewingExpense = ref<Expense | null>(null);
const editingExpense = ref<Expense | null>(null);
const isCreateSheetOpen = ref(false);

const localSearch = ref<string>(filters.search ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(expenses.meta.current_page);

const resolveInitialStatusFilter = (): StatusFilter => {
    const status = filters.status;
    if (status === 'pending' || status === 'paid' || status === 'cancelled') {
        return status;
    }
    return 'all';
};

const localStatus = ref<StatusFilter>(resolveInitialStatusFilter());
const localCategoryId = ref<number | null>(filters.category_id ?? null);
const localClinicId = ref<number | null>(filters.clinic_id ?? null);
const localDateFrom = ref<string>(filters.date_from ?? '');
const localDateTo = ref<string>(filters.date_to ?? '');
const localPaymentMethod = ref<string>(filters.payment_method ?? '');

const allowedSortFields: ExpenseSortField[] = ['amount', 'expense_date', 'status', 'created_at'];

const resolveInitialSortBy = (): ExpenseSortField => {
    const sortBy = filters.sort_by;
    if (sortBy !== null && allowedSortFields.includes(sortBy as ExpenseSortField)) {
        return sortBy;
    }
    return 'created_at';
};

const localSortBy = ref<ExpenseSortField>(resolveInitialSortBy());
const localSortDirection = ref<SortDirection>(
    filters.sort_direction === 'asc' ? 'asc' : 'desc',
);

const statusOptions = [
    { label: 'الكل', value: 'all' },
    { label: 'معلق', value: 'pending' },
    { label: 'مدفوع', value: 'paid' },
    { label: 'ملغي', value: 'cancelled' },
];

const paymentMethodOptions = [
    { label: 'الكل', value: '' },
    { label: 'نقداً', value: 'cash' },
    { label: 'تحويل', value: 'transfer' },
    { label: 'بطاقة', value: 'card' },
    { label: 'أخرى', value: 'other' },
];

const categoryOptions = computed(() =>
    categories.map(cat => ({ label: cat.name, value: cat.id }))
);

const clinicOptions = computed(() => [
    { label: 'الكل', value: null as number | null },
    ...clinics.map(c => ({ label: c.name, value: c.id })),
]);

const activeFilters = computed(() => {
    const filters: { key: string; label: string; value: string | null }[] = [];
    if (localSearch.value.trim()) {
        filters.push({ key: 'search', label: 'بحث', value: localSearch.value.trim() });
    }
    if (localStatus.value !== 'all') {
        filters.push({ key: 'status', label: 'الحالة', value: statusLabel(localStatus.value) });
    }
    if (localCategoryId.value) {
        const cat = categories.find(c => c.id === localCategoryId.value);
        filters.push({ key: 'category_id', label: 'التصنيف', value: cat?.name || String(localCategoryId.value) });
    }
    if (localClinicId.value) {
        const clinic = clinics.find(c => c.id === localClinicId.value);
        filters.push({ key: 'clinic_id', label: 'العيادة', value: clinic?.name || String(localClinicId.value) });
    }
    if (localPaymentMethod.value) {
        const pm = paymentMethodOptions.find(p => p.value === localPaymentMethod.value);
        filters.push({ key: 'payment_method', label: 'طريقة الدفع', value: pm?.label || localPaymentMethod.value });
    }
    if (localDateFrom.value || localDateTo.value) {
        const dateLabel = localDateFrom.value && localDateTo.value
            ? `${localDateFrom.value} - ${localDateTo.value}`
            : localDateFrom.value || localDateTo.value;
        filters.push({ key: 'date', label: 'نطاق التاريخ', value: dateLabel });
    }
    return filters;
});

const goToPreviousPage = () => {
    if (localPage.value <= 1) return;
    localPage.value -= 1;
    reloadExpenses({ page: localPage.value });
};

const goToNextPage = () => {
    if (localPage.value >= totalLocalPages.value) return;
    localPage.value += 1;
    reloadExpenses({ page: localPage.value });
};

const visibleExpenses = computed<Expense[]>(() => expenses.data);
const totalLocalPages = computed<number>(() => Math.max(1, expenses.meta.last_page));
const localVisibleFrom = computed<number>(() => expenses.meta.from ?? 0);
const localVisibleTo = computed<number>(() => expenses.meta.to ?? 0);

let expenseFiltersDebounceTimeout: ReturnType<typeof setTimeout> | null = null;

const buildIndexQuery = (
    overrides: Partial<{
        search: string;
        status: StatusFilter;
        category_id: number | null;
        clinic_id: number | null;
        date_from: string;
        date_to: string;
        payment_method: string;
        per_page: number;
        page: number;
        sort_by: ExpenseSortField;
        sort_direction: SortDirection;
    }> = {},
) => {
    const statusQuery = (overrides.status ?? localStatus.value) === 'all'
        ? undefined
        : overrides.status ?? localStatus.value;
    return {
        search: (overrides.search ?? localSearch.value).trim(),
        status: statusQuery,
        category_id: overrides.category_id ?? localCategoryId.value ?? undefined,
        clinic_id: overrides.clinic_id ?? localClinicId.value ?? undefined,
        date_from: (overrides.date_from ?? localDateFrom.value) || undefined,
        date_to: (overrides.date_to ?? localDateTo.value) || undefined,
        payment_method: (overrides.payment_method ?? localPaymentMethod.value) || undefined,
        per_page: overrides.per_page ?? localRowsPerPage.value,
        page: overrides.page ?? localPage.value,
        sort_by: overrides.sort_by ?? localSortBy.value,
        sort_direction: overrides.sort_direction ?? localSortDirection.value,
    };
};

const reloadExpenses = (
    overrides: Partial<{
        search: string;
        status: StatusFilter;
        category_id: number | null;
        clinic_id: number | null;
        date_from: string;
        date_to: string;
        payment_method: string;
        per_page: number;
        page: number;
        sort_by: ExpenseSortField;
        sort_direction: SortDirection;
    }> = {},
    debounce = false,
) => {
    if (isSyncingFromServer.value) return;
    const executeReload = () => {
        router.cancelAll();
        router.get(ExpenseController.index.url(), buildIndexQuery(overrides), {
            only: ['expenses', 'categories', 'filters', 'stats'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };
    if (debounce) {
        if (expenseFiltersDebounceTimeout !== null) clearTimeout(expenseFiltersDebounceTimeout);
        expenseFiltersDebounceTimeout = setTimeout(executeReload, 300);
        return;
    }
    executeReload();
};

const selectableExpenseIds = computed<number[]>(() =>
    visibleExpenses.value.map((expense) => expense.id),
);

const areAllExpensesSelected = computed<boolean>(() => {
    if (selectableExpenseIds.value.length === 0) return false;
    return selectableExpenseIds.value.every((id) =>
        selectedExpenseIds.value.includes(id),
    );
});

const toggleAllExpensesSelection = (event: Event) => {
    const target = event.target as HTMLInputElement;
    selectedExpenseIds.value = target.checked
        ? [...selectableExpenseIds.value]
        : [];
};

const clearSelectedExpenses = () => {
    selectedExpenseIds.value = [];
};

const statusClass = (status: string): string => {
    if (status === 'paid') {
        return 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/40 dark:bg-success-500/15 dark:text-success-100';
    }
    if (status === 'cancelled') {
        return 'border-destructive/70 bg-destructive/10 text-destructive dark:border-destructive/40 dark:bg-destructive/15 dark:text-destructive-foreground';
    }
    return 'border-warning-300/70 bg-warning-50 text-warning-800 dark:border-warning-500/40 dark:bg-warning-500/15 dark:text-warning-100';
};

const statusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        pending: 'معلق',
        paid: 'مدفوع',
        cancelled: 'ملغي',
    };
    return labels[status] ?? status;
};

const paymentMethodLabel = (method: string | null): string => {
    if (!method) return '-';
    const labels: Record<string, string> = {
        cash: 'نقداً',
        transfer: 'تحويل',
        card: 'بطاقة',
        other: 'أخرى',
    };
    return labels[method] ?? method;
};

const isSyncingFromServer = ref(false);
const defaultRowsPerPage = 15;

const sortIconFor = (field: ExpenseSortField) => {
    if (localSortBy.value !== field) return ArrowUpDown;
    return localSortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

const toggleSort = (field: ExpenseSortField) => {
    if (localSortBy.value === field) {
        localSortDirection.value = localSortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        localSortBy.value = field;
        localSortDirection.value = 'asc';
    }
};

const resetLocalFilters = () => {
    isSyncingFromServer.value = true;
    localSearch.value = '';
    localStatus.value = 'all';
    localCategoryId.value = null;
    localClinicId.value = null;
    localDateFrom.value = '';
    localDateTo.value = '';
    localPaymentMethod.value = '';
    localRowsPerPage.value = defaultRowsPerPage;
    localSortBy.value = 'created_at';
    localSortDirection.value = 'desc';
    localPage.value = 1;
    isSyncingFromServer.value = false;
    reloadExpenses({
        search: '',
        status: 'all',
        category_id: null,
        clinic_id: null,
        date_from: '',
        date_to: '',
        payment_method: '',
        per_page: defaultRowsPerPage,
        page: 1,
        sort_by: 'created_at',
        sort_direction: 'desc',
    });
};

const deleteExpense = async (expense: Expense) => {
    const confirmed = await confirm({
        title: 'حذف المصروف',
        description: `هل أنت متأكد من حذف المصروف "${expense.title || expense.expense_number}" بمبلغ ${formatMoney(expense.amount)}؟`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });
    if (confirmed) {
        router.delete(ExpenseController.destroy(expense.id), {
            onSuccess: () => {
                closeConfirm();
                toast.success('تم حذف المصروف بنجاح');
            },
            onError: () => { toast.error('فشل حذف المصروف'); },
        });
    }
};

const handleBulkDelete = async () => {
    const confirmed = await confirm({
        title: 'حذف المصروفات',
        description: `هل أنت متأكد من حذف ${selectedExpenseIds.value.length} مصروف؟`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });
    if (confirmed) {
        router.delete(ExpenseController.bulkDestroy.url(), {
            data: { ids: selectedExpenseIds.value },
            onSuccess: () => {
                closeConfirm();
                clearSelectedExpenses();
                toast.success(`تم حذف ${selectedExpenseIds.value.length} مصروف بنجاح`);
            },
            onError: () => { toast.error('فشل حذف المصروفات'); },
        });
    }
};

const openViewExpense = (expense: Expense) => {
    viewingExpense.value = expense;
};

const openEditExpense = (expense: Expense) => {
    editingExpense.value = expense;
};

const handleRemoveFilter = (key: string) => {
    if (key === 'search') localSearch.value = '';
    if (key === 'status') localStatus.value = 'all';
    if (key === 'category_id') localCategoryId.value = null;
    if (key === 'clinic_id') localClinicId.value = null;
    if (key === 'payment_method') localPaymentMethod.value = '';
    if (key === 'date') { localDateFrom.value = ''; localDateTo.value = ''; }
    localPage.value = 1;
    reloadExpenses({ page: 1 });
};

const exportExcel = () => {
    const params = new URLSearchParams();
    params.set('export', 'excel');
    if (localSearch.value.trim()) params.set('search', localSearch.value.trim());
    if (localStatus.value !== 'all') params.set('status', localStatus.value);
    if (localCategoryId.value) params.set('category_id', String(localCategoryId.value));
    if (localClinicId.value) params.set('clinic_id', String(localClinicId.value));
    if (localDateFrom.value) params.set('date_from', localDateFrom.value);
    if (localDateTo.value) params.set('date_to', localDateTo.value);
    if (localPaymentMethod.value) params.set('payment_method', localPaymentMethod.value);
    window.open(`${ExpenseController.index.url()}?${params.toString()}`, '_blank');
};

watch(
    () => [filters.search, filters.status, filters.per_page, expenses.meta.current_page],
    () => {
        isSyncingFromServer.value = true;
        localSearch.value = filters.search ?? '';
        localRowsPerPage.value = filters.per_page;
        localPage.value = expenses.meta.current_page;
        isSyncingFromServer.value = false;
    },
    { immediate: true },
);

watch(localSearch, () => {
    localPage.value = 1;
    reloadExpenses({ page: 1, search: localSearch.value.trim() }, true);
});

watch(localRowsPerPage, () => {
    localPage.value = 1;
    reloadExpenses({ page: 1, per_page: localRowsPerPage.value });
});

watch([localSortBy, localSortDirection], () => {
    localPage.value = 1;
    reloadExpenses({ page: 1, sort_by: localSortBy.value, sort_direction: localSortDirection.value });
});

watch(localStatus, () => {
    localPage.value = 1;
    reloadExpenses({ page: 1, status: localStatus.value });
});

watch(localCategoryId, () => {
    localPage.value = 1;
    reloadExpenses({ page: 1, category_id: localCategoryId.value });
});

watch(localClinicId, () => {
    localPage.value = 1;
    reloadExpenses({ page: 1, clinic_id: localClinicId.value });
});

watch(localPaymentMethod, () => {
    localPage.value = 1;
    reloadExpenses({ page: 1, payment_method: localPaymentMethod.value });
});

onBeforeUnmount(() => {
    if (expenseFiltersDebounceTimeout !== null) clearTimeout(expenseFiltersDebounceTimeout);
});

watch(selectableExpenseIds, (ids) => {
    selectedExpenseIds.value = selectedExpenseIds.value.filter((id) => ids.includes(id));
});
</script>

<template>
    <Head title="المصاريف" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="page-title">المصاريف</h1>
                <p class="mt-1 text-sm text-muted-foreground">إدارة مصاريف المجمع الطبي وتتبع الخرج التشغيلي والإداري.</p>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="can('expenses.create')"
                    variant="clay"
                    size="sm"
                    class="h-8 rounded-lg px-3 text-xs"
                    @click="isCreateSheetOpen = true"
                >
                    <Plus class="size-3.5" />
                    إضافة مصروف
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 rounded-lg px-3 text-xs"
                    @click="exportExcel"
                >
                    <FileSpreadsheet class="size-3.5" />
                    تصدير Excel
                </Button>
            </div>
        </div>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <article class="rounded-xl border border-rose-500/20 bg-rose-500/5 p-4">
                <p class="text-xs font-bold text-muted-foreground">إجمالي المصاريف</p>
                <p class="mt-2 text-xl font-black text-rose-700 tabular-nums">{{ formatMoney(stats.total_expenses) }}</p>
            </article>
            <article class="rounded-xl border border-orange-500/20 bg-orange-500/5 p-4">
                <p class="text-xs font-bold text-muted-foreground">مصاريف الشهر الحالي</p>
                <p class="mt-2 text-xl font-black text-orange-700 tabular-nums">{{ formatMoney(stats.monthly_expenses) }}</p>
            </article>
            <article class="rounded-xl border border-success-500/20 bg-success-500/5 p-4">
                <p class="text-xs font-bold text-muted-foreground">المصاريف المدفوعة</p>
                <p class="mt-2 text-xl font-black text-success-700 tabular-nums">{{ formatMoney(stats.paid_expenses) }}</p>
            </article>
            <article class="rounded-xl border border-warning-500/20 bg-warning-500/5 p-4">
                <p class="text-xs font-bold text-muted-foreground">المصاريف المعلقة</p>
                <p class="mt-2 text-xl font-black text-warning-700 tabular-nums">{{ formatMoney(stats.pending_expenses) }}</p>
            </article>
            <article class="rounded-xl border border-violet-500/20 bg-violet-500/5 p-4">
                <p class="text-xs font-bold text-muted-foreground">أكبر تصنيف</p>
                <p class="mt-2 text-sm font-bold text-violet-700">{{ stats.top_category?.name ?? '-' }}</p>
                <p class="text-xs text-muted-foreground">{{ formatMoney(stats.top_category?.total ?? 0) }}</p>
            </article>
            <article class="rounded-xl border border-border bg-card/95 p-4">
                <p class="text-xs font-bold text-muted-foreground">عدد المصاريف</p>
                <p class="mt-2 text-xl font-black text-foreground tabular-nums">{{ stats.expenses_count }}</p>
            </article>
        </section>

        <ExpenseTable
            :expenses="visibleExpenses"
            :visible-from="localVisibleFrom"
            :visible-to="localVisibleTo"
            :total="expenses.meta.total"
            :local-page="localPage"
            :total-pages="totalLocalPages"
            :local-search="localSearch"
            :local-status="localStatus"
            :local-category-id="localCategoryId"
            :local-clinic-id="localClinicId"
            :local-date-from="localDateFrom"
            :local-date-to="localDateTo"
            :local-payment-method="localPaymentMethod"
            :local-rows-per-page="localRowsPerPage"
            :selected-ids="selectedExpenseIds"
            :are-all-selected="areAllExpensesSelected"
            :can-delete="can('expenses.delete')"
            :can-view="can('expenses.view')"
            :can-update="can('expenses.update')"
            :active-filters="activeFilters"
            :status-options="statusOptions"
            :category-options="categoryOptions"
            :clinic-options="clinicOptions"
            :payment-method-options="paymentMethodOptions"
            :sort-by="localSortBy"
            :sort-direction="localSortDirection"
            @toggle-sort="toggleSort"
            @toggle-all-selection="toggleAllExpensesSelection"
            @change-page="(page) => { localPage.value = page; reloadExpenses({ page }); }"
            @change-rows-per-page="(v) => { localRowsPerPage.value = v; }"
            @open-view="openViewExpense"
            @open-edit="openEditExpense"
            @delete-expense="deleteExpense"
            @remove-filter="handleRemoveFilter"
            @clear-filters="resetLocalFilters"
        />

        <ExpenseCreateSheet
            :open="isCreateSheetOpen"
            :categories="categories"
            :clinics="clinics"
            @update:open="isCreateSheetOpen = $event"
        />

        <ExpenseViewDialog
            :expense="viewingExpense"
            @close="viewingExpense = null"
        />

        <ExpenseEditDialog
            :expense="editingExpense"
            :categories="categories"
            :clinics="clinics"
            @close="editingExpense = null"
        />

        <ConfirmationDialog
            :open="isConfirmOpen"
            :options="confirmOptions"
            @confirm="handleConfirmDelete"
            @cancel="handleConfirmCancel"
            @update:open="handleConfirmCancel"
        />
    </div>
</template>
