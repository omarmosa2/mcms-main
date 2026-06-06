<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
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
import { toDatetimeLocalValue } from './appointmentHelpers';
import AppointmentWorkingHoursInput from './AppointmentWorkingHoursInput.vue';
import type {
    Appointment,
    ClinicWorkingHour,
    DepartmentOption,
    Option,
} from './types';

const props = defineProps<{
    appointment: Appointment | null;
    patients: Option[];
    doctors: Option[];
    departments: DepartmentOption[];
    clinicWorkingHours: ClinicWorkingHour[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const selectedDepartmentId = ref('');

watch(
    () => props.appointment,
    (appointment) => {
        selectedDepartmentId.value =
            appointment?.doctor?.department?.id !== undefined
                ? String(appointment.doctor.department.id)
                : '';
    },
    { immediate: true },
);

const filteredDoctors = computed(() => {
    if (!selectedDepartmentId.value) {
        return props.doctors;
    }

    const departmentId = Number(selectedDepartmentId.value);

    return props.doctors.filter(
        (doctor) => doctor.department_id === departmentId,
    );
});
</script>

<template>
    <Dialog
        :open="props.appointment !== null"
        @update:open="(open: boolean) => !open && emit('close')"
    >
        <DialogContent size="lg">
            <DialogHeader>
                <DialogTitle>تعديل بيانات الموعد</DialogTitle>
                <DialogDescription
                    >تحديث تفاصيل الجدولة بسرعة.</DialogDescription
                >
            </DialogHeader>

            <DialogBody>
                <Form
                    v-if="props.appointment"
                    v-bind="
                        AppointmentController.update.form(props.appointment.id)
                    "
                    class="space-y-4"
                    :options="{ preserveScroll: true }"
                    v-slot="{ errors, processing }"
                    @success="emit('close')"
                >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_appointment_number"
                                >رقم الموعد</Label
                            >
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
                            <Label for="edit_appointment_duration"
                                >المدة (دقيقة)</Label
                            >
                            <select
                                id="edit_appointment_duration"
                                name="duration_minutes"
                                :value="
                                    String(props.appointment.duration_minutes)
                                "
                                class="pattern-field-clay h-10 px-3 py-2"
                                required
                            >
                                <option value="15">15 دقيقة</option>
                                <option value="30">30 دقيقة</option>
                                <option value="45">45 دقيقة</option>
                                <option value="60">60 دقيقة</option>
                            </select>
                            <InputError :message="errors.duration_minutes" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
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
                                    {{
                                        patient.file_number
                                            ? `${patient.full_name} - ${patient.file_number}`
                                            : patient.full_name
                                    }}
                                </option>
                                <option
                                    v-if="
                                        !props.patients.some(
                                            (p) =>
                                                p.id ===
                                                props.appointment!.patient_id,
                                        )
                                    "
                                    :key="`edit-appointment-patient-current-${props.appointment.patient_id}`"
                                    :value="props.appointment.patient_id"
                                    selected
                                >
                                    {{
                                        props.appointment.patient?.full_name ??
                                        'مريض حالي'
                                    }}
                                </option>
                            </select>
                            <InputError :message="errors.patient_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_appointment_department"
                                >العيادة</Label
                            >
                            <select
                                id="edit_appointment_department"
                                v-model="selectedDepartmentId"
                                class="pattern-field-clay h-10 px-3 py-2"
                            >
                                <option value="">كل العيادات</option>
                                <option
                                    v-for="department in props.departments"
                                    :key="department.id"
                                    :value="String(department.id)"
                                >
                                    {{ department.name }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_appointment_doctor">الطبيب</Label>
                            <select
                                id="edit_appointment_doctor"
                                name="doctor_id"
                                class="pattern-field-clay h-10 px-3 py-2"
                                :value="
                                    props.appointment.doctor_id !== null
                                        ? String(props.appointment.doctor_id)
                                        : ''
                                "
                            >
                                <option value="">غير محدد</option>
                                <option
                                    v-for="doctor in filteredDoctors"
                                    :key="`edit-appointment-doctor-${doctor.id}`"
                                    :value="doctor.id"
                                >
                                    {{
                                        doctor.department?.name
                                            ? `${doctor.name} - ${doctor.department.name}`
                                            : doctor.name
                                    }}
                                </option>
                                <option
                                    v-if="
                                        props.appointment.doctor_id !== null &&
                                        !filteredDoctors.some(
                                            (doctor) =>
                                                doctor.id ===
                                                props.appointment!.doctor_id,
                                        )
                                    "
                                    :key="`edit-appointment-doctor-current-${props.appointment.doctor_id}`"
                                    :value="props.appointment.doctor_id"
                                    selected
                                >
                                    {{
                                        props.appointment.doctor?.name ??
                                        'طبيب حالي'
                                    }}
                                </option>
                            </select>
                            <InputError :message="errors.doctor_id" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <AppointmentWorkingHoursInput
                            :working-hours="clinicWorkingHours"
                            :default-value="
                                toDatetimeLocalValue(
                                    props.appointment.scheduled_for,
                                )
                            "
                            label="موعد"
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
                        <Button
                            type="button"
                            variant="ghost"
                            class="text-[#6B7280] hover:bg-[#F9FAFB] hover:text-[#374151]"
                            :disabled="processing"
                            @click="emit('close')"
                            >إلغاء</Button
                        >
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
