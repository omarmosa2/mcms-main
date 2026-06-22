<script setup lang="ts">
import { AlarmClock, CalendarDays, Clock, Copy, RotateCcw } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { ClinicWorkingHour, DoctorSchedule } from './types';

const props = defineProps<{
    modelValue: DoctorSchedule[];
    errors: Record<string, string>;
    hasSelectedClinic: boolean;
    clinicWorkingHours?: ClinicWorkingHour[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: DoctorSchedule[]];
    changed: [dayOfWeek: number];
}>();

const days = [
    { value: 6, label: 'السبت' },
    { value: 0, label: 'الأحد' },
    { value: 1, label: 'الإثنين' },
    { value: 2, label: 'الثلاثاء' },
    { value: 3, label: 'الأربعاء' },
    { value: 4, label: 'الخميس' },
    { value: 5, label: 'الجمعة' },
];

const labelForDay = (dayOfWeek: number): string => {
    return days.find((item) => item.value === dayOfWeek)?.label ?? '';
};

const updateDay = (index: number, updates: Partial<DoctorSchedule>): void => {
    const next = props.modelValue.map((day, dayIndex) => {
        if (dayIndex !== index) {
            return day;
        }

        return { ...day, ...updates };
    });

    emit('update:modelValue', next);
    emit('changed', props.modelValue[index]?.day_of_week ?? 0);
};

const toggleDay = (index: number, isActive: boolean): void => {
    updateDay(index, {
        is_available: isActive,
        start_time: isActive
            ? (props.modelValue[index]?.start_time ?? null)
            : null,
        end_time: isActive ? (props.modelValue[index]?.end_time ?? null) : null,
    });
};

const clinicHoursFor = (dayOfWeek: number): ClinicWorkingHour | undefined =>
    (props.clinicWorkingHours ?? []).find((day) => day.day_of_week === dayOfWeek);

const formatClinicHours = (dayOfWeek: number): string => {
    const clinicHours = clinicHoursFor(dayOfWeek);

    if (!clinicHours?.start_time || !clinicHours.end_time) {
        return 'دوام العيادة غير محدد';
    }

    return `دوام العيادة: ${clinicHours.start_time.slice(0, 5)} - ${clinicHours.end_time.slice(0, 5)}`;
};

const activeDaysCount = computed(() => props.modelValue.filter((day) => day.is_available).length);

const weeklyHours = computed(() => props.modelValue.reduce((total, day) => {
    if (!day.is_available || !day.start_time || !day.end_time) {
        return total;
    }

    const [startHour, startMinute] = day.start_time.split(':').map(Number);
    const [endHour, endMinute] = day.end_time.split(':').map(Number);

    return total + Math.max(0, endHour * 60 + endMinute - startHour * 60 - startMinute);
}, 0));

const weeklyHoursLabel = computed(() => {
    const hours = Math.floor(weeklyHours.value / 60);
    const minutes = weeklyHours.value % 60;

    return minutes === 0 ? `${hours} ساعة` : `${hours} ساعة و${minutes} د`;
});

const selectAll = (): void => {
    emit('update:modelValue', props.modelValue.map((day) => ({ ...day, is_available: true })));
    props.modelValue.forEach((day) => emit('changed', day.day_of_week));
};

const clearAll = (): void => {
    emit('update:modelValue', props.modelValue.map((day) => ({ ...day, is_available: false, start_time: null, end_time: null })));
    props.modelValue.forEach((day) => emit('changed', day.day_of_week));
};

const copyFirstActiveDay = (): void => {
    const source = props.modelValue.find((day) => day.is_available && day.start_time && day.end_time);

    if (source === undefined) {
        return;
    }

    emit('update:modelValue', props.modelValue.map((day) => day.is_available ? { ...day, start_time: source.start_time, end_time: source.end_time } : day));
    props.modelValue.filter((day) => day.is_available).forEach((day) => emit('changed', day.day_of_week));
};
</script>

<template>
    <section class="space-y-4 rounded-2xl border border-border bg-muted/30 p-4 sm:p-5" dir="rtl">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <span class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary"><CalendarDays class="size-5" /></span>
                <div><h4 class="font-bold text-foreground">جدول دوام الطبيب</h4><p class="mt-1 text-sm text-muted-foreground">اختر أيام عمل الطبيب وحدد وقت البداية والنهاية.</p></div>
            </div>
            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                <span class="rounded-full bg-primary/10 px-3 py-1.5 text-primary">{{ activeDaysCount }} أيام مفعّلة</span>
                <span class="rounded-full bg-card px-3 py-1.5 text-foreground shadow-sm">{{ weeklyHoursLabel }} أسبوعيًا</span>
            </div>
        </header>

        <div class="flex flex-wrap gap-2 border-y border-border/70 py-3">
            <Button type="button" variant="outline" size="sm" @click="selectAll"><CalendarDays class="size-4" />تحديد المتاح</Button>
            <Button type="button" variant="outline" size="sm" @click="clearAll"><RotateCcw class="size-4" />إلغاء الكل</Button>
            <Button type="button" variant="outline" size="sm" :disabled="activeDaysCount === 0" @click="copyFirstActiveDay"><Copy class="size-4" />نسخ أوقات أول يوم</Button>
            <p v-if="activeDaysCount === 0 && hasSelectedClinic" class="self-center text-xs font-medium text-warning">اختر يوم دوام واحدًا على الأقل للطبيب.</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-if="!hasSelectedClinic"
                class="sm:col-span-2 xl:col-span-3 rounded-xl border border-dashed border-border bg-card px-4 py-5 text-sm font-medium text-muted-foreground"
            >
                يرجى اختيار العيادة أولاً لعرض أيام الدوام المتاحة.
            </div>

            <div
                v-else-if="modelValue.length === 0"
                class="sm:col-span-2 xl:col-span-3 rounded-xl border border-dashed border-border bg-card px-4 py-5 text-sm font-medium text-muted-foreground"
            >
                لا توجد بيانات دوام مسجلة لهذا الطبيب.
            </div>

            <template v-else>
                <article
                    v-for="(day, index) in modelValue"
                    :key="day.day_of_week"
                    class="rounded-2xl border p-4 transition duration-200 motion-reduce:transition-none"
                    :class="day.is_available ? 'border-primary/30 bg-card shadow-[0_8px_20px_rgba(26,126,102,0.08)]' : 'border-border bg-muted/50 hover:border-primary/30'"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-bold text-foreground">
                                {{ labelForDay(day.day_of_week) }}
                            </p>

                            <p class="mt-1 inline-flex rounded-full bg-muted px-2 py-1 text-[11px] font-medium text-muted-foreground tabular-nums">{{ formatClinicHours(day.day_of_week) }}</p>
                        </div>

                        <Switch
                            class="h-6 w-11 data-[state=checked]:bg-primary"
                            :model-value="day.is_available"
                            @update:model-value="
                                toggleDay(index, Boolean($event))
                            "
                        />
                    </div>

                    <Transition name="schedule-fields">
                    <div v-if="day.is_available" class="mt-4 grid gap-3 overflow-hidden sm:grid-cols-2">
                        <div class="grid min-w-0 gap-1.5">
                            <Label :for="`doctor_start_${day.day_of_week}`">
                                بداية الدوام
                            </Label>

                            <Input
                                :id="`doctor_start_${day.day_of_week}`"
                                type="time"
                                class="h-10 w-full min-w-0 rounded-lg bg-card tabular-nums"
                                :model-value="day.start_time ?? ''"
                                @update:model-value="
                                    updateDay(index, {
                                        start_time: String($event),
                                    })
                                "
                            />

                            <InputError
                                :message="
                                    errors[`schedules.${index}.start_time`]
                                "
                            />
                        </div>

                        <div class="grid min-w-0 gap-1.5">
                            <Label :for="`doctor_end_${day.day_of_week}`">
                                نهاية الدوام
                            </Label>

                            <Input
                                :id="`doctor_end_${day.day_of_week}`"
                                type="time"
                                class="h-10 w-full min-w-0 rounded-lg bg-card tabular-nums"
                                :model-value="day.end_time ?? ''"
                                @update:model-value="
                                    updateDay(index, {
                                        end_time: String($event),
                                    })
                                "
                            />

                            <InputError
                                :message="
                                    errors[`schedules.${index}.end_time`]
                                "
                            />
                        </div>
                    </div></Transition>
                    <p v-if="!day.is_available" class="mt-4 text-xs text-muted-foreground">فعّل اليوم لإدخال أوقات الدوام.</p>
                </article>
            </template>
        </div>
    </section>
</template>

<style scoped>
.schedule-fields-enter-active,
.schedule-fields-leave-active { transition: opacity 180ms ease-out, transform 180ms ease-out; }
.schedule-fields-enter-from,
.schedule-fields-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
