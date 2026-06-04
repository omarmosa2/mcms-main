<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { CheckCircle, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

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

const props = defineProps<{ expense: Expense | null }>();
const emit = defineEmits<{ close: [] }>();

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
</script>

<template>
    <Dialog :open="expense !== null" @update:open="(open) => !open && emit('close')">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>تفاصيل المصروف</DialogTitle>
                <DialogDescription>{{ expense?.description }}</DialogDescription>
            </DialogHeader>

            <div v-if="expense" class="grid gap-4">
                <dl class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الوصف</dt>
                        <dd class="text-sm">{{ expense.description }}</dd>
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
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الحالة</dt>
                        <dd>
                            <span :class="statusClass(expense.status)" class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize">
                                <CheckCircle v-if="expense.status === 'approved'" class="size-3" />
                                <XCircle v-else-if="expense.status === 'rejected'" class="size-3" />
                                {{ statusLabel(expense.status) }}
                            </span>
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">سجل بواسطة</dt>
                        <dd class="text-sm">{{ expense.user?.name ?? '-' }}</dd>
                    </div>
                    <div v-if="expense.approver" class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">وافق بواسطة</dt>
                        <dd class="text-sm">{{ expense.approver.name }}</dd>
                    </div>
                    <div v-if="expense.approved_at" class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">تاريخ الموافقة</dt>
                        <dd class="text-sm">{{ expense.approved_at }}</dd>
                    </div>
                    <div v-if="expense.notes" class="space-y-1 sm:col-span-2">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">ملاحظات</dt>
                        <dd class="text-sm leading-6 text-muted-foreground">{{ expense.notes }}</dd>
                    </div>
                </dl>
            </div>

            <DialogFooter>
                <Button type="button" variant="ghost" @click="emit('close')">إغلاق</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
