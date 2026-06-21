<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    Building2,
    Calculator,
    CalendarPlus,
    Clock,
    DollarSign,
    FileText,
    Hash,
    Plus,
    Stethoscope,
    User,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
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
import type {
    AvailabilityPeriod,
    ClinicWorkingHour,
    ClinicOption,
    Option,
    TodayAvailability,
} from './types';

const props = defineProps<{
    open: boolean;
    patients: Option[];
    doctors: Option[];
    clinics: ClinicOption[];
    clinicWorkingHours: ClinicWorkingHour[];
    todayAvailability: TodayAvailability;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const selectedClinicId = ref('');
const selectedDoctorId = ref('');
const selectedDuration = ref('30');
const formResetKey = ref(0);

const noDoctorSelected = computed(() => {
    const doctorId = Number(selectedDoctorId.value);
    return !Number.isFinite(doctorId) || doctorId <= 0;
});

const todayAvailableClinicIds = computed(
    () => new Set(props.todayAvailability.clinics),
);

const availableClinics = computed(() =>
    props.clinics.filter((clinic) =>
        todayAvailableClinicIds.value.has(clinic.id),
    ),
);

const filteredDoctors = computed(() => {
    const todayAvailableDoctorIds = new Set(
        props.todayAvailability.doctors.map((doctor) => doctor.id),
    );
    const availableDoctors = props.doctors.filter((doctor) =>
        todayAvailableDoctorIds.has(doctor.id),
    );

    if (!selectedClinicId.value) {
        return availableDoctors;
    }

    const clinicId = Number(selectedClinicId.value);

    return availableDoctors.filter(
        (doctor) => doctor.clinic_id === clinicId,
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

    return [];
});

const handleClinicChange = (value: unknown): void => {
    const clinicId = String(value ?? '');

    selectedClinicId.value = clinicId === '__all__' ? '' : clinicId;
    selectedDoctorId.value = '';
};

const handleDoctorChange = (value: unknown): void => {
    const doctorId = String(value ?? '');

    selectedDoctorId.value = doctorId === '__none__' ? '' : doctorId;
};

const resetFormState = (): void => {
    selectedClinicId.value = '';
    selectedDoctorId.value = '';
    selectedDuration.value = '30';
    formResetKey.value += 1;
};

const handleSuccess = (): void => {
    resetFormState();
    emit('update:open', false);
};

const defaultScheduledFor = computed(() => {
    const now = new Date();
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
        <DialogContent
            class="max-h-[92vh] overflow-hidden bg-card p-0"
            size="2xl"
        >
            <DialogHeader
                class="border-b border-border bg-muted/30 px-6 py-5 text-right"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <DialogTitle class="text-2xl font-bold tracking-normal">
                            إضافة موعد جديد
                        </DialogTitle>
                        <DialogDescription
                            class="text-sm text-muted-foreground"
                        >
                            أدخل بيانات الموعد وحدد المريض والتوقيت المناسب.
                        </DialogDescription>
                    </div>
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <CalendarPlus class="size-6" />
                    </div>
                </div>
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
                <div
                    class="max-h-[calc(92vh-9.5rem)] space-y-5 overflow-y-auto px-6 py-5"
                >
                    <section
                        class="rounded-xl border border-border bg-muted/20 p-4"
                    >
                        <div class="mb-4">
                            <h3 class="text-base font-bold text-foreground">
                                بيانات الموعد
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                رقم الموعد والمريض المرتبط بالحجز.
                            </p>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-[1fr_1.2fr]">
                            <div class="grid gap-1.5">
                                <Label
                                    for="appointment_number"
                                    class="flex items-center gap-1.5 text-xs font-medium"
                                >
                                    <Hash
                                        class="size-3.5 text-muted-foreground"
                                    />
                                    رقم الموعد
                                </Label>
                                <Input
                                    id="appointment_number"
                                    name="appointment_number"
                                    class="h-11 rounded-lg bg-background"
                                    placeholder="يولد تلقائيا إذا ترك فارغا"
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
                                        <User
                                            class="size-3.5 text-muted-foreground"
                                        />
                                        المريض
                                        <span class="text-destructive">*</span>
                                    </Label>
                                    <a
                                        :href="PatientController.index.url()"
                                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-primary hover:bg-primary/10"
                                    >
                                        <Plus class="size-3.5" />
                                        إضافة مريض
                                    </a>
                                </div>
                                <Select name="patient_id" required>
                                    <SelectTrigger
                                        id="patient_id"
                                        class="h-11 rounded-lg bg-background"
                                        :class="{
                                            'border-destructive':
                                                errors.patient_id,
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
                                                    : (patient.full_name ??
                                                      patient.name)
                                            }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.patient_id" />
                            </div>
                        </div>
                    </section>

                    <section
                        class="rounded-xl border border-border bg-muted/20 p-4"
                    >
                        <div class="mb-4">
                            <h3 class="text-base font-bold text-foreground">
                                العيادة والطبيب
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                تظهر الخيارات حسب الدوام المتاح لهذا اليوم.
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label
                                    for="clinic_id"
                                    class="flex items-center gap-1.5 text-xs font-medium"
                                >
                                    <Building2
                                        class="size-3.5 text-muted-foreground"
                                    />
                                    العيادة
                                </Label>
                                <Select
                                    :model-value="selectedClinicId"
                                    @update:model-value="handleClinicChange"
                                >
                                    <SelectTrigger
                                        id="clinic_id"
                                        class="h-11 rounded-lg bg-background"
                                    >
                                        <SelectValue
                                            placeholder="كل العيادات"
                                        />
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
                            </div>

                            <div class="grid gap-1.5">
                                <Label
                                    for="doctor_id"
                                    class="flex items-center gap-1.5 text-xs font-medium"
                                >
                                    <Stethoscope
                                        class="size-3.5 text-muted-foreground"
                                    />
                                    الطبيب
                                </Label>
                                <Select
                                    name="doctor_id"
                                    :model-value="selectedDoctorId"
                                    @update:model-value="handleDoctorChange"
                                >
                                    <SelectTrigger
                                        id="doctor_id"
                                        class="h-11 rounded-lg bg-background"
                                        :class="{
                                            'border-destructive':
                                                errors.doctor_id,
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
                                                    : (doctor.name ??
                                                      `#${doctor.id}`)
                                            }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.doctor_id" />
                            </div>
                        </div>
                    </section>

                    <section
                        class="rounded-xl border border-border bg-muted/20 p-4"
                    >
                        <div class="mb-4">
                            <h3 class="text-base font-bold text-foreground">
                                التوقيت والتكلفة
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                اختر وقت الحجز ونوع الزيارة وقيمة الموعد.
                            </p>
                        </div>

                        <div class="grid gap-4 xl:grid-cols-2">
                            <div>
                                <AppointmentWorkingHoursInput
                                    :working-hours="props.clinicWorkingHours"
                                    :available-periods="
                                        selectedAvailablePeriods
                                    "
                                    :availability-date="
                                        props.todayAvailability.date
                                    "
                                    :default-value="defaultScheduledFor"
                                    :duration-minutes="Number(selectedDuration)"
                                    :no-doctor-selected="noDoctorSelected"
                                    label="التاريخ والوقت"
                                />
                                <InputError :message="errors.scheduled_for" />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-1.5">
                                    <Label
                                        for="duration_minutes"
                                        class="flex items-center gap-1.5 text-xs font-medium"
                                    >
                                        <Clock
                                            class="size-3.5 text-muted-foreground"
                                        />
                                        المدة
                                        <span class="text-destructive">*</span>
                                    </Label>
                                    <Select name="duration_minutes" required :model-value="selectedDuration" @update:model-value="selectedDuration = String($event ?? '30')">
                                        <SelectTrigger
                                            id="duration_minutes"
                                            class="h-11 rounded-lg bg-background"
                                            :class="{
                                                'border-destructive':
                                                    errors.duration_minutes,
                                            }"
                                        >
                                            <SelectValue
                                                placeholder="30 دقيقة"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="15">
                                                15 دقيقة
                                            </SelectItem>
                                            <SelectItem value="30">
                                                30 دقيقة
                                            </SelectItem>
                                            <SelectItem value="45">
                                                45 دقيقة
                                            </SelectItem>
                                            <SelectItem value="60">
                                                60 دقيقة
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="errors.duration_minutes"
                                    />
                                </div>

                                <div class="grid gap-1.5">
                                    <Label
                                        for="appointment_type"
                                        class="flex items-center gap-1.5 text-xs font-medium"
                                    >
                                        <Calculator
                                            class="size-3.5 text-muted-foreground"
                                        />
                                        نوع الموعد
                                        <span class="text-destructive">*</span>
                                    </Label>
                                    <Select name="appointment_type" required>
                                        <SelectTrigger
                                            id="appointment_type"
                                            class="h-11 rounded-lg bg-background"
                                            :class="{
                                                'border-destructive':
                                                    errors.appointment_type,
                                            }"
                                        >
                                            <SelectValue
                                                placeholder="كشفية أولى"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="first_visit">
                                                كشفية أولى
                                            </SelectItem>
                                            <SelectItem value="review">
                                                مراجعة
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="errors.appointment_type"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-1.5 xl:col-span-2">
                                <Label
                                    for="cost"
                                    class="flex items-center gap-1.5 text-xs font-medium"
                                >
                                    <DollarSign
                                        class="size-3.5 text-muted-foreground"
                                    />
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
                                    class="h-11 rounded-lg bg-background"
                                    placeholder="0"
                                    :class="{
                                        'border-destructive': errors.cost,
                                    }"
                                />
                                <InputError :message="errors.cost" />
                            </div>
                        </div>
                    </section>

                    <section
                        class="rounded-xl border border-border bg-muted/20 p-4"
                    >
                        <div class="grid gap-1.5">
                            <Label
                                for="notes"
                                class="flex items-center gap-1.5 text-xs font-medium"
                            >
                                <FileText
                                    class="size-3.5 text-muted-foreground"
                                />
                                ملاحظات
                            </Label>
                            <textarea
                                id="notes"
                                name="notes"
                                rows="3"
                                class="flex min-h-[96px] w-full rounded-lg border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                placeholder="أي ملاحظات إضافية حول الموعد..."
                            />
                            <InputError :message="errors.notes" />
                        </div>
                    </section>
                </div>

                <DialogFooter
                    class="border-t border-border bg-background px-6 py-4"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="min-w-24 rounded-lg"
                        @click="emit('update:open', false)"
                    >
                        إلغاء
                    </Button>
                    <Button
                        type="submit"
                        class="min-w-36 rounded-lg"
                        :disabled="processing"
                    >
                        <span
                            v-if="processing"
                            class="inline-block size-4 animate-spin rounded-full border-2 border-current border-t-transparent"
                        />
                        {{ processing ? 'جاري الإنشاء...' : 'إنشاء الموعد' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
