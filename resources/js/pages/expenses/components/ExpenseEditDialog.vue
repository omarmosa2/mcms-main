<script setup lang="ts">
import ExpenseController from '@/actions/App/Http/Controllers/Expenses/ExpenseController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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

const props = defineProps<{ expense: Expense | null; categories: ExpenseCategory[] }>();
const emit = defineEmits<{ close: [] }>();
</script>

<template>
    <Dialog :open="expense !== null" @update:open="(open) => !open && emit('close')">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>تعديل المصروف</DialogTitle>
                <DialogDescription>تعديل بيانات المصروف.</DialogDescription>
            </DialogHeader>

            <Form
                v-if="expense"
                v-bind="ExpenseController.update.form(expense.id)"
                class="space-y-4"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="edit_description">الوصف</Label>
                    <Input
                        id="edit_description"
                        name="description"
                        :default-value="expense.description"
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
                        :default-value="expense.amount"
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
                            :selected="expense.category?.id === category.id"
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
                        :default-value="expense.expense_date ?? ''"
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
                    >{{ expense.notes ?? '' }}</textarea>
                    <InputError :message="errors.notes" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="ghost" @click="emit('close')">إلغاء</Button>
                    <Button type="submit" variant="clay" :disabled="processing">حفظ التغييرات</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
