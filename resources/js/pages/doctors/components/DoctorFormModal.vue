<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CalendarClock, KeyRound, Save, Stethoscope, UserPlus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { store, update } from '@/actions/App/Http/Controllers/DoctorController';
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
import { Switch } from '@/components/ui/switch';
import type {
    Clinic,
    CompensationType,
    Doctor,
    DoctorFormData,
    DoctorSchedule,
} from '../types';
import { DAY_NAMES, ORDERED_DAYS } from '../types';

const props = defineProps<{
    open: boolean;
    doctor: Doctor | null;
    clinics: Clinic[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const selectedClinic = computed<Clinic | null>(() => {
    if (form.clinic_id === '' || form.clinic_id === null) {
        return null;
    }
    return props.clinics.find((c) => c.id === Number(form.clinic_id)) ?? null;
});

const normalizeTime = (value: string | null): string | null =>
    value === null ? null : value.slice(0, 5);

const buildSchedulesForClinic = (clinic: Clinic | null, existing: DoctorSchedule[] = []): DoctorSchedule[] => {
    if (clinic === null) {
        return [];
    }

    const existingByDay = new Map(
        existing.map((s) => [Number(s.day_of_week), s]),
    );

    return clinic.working_hours
        .filter((wh) => wh.is_active)
        .map((wh) => {
            const day = Number(wh.day_of_week);
            const schedule = existingByDay.get(day);

            return {
                day_of_week: day,
                is_available: schedule?.is_available ?? false,
                start_time: schedule?.is_available ? normalizeTime(schedule.start_time) : null,
                end_time: schedule?.is_available ? normalizeTime(schedule.end_time) : null,
            };
        });
};

const defaultsFor = (doctor: Doctor | null): DoctorFormData => ({
    clinic_id: doctor?.clinic_id ?? '',
    user_id: doctor?.user_id ?? null,
    full_name: doctor?.full_name ?? '',
    gender: doctor?.gender ?? 'male',
    specialty: doctor?.specialty ?? '',
    phone: doctor?.phone ?? '',
    email: doctor?.email ?? '',
    username: doctor?.username ?? '',
    employment_start_date: doctor?.employment_start_date ?? '',
    compensation_type: doctor?.compensation_type ?? 'percentage',
    compensation_value:
        doctor?.compensation_value !== null && doctor?.compensation_value !== undefined
            ? String(doctor.compensation_value)
            : '',
    is_active: doctor?.is_active ?? true,
    notes: doctor?.notes ?? '',
    schedules: doctor
        ? buildSchedulesForClinic(
              props.clinics.find((c) => c.id === doctor.clinic_id) ?? null,
              doctor.schedules,
          )
        : [],
});

const form = useForm<DoctorFormData>(defaultsFor(props.doctor));
const isHydrating = ref(false);

const isEditing = computed(() => props.doctor !== null);

const compensationLabel = computed(() => {
    switch (form.compensation_type) {
        case 'percentage':
            return 'نسبة الطبيب (%)';
        case 'weekly_fixed':
            return 'قيمة الأجر الأسبوعي';
        case 'monthly_fixed':
            return 'قيمة الأجر الشهري';
        default:
            return 'قيمة الأجر';
    }
});

const activeScheduleDays = computed(() => form.schedules.filter((s) => s.is_available));

const totalHours = computed(() => {
    let minutes = 0;
    for (const schedule of activeScheduleDays.value) {
        if (schedule.start_time && schedule.end_time) {
            const [sh, sm] = schedule.start_time.split(':').map(Number);
            const [eh, em] = schedule.end_time.split(':').map(Number);
            minutes += eh * 60 + em - (sh * 60 + sm);
        }
    }
    return (minutes / 60).toFixed(1);
});

watch(
    () => [props.open, props.doctor?.id],
    () => {
        if (!props.open) {
            return;
        }
        isHydrating.value = true;
        const next = defaultsFor(props.doctor);
        form.defaults(next);
        form.reset();
        Object.assign(form, next);
        form.clearErrors();
        isHydrating.value = false;
    },
);

watch(
    () => form.clinic_id,
    (newId, oldId) => {
        if (isHydrating.value || newId === '' || oldId === undefined || newId === oldId) {
            return;
        }
        form.schedules = buildSchedulesForClinic(selectedClinic.value);
        form.clearErrors('schedules');
    },
    { flush: 'sync' },
);

const toggleDay = (index: number, checked: boolean): void => {
    const schedule = form.schedules[index];
    if (checked) {
        const clinicWh = selectedClinic.value?.working_hours.find(
            (wh) => Number(wh.day_of_week) === Number(schedule.day_of_week),
        );
        form.schedules[index] = {
            ...schedule,
            is_available: true,
            start_time: schedule.start_time ?? clinicWh?.start_time ?? '09:00',
            end_time: schedule.end_time ?? clinicWh?.end_time ?? '17:00',
        };
    } else {
        form.schedules[index] = {
            ...schedule,
            is_available: false,
            start_time: null,
            end_time: null,
        };
    }
};

const updateDayField = (index: number, field: 'start_time' | 'end_time', value: string): void => {
    form.schedules[index] = {
        ...form.schedules[index],
        [field]: value === '' ? null : value,
    };
};

const dayName = (day: number): string => DAY_NAMES[day] ?? '';

const clinicRefFor = (schedule: DoctorSchedule): string => {
    const wh = selectedClinic.value?.working_hours.find(
        (w) => Number(w.day_of_week) === Number(schedule.day_of_week),
    );
    if (!wh || !wh.start_time || !wh.end_time) {
        return '—';
    }
    return `${wh.start_time} - ${wh.end_time}`;
};

const close = (): void => emit('update:open', false);

const submit = (): void => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            close();
        },
    };

    if (props.doctor !== null) {
        form.put(update.url(props.doctor.id), options);
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
                    إدارة بيانات الطبيب والحساب والدوام ونظام الأجر من نموذج واحد.
                </DialogDescription>
            </DialogHeader>

            <form
                class="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-5 pb-28 sm:px-6"
                @submit.prevent="submit"
            >
                <!-- 1. البيانات الأساسية -->
                <section class="space-y-3">
                    <h3 class="text-sm font-bold text-foreground">البيانات الأساسية</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_full_name">الاسم الكامل</Label>
                            <Input
                                id="doctor_full_name"
                                v-model="form.full_name"
                                class="h-11 rounded-lg"
                            />
                            <InputError :message="form.errors.full_name" />
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
                            <Label for="doctor_phone">رقم الهاتف (اختياري)</Label>
                            <Input
                                id="doctor_phone"
                                v-model="form.phone"
                                class="h-11 rounded-lg"
                            />
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_email">البريد الإلكتروني (اختياري)</Label>
                            <Input
                                id="doctor_email"
                                v-model="form.email"
                                type="email"
                                class="h-11 rounded-lg"
                            />
                            <InputError :message="form.errors.email" />
                        </div>
                    </div>
                </section>

                <!-- 2. بيانات العيادة والعمل -->
                <section
                    class="space-y-3 rounded-lg border border-border bg-card p-4"
                >
                    <h3 class="text-sm font-bold text-foreground">بيانات العيادة والعمل</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_clinic">العيادة التابعة للطبيب</Label>
                            <select
                                id="doctor_clinic"
                                v-model="form.clinic_id"
                                class="h-11 rounded-lg border border-input bg-muted px-3 text-sm"
                            >
                                <option value="">يرجى اختيار العيادة</option>
                                <option
                                    v-for="clinic in clinics"
                                    :key="clinic.id"
                                    :value="clinic.id"
                                >
                                    {{ clinic.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.clinic_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_employment_start_date">تاريخ مباشرة العمل</Label>
                            <Input
                                id="doctor_employment_start_date"
                                v-model="form.employment_start_date"
                                type="date"
                                class="h-11 rounded-lg"
                            />
                            <InputError :message="form.errors.employment_start_date" />
                        </div>
                    </div>
                </section>

                <!-- 3. جدول الدوام -->
                <section
                    v-if="selectedClinic !== null"
                    class="space-y-3 rounded-lg border border-border bg-card p-4"
                >
                    <div class="flex items-center gap-2">
                        <CalendarClock class="size-4 text-primary" />
                        <h3 class="text-sm font-bold text-foreground">جدول الدوام</h3>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        يظهر دوام العيادة كمرجع. فعّل الأيام وحدد أوقات الطبيب ضمنها.
                    </p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="(schedule, index) in form.schedules"
                            :key="schedule.day_of_week"
                            class="rounded-lg border border-border/70 bg-muted/40 p-3"
                        >
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <div>
                                    <p class="font-bold text-foreground">
                                        {{ dayName(Number(schedule.day_of_week)) }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        دوام العيادة: {{ clinicRefFor(schedule) }}
                                    </p>
                                </div>
                                <Switch
                                    :model-value="schedule.is_available"
                                    @update:model-value="toggleDay(index, $event)"
                                />
                            </div>

                            <div v-if="schedule.is_available" class="grid gap-2 grid-cols-2">
                                <div>
                                    <Label class="text-xs">بداية الدوام</Label>
                                    <Input
                                        :value="schedule.start_time ?? ''"
                                        type="time"
                                        class="h-9 rounded-lg"
                                        @input="updateDayField(index, 'start_time', ($event.target as HTMLInputElement).value)"
                                    />
                                    <InputError :message="form.errors[`schedules.${index}.start_time`]" />
                                </div>
                                <div>
                                    <Label class="text-xs">نهاية الدوام</Label>
                                    <Input
                                        :value="schedule.end_time ?? ''"
                                        type="time"
                                        class="h-9 rounded-lg"
                                        @input="updateDayField(index, 'end_time', ($event.target as HTMLInputElement).value)"
                                    />
                                    <InputError :message="form.errors[`schedules.${index}.end_time`]" />
                                </div>
                                <InputError
                                    v-if="form.errors[`schedules.${index}.day_of_week`]"
                                    :message="form.errors[`schedules.${index}.day_of_week`]"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 rounded-lg bg-primary/5 px-3 py-2 text-sm">
                        <span class="font-semibold text-foreground">
                            الأيام المفعّلة: {{ activeScheduleDays.length }}
                        </span>
                        <span class="font-semibold text-foreground">
                            إجمالي الساعات: {{ totalHours }}
                        </span>
                    </div>
                    <InputError :message="form.errors.schedules" />
                </section>

                <section
                    v-else-if="form.clinic_id === ''"
                    class="rounded-lg border border-dashed border-border bg-muted/30 p-6 text-center text-sm text-muted-foreground"
                >
                    اختر عيادة أولاً لتحديد جدول الدوام.
                </section>

                <!-- 4. بيانات الحساب -->
                <section
                    class="space-y-3 rounded-lg border border-border bg-card p-4"
                >
                    <div class="flex items-center gap-2">
                        <KeyRound class="size-4 text-primary" />
                        <h3 class="text-sm font-bold text-foreground">بيانات الحساب</h3>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_username">اسم المستخدم (اختياري)</Label>
                            <Input
                                id="doctor_username"
                                v-model="form.username"
                                class="h-11 rounded-lg"
                                placeholder="اسم مستخدم فريد للطبيب"
                            />
                            <InputError :message="form.errors.username" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_is_active">حالة الطبيب</Label>
                            <label class="flex items-center gap-3 rounded-lg border border-border bg-muted px-3 py-2.5">
                                <Switch v-model="form.is_active" />
                                <span class="text-sm font-semibold text-foreground">
                                    {{ form.is_active ? 'نشط' : 'غير نشط' }}
                                </span>
                            </label>
                            <InputError :message="form.errors.is_active" />
                        </div>
                    </div>
                </section>

                <!-- 5. نظام الأجر -->
                <section
                    class="space-y-3 rounded-lg border border-border bg-card p-4"
                >
                    <div class="flex items-center gap-2">
                        <Stethoscope class="size-4 text-primary" />
                        <h3 class="text-sm font-bold text-foreground">نظام أجر الطبيب</h3>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_compensation_type">نوع أجر الطبيب</Label>
                            <select
                                id="doctor_compensation_type"
                                v-model="form.compensation_type"
                                class="h-11 rounded-lg border border-input bg-muted px-3 text-sm"
                            >
                                <option value="percentage">نسبة مئوية</option>
                                <option value="weekly_fixed">أجر أسبوعي ثابت</option>
                                <option value="monthly_fixed">أجر شهري ثابت</option>
                            </select>
                            <InputError :message="form.errors.compensation_type" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_compensation_value">{{ compensationLabel }}</Label>
                            <Input
                                id="doctor_compensation_value"
                                v-model="form.compensation_value"
                                type="number"
                                min="0"
                                step="0.01"
                                class="h-11 rounded-lg"
                            />
                            <InputError :message="form.errors.compensation_value" />
                        </div>
                    </div>
                </section>

                <!-- 6. الملاحظات -->
                <section class="space-y-3">
                    <h3 class="text-sm font-bold text-foreground">الملاحظات</h3>
                    <div class="grid gap-2">
                        <textarea
                            id="doctor_notes"
                            v-model="form.notes"
                            rows="3"
                            class="rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/10 focus:outline-none"
                            placeholder="ملاحظات إدارية حول الطبيب (اختياري)"
                        ></textarea>
                        <InputError :message="form.errors.notes" />
                    </div>
                </section>
            </form>

            <!-- 7. أزرار -->
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
                    <component :is="isEditing ? Save : UserPlus" class="size-4" />
                    {{ isEditing ? 'حفظ التغييرات' : 'إضافة طبيب جديد' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
