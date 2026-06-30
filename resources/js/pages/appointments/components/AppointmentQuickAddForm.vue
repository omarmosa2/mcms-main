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
    success: [clinicId: string];
    error: [];
    reset: [];
}>();

const selectedClinicId = ref('');
const selectedDoctorId = ref('');
const selectedDoctorKey = ref('');
const selectedPatientId = ref('');
const selectedDuration = ref('30');
const selectedDate = ref(props.todayAvailability.date);
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
        id: doctor.doctor_id,
        doctor_id: doctor.doctor_id,
        doctor_profile_id: doctor.doctor_profile_id,
        name: doctor.name,
        full_name: doctor.full_name,
        clinic_id: doctor.clinic_id,
        specialty: doctor.specialty,
        clinic: doctor.clinic,
    }));
});

const selectedDoctor = computed(() => {
    if (!selectedDoctorKey.value) {
        return null;
    }

    const [clinicId, doctorId] = selectedDoctorKey.value.split(':').map(Number);

    return (
        bookingOptions.value.doctors.find(
            (doctor) =>
                doctor.clinic_id === clinicId && doctor.doctor_id === doctorId,
        ) ?? null
    );
});

const selectedAvailablePeriods = computed<AvailabilityPeriod[]>(() => {
    return selectedDoctor.value?.available_periods ?? [];
});

const handleClinicChange = (value: unknown): void => {
    const strValue = String(value ?? '');
    selectedClinicId.value = strValue === '__all__' ? '' : strValue;
    selectedDoctorId.value = '';
    selectedDoctorKey.value = '';
    void loadBookingOptions({
        clinicId: selectedClinicId.value,
        date: selectedDate.value,
    });
};

const handlePatientChange = (value: unknown): void => {
    selectedPatientId.value = String(value ?? '');
};

const handleDoctorChange = (value: unknown): void => {
    const strValue = String(value ?? '');

    if (strValue === '__none__') {
        selectedDoctorId.value = '';
        selectedDoctorKey.value = '';

        void loadBookingOptions({
            clinicId: selectedClinicId.value,
            date: selectedDate.value,
        });

        return;
    }

    const [clinicId, doctorId] = strValue.split(':').map(Number);

    if (!Number.isFinite(clinicId) || !Number.isFinite(doctorId)) {
        selectedDoctorId.value = '';
        selectedDoctorKey.value = '';

        return;
    }

    selectedClinicId.value = String(clinicId);
    selectedDoctorId.value = String(doctorId);
    selectedDoctorKey.value = strValue;

    void loadBookingOptions({
        clinicId: selectedClinicId.value,
        doctorId: selectedDoctorId.value,
        date: selectedDate.value,
    });
};

const handleDateChange = (date: string): void => {
    selectedDate.value = date;
    selectedClinicId.value = '';
    selectedDoctorId.value = '';
    selectedDoctorKey.value = '';
    void loadBookingOptions({ date });
};

const resetFormState = (): void => {
    selectedClinicId.value = '';
    selectedDoctorId.value = '';
    selectedDoctorKey.value = '';
    selectedPatientId.value = '';
    selectedDuration.value = '30';
    selectedDate.value = props.todayAvailability.date;
    formResetKey.value += 1;
    void loadBookingOptions({ date: selectedDate.value });
};

const handleReset = (): void => {
    resetFormState();
    emit('reset');
};

const handleSuccess = (): void => {
    const createdClinicId = selectedClinicId.value;

    resetFormState();
    emit('success', createdClinicId);
};

onMounted(() => {
    void loadBookingOptions({ date: selectedDate.value });
});
</script>

<template>
    <section class="glass-panel-soft overflow-hidden">
        <div
            class="flex flex-col gap-3 border-b border-border/70 bg-secondary/20 px-5 py-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-2xl border border-primary/15 bg-primary/10 text-primary"
                >
                    <Zap class="size-4.5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-foreground">
                        إضافة سريعة لموعد
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        حقول مختصرة للحجز السريع
                    </p>
                </div>
            </div>

            <div
                class="inline-flex w-fit items-center gap-1.5 rounded-full border border-border/80 bg-background px-3 py-1.5 text-[0.68rem] text-muted-foreground shadow-sm"
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
            @success="handleSuccess"
            @error="emit('error')"
        >
            <input
                type="hidden"
                name="clinic_id"
                :value="selectedClinicId"
            />
            <input
                type="hidden"
                name="doctor_id"
                :value="selectedDoctorId"
            />
            <input
                type="hidden"
                name="patient_id"
                :value="selectedPatientId"
            />

            <div class="space-y-4">
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label
                            for="quick_patient"
                            class="flex items-center gap-1 text-xs font-semibold text-foreground"
                        >
                            <User class="size-3.5 text-primary" />
                            المريض
                            <span class="text-xs text-destructive">*</span>
                        </Label>
                        <Select
                            :model-value="selectedPatientId"
                            required
                            @update:model-value="handlePatientChange"
                        >
                            <SelectTrigger
                                id="quick_patient"
                                class="h-11 w-full rounded-xl bg-secondary/40"
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
                                class="h-11 w-full rounded-xl bg-secondary/40"
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
                            :model-value="selectedDoctorKey"
                            @update:model-value="handleDoctorChange"
                        >
                            <SelectTrigger
                                id="quick_doctor"
                                class="h-11 w-full rounded-xl bg-secondary/40"
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
                                    :key="`${doctor.clinic_id}:${doctor.doctor_id}`"
                                    :value="`${doctor.clinic_id}:${doctor.doctor_id}`"
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

                <div class="grid gap-3 lg:grid-cols-[minmax(0,1.45fr)_minmax(180px,0.55fr)_minmax(180px,0.55fr)]">
                    <div class="grid gap-1.5">
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
                            :current-date="bookingOptions.current_date"
                            :current-time="bookingOptions.current_time"
                            :duration-minutes="Number(selectedDuration)"
                            :no-doctor-selected="noDoctorSelected"
                            label=""
                            @date-change="handleDateChange"
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
                                class="h-11 w-full rounded-xl bg-secondary/40"
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
                                class="h-11 w-full rounded-xl bg-secondary/40"
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

                <div class="grid gap-3 md:grid-cols-[minmax(180px,0.45fr)_minmax(0,1fr)]">
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
                            class="pattern-field-clay h-11"
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
                            class="pattern-field-clay h-11"
                            placeholder="ملاحظات إضافية..."
                        />
                    </div>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center justify-end gap-2 border-t border-border/60 pt-4">
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
                    class="h-10 min-w-32 gap-1.5 rounded-xl px-5 text-xs font-semibold shadow-sm"
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
