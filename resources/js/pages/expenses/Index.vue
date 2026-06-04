<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, Plus, CheckCircle, XCircle } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ExpenseController from '@/actions/App/Http/Controllers/Expenses/ExpenseController';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';
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

type Expense = {
    id: number;
    description: string;
    amount: number;
    expense_date: string | null;
    status: 'pending' | 'approved' | 'rejected';
    category: {
        id: number;
        name: string;
    } | null;
    user: {
        id: number;
        name: string;
    } | null;
    approver: {
        id: number;
        name: string;
    } | null;
    approved_at: string | null;
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

type ExpenseSortField = 'amount' | 'expense_date' | 'status' | 'created_at';
type SortDirection = 'asc' | 'desc';
type StatusFilter = 'all' | 'pending' | 'approved' | 'rejected';

const { expenses, categories, filters } = defineProps<{
    expenses: PaginatedResponse<Expense>;
    categories: ExpenseCategory[];
    filters: {
        search: string | null;
        status: string | null;
        category_id: number | null;
        date_from: string | null;
        date_to: string | null;
        per_page: number;
        sort_by: ExpenseSortField | null;
        sort_direction: SortDirection | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'المصروفات',
                href: ExpenseController.index(),
            },
        ],
    },
});

const { can } = usePermissions();
const { isOpen: isConfirmOpen, options: confirmOptions, confirm, handleConfirm: handleConfirmDelete, handleCancel: handleConfirmCancel } = useConfirm();
const toast = useToast();
const page = usePage();

const roleNames = computed<string[]>(() => {
    return (
        ((page.props.auth as { roles?: string[] } | undefined)?.roles ?? [])
            .filter((value): value is string => typeof value === 'string')
    );
});

const primaryRole = computed<string>(() => {
    const rolePriority = [
        'super_admin',
        'admin',
        'clinic_admin',
        'doctor',
        'receptionist',
        'accountant',
    ];
    return rolePriority.find((role) => roleNames.value.includes(role)) ?? 'staff';
});

const roleLabels: Record<string, string> = {
    super_admin: 'مدير النظام',
    admin: 'مدير',
    clinic_admin: 'مدير العيادة',
    doctor: 'طبيب',
    receptionist: 'استقبال',
    accountant: 'محاسب',
    staff: 'موظف',
};

const activeRoleLabel = computed<string>(() => roleLabels[primaryRole.value] ?? roleLabels.staff);

const selectedExpenseIds = ref<number[]>([]);
const viewingExpense = ref<Expense | null>(null);
const editingExpense = ref<Expense | null>(null);
const isCreateSheetOpen = ref(false);

const localSearch = ref<string>(filters.search ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(expenses.meta.current_page);

const resolveInitialStatusFilter = (): StatusFilter => {
    const status = filters.status;
    if (status === 'pending' || status === 'approved' || status === 'rejected') {
        return status;
    }
    return 'all';
};

const localStatus = ref<StatusFilter>(resolveInitialStatusFilter());
const localCategoryId = ref<number | null>(filters.category_id ?? null);
const localDateFrom = ref<string>(filters.date_from ?? '');
const localDateTo = ref<string>(filters.date_to ?? '');

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
    { label: 'قيد الانتظار', value: 'pending' },
    { label: 'موافق عليه', value: 'approved' },
    { label: 'مرفوض', value: 'rejected' },
];

const categoryOptions = computed(() =>
    categories.map(cat => ({ label: cat.name, value: cat.id }))
);

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

const bulkDeleteForm = ExpenseController.bulkDestroy.form();

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
        date_from: string;
        date_to: string;
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
        date_from: (overrides.date_from ?? localDateFrom.value) || undefined,
        date_to: (overrides.date_to ?? localDateTo.value) || undefined,
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
        date_from: string;
        date_to: string;
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
            only: ['expenses', 'categories', 'filters'],
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
    if (status === 'approved') {
        return 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/40 dark:bg-success-500/15 dark:text-success-100';
    }
    if (status === 'rejected') {
        return 'border-destructive/70 bg-destructive/10 text-destructive dark:border-destructive/40 dark:bg-destructive/15 dark:text-destructive-foreground';
    }
    return 'border-warning-300/70 bg-warning-50 text-warning-800 dark:border-warning-500/40 dark:bg-warning-500/15 dark:text-warning-100';
};

const statusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        pending: 'قيد الانتظار',
        approved: 'موافق عليه',
        rejected: 'مرفوض',
    };
    return labels[status] ?? status;
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
    localDateFrom.value = '';
    localDateTo.value = '';
    localRowsPerPage.value = defaultRowsPerPage;
    localSortBy.value = 'created_at';
    localSortDirection.value = 'desc';
    localPage.value = 1;
    isSyncingFromServer.value = false;
    reloadExpenses({
        search: '',
        status: 'all',
        category_id: null,
        date_from: '',
        date_to: '',
        per_page: defaultRowsPerPage,
        page: 1,
        sort_by: 'created_at',
        sort_direction: 'desc',
    });
};

const deleteExpense = async (expense: Expense) => {
    const confirmed = await confirm({
        title: 'حذف المصروف',
        description: `هل أنت متأكد من حذف المصروف "${expense.description || expense.id}"؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });
    if (confirmed) {
        router.delete(ExpenseController.destroy(expense.id), {
            onSuccess: () => { toast.success('تم حذف المصروف بنجاح'); },
            onError: () => { toast.error('فشل حذف المصروف'); },
        });
    }
};

const handleBulkDelete = async () => {
    const confirmed = await confirm({
        title: 'حذف المصروفات',
        description: `هل أنت متأكد من حذف ${selectedExpenseIds.value.length} مصروف؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });
    if (confirmed) {
        router.delete(ExpenseController.bulkDestroy.url(), {
            data: { ids: selectedExpenseIds.value },
            onSuccess: () => {
                clearSelectedExpenses();
                toast.success(`تم حذف ${selectedExpenseIds.value.length} مصروف بنجاح`);
            },
            onError: () => { toast.error('فشل حذف المصروفات'); },
        });
    }
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

onBeforeUnmount(() => {
    if (expenseFiltersDebounceTimeout !== null) clearTimeout(expenseFiltersDebounceTimeout);
});

watch(selectableExpenseIds, (ids) => {
    selectedExpenseIds.value = selectedExpenseIds.value.filter((id) => ids.includes(id));
});
</script>

<template>
    <Head title="المصروفات" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="page-title">المصروفات</h1>
                    <p class="mt-1 text-sm text-muted-foreground">تتبع وإدارة مصروفات العيادة والموافقة عليها.</p>
                </div>
                <span class="inline-flex items-center rounded-full border border-border/60 bg-muted/40 px-2.5 py-0.5 text-[0.7rem] font-medium text-muted-foreground">
                    {{ activeRoleLabel }}
                </span>
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
                    تسجيل مصروف
                </Button>
            </div>
        </div>

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
            :local-date-from="localDateFrom"
            :local-date-to="localDateTo"
            :local-rows-per-page="localRowsPerPage"
            :selected-ids="selectedExpenseIds"
            :are-all-selected="areAllExpensesSelected"
            :can-delete="can('expenses.delete')"
            :can-view="can('expenses.view')"
            :can-update="can('expenses.update')"
            :can-approve="can('expenses.approve')"
            :active-filters="activeFilters"
            :status-options="statusOptions"
            :category-options="categoryOptions"
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
            @update:open="isCreateSheetOpen = $event"
        />

        <ExpenseViewDialog
            :expense="viewingExpense"
            @close="viewingExpense = null"
        />

        <ExpenseEditDialog
            :expense="editingExpense"
            :categories="categories"
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
