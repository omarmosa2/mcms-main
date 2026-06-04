<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogBody, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { toDatetimeLocalValue } from './appointmentHelpers';
import type { Appointment, Option } from './types';

const props = defineProps<{
    appointment: Appointment | null;
    patients: Option[];
    doctors: Option[];
}>();

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <Dialog :open="props.appointment !== null" @update:open="(open: boolean) => !open && emit('close')">
        <DialogContent size="lg">
            <DialogHeader>
                <DialogTitle>تعديل بيانات الموعد</DialogTitle>
                <DialogDescription>تحديث تفاصيل الجدولة بسرعة.</DialogDescription>
            </DialogHeader>

            <DialogBody>
                <Form
                    v-if="props.appointment"
                    v-bind="AppointmentController.update.form(props.appointment.id)"
                    class="space-y-4"
                    :options="{ preserveScroll: true }"
                    @success="emit('close')"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_appointment_number">رقم الموعد</Label>
                            <Input
                                id="edit_appointment_number"
                                name="appointment_number"
                                :value="props.appointment.appointment_number"
                                class="pattern-field-clay"
                                required
                            />
                            <InputError :message="errors.appointment_number" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_appointment_duration">المدة (دقيقة)</Label>
                            <Input
                                id="edit_appointment_duration"
                                name="duration_minutes"
                                type="number"
                                min="5"
                                :value="String(props.appointment.duration_minutes)"
                                class="pattern-field-clay"
                                required
                            />
                            <InputError :message="errors.duration_minutes" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_appointment_patient">المريض</Label>
                            <select
                                id="edit_appointment_patient"
                                name="patient_id"
                                class="pattern-field-clay h-10 px-3 py-2"
                                :value="String(props.appointment.patient_id)"
                            >
                                <option
                                    v-for="patient in props.patients"
                                    :key="`edit-appointment-patient-${patient.id}`"
                                    :value="patient.id"
                                >
                                    {{ patient.full_name }}
                                </option>
                                <option
                                    v-if="!props.patients.some(p => p.id === props.appointment!.patient_id)"
                                    :key="`edit-appointment-patient-current-${props.appointment.patient_id}`"
                                    :value="props.appointment.patient_id"
                                    selected
                                >
                                    {{ props.appointment.patient?.full_name ?? 'مريض حالي' }}
                                </option>
                            </select>
                            <InputError :message="errors.patient_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_appointment_doctor">الطبيب</Label>
                            <select
                                id="edit_appointment_doctor"
                                name="doctor_id"
                                class="pattern-field-clay h-10 px-3 py-2"
                                :value="props.appointment.doctor_id !== null ? String(props.appointment.doctor_id) : ''"
                            >
                                <option value="">غير محدد</option>
                                <option
                                    v-for="doctor in props.doctors"
                                    :key="`edit-appointment-doctor-${doctor.id}`"
                                    :value="doctor.id"
                                >
                                    {{ doctor.name }}
                                </option>
                            </select>
                            <InputError :message="errors.doctor_id" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_appointment_scheduled_for">موعد</Label>
                        <Input
                            id="edit_appointment_scheduled_for"
                            name="scheduled_for"
                            type="datetime-local"
                            :value="toDatetimeLocalValue(props.appointment.scheduled_for)"
                            class="pattern-field-clay"
                            required
                        />
                        <InputError :message="errors.scheduled_for" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_appointment_notes">ملاحظات</Label>
                        <textarea
                            id="edit_appointment_notes"
                            name="notes"
                            rows="3"
                            class="pattern-field-clay"
                            :value="props.appointment.notes ?? ''"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="ghost" class="text-[#6B7280] hover:bg-[#F9FAFB] hover:text-[#374151]" :disabled="processing" @click="emit('close')">إلغاء</Button>
                        <Button type="submit" variant="default" :disabled="processing">
                            <Spinner v-if="processing" class="me-2 h-4 w-4" />
                            {{ processing ? 'جارٍ الحفظ...' : 'حفظ التغييرات' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogBody>
        </DialogContent>
    </Dialog>
</template>