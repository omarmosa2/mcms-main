<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    Building2,
    Calculator,
    Clock,
    DollarSign,
    FileText,
    RotateCcw,
    Save,
    Stethoscope,
    User,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
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
    ClinicOption,
    Option,
    TodayAvailability,
} from './types';
import { useAppointmentBookingOptions } from './useAppointmentBookingOptions';

const props = defineProps<{
    patients: Option[];
    doctors: Option[];
    clinics: ClinicOption[];
    clinicWorkingHours: ClinicWorkingHour[];
    todayAvailability: TodayAvailability;
}>();

const emit = defineEmits<{
    success: [];
    error: [];
    reset: [];
}>();

const selectedClinicId = ref('');
const selectedDoctorId = ref('');
const selectedDuration = ref('30');
const formResetKey = ref(0);
const {
    bookingOptions,
    isLoadingBookingOptions,
    loadBookingOptions,
} = useAppointmentBookingOptions(props.todayAvailability);

const noDoctorSelected = computed(() => {
    const doctorId = Number(selectedDoctorId.value);

    return !Number.isFinite(doctorId) || doctorId <= 0;
});

const availableClinics = computed(() =>
    bookingOptions.value.clinic_options ?? [],
);

const filteredDoctors = computed(() => {
    return bookingOptions.value.doctors.map((doctor) => ({
        id: doctor.id,
        name: doctor.name,
        clinic_id: doctor.clinic_id,
        specialty: doctor.specialty,
        clinic: doctor.clinic,
    }));
});

const selectedAvailablePeriods = computed<AvailabilityPeriod[]>(() => {
    const doctorId = Number(selectedDoctorId.value);

    if (Number.isFinite(doctorId) && doctorId > 0) {
        return (
            bookingOptions.value.doctors.find(
                (doctor) => doctor.id === doctorId,
            )?.available_periods ?? []
        );
    }

    return [];
});

const handleClinicChange = (value: unknown): void => {
    const strValue = String(value ?? '');
    selectedClinicId.value = strValue === '__all__' ? '' : strValue;
    selectedDoctorId.value = '';
    void loadBookingOptions({ clinicId: selectedClinicId.value });
};

const handleDoctorChange = (value: unknown): void => {
    const strValue = String(value ?? '');
    selectedDoctorId.value = strValue === '__none__' ? '' : strValue;
    void loadBookingOptions({
        clinicId: selectedClinicId.value,
        doctorId: selectedDoctorId.value,
    });
};

const resetFormState = (): void => {
    selectedClinicId.value = '';
    selectedDoctorId.value = '';
    selectedDuration.value = '30';
    formResetKey.value += 1;
    void loadBookingOptions();
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

onMounted(() => {
    void loadBookingOptions();
});
</script>

<template>
    <section class="glass-panel-soft overflow-hidden">
        <div
            class="flex flex-col gap-3 border-b border-border/60 bg-secondary/30 px-5 py-3.5 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <Zap class="size-4.5" />
                </div>
                <div>
                    <h2 class="text-sm font-bold text-foreground">
                        إضافة سريعة لموعد
                    </h2>
                    <p class="text-[0.72rem] text-muted-foreground">
                        حقول مختصرة للحجز السريع
                    </p>
                </div>
            </div>

            <div
                class="inline-flex w-fit items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1 text-[0.68rem] text-muted-foreground"
            >
                <kbd
                    class="rounded border border-border bg-secondary px-1.5 py-0.5 font-mono text-[0.62rem] text-foreground"
                >
                    Enter
                </kbd>
                حفظ سريع
            </div>
        </div>

        <Form
            :key="formResetKey"
            v-bind="AppointmentController.store.form()"
            class="p-5"
            v-slot="{ errors, processing }"
            reset-on-success
            @submit="handleSubmit"
            @success="handleSuccess"
            @error="emit('error')"
        >
            <div class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label
                            for="quick_patient"
                            class="flex items-center gap-1 text-xs font-semibold text-foreground"
                        >
                            <User class="size-3.5 text-primary" />
                            المريض
                            <span class="text-xs text-destructive">*</span>
                        </Label>
                        <Select name="patient_id" required>
                            <SelectTrigger
                                id="quick_patient"
                                class="h-10 w-full rounded-xl bg-secondary/50"
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
                        <InputError :message="errors.patient_id" class="text-[0.68rem]" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label
                            for="quick_clinic"
                            class="flex items-center gap-1 text-xs font-semibold text-foreground"
                        >
                            <Building2 class="size-3.5 text-primary" />
                            العيادة
                        </Label>
                        <Select
                            :model-value="selectedClinicId"
                            @update:model-value="handleClinicChange"
                        >
                            <SelectTrigger
                                id="quick_clinic"
                                class="h-10 w-full rounded-xl bg-secondary/50"
                            >
                                <SelectValue placeholder="كل العيادات" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__all__">
                                    كل العيادات
                                </SelectItem>
                                <SelectItem
                                    v-for="clinic in availableClinics"
                                    :key="clinic.id"
                                    :value="String(clinic.id)"
                                >
                                    {{ clinic.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="isLoadingBookingOptions"
                            class="text-[0.68rem] text-muted-foreground"
                        >
                            جار تحديث العيادات من قاعدة البيانات...
                        </p>
                        <p
                            v-else-if="availableClinics.length === 0"
                            class="text-[0.68rem] text-destructive"
                        >
                            لا توجد عيادات مداومة اليوم
                        </p>
                    </div>

                    <div class="grid gap-1.5">
                        <Label
                            for="quick_doctor"
                            class="flex items-center gap-1 text-xs font-semibold text-foreground"
                        >
                            <Stethoscope class="size-3.5 text-primary" />
                            الطبيب
                        </Label>
                        <Select
                            name="doctor_id"
                            :model-value="selectedDoctorId"
                            @update:model-value="handleDoctorChange"
                        >
                            <SelectTrigger
                                id="quick_doctor"
                                class="h-10 w-full rounded-xl bg-secondary/50"
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
                                        doctor.clinic?.name
                                            ? `${doctor.name} - ${doctor.clinic.name}`
                                            : doctor.name
                                    }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.doctor_id" class="text-[0.68rem]" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label
                            class="flex items-center gap-1 text-xs font-semibold text-foreground"
                        >
                            <Clock class="size-3.5 text-primary" />
                            التاريخ والوقت
                            <span class="text-xs text-destructive">*</span>
                        </Label>
                        <AppointmentWorkingHoursInput
                            :working-hours="props.clinicWorkingHours"
                            :available-periods="selectedAvailablePeriods"
                            :availability-date="bookingOptions.date"
                            :duration-minutes="Number(selectedDuration)"
                            :no-doctor-selected="noDoctorSelected"
                            label=""
                        />
                        <InputError :message="errors.scheduled_for" class="text-[0.68rem]" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label
                            for="quick_duration"
                            class="flex items-center gap-1 text-xs font-semibold text-foreground"
                        >
                            <Clock class="size-3.5 text-primary" />
                            المدة
                            <span class="text-xs text-destructive">*</span>
                        </Label>
                        <Select name="duration_minutes" required :model-value="selectedDuration" @update:model-value="selectedDuration = String($event ?? '30')">
                            <SelectTrigger
                                id="quick_duration"
                                class="h-10 w-full rounded-xl bg-secondary/50"
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
                        <InputError :message="errors.duration_minutes" class="text-[0.68rem]" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label
                            for="quick_type"
                            class="flex items-center gap-1 text-xs font-semibold text-foreground"
                        >
                            <Calculator class="size-3.5 text-primary" />
                            النوع
                            <span class="text-xs text-destructive">*</span>
                        </Label>
                        <Select name="appointment_type" required>
                            <SelectTrigger
                                id="quick_type"
                                class="h-10 w-full rounded-xl bg-secondary/50"
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
                        <InputError :message="errors.appointment_type" class="text-[0.68rem]" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label
                            for="quick_cost"
                            class="flex items-center gap-1 text-xs font-semibold text-foreground"
                        >
                            <DollarSign class="size-3.5 text-primary" />
                            التكلفة
                            <span class="text-xs text-destructive">*</span>
                        </Label>
                        <Input
                            id="quick_cost"
                            name="cost"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                            class="pattern-field-clay h-10"
                            placeholder="0"
                            :class="{ 'border-destructive': errors.cost }"
                        />
                        <InputError :message="errors.cost" class="text-[0.68rem]" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label
                            for="quick_notes"
                            class="flex items-center gap-1 text-xs font-semibold text-foreground"
                        >
                            <FileText class="size-3.5 text-primary" />
                            ملاحظات
                        </Label>
                        <Input
                            id="quick_notes"
                            name="notes"
                            class="pattern-field-clay h-10"
                            placeholder="ملاحظات إضافية..."
                        />
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-2 border-t border-border/50 pt-4">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-9 gap-1.5 rounded-xl px-3 text-xs"
                    @click="handleReset"
                >
                    <RotateCcw class="size-3.5" />
                    مسح
                </Button>
                <Button
                    type="submit"
                    variant="default"
                    size="sm"
                    class="h-9 min-w-28 gap-1.5 rounded-xl px-5 text-xs font-semibold shadow-sm"
                    :disabled="processing"
                >
                    <Save v-if="!processing" class="size-3.5" />
                    <span
                        v-if="processing"
                        class="inline-block size-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"
                    />
                    {{ processing ? 'جاري الحفظ...' : 'حفظ الموعد' }}
                </Button>
            </div>
        </Form>
    </section>
</template>
