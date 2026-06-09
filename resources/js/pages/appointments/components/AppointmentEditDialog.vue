<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    Building2,
    Clock,
    FileText,
    Hash,
    Stethoscope,
    User,
} from 'lucide-vue-next';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
        <DialogContent class="max-h-[calc(100vh-2rem)]" size="lg">
            <DialogHeader>
                <DialogTitle>تعديل بيانات الموعد</DialogTitle>
                <DialogDescription>
                    تحديث تفاصيل الموعد. الحقول المميزة بـ * مطلوبة.
                </DialogDescription>
            </DialogHeader>

            <DialogBody>
                <Form
                    v-if="props.appointment"
                    v-bind="
                        AppointmentController.update.form(props.appointment.id)
                    "
                    class="space-y-5"
                    :options="{ preserveScroll: true }"
                    v-slot="{ errors, processing }"
                    @success="emit('close')"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label
                                for="edit_appointment_number"
                                class="flex items-center gap-1.5 text-xs font-medium"
                            >
                                <Hash class="size-3.5 text-muted-foreground" />
                                رقم الموعد
                            </Label>
                            <Input
                                id="edit_appointment_number"
                                name="appointment_number"
                                :value="props.appointment.appointment_number"
                                required
                                :class="{
                                    'border-destructive':
                                        errors.appointment_number,
                                }"
                            />
                            <InputError :message="errors.appointment_number" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="edit_appointment_duration"
                                class="flex items-center gap-1.5 text-xs font-medium"
                            >
                                <Clock class="size-3.5 text-muted-foreground" />
                                المدة
                                <span class="text-destructive">*</span>
                            </Label>
                            <Select
                                name="duration_minutes"
                                :model-value="
                                    String(props.appointment.duration_minutes)
                                "
                                required
                            >
                                <SelectTrigger
                                    :class="{
                                        'border-destructive':
                                            errors.duration_minutes,
                                    }"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="15">15 دقيقة</SelectItem>
                                    <SelectItem value="30">30 دقيقة</SelectItem>
                                    <SelectItem value="45">45 دقيقة</SelectItem>
                                    <SelectItem value="60">60 دقيقة</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.duration_minutes" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-1.5">
                            <Label
                                for="edit_appointment_patient"
                                class="flex items-center gap-1.5 text-xs font-medium"
                            >
                                <User class="size-3.5 text-muted-foreground" />
                                المريض
                                <span class="text-destructive">*</span>
                            </Label>
                            <Select
                                name="patient_id"
                                :model-value="
                                    String(props.appointment.patient_id)
                                "
                                required
                            >
                                <SelectTrigger
                                    :class="{
                                        'border-destructive': errors.patient_id,
                                    }"
                                >
                                    <SelectValue placeholder="اختر مريضاً" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="patient in props.patients"
                                        :key="`edit-appointment-patient-${patient.id}`"
                                        :value="String(patient.id)"
                                    >
                                        {{
                                            patient.file_number
                                                ? `${patient.full_name ?? patient.name} - ${patient.file_number}`
                                                : (patient.full_name ?? patient.name)
                                        }}
                                    </SelectItem>
                                    <SelectItem
                                        v-if="
                                            !props.patients.some(
                                                (p) =>
                                                    p.id ===
                                                    props.appointment!
                                                        .patient_id,
                                            )
                                        "
                                        :key="`edit-appointment-patient-current-${props.appointment.patient_id}`"
                                        :value="
                                            String(
                                                props.appointment.patient_id,
                                            )
                                        "
                                    >
                                        {{
                                            props.appointment.patient
                                                ?.full_name ?? 'مريض حالي'
                                        }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.patient_id" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="edit_appointment_department"
                                class="flex items-center gap-1.5 text-xs font-medium"
                            >
                                <Building2 class="size-3.5 text-muted-foreground" />
                                العيادة
                            </Label>
                            <Select
                                :model-value="selectedDepartmentId"
                                @update:model-value="
                                    selectedDepartmentId = String($event ?? '')
                                "
                            >
                                <SelectTrigger id="edit_appointment_department">
                                    <SelectValue placeholder="كل العيادات" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all__">
                                        كل العيادات
                                    </SelectItem>
                                    <SelectItem
                                        v-for="department in props.departments"
                                        :key="department.id"
                                        :value="String(department.id)"
                                    >
                                        {{ department.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="edit_appointment_doctor"
                                class="flex items-center gap-1.5 text-xs font-medium"
                            >
                                <Stethoscope class="size-3.5 text-muted-foreground" />
                                الطبيب
                            </Label>
                            <Select
                                name="doctor_id"
                                :model-value="
                                    props.appointment.doctor_id !== null
                                        ? String(props.appointment.doctor_id)
                                        : ''
                                "
                            >
                                <SelectTrigger
                                    :class="{
                                        'border-destructive': errors.doctor_id,
                                    }"
                                >
                                    <SelectValue placeholder="اختر طبيباً" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__none__">
                                        غير محدد
                                    </SelectItem>
                                    <SelectItem
                                        v-for="doctor in filteredDoctors"
                                        :key="`edit-appointment-doctor-${doctor.id}`"
                                        :value="String(doctor.id)"
                                    >
                                        {{
                                            doctor.department?.name
                                                ? `${doctor.name} - ${doctor.department.name}`
                                                : (doctor.name ?? `#${doctor.id}`)
                                        }}
                                    </SelectItem>
                                    <SelectItem
                                        v-if="
                                            props.appointment.doctor_id !==
                                                null &&
                                            !filteredDoctors.some(
                                                (doctor) =>
                                                    doctor.id ===
                                                    props.appointment!
                                                        .doctor_id,
                                            )
                                        "
                                        :key="`edit-appointment-doctor-current-${props.appointment.doctor_id}`"
                                        :value="
                                            String(
                                                props.appointment.doctor_id,
                                            )
                                        "
                                    >
                                        {{
                                            props.appointment.doctor?.name ??
                                            'طبيب حالي'
                                        }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.doctor_id" />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <AppointmentWorkingHoursInput
                            :working-hours="clinicWorkingHours"
                            :default-value="
                                toDatetimeLocalValue(
                                    props.appointment.scheduled_for,
                                )
                            "
                            label="التاريخ والوقت"
                        />
                        <InputError :message="errors.scheduled_for" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label
                            for="edit_appointment_notes"
                            class="flex items-center gap-1.5 text-xs font-medium"
                        >
                            <FileText class="size-3.5 text-muted-foreground" />
                            ملاحظات
                        </Label>
                        <textarea
                            id="edit_appointment_notes"
                            name="notes"
                            rows="3"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            :value="props.appointment.notes ?? ''"
                            placeholder="أي ملاحظات إضافية حول الموعد..."
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="processing"
                            @click="emit('close')"
                        >
                            إلغاء
                        </Button>
                        <Button type="submit" :disabled="processing">
                            <span
                                v-if="processing"
                                class="inline-block size-4 animate-spin rounded-full border-2 border-current border-t-transparent"
                            ></span>
                            {{ processing ? 'جارٍ الحفظ...' : 'حفظ التغييرات' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogBody>
        </DialogContent>
    </Dialog>
</template>
