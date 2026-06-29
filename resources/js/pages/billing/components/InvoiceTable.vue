<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import InvoiceController from '@/actions/App/Http/Controllers/Billing/InvoiceController';
import PaymentController from '@/actions/App/Http/Controllers/Billing/PaymentController';
import InputError from '@/components/InputError.vue';
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
import { useMoneyFormatter } from '@/lib/money';
import type { Invoice, InvoiceSortField, Option } from './types';

const props = defineProps<{
    invoices: Invoice[];
    selectedInvoiceIds: number[];
    deletableInvoiceIds: number[];
    areAllDeletableInvoicesSelected: boolean;
    canViewInvoice: boolean;
    canEditInvoice: boolean;
    localSearch: string;
    localStatus: string;
    localRowsPerPage: number;
    localPage: number;
    localSortBy: InvoiceSortField;
    localSortDirection: string;
    totalLocalPages: number;
    localVisibleFrom: number;
    localVisibleTo: number;
    invoicesTotal: number;
    statusOptions: { label: string; value: string }[];
    activeFilters: { key: string; label: string; value: string | null }[];
    paymentMethodOptions: string[];
}>();

const emit = defineEmits<{
    'update:localSearch': [value: string];
    'update:localStatus': [value: string];
    'update:localRowsPerPage': [value: number];
    'update:selectedInvoiceIds': [value: number[]];
    'toggle-all': [event: Event];
    sort: [field: InvoiceSortField];
    'previous-page': [];
    'next-page': [];
    'remove-filter': [key: string];
    'reset-filters': [];
    view: [invoice: Invoice];
    edit: [invoice: Invoice];
}>();

const { can } = usePermissions();
const { confirm, isOpen: isConfirmOpen, options: confirmOptions, close: closeConfirm, handleConfirm: handleConfirmDelete, handleCancel: handleConfirmCancel } = useConfirm();
const toast = useToast();
const { formatMoney } = useMoneyFormatter();

const statusLabels: Record<string, string> = {
    draft: 'مسودة',
    issued: 'صادرة',
    paid: 'مدفوعة',
    overdue: 'متأخرة',
    canceled: 'ملغاة',
};

const invoiceStatusClass = (status: string): string => {
    if (status === 'draft') {
        return 'border-[var(--border-soft)] bg-[var(--accent-amber-soft)] text-[var(--accent-amber-strong)]';
    }

    if (status === 'issued') {
        return 'border-[var(--border-soft)] bg-[var(--accent-teal-soft)] text-[var(--accent-teal-strong)]';
    }

    if (status === 'paid') {
        return 'border-[var(--border-soft)] bg-[var(--accent-mint-soft)] text-[var(--accent-mint-strong)]';
    }

    if (status === 'overdue' || status === 'canceled') {
        return 'border-[var(--border-soft)] bg-[var(--accent-coral-soft)] text-[var(--accent-coral-strong)]';
    }

    return 'border-[var(--border-soft)] bg-[var(--surface-secondary)] text-[var(--surface-contrast-soft)]';
};

const invoiceStatusDotClass = (status: string): string => {
    if (status === 'paid') {
        return 'bg-[var(--accent-mint)]';
    }

    if (status === 'issued') {
        return 'bg-[var(--accent-teal)]';
    }

    if (status === 'draft') {
        return 'bg-[var(--accent-amber)]';
    }

    if (status === 'overdue' || status === 'canceled') {
        return 'bg-[var(--accent-coral)]';
    }

    return 'bg-[var(--surface-contrast-soft)]';
};

const sortIconFor = (field: InvoiceSortField) => {
    if (props.localSortBy !== field) {
        return ArrowUpDown;
    }

    return props.localSortDirection === 'asc' ? ArrowUp : ArrowDown;
};

const toggleInvoiceSelection = (invoiceId: number, checked: boolean): void => {
    if (checked) {
        emit('update:selectedInvoiceIds', [...props.selectedInvoiceIds, invoiceId]);
    } else {
        emit('update:selectedInvoiceIds', props.selectedInvoiceIds.filter((id) => id !== invoiceId));
    }
};

const deleteInvoice = async (invoice: Invoice) => {
    const confirmed = await confirm({
        title: 'حذف الفاتورة',
        description: `هل أنت متأكد من حذف الفاتورة "${invoice.invoice_number || invoice.id}" للمريض "${invoice.patient?.full_name ?? '-'}"؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(InvoiceController.destroy(invoice.id), {
            onSuccess: () => {
                closeConfirm();
                toast.success('تم حذف الفاتورة بنجاح');
            },
            onError: () => {
                toast.error('فشل حذف الفاتورة');
            },
        });
    }
};
</script>

<template>
    <div>
        <div class="glass-panel-soft p-5">
            <div
                class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3"
            >
                <h3 class="pattern-typographic-title text-[0.76rem]">
                    قائمة الفواتير
                </h3>
                <span class="text-xs text-muted-foreground">
                    الإجمالي: {{ invoicesTotal }}
                </span>
            </div>

            <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
                <div class="grid gap-3 md:grid-cols-[repeat(3,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="billing_search_filter">بحث</Label>
                        <FilterSearch
                            id="billing_search_filter"
                            :model-value="localSearch"
                            @update:model-value="emit('update:localSearch', $event)"
                            placeholder="رقم الفاتورة، اسم المريض"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="billing_status_filter">الحالة</Label>
                        <FilterSelect
                            id="billing_status_filter"
                            :model-value="localStatus"
                            @update:model-value="emit('update:localStatus', $event)"
                            :options="statusOptions"
                            placeholder="الكل"
                        />
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-[repeat(2,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2 md:max-w-44">
                        <Label for="billing_per_page">صفوف لكل صفحة</Label>
                        <select
                            id="billing_per_page"
                            :value="localRowsPerPage"
                            @change="emit('update:localRowsPerPage', Number(($event.target as HTMLSelectElement).value))"
                            class="pattern-field-clay h-10 px-3 py-2"
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
                    @remove="emit('remove-filter', $event)"
                    @clear-all="emit('reset-filters')"
                />
            </div>

            <Form
                v-if="
                    can('billing.generate') && selectedInvoiceIds.length > 0
                "
                v-bind="InvoiceController.bulkDestroy.form()"
                class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3"
                v-slot="{ processing }"
            >
                <input
                    v-for="invoiceId in selectedInvoiceIds"
                    :key="`selected-invoice-${invoiceId}`"
                    type="hidden"
                    name="ids[]"
                    :value="invoiceId"
                />
                <Button
                    type="submit"
                    variant="destructive"
                    size="sm"
                    class="h-10"
                    :disabled="processing"
                >
                    حذف المحدد ({{ selectedInvoiceIds.length }})
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-10"
                    @click="emit('update:selectedInvoiceIds', [])"
                >
                    إلغاء التحديد
                </Button>
            </Form>

            <div class="ui-table-shell">
                <table class="ui-table md:min-w-[1080px]">
                    <thead>
                        <tr>
                            <th
                                v-if="can('billing.generate')"
                                class="px-3 py-2"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-border"
                                    :checked="
                                        areAllDeletableInvoicesSelected
                                    "
                                    @change="emit('toggle-all', $event)"
                                />
                            </th>
                            <th class="px-3 py-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="emit('sort', 'invoice_number')"
                                >
                                    رقم الفاتورة
                                    <component
                                        :is="
                                            sortIconFor(
                                                'invoice_number',
                                            )
                                        "
                                        class="size-3.5"
                                    />
                                </button>
                            </th>
                            <th class="px-3 py-2">المريض</th>
                            <th class="px-3 py-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="emit('sort', 'status')"
                                >
                                    الحالة
                                    <component
                                        :is="sortIconFor('status')"
                                        class="size-3.5"
                                    />
                                </button>
                            </th>
                            <th class="px-3 py-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="emit('sort', 'due_at')"
                                >
                                    تاريخ الاستحقاق
                                    <component
                                        :is="sortIconFor('due_at')"
                                        class="size-3.5"
                                    />
                                </button>
                            </th>
                            <th class="px-3 py-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="emit('sort', 'total_amount')"
                                >
                                    الإجمالي
                                    <component
                                        :is="sortIconFor('total_amount')"
                                        class="size-3.5"
                                    />
                                </button>
                            </th>
                            <th class="px-3 py-2">المدفوع</th>
                            <th class="px-3 py-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="emit('sort', 'balance_amount')"
                                >
                                    الرصيد
                                    <component
                                        :is="
                                            sortIconFor(
                                                'balance_amount',
                                            )
                                        "
                                        class="size-3.5"
                                    />
                                </button>
                            </th>
                            <th class="px-3 py-2 text-right">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="invoice in invoices"
                            :key="invoice.id"
                            class="ui-table-row align-top"
                        >
                            <td
                                v-if="can('billing.generate')"
                                class="px-3 py-2"
                                data-label="تحديد"
                            >
                                <input
                                    v-if="
                                        invoice.status === 'draft' &&
                                        (invoice.payments ?? []).length ===
                                            0
                                    "
                                    :checked="selectedInvoiceIds.includes(invoice.id)"
                                    type="checkbox"
                                    class="size-4 rounded border-border"
                                    :value="invoice.id"
                                    @change="toggleInvoiceSelection(invoice.id, ($event.target as HTMLInputElement).checked)"
                                />
                            </td>
                            <td
                                class="px-3 py-2 font-medium"
                                data-label="رقم الفاتورة"
                            >
                                {{ invoice.invoice_number }}
                            </td>
                            <td class="px-3 py-2" data-label="المريض">
                                {{ invoice.patient?.full_name ?? '-' }}
                            </td>
                            <td class="px-3 py-2" data-label="الحالة">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium capitalize"
                                    :class="
                                        invoiceStatusClass(invoice.status)
                                    "
                                >
                                    <span
                                        class="w-1.5 h-1.5 rounded-full"
                                        :class="invoiceStatusDotClass(invoice.status)"
                                    ></span>
                                    {{ statusLabels[invoice.status] ?? invoice.status }}
                                </span>
                            </td>
                            <td class="px-3 py-2" data-label="تاريخ الاستحقاق">
                                {{ invoice.due_at ?? '-' }}
                            </td>
                            <td class="px-3 py-2" data-label="الإجمالي">
                                {{ formatMoney(invoice.total_amount) }}
                            </td>
                            <td class="px-3 py-2" data-label="المدفوع">
                                {{ formatMoney(invoice.paid_amount) }}
                            </td>
                            <td class="px-3 py-2" data-label="الرصيد">
                                {{ formatMoney(invoice.balance_amount) }}
                            </td>
                            <td
                                class="table-cell-actions px-3 py-2"
                                data-label="الإجراءات"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-end gap-2"
                                >
                                    <Button
                                        v-if="canViewInvoice"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-10 px-3 text-xs"
                                        @click="emit('view', invoice)"
                                    >
                                        عرض
                                    </Button>
                                    <Button
                                        v-if="
                                            canEditInvoice &&
                                            invoice.status === 'draft'
                                        "
                                        type="button"
                                        variant="default"
                                        size="sm"
                                        class="h-10 px-3 text-xs"
                                        @click="emit('edit', invoice)"
                                    >
                                        تعديل
                                    </Button>
                                    <Form
                                        v-if="
                                            can('billing.generate') &&
                                            invoice.status === 'draft'
                                        "
                                        v-bind="
                                            InvoiceController.issue.form(
                                                invoice.id,
                                            )
                                        "
                                        class="flex items-center gap-2"
                                        v-slot="{ processing }"
                                        @success="() => toast.success('تم إصدار الفاتورة بنجاح')"
                                        @error="() => toast.error('فشل إصدار الفاتورة')"
                                    >
                                        <Input
                                            name="due_at"
                                            type="date"
                                            aria-label="تاريخ الاستحقاق"
                                            class="pattern-field-clay h-10 w-32 px-2 py-2 text-xs"
                                        />
                                        <Button
                                            type="submit"
                                            variant="default"
                                            size="sm"
                                            class="h-10 px-2 text-xs"
                                            :disabled="processing"
                                        >
                                            إصدار
                                        </Button>
                                    </Form>

                                    <Form
                                        v-if="
                                            can('payment.record') &&
                                            invoice.balance_amount > 0
                                        "
                                        v-bind="
                                            PaymentController.store.form(
                                                invoice.id,
                                            )
                                        "
                                        class="flex items-center gap-2"
                                        v-slot="{ processing }"
                                        @success="() => toast.success('تم تسجيل الدفع بنجاح')"
                                        @error="() => toast.error('فشل تسجيل الدفع')"
                                    >
                                        <Input
                                            name="amount"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            class="pattern-field-clay h-10 w-24 px-2 py-2 text-xs"
                                            placeholder="المبلغ"
                                            required
                                        />
                                        <select
                                            name="method"
                                            class="pattern-field-clay h-10 px-2 py-2 text-xs"
                                        >
                                            <option
                                                v-for="method in paymentMethodOptions"
                                                :key="method"
                                                :value="method"
                                            >
                                                {{ method }}
                                            </option>
                                        </select>
                                        <Button
                                            type="submit"
                                            variant="default"
                                            size="sm"
                                            class="h-10 px-2 text-xs"
                                            :disabled="processing"
                                        >
                                            دفع
                                        </Button>
                                    </Form>

                                    <Button
                                        v-if="
                                            can('billing.generate') &&
                                            invoice.status === 'draft'
                                        "
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        class="h-10 px-3 text-xs"
                                        @click="deleteInvoice(invoice)"
                                    >
                                        حذف
                                    </Button>
                                </div>

                                <div
                                    v-if="
                                        can('payment.refund') &&
                                        (invoice.payments ?? []).length > 0
                                    "
                                    class="mt-2 space-y-2 rounded-md border border-border/60 bg-muted/50 p-2"
                                >
                                    <p
                                        class="pattern-typographic-title text-[0.68rem]"
                                    >
                                        استرداد المدفوعات
                                    </p>
                                    <Form
                                        v-for="payment in invoice.payments"
                                        :key="payment.id"
                                        v-bind="
                                            PaymentController.refund.form(
                                                payment.id,
                                            )
                                        "
                                        class="flex items-center gap-2"
                                        v-slot="{ processing }"
                                    >
                                        <span
                                            class="w-28 text-xs text-muted-foreground capitalize"
                                        >
                                            {{ payment.method }}
                                            ({{
                                                formatMoney(payment.amount)
                                            }})
                                        </span>
                                        <Input
                                            name="amount"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            class="pattern-field-clay h-10 w-24 px-2 py-2 text-xs"
                                            placeholder="استرداد"
                                            required
                                        />
                                        <Button
                                            type="submit"
                                            variant="default"
                                            size="sm"
                                            class="h-10 px-2 text-xs"
                                            :disabled="
                                                processing ||
                                                payment.status !==
                                                    'recorded'
                                            "
                                        >
                                            استرداد
                                        </Button>
                                    </Form>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-if="invoices.length === 0"
                            class="table-empty-state"
                        >
                            <td
                                :colspan="can('billing.generate') ? 9 : 8"
                                class="px-3 py-10 text-center text-muted-foreground"
                            >
                                لا توجد فواتير.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2"
            >
                <p class="text-xs text-muted-foreground">
                    عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من
                    {{ invoicesTotal }} سجل
                </p>
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-10 px-3 text-xs"
                        :disabled="localPage === 1"
                        @click="emit('previous-page')"
                    >
                        السابق
                    </Button>
                    <span class="text-xs font-semibold text-foreground/85">
                        صفحة {{ localPage }} / {{ totalLocalPages }}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-10 px-3 text-xs"
                        :disabled="localPage >= totalLocalPages"
                        @click="emit('next-page')"
                    >
                        التالي
                    </Button>
                </div>
            </div>
        </div>

        <ConfirmationDialog
            :open="isConfirmOpen"
            :options="confirmOptions"
            @confirm="handleConfirmDelete"
            @cancel="handleConfirmCancel"
            @update:open="handleConfirmCancel"
        />
    </div>
</template>
