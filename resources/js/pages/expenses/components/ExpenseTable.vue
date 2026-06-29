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
import { useMoneyFormatter } from '@/lib/money';

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
    localClinicId: number | null
    localDateFrom: string
    localDateTo: string
    localPaymentMethod: string
    localRowsPerPage: number
    selectedIds: number[]
    areAllSelected: boolean
    canDelete: boolean
    canView: boolean
    canUpdate: boolean
    activeFilters: { key: string; label: string; value: string | null }[]
    statusOptions: { label: string; value: string }[]
    categoryOptions: { label: string; value: number }[]
    clinicOptions: { label: string; value: number | null }[]
    paymentMethodOptions: { label: string; value: string }[]
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
}>();

const sortIconFor = (field: ExpenseSortField) => {
    if (props.sortBy !== field) {
        return ArrowUpDown;
    }
    return props.sortDirection === 'asc' ? ArrowUp : ArrowDown;
};

const { formatMoney: formatAmount } = useMoneyFormatter();

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
</script>

<template>
    <div class="glass-panel-soft p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
            <h3 class="pattern-typographic-title text-[0.76rem]">قائمة المصاريف</h3>
            <span class="text-xs text-muted-foreground">الإجمالي: {{ total }}</span>
        </div>

        <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
            <div class="grid gap-3 md:grid-cols-[repeat(4,minmax(0,1fr))] md:items-end">
                <div class="grid gap-2">
                    <Label for="expenses_search">بحث</Label>
                    <FilterSearch
                        id="expenses_search"
                        :model-value="localSearch"
                        placeholder="العنوان، الجهة، الرقم المرجعي..."
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
                    <Label for="expenses_clinic">العيادة</Label>
                    <FilterSelect
                        id="expenses_clinic"
                        :model-value="localClinicId"
                        :options="clinicOptions"
                        placeholder="الكل"
                    />
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-[repeat(4,minmax(0,1fr))] md:items-end">
                <div class="grid gap-2">
                    <Label for="expenses_payment_method">طريقة الدفع</Label>
                    <FilterSelect
                        id="expenses_payment_method"
                        :model-value="localPaymentMethod"
                        :options="paymentMethodOptions"
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
                        <th class="px-3 py-2">رقم المصروف</th>
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
                        <th class="px-3 py-2">العنوان</th>
                        <th class="px-3 py-2">التصنيف</th>
                        <th class="px-3 py-2">العيادة</th>
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
                        <th class="px-3 py-2">طريقة الدفع</th>
                        <th class="px-3 py-2">الجهة المستلمة</th>
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
                        <th class="px-3 py-2">أضيف بواسطة</th>
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
                                type="checkbox"
                                class="size-4 rounded border-border"
                                :checked="selectedIds.includes(expense.id)"
                                @change="$event.target.checked ? emit('toggle-all-selection', $event) : null"
                                :value="expense.id"
                            />
                        </td>
                        <td class="px-3 py-2 font-mono text-xs" data-label="رقم المصروف">
                            {{ expense.expense_number ?? '-' }}
                        </td>
                        <td class="px-3 py-2" data-label="التاريخ">
                            {{ expense.expense_date ?? '-' }}
                        </td>
                        <td class="px-3 py-2 font-medium" data-label="العنوان">
                            {{ expense.title }}
                        </td>
                        <td class="px-3 py-2" data-label="التصنيف">
                            <span v-if="expense.category" class="text-sm">
                                {{ expense.category.name }}
                            </span>
                            <span v-else class="text-muted-foreground">-</span>
                        </td>
                        <td class="px-3 py-2" data-label="العيادة">
                            <span v-if="expense.clinic" class="text-sm">
                                {{ expense.clinic.name }}
                            </span>
                            <span v-else class="text-xs text-muted-foreground">عام</span>
                        </td>
                        <td class="px-3 py-2 font-mono font-semibold" data-label="المبلغ">
                            {{ formatAmount(expense.amount) }}
                        </td>
                        <td class="px-3 py-2 text-sm" data-label="طريقة الدفع">
                            {{ paymentMethodLabel(expense.payment_method) }}
                        </td>
                        <td class="px-3 py-2 text-sm" data-label="الجهة المستلمة">
                            {{ expense.paid_to ?? '-' }}
                        </td>
                        <td class="px-3 py-2" data-label="الحالة">
                            <span
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                                :class="statusClass(expense.status)"
                            >
                                {{ statusLabel(expense.status) }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-sm" data-label="أضيف بواسطة">
                            {{ expense.creator?.name ?? expense.user?.name ?? '-' }}
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
                            :colspan="canDelete ? 12 : 11"
                            class="px-3 py-10 text-center text-muted-foreground"
                        >
                            لا توجد مصاريف.
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
