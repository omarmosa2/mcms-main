<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import InvoiceController from '@/actions/App/Http/Controllers/Billing/InvoiceController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { usePermissions } from '@/composables/usePermissions';
import type { Invoice, Option } from './types';

const props = defineProps<{
    invoice: Invoice | null;
    patients: Option[];
    appointments: Option[];
    visits: Option[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const { can } = usePermissions();

const canEditInvoice = computed<boolean>(() => can('billing.generate'));

const handleClose = (): void => {
    emit('close');
};
</script>

<template>
    <Dialog
        :open="invoice !== null"
        @update:open="(open: boolean) => !open && handleClose()"
        aria-label="تعديل الفاتورة"
    >
        <DialogContent size="2xl">
            <DialogHeader>
                <DialogTitle>تعديل بيانات الفاتورة</DialogTitle>
                <DialogDescription>
                    تحديث بيانات الفاتورة المسودة والروابط.
                </DialogDescription>
            </DialogHeader>

            <DialogBody>
                <Form
                    v-if="
                        invoice &&
                        canEditInvoice &&
                        invoice.status === 'draft'
                    "
                    v-bind="InvoiceController.update.form(invoice.id)"
                    class="space-y-4"
                    :options="{ preserveScroll: true }"
                    @success="handleClose"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_invoice_number">رقم الفاتورة</Label>
                            <Input
                                id="edit_invoice_number"
                                name="invoice_number"
                                :value="invoice.invoice_number"
                                class="pattern-field-clay h-10"
                                required
                            />
                            <InputError :message="errors.invoice_number" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_invoice_due_at">تاريخ الاستحقاق</Label>
                            <Input
                                id="edit_invoice_due_at"
                                name="due_at"
                                type="date"
                                :value="invoice.due_at ?? ''"
                                class="pattern-field-clay h-10"
                            />
                            <InputError :message="errors.due_at" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="edit_invoice_patient">المريض</Label>
                            <select
                                id="edit_invoice_patient"
                                name="patient_id"
                                class="pattern-field-clay h-10 px-3 py-2"
                                :value="String(invoice.patient_id)"
                                required
                            >
                                <option
                                    v-for="patient in patients"
                                    :key="`edit-invoice-patient-${patient.id}`"
                                    :value="patient.id"
                                >
                                    {{ patient.full_name }}
                                </option>
                            </select>
                            <InputError :message="errors.patient_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_invoice_visit">الزيارة</Label>
                            <select
                                id="edit_invoice_visit"
                                name="visit_id"
                                class="pattern-field-clay h-10 px-3 py-2"
                                :value="
                                    invoice.visit_id !== null
                                        ? String(invoice.visit_id)
                                        : ''
                                "
                            >
                                <option value="">بدون زيارة</option>
                                <option
                                    v-for="visit in visits"
                                    :key="`edit-invoice-visit-${visit.id}`"
                                    :value="visit.id"
                                >
                                    {{ visit.visit_number }}
                                </option>
                            </select>
                            <InputError :message="errors.visit_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_invoice_appointment">
                                الموعد
                            </Label>
                            <select
                                id="edit_invoice_appointment"
                                name="appointment_id"
                                class="pattern-field-clay h-10 px-3 py-2"
                                :value="
                                    invoice.appointment_id !== null
                                        ? String(invoice.appointment_id)
                                        : ''
                                "
                            >
                                <option value="">بدون موعد</option>
                                <option
                                    v-for="appointment in appointments"
                                    :key="`edit-invoice-appointment-${appointment.id}`"
                                    :value="appointment.id"
                                >
                                    {{ appointment.appointment_number }}
                                </option>
                            </select>
                            <InputError :message="errors.appointment_id" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_invoice_notes">ملاحظات</Label>
                        <textarea
                            id="edit_invoice_notes"
                            name="notes"
                            rows="3"
                            class="pattern-field-clay"
                            :value="invoice.notes ?? ''"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            class="text-[#6B7280] hover:bg-[#F9FAFB] hover:text-[#374151]"
                            :disabled="processing"
                            @click="handleClose"
                        >
                            إلغاء
                        </Button>
                        <Button
                            type="submit"
                            variant="default"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" class="me-2 h-4 w-4" />
                            {{ processing ? 'جارٍ الحفظ...' : 'حفظ التغييرات' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogBody>
        </DialogContent>
    </Dialog>
</template>