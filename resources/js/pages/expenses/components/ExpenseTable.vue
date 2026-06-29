<script setup lang="ts">
import { ArrowDown, ArrowUp, ArrowUpDown, Eye, Pencil, Trash2 } from 'lucide-vue-next';
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
    'update-search': [value: string]
    'update-status': [value: string]
    'update-category-id': [value: number | null]
    'update-clinic-id': [value: number | null]
    'update-date-from': [value: string]
    'update-date-to': [value: string]
    'update-payment-method': [value: string]
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
    if (!method) {
return '-';
}

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
    <section class="glass-panel-soft overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-border/70 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-base font-bold text-foreground">قائمة المصاريف</h2>
                <p class="text-xs text-muted-foreground">
                    عرض {{ visibleFrom }}-{{ visibleTo }} من {{ total }} مصروف
                </p>
            </div>
            <div class="flex items-center gap-2 rounded-xl border border-border/70 bg-secondary/40 px-3 py-2 text-xs text-muted-foreground">
                <span>الصفحة</span>
                <strong class="text-foreground">{{ localPage }}</strong>
                <span>/</span>
                <strong class="text-foreground">{{ totalPages }}</strong>
            </div>
        </div>

        <div class="border-b border-border/70 bg-secondary/20 p-5">
            <div class="grid gap-3 lg:grid-cols-12 lg:items-end">
                <div class="grid gap-2 lg:col-span-4">
                    <Label for="expenses_search">بحث</Label>
                    <FilterSearch
                        id="expenses_search"
                        :model-value="localSearch"
                        placeholder="العنوان، الجهة، الرقم المرجعي..."
                        @update:model-value="(value) => emit('update-search', value)"
                    />
                </div>

                <div class="grid gap-2 sm:grid-cols-2 lg:col-span-5 lg:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="expenses_status">الحالة</Label>
                        <FilterSelect
                            id="expenses_status"
                            :model-value="localStatus"
                            :options="statusOptions"
                            placeholder="الكل"
                            @update:model-value="(value) => emit('update-status', String(value ?? 'all'))"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="expenses_category">التصنيف</Label>
                        <FilterSelect
                            id="expenses_category"
                            :model-value="localCategoryId"
                            :options="categoryOptions"
                            placeholder="الكل"
                            @update:model-value="(value) => emit('update-category-id', value === null ? null : Number(value))"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="expenses_clinic">العيادة</Label>
                        <FilterSelect
                            id="expenses_clinic"
                            :model-value="localClinicId"
                            :options="clinicOptions"
                            placeholder="الكل"
                            @update:model-value="(value) => emit('update-clinic-id', value === null ? null : Number(value))"
                        />
                    </div>
                </div>

                <div class="grid gap-2 sm:grid-cols-2 lg:col-span-3">
                    <div class="grid gap-2">
                        <Label for="expenses_payment_method">طريقة الدفع</Label>
                        <FilterSelect
                            id="expenses_payment_method"
                            :model-value="localPaymentMethod"
                            :options="paymentMethodOptions"
                            placeholder="الكل"
                            @update:model-value="(value) => emit('update-payment-method', String(value ?? ''))"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="expenses_per_page">الصفوف</Label>
                        <select
                            id="expenses_per_page"
                            :value="localRowsPerPage"
                            class="pattern-field-clay h-9 px-3 py-1.5"
                            @change="emit('change-rows-per-page', Number(($event.target as HTMLSelectElement).value))"
                        >
                            <option :value="10">10</option>
                            <option :value="15">15</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-3 grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div class="grid gap-2">
                    <Label>نطاق التاريخ</Label>
                    <FilterDateRange
                        :from="localDateFrom"
                        :to="localDateTo"
                        @update:from="(value) => emit('update-date-from', value)"
                        @update:to="(value) => emit('update-date-to', value)"
                    />
                </div>
                <Button
                    type="button"
                    variant="outline"
                    class="h-9 rounded-xl px-4 text-xs"
                    @click="emit('clear-filters')"
                >
                    تصفية جديدة
                </Button>
            </div>

            <FilterBar
                v-if="activeFilters.length > 0"
                class="mt-3"
                :active-filters="activeFilters"
                @remove="(key) => emit('remove-filter', key)"
                @clear-all="emit('clear-filters')"
            />
        </div>

        <div class="overflow-x-auto px-5 py-4">
            <table class="w-full min-w-[960px] border-separate border-spacing-0 text-sm">
                <thead>
                    <tr class="text-xs font-bold text-muted-foreground">
                        <th class="border-b border-border bg-secondary/50 px-3 py-3 text-start first:rounded-s-xl">#</th>
                        <th class="border-b border-border bg-secondary/50 px-3 py-3 text-start">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'expense_date')"
                            >
                                التاريخ
                                <component :is="sortIconFor('expense_date')" class="size-3.5" />
                            </button>
                        </th>
                        <th class="border-b border-border bg-secondary/50 px-3 py-3 text-start">العنوان</th>
                        <th class="border-b border-border bg-secondary/50 px-3 py-3 text-start">العيادة</th>
                        <th class="border-b border-border bg-secondary/50 px-3 py-3 text-start">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'amount')"
                            >
                                المبلغ
                                <component :is="sortIconFor('amount')" class="size-3.5" />
                            </button>
                        </th>
                        <th class="border-b border-border bg-secondary/50 px-3 py-3 text-start">طريقة الدفع</th>
                        <th class="border-b border-border bg-secondary/50 px-3 py-3 text-start">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'status')"
                            >
                                الحالة
                                <component :is="sortIconFor('status')" class="size-3.5" />
                            </button>
                        </th>
                        <th class="border-b border-border bg-secondary/50 px-3 py-3 text-start">أضيف بواسطة</th>
                        <th class="border-b border-border bg-secondary/50 px-3 py-3 text-end last:rounded-e-xl">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(expense, index) in expenses"
                        :key="expense.id"
                        class="group border-b border-border/60 transition-colors hover:bg-primary/5"
                    >
                        <td class="border-b border-border/60 px-3 py-3 align-middle font-mono text-xs tabular-nums" data-label="الرقم">
                            {{ visibleFrom + index }}
                        </td>
                        <td class="border-b border-border/60 px-3 py-3 align-middle tabular-nums" data-label="التاريخ">
                            {{ expense.expense_date ?? '-' }}
                        </td>
                        <td class="border-b border-border/60 px-3 py-3 align-middle" data-label="العنوان">
                            <div class="max-w-[190px]">
                                <p class="font-semibold text-foreground">{{ expense.title }}</p>
                                <p v-if="expense.reference_number" class="text-xs text-muted-foreground">
                                    {{ expense.reference_number }}
                                </p>
                            </div>
                        </td>
                        <td class="border-b border-border/60 px-3 py-3 align-middle" data-label="العيادة">
                            <span v-if="expense.clinic" class="text-sm">
                                {{ expense.clinic.name }}
                            </span>
                            <span v-else class="text-xs text-muted-foreground">عام</span>
                        </td>
                        <td class="border-b border-border/60 px-3 py-3 align-middle font-mono font-semibold tabular-nums" data-label="المبلغ">
                            {{ formatAmount(expense.amount) }}
                        </td>
                        <td class="border-b border-border/60 px-3 py-3 align-middle text-sm" data-label="طريقة الدفع">
                            {{ paymentMethodLabel(expense.payment_method) }}
                        </td>
                        <td class="border-b border-border/60 px-3 py-3 align-middle" data-label="الحالة">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                                :class="statusClass(expense.status)"
                            >
                                <span class="size-1.5 rounded-full bg-current" />
                                {{ statusLabel(expense.status) }}
                            </span>
                        </td>
                        <td class="border-b border-border/60 px-3 py-3 align-middle text-sm" data-label="أضيف بواسطة">
                            {{ expense.creator?.name ?? expense.user?.name ?? '-' }}
                        </td>
                        <td class="border-b border-border/60 px-3 py-3 align-middle text-end" data-label="الإجراءات">
                            <div class="flex flex-wrap justify-end gap-1.5">
                                <Button
                                    v-if="canView"
                                    type="button"
                                    variant="neumorphic"
                                    size="sm"
                                    class="h-8 rounded-lg px-2.5 text-xs"
                                    @click="emit('open-view', expense)"
                                >
                                    <Eye class="size-3.5" />
                                    عرض
                                </Button>
                                <Button
                                    v-if="canUpdate"
                                    type="button"
                                    variant="clay"
                                    size="sm"
                                    class="h-8 rounded-lg px-2.5 text-xs"
                                    @click="emit('open-edit', expense)"
                                >
                                    <Pencil class="size-3.5" />
                                    تعديل
                                </Button>
                                <Button
                                    v-if="canDelete"
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    class="h-8 rounded-lg px-2.5 text-xs"
                                    @click="emit('delete-expense', expense)"
                                >
                                    <Trash2 class="size-3.5" />
                                    حذف
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="expenses.length === 0" class="table-empty-state">
                        <td
                            colspan="9"
                            class="px-3 py-14 text-center text-muted-foreground"
                        >
                            لا توجد مصاريف مطابقة للفلاتر الحالية.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border/70 bg-secondary/20 px-5 py-4">
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
    </section>
</template>
