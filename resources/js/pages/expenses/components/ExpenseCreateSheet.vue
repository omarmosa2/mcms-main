<script setup lang="ts">
import ExpenseController from '@/actions/App/Http/Controllers/Expenses/ExpenseController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Form } from '@inertiajs/vue3';

type ExpenseCategory = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
};

const props = defineProps<{ open: boolean; categories: ExpenseCategory[] }>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-[520px] p-0 overflow-hidden">
            <DialogHeader class="p-6 pb-4 border-b border-border/60">
                <DialogTitle>تسجيل مصروف</DialogTitle>
                <DialogDescription>تسجيل مصروف جديد.</DialogDescription>
            </DialogHeader>

            <Form
                id="expense-create-form"
                v-bind="ExpenseController.store.form()"
                class="px-6 py-4 space-y-4 max-h-[60vh] overflow-y-auto"
                reset-on-success
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="description">الوصف</Label>
                    <Input
                        id="description"
                        name="description"
                        required
                        placeholder="مستلزمات مكتبية"
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label for="amount">المبلغ</Label>
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
                    <Label for="expense_date">تاريخ المصروف</Label>
                    <Input
                        id="expense_date"
                        name="expense_date"
                        type="date"
                        required
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.expense_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">ملاحظات</Label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="2"
                        class="pattern-field-clay"
                        placeholder="ملاحظات اختيارية"
                    />
                    <InputError :message="errors.notes" />
                </div>
            </Form>
            <DialogFooter class="p-6 pt-4 border-t border-border/60">
                <Button type="button" variant="outline" @click="emit('update:open', false)">إلغاء</Button>
                <Button form="expense-create-form" type="submit" variant="clay">تسجيل مصروف</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
