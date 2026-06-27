<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { CalendarPlus, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
import { Spinner } from '@/components/ui/spinner';
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
    const clinicId = String(value ?? '');

    selectedClinicId.value = clinicId === '__all__' ? '' : clinicId;
    selectedDoctorId.value = '';
    void loadBookingOptions({ clinicId: selectedClinicId.value });
};

const handleDoctorChange = (value: unknown): void => {
    const doctorId = String(value ?? '');

    selectedDoctorId.value = doctorId === '__none__' ? '' : doctorId;
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

watch(
    () => props.open,
    (open) => {
        if (open) {
            void loadBookingOptions();
        }
    },
);
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-[600px] bg-card rounded-xl">
            <DialogHeader class="p-6 pb-4 border-b border-border">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-0.5">
                        <DialogTitle class="text-base font-medium text-foreground">
                            إضافة موعد جديد
                        </DialogTitle>
                        <DialogDescription class="text-sm text-muted-foreground">
                            أدخل بيانات الموعد وحدد المريض والتوقيت المناسب.
                        </DialogDescription>
                    </div>
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <CalendarPlus class="size-5" />
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
                <DialogBody class="p-6 space-y-5 max-h-[70vh] overflow-y-auto">
                    <section
                        class="rounded-xl border border-border bg-muted p-4"
                    >
                        <div class="mb-4">
                            <h3 class="text-sm font-bold text-foreground">
                                بيانات الموعد
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                المريض المرتبط بالحجز.
                            </p>
                        </div>

                        <div class="grid gap-1.5">
                            <div class="flex items-center justify-between">
                                <Label
                                    for="patient_id"
                                    class="text-sm font-medium text-foreground"
                                >
                                    المريض
                                    <span class="text-destructive mr-1">*</span>
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
                                    class="h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
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
                    </section>

                    <section
                        class="rounded-xl border border-border bg-muted p-4"
                    >
                        <div class="mb-4">
                            <h3 class="text-sm font-bold text-foreground">
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
                                    class="text-sm font-medium text-foreground"
                                >
                                    العيادة
                                </Label>
                                <Select
                                    :model-value="selectedClinicId"
                                    @update:model-value="handleClinicChange"
                                >
                                    <SelectTrigger
                                        id="clinic_id"
                                        class="h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
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
                                    for="doctor_id"
                                    class="text-sm font-medium text-foreground"
                                >
                                    الطبيب
                                </Label>
                                <Select
                                    name="doctor_id"
                                    :model-value="selectedDoctorId"
                                    @update:model-value="handleDoctorChange"
                                >
                                    <SelectTrigger
                                        id="doctor_id"
                                        class="h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
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
                        class="rounded-xl border border-border bg-muted p-4"
                    >
                        <div class="mb-4">
                            <h3 class="text-sm font-bold text-foreground">
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
                                        bookingOptions.date
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
                                        class="text-sm font-medium text-foreground"
                                    >
                                        المدة
                                        <span class="text-destructive mr-1">*</span>
                                    </Label>
                                    <Select name="duration_minutes" required :model-value="selectedDuration" @update:model-value="selectedDuration = String($event ?? '30')">
                                        <SelectTrigger
                                            id="duration_minutes"
                                            class="h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
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
                                        class="text-sm font-medium text-foreground"
                                    >
                                        نوع الموعد
                                        <span class="text-destructive mr-1">*</span>
                                    </Label>
                                    <Select name="appointment_type" required>
                                        <SelectTrigger
                                            id="appointment_type"
                                            class="h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
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
                                    class="text-sm font-medium text-foreground"
                                >
                                    التكلفة
                                    <span class="text-destructive mr-1">*</span>
                                </Label>
                                <Input
                                    id="cost"
                                    name="cost"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                    placeholder="0"
                                    class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                                    :class="{
                                        'border-destructive': errors.cost,
                                    }"
                                />
                                <InputError :message="errors.cost" />
                            </div>
                        </div>
                    </section>

                    <section
                        class="rounded-xl border border-border bg-muted p-4"
                    >
                        <div class="grid gap-1.5">
                            <Label
                                for="notes"
                                class="text-sm font-medium text-foreground"
                            >
                                ملاحظات
                            </Label>
                            <textarea
                                id="notes"
                                name="notes"
                                rows="3"
                                class="w-full rounded-lg border border-input bg-secondary/50 px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors resize-y"
                                placeholder="أي ملاحظات إضافية حول الموعد..."
                            />
                            <InputError :message="errors.notes" />
                        </div>
                    </section>
                </DialogBody>

                <DialogFooter
                    class="p-6 pt-4 border-t border-border flex items-center gap-2"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="h-9 px-4 rounded-lg border border-input bg-card text-muted-foreground text-sm font-medium hover:bg-muted hover:text-foreground transition-colors duration-150"
                        :disabled="processing"
                        @click="emit('update:open', false)"
                    >
                        إلغاء
                    </Button>
                    <Button
                        type="submit"
                        class="flex-1 h-9 px-4 rounded-lg bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 active:scale-[0.98] transition-all duration-150"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" class="size-4 animate-spin motion-reduce:animate-none motion-reduce:opacity-50" />
                        {{ processing ? 'جارٍ الإنشاء...' : 'إنشاء الموعد' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
