<script setup lang="ts">
import ExpenseController from '@/actions/App/Http/Controllers/Expenses/ExpenseController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form } from '@inertiajs/vue3';

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

const props = defineProps<{ expense: Expense | null; categories: ExpenseCategory[]; clinics: Clinic[] }>();
const emit = defineEmits<{ close: [] }>();
</script>

<template>
    <Dialog :open="expense !== null" @update:open="(open) => !open && emit('close')">
        <DialogContent class="max-w-[600px]">
            <DialogHeader>
                <DialogTitle>تعديل المصروف</DialogTitle>
                <DialogDescription>تعديل بيانات المصروف رقم {{ expense?.expense_number }}</DialogDescription>
            </DialogHeader>

            <Form
                v-if="expense"
                v-bind="ExpenseController.update.form(expense.id)"
                class="space-y-4 max-h-[65vh] overflow-y-auto"
                v-slot="{ errors, processing }"
                @success="emit('close')"
            >
                <div class="grid gap-2">
                    <Label for="edit_title">عنوان المصروف *</Label>
                    <Input
                        id="edit_title"
                        name="title"
                        :default-value="expense.title"
                        required
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="edit_amount">المبلغ *</Label>
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
                        <Label for="edit_expense_date">تاريخ المصروف *</Label>
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
                </div>

                <div class="grid grid-cols-2 gap-4">
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
                        <Label for="edit_clinic_id">العيادة</Label>
                        <select
                            id="edit_clinic_id"
                            name="clinic_id"
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="">مصروف عام</option>
                            <option
                                v-for="clinic in clinics"
                                :key="clinic.id"
                                :value="clinic.id"
                                :selected="expense.clinic?.id === clinic.id"
                            >
                                {{ clinic.name }}
                            </option>
                        </select>
                        <InputError :message="errors.clinic_id" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="edit_payment_method">طريقة الدفع *</Label>
                        <select
                            id="edit_payment_method"
                            name="payment_method"
                            required
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="cash" :selected="expense.payment_method === 'cash'">نقداً</option>
                            <option value="transfer" :selected="expense.payment_method === 'transfer'">تحويل</option>
                            <option value="card" :selected="expense.payment_method === 'card'">بطاقة</option>
                            <option value="other" :selected="expense.payment_method === 'other'">أخرى</option>
                        </select>
                        <InputError :message="errors.payment_method" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit_status">الحالة *</Label>
                        <select
                            id="edit_status"
                            name="status"
                            required
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="pending" :selected="expense.status === 'pending'">معلق</option>
                            <option value="paid" :selected="expense.status === 'paid'">مدفوع</option>
                            <option value="cancelled" :selected="expense.status === 'cancelled'">ملغي</option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="edit_paid_to">الجهة المستلمة</Label>
                    <Input
                        id="edit_paid_to"
                        name="paid_to"
                        :default-value="expense.paid_to ?? ''"
                        placeholder="اسم الشخص أو الجهة"
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.paid_to" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit_reference_number">الرقم المرجعي</Label>
                    <Input
                        id="edit_reference_number"
                        name="reference_number"
                        :default-value="expense.reference_number ?? ''"
                        placeholder="رقم الفاتورة أو المرجع"
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.reference_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit_description">ملاحظات</Label>
                    <textarea
                        id="edit_description"
                        name="description"
                        rows="2"
                        class="pattern-field-clay"
                        placeholder="ملاحظات اختيارية"
                    >{{ expense.description ?? '' }}</textarea>
                    <InputError :message="errors.description" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="emit('close')">إلغاء</Button>
                    <Button type="submit" variant="clay" :disabled="processing">حفظ التغييرات</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
