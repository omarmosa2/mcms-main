<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, Plus, CheckCircle, XCircle } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ExpenseController from '@/actions/App/Http/Controllers/Expenses/ExpenseController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    FilterBar,
    FilterSearch,
    FilterSelect,
    FilterDateRange,
} from '@/components/ui/filter';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';

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

const visibleExpenses = computed<Expense[]>(() => expenses.data);
const totalLocalPages = computed<number>(() => Math.max(1, expenses.meta.last_page));
const localVisibleFrom = computed<number>(() => expenses.meta.from ?? 0);
const localVisibleTo = computed<number>(() => expenses.meta.to ?? 0);

const defaultRowsPerPage = 15;
const isSyncingFromServer = ref(false);
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
    if (isSyncingFromServer.value) {
        return;
    }

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
        if (expenseFiltersDebounceTimeout !== null) {
            clearTimeout(expenseFiltersDebounceTimeout);
        }

        expenseFiltersDebounceTimeout = setTimeout(executeReload, 300);

        return;
    }

    executeReload();
};

const sortIconFor = (field: ExpenseSortField) => {
    if (localSortBy.value !== field) {
        return ArrowUpDown;
    }

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

const goToPreviousPage = () => {
    if (localPage.value <= 1) {
        return;
    }

    localPage.value -= 1;
    reloadExpenses({ page: localPage.value });
};

const goToNextPage = () => {
    if (localPage.value >= totalLocalPages.value) {
        return;
    }

    localPage.value += 1;
    reloadExpenses({ page: localPage.value });
};

watch(
    () => [
        filters.search,
        filters.status,
        filters.per_page,
        expenses.meta.current_page,
    ],
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
    reloadExpenses({
        page: 1,
        sort_by: localSortBy.value,
        sort_direction: localSortDirection.value,
    });
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
    if (expenseFiltersDebounceTimeout !== null) {
        clearTimeout(expenseFiltersDebounceTimeout);
    }
});

const selectableExpenseIds = computed<number[]>(() =>
    visibleExpenses.value.map((expense) => expense.id),
);

const areAllExpensesSelected = computed<boolean>(() => {
    if (selectableExpenseIds.value.length === 0) {
        return false;
    }

    return selectableExpenseIds.value.every((id) =>
        selectedExpenseIds.value.includes(id),
    );
});

watch(selectableExpenseIds, (ids) => {
    selectedExpenseIds.value = selectedExpenseIds.value.filter((id) =>
        ids.includes(id),
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

const formatAmount = (amount: number | string): string => {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(num);
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

const statusOptions = [
    { label: 'الكل', value: 'all' },
    { label: 'قيد الانتظار', value: 'pending' },
    { label: 'موافق عليه', value: 'approved' },
    { label: 'مرفوض', value: 'rejected' },
];

const categoryOptions = computed(() =>
    categories.map(cat => ({ label: cat.name, value: cat.id }))
);

const handleRemoveFilter = (key: string) => {
    if (key === 'search') {
        localSearch.value = '';
    } else if (key === 'status') {
        localStatus.value = 'all';
    } else if (key === 'category_id') {
        localCategoryId.value = null;
    } else if (key === 'date') {
        localDateFrom.value = '';
        localDateTo.value = '';
    }
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
            onSuccess: () => {
                toast.success('تم حذف المصروف بنجاح');
            },
            onError: () => {
                toast.error('فشل حذف المصروف');
            },
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
            onError: () => {
                toast.error('فشل حذف المصروفات');
            },
        });
    }
};

const openViewExpense = (expense: Expense) => {
    viewingExpense.value = expense;
};

const closeViewExpense = () => {
    viewingExpense.value = null;
};

const openEditExpense = (expense: Expense) => {
    editingExpense.value = expense;
};

const closeEditExpense = () => {
    editingExpense.value = null;
};
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

        <div class="glass-panel-soft p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                <h3 class="pattern-typographic-title text-[0.76rem]">قائمة المصروفات</h3>
                <span class="text-xs text-muted-foreground">الإجمالي: {{ expenses.meta.total }}</span>
            </div>

            <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
                <div class="grid gap-3 md:grid-cols-[repeat(4,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2">
                        <Label for="expenses_search">بحث</Label>
                        <FilterSearch
                            id="expenses_search"
                            v-model="localSearch"
                            placeholder="الوصف..."
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="expenses_status">الحالة</Label>
                        <FilterSelect
                            id="expenses_status"
                            v-model="localStatus"
                            :options="statusOptions"
                            placeholder="الكل"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="expenses_category">التصنيف</Label>
                        <FilterSelect
                            id="expenses_category"
                            v-model="localCategoryId"
                            :options="categoryOptions"
                            placeholder="الكل"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="expenses_date">نطاق التاريخ</Label>
                        <FilterDateRange
                            v-model:from="localDateFrom"
                            v-model:to="localDateTo"
                        />
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-[repeat(2,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2">
                        <Label for="expenses_per_page">صفوف</Label>
                        <select
                            id="expenses_per_page"
                            v-model.number="localRowsPerPage"
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>

                <FilterBar
                    v-if="activeFilters.length > 0"
                    :active-filters="activeFilters"
                    @remove="handleRemoveFilter"
                    @clear-all="resetLocalFilters"
                />
            </div>

            <Form
                v-if="can('expenses.delete') && selectedExpenseIds.length > 0"
                v-bind="ExpenseController.bulkDestroy.form()"
                class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3"
                v-slot="{ processing }"
                @submit.prevent="handleBulkDelete"
            >
                <input
                    v-for="expenseId in selectedExpenseIds"
                    :key="`selected-expense-${expenseId}`"
                    type="hidden"
                    name="ids[]"
                    :value="expenseId"
                />
                <Button
                    type="submit"
                    variant="destructive"
                    size="sm"
                    :disabled="processing"
                >
                    حذف المحدد ({{ selectedExpenseIds.length }})
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="clearSelectedExpenses"
                >
                    إلغاء التحديد
                </Button>
            </Form>

            <div class="ui-table-shell">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th v-if="can('expenses.delete')" class="px-3 py-2">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-border"
                                    :checked="areAllExpensesSelected"
                                    @change="toggleAllExpensesSelection"
                                />
                            </th>
                            <th class="px-3 py-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="toggleSort('expense_date')"
                                >
                                    التاريخ
                                    <component :is="sortIconFor('expense_date')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2">الوصف</th>
                            <th class="px-3 py-2">التصنيف</th>
                            <th class="px-3 py-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="toggleSort('amount')"
                                >
                                    المبلغ
                                    <component :is="sortIconFor('amount')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="toggleSort('status')"
                                >
                                    الحالة
                                    <component :is="sortIconFor('status')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2 text-right">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="expense in visibleExpenses"
                            :key="expense.id"
                            class="ui-table-row"
                        >
                            <td v-if="can('expenses.delete')" class="px-3 py-2" data-label="تحديد">
                                <input
                                    v-model="selectedExpenseIds"
                                    type="checkbox"
                                    class="size-4 rounded border-border"
                                    :value="expense.id"
                                />
                            </td>
                            <td class="px-3 py-2" data-label="التاريخ">
                                {{ expense.expense_date ?? '-' }}
                            </td>
                            <td class="px-3 py-2 font-medium" data-label="الوصف">
                                {{ expense.description }}
                            </td>
                            <td class="px-3 py-2" data-label="التصنيف">
                                <span v-if="expense.category" class="text-sm">
                                    {{ expense.category.name }}
                                </span>
                                <span v-else class="text-muted-foreground">-</span>
                            </td>
                            <td class="px-3 py-2 font-mono font-semibold" data-label="المبلغ">
                                {{ formatAmount(expense.amount) }}
                            </td>
                            <td class="px-3 py-2" data-label="الحالة">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                                    :class="statusClass(expense.status)"
                                >
                                    <CheckCircle v-if="expense.status === 'approved'" class="size-3" />
                                    <XCircle v-else-if="expense.status === 'rejected'" class="size-3" />
                                    {{ statusLabel(expense.status) }}
                                </span>
                            </td>
                            <td class="table-cell-actions px-3 py-2 md:text-right" data-label="الإجراءات">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <Button
                                        v-if="can('expenses.view')"
                                        type="button"
                                        variant="neumorphic"
                                        size="sm"
                                        class="h-8 px-3 text-xs"
                                        @click="openViewExpense(expense)"
                                    >
                                        عرض
                                    </Button>
                                    <Button
                                        v-if="can('expenses.update')"
                                        type="button"
                                        variant="clay"
                                        size="sm"
                                        class="h-8 px-3 text-xs"
                                        @click="openEditExpense(expense)"
                                    >
                                        تعديل
                                    </Button>
                                    <Form
                                        v-if="can('expenses.approve') && expense.status === 'pending'"
                                        :action="ExpenseController.approve.url(expense.id)"
                                        method="post"
                                        v-slot="{ processing }"
                                        @success="() => toast.success('تمت الموافقة على المصروف')"
                                        @error="() => toast.error('فشل الموافقة على المصروف')"
                                    >
                                        <Button
                                            type="submit"
                                            variant="neumorphic"
                                            size="sm"
                                            class="h-8 px-3 text-xs"
                                            :disabled="processing"
                                        >
                                            موافقة
                                        </Button>
                                    </Form>
                                    <Form
                                        v-if="can('expenses.approve') && expense.status === 'pending'"
                                        :action="ExpenseController.reject.url(expense.id)"
                                        method="post"
                                        v-slot="{ processing }"
                                        @success="() => toast.success('تم رفض المصروف')"
                                        @error="() => toast.error('فشل رفض المصروف')"
                                    >
                                        <Button
                                            type="submit"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 px-3 text-xs text-destructive"
                                            :disabled="processing"
                                        >
                                            رفض
                                        </Button>
                                    </Form>
                                    <Button
                                        v-if="can('expenses.delete')"
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        class="h-8 px-3 text-xs"
                                        @click="deleteExpense(expense)"
                                    >
                                        حذف
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="visibleExpenses.length === 0" class="table-empty-state">
                            <td
                                :colspan="can('expenses.delete') ? 7 : 6"
                                class="px-3 py-10 text-center text-muted-foreground"
                            >
                                لا توجد مصروفات.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2">
                <p class="text-xs text-muted-foreground">
                    عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من {{ expenses.meta.total }}
                </p>
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="neumorphic"
                        size="sm"
                        class="h-8 px-3 text-xs"
                        :disabled="localPage === 1"
                        @click="goToPreviousPage"
                    >
                        السابق
                    </Button>
                    <span class="text-xs font-semibold text-foreground/85">
                        صفحة {{ localPage }} / {{ totalLocalPages }}
                    </span>
                    <Button
                        type="button"
                        variant="neumorphic"
                        size="sm"
                        class="h-8 px-3 text-xs"
                        :disabled="localPage >= totalLocalPages"
                        @click="goToNextPage"
                    >
                        التالي
                    </Button>
                </div>
            </div>
        </div>

        <Sheet :open="isCreateSheetOpen" @update:open="isCreateSheetOpen = $event">
            <SheetContent side="left" class="w-full sm:max-w-lg">
                <SheetHeader class="text-right">
                    <SheetTitle>تسجيل مصروف</SheetTitle>
                    <SheetDescription>تسجيل مصروف جديد.</SheetDescription>
                </SheetHeader>

                <Form
                    v-bind="ExpenseController.store.form()"
                    class="mt-6 space-y-4"
                    reset-on-success
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="description">الوصف</Label>
                        <Input
                            id="description"
                            name="description"
                            required
                            placeholder="مستلزمات مكتبية"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="amount">المبلغ</Label>
                        <Input
                            id="amount"
                            name="amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                            placeholder="0.00"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.amount" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="category_id">التصنيف</Label>
                        <select
                            id="category_id"
                            name="category_id"
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="">اختر تصنيفاً</option>
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                        <InputError :message="errors.category_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="expense_date">تاريخ المصروف</Label>
                        <Input
                            id="expense_date"
                            name="expense_date"
                            type="date"
                            required
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.expense_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">ملاحظات</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="2"
                            class="pattern-field-clay"
                            placeholder="ملاحظات اختيارية"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <Button
                        :disabled="processing"
                        variant="clay"
                        class="w-full"
                    >
                        <Plus class="ml-2 size-4" />
                        تسجيل مصروف
                    </Button>
                </Form>
            </SheetContent>
        </Sheet>

        <Dialog :open="viewingExpense !== null" @update:open="(open) => !open && closeViewExpense()">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>تفاصيل المصروف</DialogTitle>
                    <DialogDescription>{{ viewingExpense?.description }}</DialogDescription>
                </DialogHeader>

                <div v-if="viewingExpense" class="grid gap-4">
                    <dl class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4 sm:grid-cols-2">
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">الوصف</dt>
                            <dd class="text-sm">{{ viewingExpense.description }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">المبلغ</dt>
                            <dd class="font-mono text-sm font-semibold">{{ formatAmount(viewingExpense.amount) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">التاريخ</dt>
                            <dd class="text-sm">{{ viewingExpense.expense_date ?? '-' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">التصنيف</dt>
                            <dd class="text-sm">{{ viewingExpense.category?.name ?? '-' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">الحالة</dt>
                            <dd>
                                <span :class="statusClass(viewingExpense.status)" class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize">
                                    {{ statusLabel(viewingExpense.status) }}
                                </span>
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">سجل بواسطة</dt>
                            <dd class="text-sm">{{ viewingExpense.user?.name ?? '-' }}</dd>
                        </div>
                        <div v-if="viewingExpense.approver" class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">وافق بواسطة</dt>
                            <dd class="text-sm">{{ viewingExpense.approver.name }}</dd>
                        </div>
                        <div v-if="viewingExpense.approved_at" class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">تاريخ الموافقة</dt>
                            <dd class="text-sm">{{ viewingExpense.approved_at }}</dd>
                        </div>
                        <div v-if="viewingExpense.notes" class="space-y-1 sm:col-span-2">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">ملاحظات</dt>
                            <dd class="text-sm leading-6 text-muted-foreground">{{ viewingExpense.notes }}</dd>
                        </div>
                    </dl>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="closeViewExpense()">إغلاق</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="editingExpense !== null" @update:open="(open) => !open && closeEditExpense()">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>تعديل المصروف</DialogTitle>
                    <DialogDescription>تعديل بيانات المصروف.</DialogDescription>
                </DialogHeader>

                <Form
                    v-if="editingExpense"
                    v-bind="ExpenseController.update.form(editingExpense.id)"
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="edit_description">الوصف</Label>
                        <Input
                            id="edit_description"
                            name="description"
                            :default-value="editingExpense.description"
                            required
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit_amount">المبلغ</Label>
                        <Input
                            id="edit_amount"
                            name="amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :default-value="editingExpense.amount"
                            required
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.amount" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit_category_id">التصنيف</Label>
                        <select
                            id="edit_category_id"
                            name="category_id"
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="">اختر تصنيفاً</option>
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                                :selected="editingExpense.category?.id === category.id"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                        <InputError :message="errors.category_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit_expense_date">تاريخ المصروف</Label>
                        <Input
                            id="edit_expense_date"
                            name="expense_date"
                            type="date"
                            :default-value="editingExpense.expense_date ?? ''"
                            required
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.expense_date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit_notes">ملاحظات</Label>
                        <textarea
                            id="edit_notes"
                            name="notes"
                            rows="2"
                            class="pattern-field-clay"
                            placeholder="ملاحظات اختيارية"
                        >{{ editingExpense.notes ?? '' }}</textarea>
                        <InputError :message="errors.notes" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" @click="closeEditExpense()">إلغاء</Button>
                        <Button type="submit" variant="clay" :disabled="processing">حفظ التغييرات</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <ConfirmationDialog
            :open="isConfirmOpen"
            :options="confirmOptions"
            @confirm="handleConfirmDelete"
            @cancel="handleConfirmCancel"
            @update:open="handleConfirmCancel"
        />
    </div>
</template>
