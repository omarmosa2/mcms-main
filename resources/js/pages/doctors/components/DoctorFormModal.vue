<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { KeyRound, Save, UserPlus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Doctors/DoctorProfileController';
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
import DoctorWorkingHoursSelector from './DoctorWorkingHoursSelector.vue';
import type {
    ClinicOption,
    ClinicSelectOption,
    CompensationType,
    DoctorGender,
    DoctorProfile,
    WorkingHour,
} from './types';

const props = defineProps<{
    open: boolean;
    profile: DoctorProfile | null;
    clinic: ClinicOption;
    clinics: ClinicSelectOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

type DoctorForm = {
    name: string;
    gender: DoctorGender;
    specialty: string;
    phone: string;
    work_start_date: string;
    clinic_id: number | '';
    username: string;
    password: string;
    status: 'active' | 'on_leave' | 'inactive';
    is_active: boolean;
    compensation_type: CompensationType;
    compensation_value: string;
    consultation_duration_minutes: number;
    working_hours: WorkingHour[];
};

const normalizeTime = (value: string | null): string | null => {
    return value === null ? null : value.slice(0, 5);
};

const clinicForId = (
    clinicId: number | '' | null | undefined,
): ClinicSelectOption | null => {
    if (
        clinicId === null ||
        clinicId === undefined ||
        clinicId === ''
    ) {
        return null;
    }

    return (
        props.clinics.find(
            (clinicOption) => clinicOption.id === Number(clinicId),
        ) ?? null
    );
};

const ALL_DAYS = [6, 0, 1, 2, 3, 4, 5] as const;

const normalizeDayOfWeek = (value: unknown): number => {
    const numericValue = Number(value);

    if (Number.isInteger(numericValue) && numericValue >= 0 && numericValue <= 6) {
        return numericValue;
    }

    const dayIndexes: Record<string, number> = {
        sunday: 0,
        monday: 1,
        tuesday: 2,
        wednesday: 3,
        thursday: 4,
        friday: 5,
        saturday: 6,
    };

    return dayIndexes[String(value).trim().toLowerCase()] ?? 0;
};

const buildWorkingHoursFromProfile = (
    profile: DoctorProfile | null,
): WorkingHour[] => {
    if (profile === null) {
        return ALL_DAYS.map((dayOfWeek) => ({
            day_of_week: dayOfWeek,
            is_active: false,
            start_time: null,
            end_time: null,
        }));
    }

    const clinicWorkingDays = profile.clinic_working_days ??
        clinicForId(profile.clinic_id)?.working_hours ?? [];
    const doctorSchedules = new Map(
        profile.doctor_schedules.map((schedule) => [
            normalizeDayOfWeek(schedule.day_of_week),
            schedule,
        ]),
    );

    return clinicWorkingDays
        .filter((day) => day.is_active)
        .map((clinicDay) => {
            const dayOfWeek = normalizeDayOfWeek(clinicDay.day_of_week);
            const schedule = doctorSchedules.get(dayOfWeek);
            const isAvailable = schedule !== undefined && schedule.is_available;

            return {
                day_of_week: dayOfWeek,
                is_active: isAvailable,
                start_time: isAvailable
                    ? normalizeTime(schedule?.start_time ?? null)
                    : null,
                end_time: isAvailable
                    ? normalizeTime(schedule?.end_time ?? null)
                    : null,
            };
        });
};

const defaultsFor = (profile: DoctorProfile | null): DoctorForm => ({
    name: profile?.user?.name ?? '',
    gender: profile?.gender ?? 'male',
    specialty: profile?.specialty ?? '',
    phone: profile?.phone ?? '',
    work_start_date: profile?.work_start_date ?? '',
    clinic_id: profile?.clinic_id ?? '',
    username: profile?.user?.email ?? '',
    password: '',
    status: profile?.status ?? 'active',
    is_active: profile?.user?.is_active ?? true,
    compensation_type: profile?.compensation_type ?? 'percentage',
    compensation_value:
        profile?.compensation_value !== null &&
        profile?.compensation_value !== undefined
            ? String(Number(profile.compensation_value))
            : '',
    consultation_duration_minutes: profile?.consultation_duration_minutes ?? 30,
    working_hours: buildWorkingHoursFromProfile(profile),
});

const form = useForm<DoctorForm>(defaultsFor(props.profile));
const isHydratingForm = ref(false);

const isEditing = computed(() => props.profile !== null);
const selectedClinic = computed<ClinicSelectOption | null>(() =>
    clinicForId(form.clinic_id),
);
const compensationLabel = computed(() => {
    if (form.compensation_type === 'percentage') {
        return 'نسبة الطبيب (%)';
    }

    if (form.compensation_type === 'weekly') {
        return 'قيمة الأجر الأسبوعي';
    }

    return 'قيمة الأجر الشهري';
});

watch(
    () => [props.open, props.profile?.id],
    () => {
        if (!props.open) {
            return;
        }

        isHydratingForm.value = true;
        const nextDefaults = defaultsFor(props.profile);

        form.defaults(nextDefaults);
        form.reset();
        Object.assign(form, nextDefaults);
        form.clearErrors();
        isHydratingForm.value = false;
    },
);

const resetWorkingHoursForSelectedClinic = (): void => {
    form.working_hours = selectedClinic.value?.working_hours
        .filter((day) => day.is_active)
        .map((day) => ({
            day_of_week: normalizeDayOfWeek(day.day_of_week),
            is_active: false,
            start_time: null,
            end_time: null,
        })) ?? [];
    form.clearErrors();
};

watch(
    () => form.compensation_type,
    () => {
        form.compensation_value = '';
    },
);

const close = (): void => {
    emit('update:open', false);
};

const submit = (): void => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            close();
        },
    };

    if (props.profile !== null) {
        form.put(update.url(props.profile.id), options);

        return;
    }

    form.post(store.url(), options);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="flex max-h-[90vh] max-w-4xl flex-col overflow-hidden rounded-xl bg-card p-0"
            dir="rtl"
        >
            <DialogHeader
                class="shrink-0 border-b border-border px-4 py-4 text-right sm:px-6 sm:py-5"
            >
                <DialogTitle class="text-2xl font-bold text-foreground">
                    {{ isEditing ? 'تعديل بيانات الطبيب' : 'إضافة طبيب جديد' }}
                </DialogTitle>
                <DialogDescription class="text-muted-foreground">
                    إدارة بيانات الطبيب والحساب والدوام ونظام الأجر من نموذج
                    واحد.
                </DialogDescription>
            </DialogHeader>

            <form
                class="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-5 pb-28 sm:px-6"
                @submit.prevent="submit"
            >
                <section class="space-y-3">
                    <h3 class="text-sm font-bold text-foreground">
                        البيانات الأساسية
                    </h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_name">الاسم الكامل</Label>
                            <Input
                                id="doctor_name"
                                v-model="form.name"
                                class="h-11 rounded-lg"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_gender">الجنس</Label>
                            <select
                                id="doctor_gender"
                                v-model="form.gender"
                                class="h-11 rounded-lg border border-input bg-muted px-3 text-sm"
                            >
                                <option value="male">ذكر</option>
                                <option value="female">أنثى</option>
                            </select>
                            <InputError :message="form.errors.gender" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_specialty">الاختصاص</Label>
                            <Input
                                id="doctor_specialty"
                                v-model="form.specialty"
                                class="h-11 rounded-lg"
                            />
                            <InputError :message="form.errors.specialty" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_phone"
                                >رقم الهاتف (اختياري)</Label
                            >
                            <Input
                                id="doctor_phone"
                                v-model="form.phone"
                                class="h-11 rounded-lg"
                            />
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_work_start_date"
                                >تاريخ مباشرة العمل</Label
                            >
                            <Input
                                id="doctor_work_start_date"
                                v-model="form.work_start_date"
                                type="date"
                                class="h-11 rounded-lg"
                            />
                            <InputError
                                :message="form.errors.work_start_date"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_clinic_select"
                                >العيادة التابعة للطبيب</Label
                            >
                            <select
                                id="doctor_clinic_select"
                                v-model="form.clinic_id"
                                class="h-11 rounded-lg border border-input bg-muted px-3 text-sm"
                                @change="resetWorkingHoursForSelectedClinic"
                            >
                                <option value="">يرجى اختيار العيادة</option>
                                <option
                                    v-for="clinicOption in clinics"
                                    :key="clinicOption.id"
                                    :value="clinicOption.id"
                                >
                                    {{ clinicOption.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.clinic_id" />
                        </div>
                    </div>
                </section>

                <DoctorWorkingHoursSelector
                    v-model="form.working_hours"
                    :errors="form.errors"
                    :clinic-working-hours="
                        selectedClinic?.working_hours ?? []
                    "
                    :has-selected-clinic="selectedClinic !== null"
                />

                <section
                    class="space-y-3 rounded-lg border border-border bg-card p-4"
                >
                    <div class="flex items-center gap-2">
                        <KeyRound class="size-4 text-primary" />
                        <h3 class="text-sm font-bold text-foreground">
                            بيانات الحساب
                        </h3>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_username">اسم المستخدم</Label>
                            <Input
                                id="doctor_username"
                                v-model="form.username"
                                type="email"
                                class="h-11 rounded-lg"
                                placeholder="doctor@example.com"
                            />
                            <InputError :message="form.errors.username" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_password">كلمة المرور</Label>
                            <Input
                                id="doctor_password"
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                class="h-11 rounded-lg"
                                :placeholder="
                                    isEditing ? 'اتركها فارغة بدون تغيير' : ''
                                "
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <label
                            v-if="isEditing"
                            class="flex items-center justify-between gap-3 rounded-lg border border-border bg-muted px-3 py-2 md:col-span-2"
                        >
                            <span>
                                <span
                                    class="block text-sm font-semibold text-foreground"
                                    >حالة الحساب</span
                                >
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{
                                        form.is_active ? 'نشط' : 'غير نشط'
                                    }}</span
                                >
                            </span>
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="size-5 accent-primary"
                            />
                        </label>
                    </div>
                </section>

                <section
                    class="space-y-3 rounded-lg border border-border bg-card p-4"
                >
                    <h3 class="text-sm font-bold text-foreground">
                        نظام أجر الطبيب
                    </h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_compensation_type"
                                >نوع أجر الطبيب</Label
                            >
                            <select
                                id="doctor_compensation_type"
                                v-model="form.compensation_type"
                                class="h-11 rounded-lg border border-input bg-muted px-3 text-sm"
                            >
                                <option value="percentage">نسبة مئوية</option>
                                <option value="weekly">أجر أسبوعي</option>
                                <option value="monthly">أجر شهري</option>
                            </select>
                            <InputError
                                :message="form.errors.compensation_type"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_compensation_value">{{
                                compensationLabel
                            }}</Label>
                            <Input
                                id="doctor_compensation_value"
                                v-model="form.compensation_value"
                                type="number"
                                min="0"
                                step="0.01"
                                class="h-11 rounded-lg"
                            />
                            <InputError
                                :message="form.errors.compensation_value"
                            />
                        </div>
                    </div>
                </section>
            </form>

            <DialogFooter
                class="sticky bottom-0 z-10 shrink-0 border-t border-border bg-card px-4 py-3 shadow-[0_-12px_24px_rgba(15,23,42,0.06)] sm:px-6 sm:py-4"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="h-10 w-full sm:w-auto"
                    :disabled="form.processing"
                    @click="close"
                >
                    <X class="size-4" />
                    إلغاء
                </Button>
                <Button
                    type="button"
                    class="h-10 w-full bg-primary text-primary-foreground hover:bg-primary/90 sm:w-auto"
                    :disabled="form.processing"
                    @click="submit"
                >
                    <component
                        :is="isEditing ? Save : UserPlus"
                        class="size-4"
                    />
                    {{ isEditing ? 'حفظ التغييرات' : 'إضافة طبيب جديد' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
