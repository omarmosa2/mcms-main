<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    Building2,
    Calculator,
    Clock,
    DollarSign,
    FileText,
    Hash,
    Stethoscope,
    User,
} from 'lucide-vue-next';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppointmentWorkingHoursInput from './AppointmentWorkingHoursInput.vue';
import type { AvailabilityPeriod, ClinicWorkingHour, DepartmentOption, Option, TodayAvailability } from './types';

const props = defineProps<{
    open: boolean;
    patients: Option[];
    doctors: Option[];
    departments: DepartmentOption[];
    clinicWorkingHours: ClinicWorkingHour[];
    todayAvailability: TodayAvailability;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const selectedDepartmentId = ref('');
const selectedDoctorId = ref('');
const formResetKey = ref(0);

const todayAvailableDepartmentIds = computed(
    () => new Set(props.todayAvailability.departments),
);

const availableDepartments = computed(() =>
    props.departments.filter((department) =>
        todayAvailableDepartmentIds.value.has(department.id),
    ),
);

const filteredDoctors = computed(() => {
    const todayAvailableDoctorIds = new Set(
        props.todayAvailability.doctors.map((doctor) => doctor.id),
    );
    const availableDoctors = props.doctors.filter((doctor) =>
        todayAvailableDoctorIds.has(doctor.id),
    );

    if (!selectedDepartmentId.value) {
        return availableDoctors;
    }

    const departmentId = Number(selectedDepartmentId.value);

    return availableDoctors.filter(
        (doctor) => doctor.department_id === departmentId,
    );
});

const selectedAvailablePeriods = computed<AvailabilityPeriod[]>(() => {
    const doctorId = Number(selectedDoctorId.value);

    if (Number.isFinite(doctorId) && doctorId > 0) {
        return (
            props.todayAvailability.doctors.find((doctor) => doctor.id === doctorId)
                ?.available_periods ?? []
        );
    }

    const departmentId = Number(selectedDepartmentId.value);

    if (Number.isFinite(departmentId) && departmentId > 0) {
        return props.todayAvailability.department_periods[departmentId] ?? [];
    }

    return Object.values(props.todayAvailability.department_periods).flat();
});

const handleDepartmentChange = (value: unknown): void => {
    const departmentId = String(value ?? '');

    selectedDepartmentId.value = departmentId === '__all__' ? '' : departmentId;
    selectedDoctorId.value = '';
};

const handleDoctorChange = (value: unknown): void => {
    const doctorId = String(value ?? '');

    selectedDoctorId.value = doctorId === '__none__' ? '' : doctorId;
};

const resetFormState = (): void => {
    selectedDepartmentId.value = '';
    selectedDoctorId.value = '';
    formResetKey.value += 1;
};

const handleSuccess = (): void => {
    resetFormState();
    emit('update:open', false);
};

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
                <DialogTitle>إضافة موعد جديد</DialogTitle>
                <DialogDescription>
                    أدخل بيانات الموعد الجديد. الحقول المميزة بـ * مطلوبة.
                </DialogDescription>
            </DialogHeader>

            <Form
                :key="formResetKey"
                id="appointment-create-form"
                v-bind="AppointmentController.store.form()"
                class="contents"
                v-slot="{ errors, processing }"
                reset-on-success
                @success="handleSuccess"
            >
                <DialogBody class="space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label
                                for="appointment_number"
                                class="flex items-center gap-1.5 text-xs font-medium"
                            >
                                <Hash class="size-3.5 text-muted-foreground" />
                                رقم الموعد
                            </Label>
                            <Input
                                id="appointment_number"
                                name="appointment_number"
                                placeholder="يُولّد تلقائياً إذا ترك فارغاً"
                                :class="{
                                    'border-destructive':
                                        errors.appointment_number,
                                }"
                            />
                            <InputError
                                :message="errors.appointment_number"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <div class="flex items-center justify-between">
                                <Label
                                    for="patient_id"
                                    class="flex items-center gap-1.5 text-xs font-medium"
                                >
                                    <User class="size-3.5 text-muted-foreground" />
                                    المريض
                                    <span class="text-destructive">*</span>
                                </Label>
                                <a
                                    :href="PatientController.index.url()"
                                    class="text-xs font-medium text-primary hover:underline"
                                >
                                    إضافة مريض
                                </a>
                            </div>
                            <Select name="patient_id" required>
                                <SelectTrigger
                                    id="patient_id"
                                    :class="{
                                        'border-destructive': errors.patient_id,
                                    }"
                                >
                                    <SelectValue placeholder="اختر مريضاً" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="patient in props.patients"
                                        :key="patient.id"
                                        :value="String(patient.id)"
                                    >
                                        {{
                                            patient.file_number
                                                ? `${patient.full_name ?? patient.name} - ${patient.file_number}`
                                                : (patient.full_name ?? patient.name)
                                        }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.patient_id" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label
                                for="department_id"
                                class="flex items-center gap-1.5 text-xs font-medium"
                            >
                                <Building2 class="size-3.5 text-muted-foreground" />
                                العيادة
                            </Label>
                            <Select
                                :model-value="selectedDepartmentId"
                                @update:model-value="handleDepartmentChange"
                            >
                                <SelectTrigger id="department_id">
                                    <SelectValue placeholder="كل العيادات" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all__">
                                        كل العيادات
                                    </SelectItem>
                                    <SelectItem
                                        v-for="department in availableDepartments"
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
                                for="doctor_id"
                                class="flex items-center gap-1.5 text-xs font-medium"
                            >
                                <Stethoscope class="size-3.5 text-muted-foreground" />
                                الطبيب
                            </Label>
                            <Select
                                name="doctor_id"
                                :model-value="selectedDoctorId"
                                @update:model-value="handleDoctorChange"
                            >
                                <SelectTrigger
                                    id="doctor_id"
                                    :class="{
                                        'border-destructive': errors.doctor_id,
                                    }"
                                >
                                    <SelectValue placeholder="اختر طبيباً" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__none__">
                                        يُحدد لاحقاً
                                    </SelectItem>
                                    <SelectItem
                                        v-for="doctor in filteredDoctors"
                                        :key="doctor.id"
                                        :value="String(doctor.id)"
                                    >
                                        {{
                                            doctor.department?.name
                                                ? `${doctor.name} - ${doctor.department.name}`
                                                : (doctor.name ?? `#${doctor.id}`)
                                        }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.doctor_id" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <AppointmentWorkingHoursInput
                                :working-hours="props.clinicWorkingHours"
                                :available-periods="selectedAvailablePeriods"
                                :availability-date="props.todayAvailability.date"
                                :default-value="defaultScheduledFor"
                                label="التاريخ والوقت"
                            />
                            <InputError :message="errors.scheduled_for" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="duration_minutes"
                                class="flex items-center gap-1.5 text-xs font-medium"
                            >
                                <Clock class="size-3.5 text-muted-foreground" />
                                المدة
                                <span class="text-destructive">*</span>
                            </Label>
                            <Select name="duration_minutes" required>
                                <SelectTrigger
                                    id="duration_minutes"
                                    :class="{
                                        'border-destructive':
                                            errors.duration_minutes,
                                    }"
                                >
                                    <SelectValue placeholder="30 دقيقة" />
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

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label
                                for="appointment_type"
                                class="flex items-center gap-1.5 text-xs font-medium"
                            >
                                <Calculator class="size-3.5 text-muted-foreground" />
                                نوع الموعد
                                <span class="text-destructive">*</span>
                            </Label>
                            <Select name="appointment_type" required>
                                <SelectTrigger
                                    id="appointment_type"
                                    :class="{
                                        'border-destructive':
                                            errors.appointment_type,
                                    }"
                                >
                                    <SelectValue placeholder="كشفية أولى" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="first_visit">
                                        كشفية أولى
                                    </SelectItem>
                                    <SelectItem value="review">مراجعة</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.appointment_type" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="cost"
                                class="flex items-center gap-1.5 text-xs font-medium"
                            >
                                <DollarSign class="size-3.5 text-muted-foreground" />
                                التكلفة
                                <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="cost"
                                name="cost"
                                type="number"
                                min="0"
                                step="0.01"
                                required
                                placeholder="0"
                                :class="{ 'border-destructive': errors.cost }"
                            />
                            <InputError :message="errors.cost" />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label
                            for="notes"
                            class="flex items-center gap-1.5 text-xs font-medium"
                        >
                            <FileText class="size-3.5 text-muted-foreground" />
                            ملاحظات
                        </Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="3"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            placeholder="أي ملاحظات إضافية حول الموعد..."
                        />
                        <InputError :message="errors.notes" />
                    </div>
                </DialogBody>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('update:open', false)"
                    >
                        إلغاء
                    </Button>
                    <Button type="submit" :disabled="processing">
                        <span
                            v-if="processing"
                            class="inline-block size-4 animate-spin rounded-full border-2 border-current border-t-transparent"
                        ></span>
                        {{ processing ? 'جاري الإنشاء...' : 'إنشاء الموعد' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
