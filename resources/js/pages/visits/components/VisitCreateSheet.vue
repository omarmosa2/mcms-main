<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import VisitController from '@/actions/App/Http/Controllers/Visits/VisitController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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

const props = defineProps<{
    open: boolean;
    patients: Option[];
    queueEntries: Option[];
    appointments: Option[];
    doctors: Option[];
    prefill?: {
        patient_id: number | null;
        appointment_id: number | null;
        doctor_id: number | null;
    };
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-[520px] overflow-hidden p-0">
            <DialogHeader class="border-b border-border/60 p-6 pb-4">
                <DialogTitle>بدء زيارة</DialogTitle>
                <DialogDescription>بدء زيارة سريرية جديدة.</DialogDescription>
            </DialogHeader>

            <Form
                id="visit-create-form"
                v-bind="VisitController.store.form()"
                class="max-h-[60vh] space-y-4 overflow-y-auto px-6 py-4"
                v-slot="{ errors, processing }"
                @success="emit('update:open', false)"
            >
                <div class="grid gap-2">
                    <Label
                        for="patient_id"
                        class="text-xs font-medium text-slate-600"
                        >المريض</Label
                    >
                    <select
                        id="patient_id"
                        name="patient_id"
                        required
                        :value="props.prefill?.patient_id ?? ''"
                        class="h-9 w-full rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-[#0EA5E9]/40 focus:ring-1 focus:ring-[#0EA5E9]/20 focus:outline-none"
                    >
                        <option value="">اختر المريض</option>
                        <option
                            v-for="patient in props.patients"
                            :key="patient.id"
                            :value="patient.id"
                        >
                            {{ patient.full_name }}
                        </option>
                        <option
                            v-if="
                                props.prefill?.patient_id &&
                                !props.patients.some(
                                    (patient) =>
                                        patient.id ===
                                        props.prefill?.patient_id,
                                )
                            "
                            :value="props.prefill.patient_id"
                        >
                            مريض محدد
                        </option>
                    </select>
                    <InputError :message="errors.patient_id" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="queue_entry_id"
                        class="text-xs font-medium text-slate-600"
                        >قائمة الانتظار</Label
                    >
                    <select
                        id="queue_entry_id"
                        name="queue_entry_id"
                        class="h-9 w-full rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-[#0EA5E9]/40 focus:ring-1 focus:ring-[#0EA5E9]/20 focus:outline-none"
                    >
                        <option value="">بدون قائمة انتظار</option>
                        <option
                            v-for="queueEntry in props.queueEntries"
                            :key="queueEntry.id"
                            :value="queueEntry.id"
                        >
                            {{ queueEntry.label }}
                        </option>
                    </select>
                    <InputError :message="errors.queue_entry_id" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="appointment_id"
                        class="text-xs font-medium text-slate-600"
                        >الموعد</Label
                    >
                    <select
                        id="appointment_id"
                        name="appointment_id"
                        :value="props.prefill?.appointment_id ?? ''"
                        class="h-9 w-full rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-[#0EA5E9]/40 focus:ring-1 focus:ring-[#0EA5E9]/20 focus:outline-none"
                    >
                        <option value="">بدون موعد</option>
                        <option
                            v-for="appointment in props.appointments"
                            :key="appointment.id"
                            :value="appointment.id"
                        >
                            {{ appointment.appointment_number }}
                        </option>
                        <option
                            v-if="
                                props.prefill?.appointment_id &&
                                !props.appointments.some(
                                    (appointment) =>
                                        appointment.id ===
                                        props.prefill?.appointment_id,
                                )
                            "
                            :value="props.prefill.appointment_id"
                        >
                            موعد محدد
                        </option>
                    </select>
                    <InputError :message="errors.appointment_id" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="doctor_id"
                        class="text-xs font-medium text-slate-600"
                        >الطبيب</Label
                    >
                    <select
                        id="doctor_id"
                        name="doctor_id"
                        :value="props.prefill?.doctor_id ?? ''"
                        class="h-9 w-full rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-[#0EA5E9]/40 focus:ring-1 focus:ring-[#0EA5E9]/20 focus:outline-none"
                    >
                        <option value="">غير محدد</option>
                        <option
                            v-for="doctor in props.doctors"
                            :key="doctor.id"
                            :value="doctor.id"
                        >
                            {{ doctor.name }}
                        </option>
                        <option
                            v-if="
                                props.prefill?.doctor_id &&
                                !props.doctors.some(
                                    (doctor) =>
                                        doctor.id === props.prefill?.doctor_id,
                                )
                            "
                            :value="props.prefill.doctor_id"
                        >
                            طبيب محدد
                        </option>
                    </select>
                    <InputError :message="errors.doctor_id" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="chief_complaint"
                        class="text-xs font-medium text-slate-600"
                        >الشكوى الرئيسية</Label
                    >
                    <textarea
                        id="chief_complaint"
                        name="chief_complaint"
                        rows="3"
                        class="h-auto w-full rounded-lg border border-slate-200/80 bg-white px-3 py-2 text-sm text-slate-700 focus:border-[#0EA5E9]/40 focus:ring-1 focus:ring-[#0EA5E9]/20 focus:outline-none"
                    ></textarea>
                    <InputError :message="errors.chief_complaint" />
                </div>

                <Button
                    :disabled="processing"
                    variant="default"
                    class="h-9 w-full rounded-lg bg-[#0EA5E9] shadow-sm hover:bg-[#0284C7]"
                    >بدء الزيارة</Button
                >
            </Form>

            <DialogFooter class="border-t border-border/60 p-6 pt-4">
                <Button
                    type="button"
                    variant="outline"
                    @click="emit('update:open', false)"
                >
                    إلغاء
                </Button>
                <Button
                    form="visit-create-form"
                    type="submit"
                    variant="default"
                >
                    بدء الزيارة
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
