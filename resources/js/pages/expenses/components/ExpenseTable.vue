<script setup lang="ts">
import ExpenseController from '@/actions/App/Http/Controllers/Expenses/ExpenseController';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    FilterBar,
    FilterSearch,
    FilterSelect,
    FilterDateRange,
} from '@/components/ui/filter';
import { Label } from '@/components/ui/label';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useMoneyFormatter } from '@/lib/money';

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

type ExpenseCategory = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
};

type ExpenseSortField = 'amount' | 'expense_date' | 'status' | 'created_at';
type SortDirection = 'asc' | 'desc';

const props = defineProps<{
    expenses: Expense[]
    visibleFrom: number
    visibleTo: number
    total: number
    localPage: number
    totalPages: number
    localSearch: string
    localStatus: string
    localCategoryId: number | null
    localDateFrom: string
    localDateTo: string
    localRowsPerPage: number
    selectedIds: number[]
    areAllSelected: boolean
    canDelete: boolean
    canView: boolean
    canUpdate: boolean
    canApprove: boolean
    activeFilters: { key: string; label: string; value: string | null }[]
    statusOptions: { label: string; value: string }[]
    categoryOptions: { label: string; value: number }[]
    sortBy: ExpenseSortField
    sortDirection: SortDirection
}>();

const emit = defineEmits<{
    'toggle-sort': [field: ExpenseSortField]
    'toggle-all-selection': [event: Event]
    'change-page': [page: number]
    'change-rows-per-page': [value: number]
    'open-view': [expense: Expense]
    'open-edit': [expense: Expense]
    'delete-expense': [expense: Expense]
    'remove-filter': [key: string]
    'clear-filters': []
    'toggle-status': [expense: Expense]
    'approve-expense': [expense: Expense]
}>();

const sortIconFor = (field: ExpenseSortField) => {
    if (props.sortBy !== field) {
        return ArrowUpDown;
    }
    return props.sortDirection === 'asc' ? ArrowUp : ArrowDown;
};

const { formatMoney: formatAmount } = useMoneyFormatter();

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
</script>

<template>
    <div class="glass-panel-soft p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
            <h3 class="pattern-typographic-title text-[0.76rem]">قائمة المصروفات</h3>
            <span class="text-xs text-muted-foreground">الإجمالي: {{ total }}</span>
        </div>

        <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
            <div class="grid gap-3 md:grid-cols-[repeat(4,minmax(0,1fr))] md:items-end">
                <div class="grid gap-2">
                    <Label for="expenses_search">بحث</Label>
                    <FilterSearch
                        id="expenses_search"
                        :model-value="localSearch"
                        placeholder="الوصف..."
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="expenses_status">الحالة</Label>
                    <FilterSelect
                        id="expenses_status"
                        :model-value="localStatus"
                        :options="statusOptions"
                        placeholder="الكل"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="expenses_category">التصنيف</Label>
                    <FilterSelect
                        id="expenses_category"
                        :model-value="localCategoryId"
                        :options="categoryOptions"
                        placeholder="الكل"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="expenses_date">نطاق التاريخ</Label>
                    <FilterDateRange
                        :from="localDateFrom"
                        :to="localDateTo"
                    />
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-[repeat(2,minmax(0,1fr))] md:items-end">
                <div class="grid gap-2">
                    <Label for="expenses_per_page">صفوف</Label>
                    <select
                        id="expenses_per_page"
                        :model-value="localRowsPerPage"
                        @change="emit('change-rows-per-page', Number(($event.target as HTMLSelectElement).value))"
                        class="pattern-field-clay h-9 px-3 py-1.5"
                    >
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                </div>
            </div>

            <FilterBar
                v-if="activeFilters.length > 0"
                :active-filters="activeFilters"
                @remove="(key) => emit('remove-filter', key)"
                @clear-all="emit('clear-filters')"
            />
        </div>

        <Form
            v-if="canDelete && selectedIds.length > 0"
            v-bind="ExpenseController.bulkDestroy.form()"
            class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3"
            v-slot="{ processing }"
            @submit.prevent="emit('approve-expense', selectedIds[0])"
        >
            <input
                v-for="expenseId in selectedIds"
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
                حذف المحدد ({{ selectedIds.length }})
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="emit('toggle-all-selection', new Event('change'))"
            >
                إلغاء التحديد
            </Button>
        </Form>

        <div class="ui-table-shell">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th v-if="canDelete" class="px-3 py-2">
                            <input
                                type="checkbox"
                                class="size-4 rounded border-border"
                                :checked="areAllSelected"
                                @change="emit('toggle-all-selection', $event)"
                            />
                        </th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'expense_date')"
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
                                @click="emit('toggle-sort', 'amount')"
                            >
                                المبلغ
                                <component :is="sortIconFor('amount')" class="size-3.5" />
                            </button>
                        </th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'status')"
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
                        v-for="expense in expenses"
                        :key="expense.id"
                        class="ui-table-row"
                    >
                        <td v-if="canDelete" class="px-3 py-2" data-label="تحديد">
                            <input
                                :model-value="selectedIds"
                                type="checkbox"
                                class="size-4 rounded border-border"
                                :checked="selectedIds.includes(expense.id)"
                                @change="$event.target.checked ? emit('toggle-all-selection', $event) : null"
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
                                {{ statusLabel(expense.status) }}
                            </span>
                        </td>
                        <td class="table-cell-actions px-3 py-2 md:text-right" data-label="الإجراءات">
                            <div class="flex flex-wrap justify-end gap-2">
                                <Button
                                    v-if="canView"
                                    type="button"
                                    variant="neumorphic"
                                    size="sm"
                                    class="h-8 px-3 text-xs"
                                    @click="emit('open-view', expense)"
                                >
                                    عرض
                                </Button>
                                <Button
                                    v-if="canUpdate"
                                    type="button"
                                    variant="clay"
                                    size="sm"
                                    class="h-8 px-3 text-xs"
                                    @click="emit('open-edit', expense)"
                                >
                                    تعديل
                                </Button>
                                <Button
                                    v-if="canDelete"
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    class="h-8 px-3 text-xs"
                                    @click="emit('delete-expense', expense)"
                                >
                                    حذف
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="expenses.length === 0" class="table-empty-state">
                        <td
                            :colspan="canDelete ? 7 : 6"
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
                عرض {{ visibleFrom }}-{{ visibleTo }} من {{ total }}
            </p>
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="neumorphic"
                    size="sm"
                    class="h-8 px-3 text-xs"
                    :disabled="localPage === 1"
                    @click="emit('change-page', localPage - 1)"
                >
                    السابق
                </Button>
                <span class="text-xs font-semibold text-foreground/85">
                    صفحة {{ localPage }} / {{ totalPages }}
                </span>
                <Button
                    type="button"
                    variant="neumorphic"
                    size="sm"
                    class="h-8 px-3 text-xs"
                    :disabled="localPage >= totalPages"
                    @click="emit('change-page', localPage + 1)"
                >
                    التالي
                </Button>
            </div>
        </div>
    </div>
</template>
