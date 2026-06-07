<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import PaymentController from '@/actions/App/Http/Controllers/Billing/PaymentController';
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
import { useToast } from '@/composables/useToast';
import type { Invoice, Option } from './types';

const props = defineProps<{
    invoice: Invoice | null;
    patients: Option[];
    appointments: Option[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const { can } = usePermissions();
const toast = useToast();

const statusLabels: Record<string, string> = {
    draft: 'مسودة',
    issued: 'صادرة',
    paid: 'مدفوعة',
    overdue: 'متأخرة',
    canceled: 'ملغاة',
};

const resolvePatientName = (patientId: number): string => {
    return props.patients.find((patient) => patient.id === patientId)?.full_name ?? '-';
};

const resolveAppointmentNumber = (appointmentId: number | null): string => {
    if (appointmentId === null) {
        return '-';
    }

    return (
        props.appointments.find((appointment) => appointment.id === appointmentId)
            ?.appointment_number ?? '-'
    );
};

const handleClose = (): void => {
    emit('close');
};
</script>

<template>
    <Dialog
        :open="invoice !== null"
        @update:open="(open: boolean) => !open && handleClose()"
        aria-label="عرض تفاصيل الفاتورة"
    >
        <DialogContent size="lg">
            <DialogHeader>
                <DialogTitle>عرض تفاصيل الفاتورة</DialogTitle>
                <DialogDescription>
                    ملخص الفاتورة وحالة الدفع.
                </DialogDescription>
            </DialogHeader>

            <DialogBody>
                <dl
                    v-if="invoice"
                    class="grid gap-3 sm:grid-cols-2"
                >
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                        >
                            المريض
                        </dt>
                        <dd class="text-sm">
                            {{
                                invoice.patient?.full_name ??
                                resolvePatientName(invoice.patient_id)
                            }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                        >
                            الحالة
                        </dt>
                        <dd class="text-sm capitalize">
                            {{ statusLabels[invoice.status] ?? invoice.status }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                        >
                            الموعد
                        </dt>
                        <dd class="text-sm">
                            {{
                                resolveAppointmentNumber(
                                    invoice.appointment_id,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                        >
                            تاريخ الاستحقاق
                        </dt>
                        <dd class="text-sm">
                            {{ invoice.due_at ?? 'غير محدد' }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                        >
                            تاريخ الإصدار
                        </dt>
                        <dd class="text-sm">
                            {{
                                invoice.issued_at
                                    ? new Date(
                                          invoice.issued_at,
                                      ).toLocaleString()
                                    : 'غير صادرة'
                            }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                        >
                            المجموع الفرعي
                        </dt>
                        <dd class="text-sm">
                            {{ invoice.subtotal_amount.toFixed(2) }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                        >
                            الخصم / الضريبة
                        </dt>
                        <dd class="text-sm">
                            -{{ invoice.discount_amount.toFixed(2) }} /
                            +{{ invoice.tax_amount.toFixed(2) }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                        >
                            الإجمالي
                        </dt>
                        <dd class="text-sm">
                            {{ invoice.total_amount.toFixed(2) }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                        >
                            المدفوع / الرصيد
                        </dt>
                        <dd class="text-sm">
                            {{ invoice.paid_amount.toFixed(2) }} /
                            {{ invoice.balance_amount.toFixed(2) }}
                        </dd>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                        >
                            ملاحظات
                        </dt>
                        <dd class="text-sm leading-6 text-muted-foreground">
                            {{ invoice.notes ?? 'لا توجد ملاحظات' }}
                        </dd>
                    </div>
                    <div
                        v-if="(invoice.items ?? []).length > 0"
                        class="space-y-2 sm:col-span-2"
                    >
                        <dt
                            class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                        >
                            البنود
                        </dt>
                        <ul class="space-y-1 text-sm">
                            <li
                                v-for="item in invoice.items"
                                :key="item.id"
                                class="flex items-center justify-between gap-3 rounded-lg border border-border/60 bg-background/60 px-3 py-2"
                            >
                                <span class="text-muted-foreground">
                                    {{ item.description }}
                                </span>
                                <span class="font-medium">
                                    {{ item.line_total.toFixed(2) }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </dl>
                <div
                    v-if="invoice && invoice.balance_amount > 0 && can('payment.record')"
                        class="rounded-xl border-2 border-dashed border-success-300/50 bg-success-50/50 p-4"
                    >
                        <Form
                            v-bind="PaymentController.store.form(invoice.id)"
                            class="flex items-center gap-2"
                            v-slot="{ errors, processing }"
                            @success="() => {
                                toast.success('تم تسجيل الدفعة بنجاح');
                                handleClose();
                            }"
                        >
                            <Input
                                name="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                :max="invoice.balance_amount"
                                :placeholder="`الرصيد: ${invoice.balance_amount.toFixed(2)}`"
                                class="pattern-field-clay h-10 text-sm"
                                required
                            />
                            <InputError :message="errors.amount" />
                            <Button
                                :disabled="processing"
                                variant="default"
                                size="sm"
                                class="h-10 px-4 text-xs"
                            >
                                <Spinner v-if="processing" class="me-2 h-3 w-3" />
                                {{ processing ? 'جارٍ...' : 'تسجيل الدفعة' }}
                            </Button>
                        </Form>
                    </div>
            </DialogBody>

            <DialogFooter>
                <Button
                    type="button"
                    variant="ghost"
                    class="text-[#6B7280] hover:bg-[#F9FAFB] hover:text-[#374151]"
                    @click="handleClose"
                >
                    إغلاق
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>