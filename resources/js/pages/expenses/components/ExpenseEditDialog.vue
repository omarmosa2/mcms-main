<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import ExpenseController from '@/actions/App/Http/Controllers/Expenses/ExpenseController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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

defineProps<{ expense: Expense | null; categories: ExpenseCategory[]; clinics: Clinic[] }>();
const emit = defineEmits<{ close: [] }>();
</script>

<template>
    <Dialog :open="expense !== null" @update:open="(open) => !open && emit('close')">
        <DialogContent class="max-h-[calc(100vh-2rem)] max-w-[680px] bg-card p-0" dir="rtl">
            <DialogHeader class="border-b border-border/60 px-6 py-5">
                <DialogTitle class="text-xl font-semibold">تعديل المصروف</DialogTitle>
                <DialogDescription class="mt-1 text-sm">
                    تحديث بيانات المصروف {{ expense?.expense_number ? `رقم ${expense.expense_number}` : '' }}
                </DialogDescription>
            </DialogHeader>

            <Form
                v-if="expense"
                v-bind="ExpenseController.update.form(expense.id)"
                class="flex min-h-0 flex-1 flex-col"
                v-slot="{ errors, processing }"
                @success="emit('close')"
            >
                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-6 py-5">
                    <div class="grid gap-2">
                        <Label for="edit_title">عنوان المصروف *</Label>
                        <Input
                            id="edit_title"
                            name="title"
                            :default-value="expense.title"
                            required
                            class="pattern-field-clay h-10"
                        />
                        <InputError :message="errors.title" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
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
                                class="pattern-field-clay h-10"
                            />
                            <InputError :message="errors.amount" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_expense_date">التاريخ *</Label>
                            <Input
                                id="edit_expense_date"
                                name="expense_date"
                                type="date"
                                :default-value="expense.expense_date ?? ''"
                                required
                                class="pattern-field-clay h-10"
                            />
                            <InputError :message="errors.expense_date" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="edit_category_id">التصنيف</Label>
                            <select
                                id="edit_category_id"
                                name="category_id"
                                class="pattern-field-clay h-10 px-3 py-2"
                            >
                                <option value="">بدون تصنيف</option>
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
                            <Label for="edit_payment_method">طريقة الدفع *</Label>
                            <select
                                id="edit_payment_method"
                                name="payment_method"
                                required
                                class="pattern-field-clay h-10 px-3 py-2"
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
                                class="pattern-field-clay h-10 px-3 py-2"
                            >
                                <option value="pending" :selected="expense.status === 'pending'">معلق</option>
                                <option value="paid" :selected="expense.status === 'paid'">مدفوع</option>
                                <option value="cancelled" :selected="expense.status === 'cancelled'">ملغي</option>
                            </select>
                            <InputError :message="errors.status" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_clinic_id">العيادة</Label>
                            <select
                                id="edit_clinic_id"
                                name="clinic_id"
                                class="pattern-field-clay h-10 px-3 py-2"
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
                        <div class="grid gap-2">
                            <Label for="edit_paid_to">الجهة المستلمة</Label>
                            <Input
                                id="edit_paid_to"
                                name="paid_to"
                                :default-value="expense.paid_to ?? ''"
                                placeholder="اسم الشخص أو الجهة"
                                class="pattern-field-clay h-10"
                            />
                            <InputError :message="errors.paid_to" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_reference_number">الرقم المرجعي</Label>
                        <Input
                            id="edit_reference_number"
                            name="reference_number"
                            :default-value="expense.reference_number ?? ''"
                            placeholder="رقم الفاتورة أو المرجع"
                            class="pattern-field-clay h-10"
                        />
                        <InputError :message="errors.reference_number" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_description">ملاحظات</Label>
                        <textarea
                            id="edit_description"
                            name="description"
                            rows="3"
                            class="pattern-field-clay min-h-24"
                            placeholder="ملاحظات اختيارية"
                            :value="expense.description ?? ''"
                        />
                        <InputError :message="errors.description" />
                    </div>
                </div>

                <DialogFooter class="justify-start gap-2">
                    <Button type="submit" variant="clay" :disabled="processing">
                        {{ processing ? 'جاري الحفظ...' : 'حفظ التعديل' }}
                    </Button>
                    <Button type="button" variant="outline" :disabled="processing" @click="emit('close')">إلغاء</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
