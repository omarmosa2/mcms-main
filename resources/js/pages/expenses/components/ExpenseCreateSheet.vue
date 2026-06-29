<script setup lang="ts">
import ExpenseController from '@/actions/App/Http/Controllers/Expenses/ExpenseController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Form } from '@inertiajs/vue3';

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

const props = defineProps<{ open: boolean; categories: ExpenseCategory[]; clinics: Clinic[] }>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-[600px] p-0 overflow-hidden">
            <DialogHeader class="p-6 pb-4 border-b border-border/60">
                <DialogTitle>إضافة مصروف</DialogTitle>
                <DialogDescription>تسجيل مصروف جديد للمجمع الطبي.</DialogDescription>
            </DialogHeader>

            <Form
                id="expense-create-form"
                v-bind="ExpenseController.store.form()"
                class="px-6 py-4 space-y-4 max-h-[65vh] overflow-y-auto"
                reset-on-success
                v-slot="{ errors, processing }"
                @success="emit('update:open', false)"
            >
                <div class="grid gap-2">
                    <Label for="title">عنوان المصروف *</Label>
                    <Input
                        id="title"
                        name="title"
                        required
                        placeholder="مثال: فاتورة كهرباء"
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="amount">المبلغ *</Label>
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
                        <Label for="expense_date">تاريخ المصروف *</Label>
                        <Input
                            id="expense_date"
                            name="expense_date"
                            type="date"
                            required
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.expense_date" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
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
                        <Label for="clinic_id">العيادة</Label>
                        <select
                            id="clinic_id"
                            name="clinic_id"
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="">مصروف عام</option>
                            <option
                                v-for="clinic in clinics"
                                :key="clinic.id"
                                :value="clinic.id"
                            >
                                {{ clinic.name }}
                            </option>
                        </select>
                        <InputError :message="errors.clinic_id" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="payment_method">طريقة الدفع *</Label>
                        <select
                            id="payment_method"
                            name="payment_method"
                            required
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="">اختر طريقة الدفع</option>
                            <option value="cash">نقداً</option>
                            <option value="transfer">تحويل</option>
                            <option value="card">بطاقة</option>
                            <option value="other">أخرى</option>
                        </select>
                        <InputError :message="errors.payment_method" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="status">الحالة *</Label>
                        <select
                            id="status"
                            name="status"
                            required
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="pending">معلق</option>
                            <option value="paid">مدفوع</option>
                            <option value="cancelled">ملغي</option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="paid_to">الجهة المستلمة</Label>
                    <Input
                        id="paid_to"
                        name="paid_to"
                        placeholder="اسم الشخص أو الجهة"
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.paid_to" />
                </div>

                <div class="grid gap-2">
                    <Label for="reference_number">الرقم المرجعي</Label>
                    <Input
                        id="reference_number"
                        name="reference_number"
                        placeholder="رقم الفاتورة أو المرجع"
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.reference_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">ملاحظات</Label>
                    <textarea
                        id="description"
                        name="description"
                        rows="2"
                        class="pattern-field-clay"
                        placeholder="ملاحظات اختيارية"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label for="attachment">مرفق (PDF أو صورة)</Label>
                    <Input
                        id="attachment"
                        name="attachment"
                        type="file"
                        accept=".pdf,.png,.jpg,.jpeg"
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.attachment" />
                </div>
            </Form>
            <DialogFooter class="p-6 pt-4 border-t border-border/60">
                <Button type="button" variant="outline" @click="emit('update:open', false)">إلغاء</Button>
                <Button form="expense-create-form" type="submit" variant="clay">إضافة المصروف</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
