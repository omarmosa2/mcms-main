<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
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

const props = defineProps<{ expense: Expense | null }>();
const emit = defineEmits<{ close: [] }>();
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
    <Dialog :open="expense !== null" @update:open="(open) => !open && emit('close')">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>تفاصيل المصروف</DialogTitle>
                <DialogDescription>{{ expense?.expense_number }} - {{ expense?.title }}</DialogDescription>
            </DialogHeader>

            <div v-if="expense" class="grid gap-4">
                <dl class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">رقم المصروف</dt>
                        <dd class="text-sm font-mono">{{ expense.expense_number ?? '-' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">العنوان</dt>
                        <dd class="text-sm">{{ expense.title }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">المبلغ</dt>
                        <dd class="font-mono text-sm font-semibold">{{ formatAmount(expense.amount) }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">التاريخ</dt>
                        <dd class="text-sm">{{ expense.expense_date ?? '-' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">التصنيف</dt>
                        <dd class="text-sm">{{ expense.category?.name ?? '-' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">العيادة</dt>
                        <dd class="text-sm">{{ expense.clinic?.name ?? 'عام' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">طريقة الدفع</dt>
                        <dd class="text-sm">{{ paymentMethodLabel(expense.payment_method) }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الحالة</dt>
                        <dd>
                            <span :class="statusClass(expense.status)" class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize">
                                {{ statusLabel(expense.status) }}
                            </span>
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الجهة المستلمة</dt>
                        <dd class="text-sm">{{ expense.paid_to ?? '-' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الرقم المرجعي</dt>
                        <dd class="text-sm font-mono">{{ expense.reference_number ?? '-' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">أضيف بواسطة</dt>
                        <dd class="text-sm">{{ expense.creator?.name ?? expense.user?.name ?? '-' }}</dd>
                    </div>
                    <div v-if="expense.description" class="space-y-1 sm:col-span-2">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">ملاحظات</dt>
                        <dd class="text-sm leading-6 text-muted-foreground">{{ expense.description }}</dd>
                    </div>
                </dl>
            </div>

            <DialogFooter>
                <Button type="button" variant="ghost" @click="emit('close')">إغلاق</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
