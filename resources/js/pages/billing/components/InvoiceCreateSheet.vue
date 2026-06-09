<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InvoiceController from '@/actions/App/Http/Controllers/Billing/InvoiceController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Option } from './types';

defineProps<{
    open: boolean;
    patients: Option[];
    appointments: Option[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-[520px] p-0 overflow-hidden">
            <DialogHeader class="p-6 pb-4 border-b border-border/60">
                <DialogTitle>إنشاء فاتورة</DialogTitle>
                <DialogDescription>تسجيل فاتورة جديدة.</DialogDescription>
            </DialogHeader>

            <Form
                id="invoice-create-form"
                v-bind="InvoiceController.store.form()"
                class="px-6 py-4 space-y-4 max-h-[60vh] overflow-y-auto"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="patient_id">المريض</Label>
                    <select
                        id="patient_id"
                        name="patient_id"
                        required
                        class="pattern-field-clay h-10 px-3 py-2"
                    >
                        <option value="">اختر مريض</option>
                        <option
                            v-for="patient in patients"
                            :key="patient.id"
                            :value="patient.id"
                        >
                            {{ patient.full_name }}
                        </option>
                    </select>
                    <InputError :message="errors.patient_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="appointment_id">الموعد</Label>
                    <select
                        id="appointment_id"
                        name="appointment_id"
                        class="pattern-field-clay h-10 px-3 py-2"
                    >
                        <option value="">بدون موعد</option>
                        <option
                            v-for="appointment in appointments"
                            :key="appointment.id"
                            :value="appointment.id"
                        >
                            {{ appointment.appointment_number }}
                        </option>
                    </select>
                    <InputError :message="errors.appointment_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="invoice_number">رقم الفاتورة</Label>
                    <Input
                        id="invoice_number"
                        name="invoice_number"
                        placeholder="INV-1001"
                        class="pattern-field-clay h-10"
                    />
                    <InputError :message="errors.invoice_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="due_at">تاريخ الاستحقاق</Label>
                    <Input id="due_at" name="due_at" type="date" class="pattern-field-clay h-10" />
                    <InputError :message="errors.due_at" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">ملاحظات</Label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="2"
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.notes" />
                </div>

                <div class="pattern-surface-flat border-dashed p-3">
                    <p class="pattern-typographic-title mb-3 text-[0.7rem]">
                        البند الأول
                    </p>
                    <div class="space-y-3">
                        <div class="grid gap-2">
                            <Label for="items_0_description">
                                الوصف
                            </Label>
                            <Input
                                id="items_0_description"
                                name="items[0][description]"
                                required
                                placeholder="استشارة"
                                class="h-10"
                            />
                            <InputError
                                :message="errors['items.0.description']"
                            />
                        </div>

                        <div class="grid gap-2 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="items_0_quantity">الكمية</Label>
                                <Input
                                    id="items_0_quantity"
                                    name="items[0][quantity]"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    value="1"
                                    required
                                    class="h-10"
                                />
                                <InputError
                                    :message="errors['items.0.quantity']"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="items_0_unit_price">
                                    سعر الوحدة
                                </Label>
                                <Input
                                    id="items_0_unit_price"
                                    name="items[0][unit_price]"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value="0"
                                    required
                                    class="h-10"
                                />
                                <InputError
                                    :message="errors['items.0.unit_price']"
                                />
                            </div>
                        </div>

                        <div class="grid gap-2 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="items_0_discount_amount">
                                    الخصم
                                </Label>
                                <Input
                                    id="items_0_discount_amount"
                                    name="items[0][discount_amount]"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value="0"
                                    class="h-10"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="items_0_tax_amount">الضريبة</Label>
                                <Input
                                    id="items_0_tax_amount"
                                    name="items[0][tax_amount]"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value="0"
                                    class="h-10"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </Form>
            <DialogFooter class="p-6 pt-4 border-t border-border/60">
                <Button type="button" variant="outline" @click="emit('update:open', false)">إلغاء</Button>
                <Button form="invoice-create-form" type="submit" variant="default">إنشاء فاتورة</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>