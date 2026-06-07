<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
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
import AppointmentWorkingHoursInput from './AppointmentWorkingHoursInput.vue';
import type { ClinicWorkingHour, DepartmentOption, Option } from './types';

const props = defineProps<{
    open: boolean;
    patients: Option[];
    doctors: Option[];
    departments: DepartmentOption[];
    clinicWorkingHours: ClinicWorkingHour[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const selectedDepartmentId = ref('');

const filteredDoctors = computed(() => {
    if (!selectedDepartmentId.value) {
        return props.doctors;
    }

    const departmentId = Number(selectedDepartmentId.value);

    return props.doctors.filter(
        (doctor) => doctor.department_id === departmentId,
    );
});

const defaultScheduledFor = computed(() => {
    const now = new Date();
    now.setHours(now.getHours() + 1);
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
});
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[calc(100vh-2rem)]" size="lg">
            <DialogHeader>
                <DialogTitle>موعد جديد</DialogTitle>
                <DialogDescription>إضافة موعد جديد بسرعة.</DialogDescription>
            </DialogHeader>

            <Form
                id="appointment-create-form"
                v-bind="AppointmentController.store.form()"
                class="contents"
                v-slot="{ errors }"
                @success="emit('update:open', false)"
            >
                <DialogBody class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="appointment_number">
                            رقم الموعد
                            <span class="text-xs text-muted-foreground">
                                (يُولّد تلقائياً إذا ترك فارغاً)
                            </span>
                        </Label>
                        <Input
                            id="appointment_number"
                            name="appointment_number"
                            placeholder="APT-20250421-0001"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.appointment_number" />
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between gap-2">
                            <Label for="patient_id">المريض</Label>
                            <a
                                :href="PatientController.index.url()"
                                class="text-xs font-medium text-primary hover:underline"
                            >
                                إضافة مريض
                            </a>
                        </div>
                        <select
                            id="patient_id"
                            name="patient_id"
                            required
                            class="pattern-field-clay h-10 px-3 py-1.5"
                        >
                            <option value="">اختر المريض</option>
                            <option
                                v-for="patient in props.patients"
                                :key="patient.id"
                                :value="patient.id"
                            >
                                {{
                                    patient.file_number
                                        ? `${patient.full_name} - ${patient.file_number}`
                                        : patient.full_name
                                }}
                            </option>
                        </select>
                        <InputError :message="errors.patient_id" />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="department_id">العيادة</Label>
                            <select
                                id="department_id"
                                v-model="selectedDepartmentId"
                                class="pattern-field-clay h-10 px-3 py-1.5"
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
                            <Label for="doctor_id">الطبيب</Label>
                            <select
                                id="doctor_id"
                                name="doctor_id"
                                class="pattern-field-clay h-10 px-3 py-1.5"
                            >
                                <option value="">يُحدد لاحقاً</option>
                                <option
                                    v-for="doctor in filteredDoctors"
                                    :key="doctor.id"
                                    :value="doctor.id"
                                >
                                    {{
                                        doctor.department?.name
                                            ? `${doctor.name} - ${doctor.department.name}`
                                            : doctor.name
                                    }}
                                </option>
                            </select>
                            <InputError :message="errors.doctor_id" />
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <AppointmentWorkingHoursInput
                                :working-hours="props.clinicWorkingHours"
                                :default-value="defaultScheduledFor"
                                label="موعد"
                            />
                            <InputError :message="errors.scheduled_for" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="duration_minutes">المدة (دقيقة)</Label>
                            <select
                                id="duration_minutes"
                                name="duration_minutes"
                                required
                                class="pattern-field-clay h-10 px-3 py-1.5"
                            >
                                <option value="15">15 دقيقة</option>
                                <option value="30" selected>30 دقيقة</option>
                                <option value="45">45 دقيقة</option>
                                <option value="60">60 دقيقة</option>
                            </select>
                            <InputError :message="errors.duration_minutes" />
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="appointment_type">نوع الموعد</Label>
                            <select
                                id="appointment_type"
                                name="appointment_type"
                                required
                                class="pattern-field-clay h-10 px-3 py-1.5"
                            >
                                <option value="first_visit">كشفية أولى</option>
                                <option value="review">مراجعة</option>
                            </select>
                            <InputError :message="errors.appointment_type" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="cost">تكلفة الموعد</Label>
                            <Input
                                id="cost"
                                name="cost"
                                type="number"
                                min="0"
                                step="0.01"
                                required
                                placeholder="0"
                            />
                            <InputError :message="errors.cost" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">ملاحظات</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="3"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.notes" />
                    </div>
                </DialogBody>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-[44px]"
                        @click="emit('update:open', false)"
                    >
                        إلغاء
                    </Button>
                    <Button
                        type="submit"
                        variant="default"
                        class="min-h-[44px]"
                    >
                        إنشاء الموعد
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
