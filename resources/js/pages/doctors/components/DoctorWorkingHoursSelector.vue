<script setup lang="ts">
import { Clock } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { ClinicWorkingHour, WorkingHour } from './types';

const props = defineProps<{
    modelValue: WorkingHour[];
    errors: Record<string, string>;
    clinicWorkingHours: ClinicWorkingHour[];
    hasSelectedDepartment: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: WorkingHour[]];
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

const clinicDayToDoctorDay: Record<ClinicWorkingHour['day_of_week'], number> = {
    sunday: 0,
    monday: 1,
    tuesday: 2,
    wednesday: 3,
    thursday: 4,
    friday: 5,
    saturday: 6,
};

const clinicHoursForDay = (
    dayOfWeek: number,
): ClinicWorkingHour | undefined => {
    return props.clinicWorkingHours.find(
        (clinicDay) =>
            clinicDay.is_active &&
            clinicDayToDoctorDay[clinicDay.day_of_week] === dayOfWeek,
    );
};

const labelForDay = (dayOfWeek: number): string => {
    return days.find((item) => item.value === dayOfWeek)?.label ?? '';
};

const defaultStartTimeFor = (dayOfWeek: number): string => {
    return clinicHoursForDay(dayOfWeek)?.start_time ?? '09:00';
};

const defaultEndTimeFor = (dayOfWeek: number): string => {
    return clinicHoursForDay(dayOfWeek)?.end_time ?? '17:00';
};

const updateDay = (index: number, updates: Partial<WorkingHour>): void => {
    const next = props.modelValue.map((day, dayIndex) => {
        if (dayIndex !== index) {
            return day;
        }

        return { ...day, ...updates };
    });

    emit('update:modelValue', next);
};

const toggleDay = (index: number, isActive: boolean): void => {
    const dayOfWeek = props.modelValue[index]?.day_of_week ?? 0;

    updateDay(index, {
        is_active: isActive,
        start_time: isActive
            ? (props.modelValue[index]?.start_time ??
              defaultStartTimeFor(dayOfWeek))
            : null,
        end_time: isActive
            ? (props.modelValue[index]?.end_time ??
              defaultEndTimeFor(dayOfWeek))
            : null,
    });
};
</script>

<template>
    <section
        class="space-y-3 rounded-lg border border-sky-100 bg-sky-50/35 p-3"
    >
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <h4 class="text-sm font-bold text-slate-900">بيانات الدوام</h4>
                <p class="text-xs leading-5 text-slate-500">
                    فعّل الأيام المطلوبة وحدد وقت بداية ونهاية دوام الطبيب.
                </p>
            </div>
            <Clock class="size-5 shrink-0 text-sky-500" />
        </div>

        <div class="grid gap-2">
            <div
                v-if="!hasSelectedDepartment"
                class="rounded-lg border border-dashed border-slate-200 bg-white px-3 py-4 text-sm font-medium text-slate-500"
            >
                يرجى اختيار العيادة أولاً لعرض أيام الدوام المتاحة.
            </div>
            <div
                v-else-if="modelValue.length === 0"
                class="rounded-lg border border-dashed border-slate-200 bg-white px-3 py-4 text-sm font-medium text-slate-500"
            >
                لا توجد أيام دوام مفعّلة لهذه العيادة.
            </div>
            <template v-else>
                <article
                    v-for="(day, index) in modelValue"
                    :key="day.day_of_week"
                    class="rounded-lg border border-slate-200 px-3 py-3 transition-colors duration-200"
                    :class="
                        day.is_active ? 'bg-white shadow-sm' : 'bg-slate-50/80'
                    "
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">
                                {{ labelForDay(day.day_of_week) }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ day.is_active ? 'دوام' : 'لا يوجد دوام' }}
                            </p>
                            <p class="text-[11px] font-medium text-sky-600">
                                {{
                                    clinicHoursForDay(day.day_of_week)
                                        ?.start_time
                                }}
                                -
                                {{
                                    clinicHoursForDay(day.day_of_week)?.end_time
                                }}
                            </p>
                        </div>

                        <Switch
                            class="h-6 w-11 data-[state=checked]:bg-sky-500"
                            :model-value="day.is_active"
                            @update:model-value="
                                toggleDay(index, Boolean($event))
                            "
                        />
                    </div>

                    <p
                        v-if="!day.is_active"
                        class="mt-3 rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-500"
                    >
                        لا يوجد دوام
                    </p>

                    <div
                        v-if="day.is_active"
                        class="mt-3 grid gap-3 overflow-hidden transition-all duration-200 ease-out sm:grid-cols-2"
                    >
                        <div class="grid min-w-0 gap-1.5">
                            <Label :for="`doctor_start_${day.day_of_week}`">
                                بداية الدوام
                            </Label>
                            <Input
                                :id="`doctor_start_${day.day_of_week}`"
                                type="time"
                                class="h-10 w-full min-w-0 rounded-lg border-slate-200 bg-slate-50"
                                :min="
                                    clinicHoursForDay(day.day_of_week)
                                        ?.start_time ?? undefined
                                "
                                :max="
                                    clinicHoursForDay(day.day_of_week)
                                        ?.end_time ?? undefined
                                "
                                :model-value="
                                    day.start_time ??
                                    defaultStartTimeFor(day.day_of_week)
                                "
                                @update:model-value="
                                    updateDay(index, {
                                        start_time: String($event),
                                    })
                                "
                            />
                            <InputError
                                :message="
                                    errors[`working_hours.${index}.start_time`]
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
                                class="h-10 w-full min-w-0 rounded-lg border-slate-200 bg-slate-50"
                                :min="
                                    clinicHoursForDay(day.day_of_week)
                                        ?.start_time ?? undefined
                                "
                                :max="
                                    clinicHoursForDay(day.day_of_week)
                                        ?.end_time ?? undefined
                                "
                                :model-value="
                                    day.end_time ??
                                    defaultEndTimeFor(day.day_of_week)
                                "
                                @update:model-value="
                                    updateDay(index, {
                                        end_time: String($event),
                                    })
                                "
                            />
                            <InputError
                                :message="
                                    errors[`working_hours.${index}.end_time`]
                                "
                            />
                        </div>
                    </div>
                </article>
            </template>
        </div>
    </section>
</template>
