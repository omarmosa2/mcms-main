<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    Building2,
    Calculator,
    Clock,
    DollarSign,
    RotateCcw,
    Stethoscope,
    User,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import type {
    AvailabilityPeriod,
    ClinicWorkingHour,
    DepartmentOption,
    Option,
    TodayAvailability,
} from './types';

const props = defineProps<{
    patients: Option[];
    doctors: Option[];
    departments: DepartmentOption[];
    clinicWorkingHours: ClinicWorkingHour[];
    todayAvailability: TodayAvailability;
}>();

const emit = defineEmits<{
    success: [];
    error: [];
    reset: [];
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
            props.todayAvailability.doctors.find(
                (doctor) => doctor.id === doctorId,
            )?.available_periods ?? []
        );
    }

    const departmentId = Number(selectedDepartmentId.value);

    if (Number.isFinite(departmentId) && departmentId > 0) {
        return props.todayAvailability.department_periods[departmentId] ?? [];
    }

    return Object.values(props.todayAvailability.department_periods).flat();
});

const handleDepartmentChange = (value: unknown): void => {
    const strValue = String(value ?? '');
    selectedDepartmentId.value = strValue === '__all__' ? '' : strValue;
    selectedDoctorId.value = '';
};

const handleDoctorChange = (value: unknown): void => {
    const strValue = String(value ?? '');
    selectedDoctorId.value = strValue === '__none__' ? '' : strValue;
};

const resetFormState = (): void => {
    selectedDepartmentId.value = '';
    selectedDoctorId.value = '';
    formResetKey.value += 1;
};

const handleReset = (): void => {
    resetFormState();
    emit('reset');
};

const handleSuccess = (): void => {
    resetFormState();
    emit('success');
};

const handleSubmit = (event: SubmitEvent): void => {
    const form = event.target as HTMLFormElement;
    const doctorSelect = form.querySelector(
        'select[name="doctor_id"]',
    ) as HTMLSelectElement | null;

    if (doctorSelect && doctorSelect.value === '__none__') {
        doctorSelect.disabled = true;
        setTimeout(() => {
            doctorSelect.disabled = false;
        }, 0);
    }
};
</script>

<template>
    <section class="glass-panel-soft p-5">
        <div
            class="flex flex-col gap-3 border-b border-border/70 bg-secondary/40 px-5 py-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <Zap class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-foreground">
                        إضافة سريعة لموعد
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        حقول مختصرة للحجز اليومي، التفاصيل الإضافية من زر إضافة
                        موعد.
                    </p>
                </div>
            </div>

            <div
                class="inline-flex w-fit items-center gap-2 rounded-xl border border-border bg-background px-3 py-1.5 text-xs text-muted-foreground"
            >
                <kbd
                    class="rounded-md border border-border bg-secondary px-2 py-0.5 font-mono text-[0.68rem] text-foreground"
                >
                    Enter
                </kbd>
                حفظ سريع
            </div>
        </div>

        <Form
            :key="formResetKey"
            v-bind="AppointmentController.store.form()"
            class="pt-5"
            v-slot="{ errors, processing }"
            reset-on-success
            @submit="handleSubmit"
            @success="handleSuccess"
            @error="emit('error')"
        >
            <div class="grid gap-4 xl:grid-cols-12 xl:items-start">
                <div class="grid gap-1.5 xl:col-span-3">
                    <Label
                        for="quick_patient"
                        class="flex items-center gap-1.5 text-xs font-medium"
                    >
                        <User class="size-3.5 text-muted-foreground" />
                        المريض
                        <span class="text-destructive">*</span>
                    </Label>
                    <Select name="patient_id" required>
                        <SelectTrigger
                            id="quick_patient"
                            class="h-11 w-full rounded-xl bg-secondary/50"
                            :class="{
                                'border-destructive': errors.patient_id,
                            }"
                        >
                            <SelectValue placeholder="اختر مريضا" />
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

                <div class="grid gap-1.5 xl:col-span-2">
                    <Label
                        for="quick_department"
                        class="flex items-center gap-1.5 text-xs font-medium"
                    >
                        <Building2 class="size-3.5 text-muted-foreground" />
                        العيادة
                    </Label>
                    <Select
                        :model-value="selectedDepartmentId"
                        @update:model-value="handleDepartmentChange"
                    >
                        <SelectTrigger
                            id="quick_department"
                            class="h-11 w-full rounded-xl bg-secondary/50"
                        >
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

                <div class="grid gap-1.5 xl:col-span-2">
                    <Label
                        for="quick_doctor"
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
                            id="quick_doctor"
                            class="h-11 w-full rounded-xl bg-secondary/50"
                            :class="{
                                'border-destructive': errors.doctor_id,
                            }"
                        >
                            <SelectValue placeholder="اختر طبيبا" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__none__">
                                يحدد لاحقا
                            </SelectItem>
                            <SelectItem
                                v-for="doctor in filteredDoctors"
                                :key="doctor.id"
                                :value="String(doctor.id)"
                            >
                                {{
                                    doctor.department?.name
                                        ? `${doctor.name} - ${doctor.department.name}`
                                        : doctor.name
                                }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.doctor_id" />
                </div>

                <div class="grid gap-1.5 xl:col-span-2">
                    <Label
                        class="flex items-center gap-1.5 text-xs font-medium"
                    >
                        <Clock class="size-3.5 text-muted-foreground" />
                        التاريخ والوقت
                        <span class="text-destructive">*</span>
                    </Label>
                    <AppointmentWorkingHoursInput
                        :working-hours="props.clinicWorkingHours"
                        :available-periods="selectedAvailablePeriods"
                        :availability-date="props.todayAvailability.date"
                        label=""
                    />
                    <InputError :message="errors.scheduled_for" />
                </div>

                <div class="grid gap-1.5 xl:col-span-1">
                    <Label
                        for="quick_duration"
                        class="flex items-center gap-1.5 text-xs font-medium"
                    >
                        <Clock class="size-3.5 text-muted-foreground" />
                        المدة
                        <span class="text-destructive">*</span>
                    </Label>
                    <Select name="duration_minutes" required>
                        <SelectTrigger
                            id="quick_duration"
                            class="h-11 w-full rounded-xl bg-secondary/50"
                            :class="{
                                'border-destructive': errors.duration_minutes,
                            }"
                        >
                            <SelectValue placeholder="15 دقيقة" />
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

                <div class="grid gap-1.5 xl:col-span-1">
                    <Label
                        for="quick_type"
                        class="flex items-center gap-1.5 text-xs font-medium"
                    >
                        <Calculator class="size-3.5 text-muted-foreground" />
                        النوع
                        <span class="text-destructive">*</span>
                    </Label>
                    <Select name="appointment_type" required>
                        <SelectTrigger
                            id="quick_type"
                            class="h-11 w-full rounded-xl bg-secondary/50"
                            :class="{
                                'border-destructive': errors.appointment_type,
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

                <div class="grid gap-1.5 xl:col-span-1">
                    <Label
                        for="quick_cost"
                        class="flex items-center gap-1.5 text-xs font-medium"
                    >
                        <DollarSign class="size-3.5 text-muted-foreground" />
                        التكلفة
                        <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="quick_cost"
                        name="cost"
                        type="number"
                        min="0"
                        step="0.01"
                        required
                        class="pattern-field-clay h-11"
                        placeholder="0"
                        :class="{ 'border-destructive': errors.cost }"
                    />
                    <InputError :message="errors.cost" />
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-10 gap-1.5 rounded-xl px-3 text-xs"
                    @click="handleReset"
                >
                    <RotateCcw class="size-3.5" />
                    مسح
                </Button>
                <Button
                    type="submit"
                    variant="default"
                    size="sm"
                    class="h-10 min-w-28 gap-1.5 rounded-xl px-4 text-xs"
                    :disabled="processing"
                >
                    <Zap v-if="!processing" class="size-3.5" />
                    <span
                        v-if="processing"
                        class="inline-block size-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"
                    />
                    {{ processing ? 'جاري الحفظ...' : 'حفظ سريع' }}
                </Button>
            </div>
        </Form>
    </section>
</template>
