<script setup lang="ts">
import { CalendarClock, CheckCheck, Copy, XCircle } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { Clinic, DoctorFormData, DoctorSchedule } from '../types';
import { DAY_NAMES, ORDERED_DAYS } from '../types';

type ScheduleEntry = {
    enabled: boolean;
    start_time: string | null;
    end_time: string | null;
};

const props = defineProps<{
    modelValue: DoctorFormData;
    selectedClinic: Clinic | null;
    errors: Record<string, string>;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: DoctorFormData];
}>();

const orderedDays: number[] = [...ORDERED_DAYS];

const scheduleMap = ref<Record<number, ScheduleEntry>>({});
let isEmitting = false;

const normalizeTime = (value: string | null | undefined): string | null => {
    if (value === null || value === undefined) {
        return null;
    }

    return value.slice(0, 5);
};

const clinicHoursForDay = (
    day: number,
): { start_time: string | null; end_time: string | null } => {
    const wh = props.selectedClinic?.working_hours.find(
        (w) => Number(w.day_of_week) === day,
    );

    return {
        start_time: normalizeTime(wh?.start_time),
        end_time: normalizeTime(wh?.end_time),
    };
};

const rebuildFromClinic = (): void => {
    const map: Record<number, ScheduleEntry> = {};

    if (props.selectedClinic) {
        for (const wh of props.selectedClinic.working_hours) {
            if (!wh.is_active) {
                continue;
            }

            const day = Number(wh.day_of_week);
            const { start_time, end_time } = clinicHoursForDay(day);
            map[day] = {
                enabled: false,
                start_time,
                end_time,
            };
        }
    }

    scheduleMap.value = map;
};

const applySchedules = (schedules: DoctorSchedule[]): void => {
    for (const s of schedules) {
        if (!s.is_available) {
            continue;
        }

        const day = Number(s.day_of_week);

        if (!scheduleMap.value[day]) {
            continue;
        }

        scheduleMap.value[day] = {
            enabled: true,
            start_time:
                normalizeTime(s.start_time) ?? scheduleMap.value[day].start_time,
            end_time: normalizeTime(s.end_time) ?? scheduleMap.value[day].end_time,
        };
    }
};

const availableDays = computed<number[]>(() => {
    if (!props.selectedClinic) {
        return [];
    }

    return props.selectedClinic.working_hours
        .filter((wh) => wh.is_active)
        .map((wh) => Number(wh.day_of_week))
        .sort((a, b) => orderedDays.indexOf(a) - orderedDays.indexOf(b));
});

const emittedSchedules = computed<DoctorSchedule[]>(() =>
    availableDays.value
        .filter((day) => scheduleMap.value[day]?.enabled)
        .map((day) => ({
            day_of_week: day,
            is_available: true,
            start_time: scheduleMap.value[day].start_time,
            end_time: scheduleMap.value[day].end_time,
        })),
);

const activeDaysCount = computed(() => emittedSchedules.value.length);

const totalHours = computed(() => {
    let minutes = 0;

    for (const schedule of emittedSchedules.value) {
        if (schedule.start_time && schedule.end_time) {
            const [sh, sm] = schedule.start_time.split(':').map(Number);
            const [eh, em] = schedule.end_time.split(':').map(Number);
            minutes += eh * 60 + em - (sh * 60 + sm);
        }
    }

    return (minutes / 60).toFixed(1);
});

const dayName = (day: number): string => DAY_NAMES[day] ?? '';

const clinicRefFor = (day: number): string => {
    const { start_time, end_time } = clinicHoursForDay(day);

    if (!start_time || !end_time) {
        return '—';
    }

    return `${start_time} – ${end_time}`;
};

const errorForDay = (
    day: number,
    field: 'start_time' | 'end_time' | 'day_of_week',
): string | undefined => {
    const index = emittedSchedules.value.findIndex(
        (s) => Number(s.day_of_week) === day,
    );

    if (index === -1) {
        return undefined;
    }

    return props.errors[`schedules.${index}.${field}`] as string | undefined;
};

const schedulesMatchEmitted = (schedules: DoctorSchedule[]): boolean => {
    const emitted = emittedSchedules.value;

    if (emitted.length !== schedules.length) {
        return false;
    }

    return emitted.every((e, i) => {
        const s = schedules[i];

        return (
            Number(e.day_of_week) === Number(s.day_of_week) &&
            (e.start_time ?? null) === (s.start_time ?? null) &&
            (e.end_time ?? null) === (s.end_time ?? null)
        );
    });
};

const emitUpdate = (): void => {
    isEmitting = true;
    emit('update:modelValue', {
        ...props.modelValue,
        schedules: emittedSchedules.value,
    });
    nextTick(() => {
        isEmitting = false;
    });
};

const toggleDay = (day: number, enabled: boolean): void => {
    const entry = scheduleMap.value[day];

    if (!entry) {
        return;
    }

    if (enabled) {
        const { start_time, end_time } = clinicHoursForDay(day);
        scheduleMap.value[day] = {
            enabled: true,
            start_time: entry.start_time ?? start_time ?? '09:00',
            end_time: entry.end_time ?? end_time ?? '17:00',
        };
    } else {
        scheduleMap.value[day] = {
            enabled: false,
            start_time: entry.start_time,
            end_time: entry.end_time,
        };
    }

    emitUpdate();
};

const updateDayField = (
    day: number,
    field: 'start_time' | 'end_time',
    value: string,
): void => {
    const entry = scheduleMap.value[day];

    if (!entry) {
        return;
    }

    scheduleMap.value[day] = {
        ...entry,
        [field]: value === '' ? null : value,
    };
    emitUpdate();
};

const selectAllAvailable = (): void => {
    for (const day of availableDays.value) {
        const { start_time, end_time } = clinicHoursForDay(day);
        scheduleMap.value[day] = {
            enabled: true,
            start_time: scheduleMap.value[day]?.start_time ?? start_time ?? '09:00',
            end_time: scheduleMap.value[day]?.end_time ?? end_time ?? '17:00',
        };
    }

    emitUpdate();
};

const clearAll = (): void => {
    for (const day of availableDays.value) {
        if (scheduleMap.value[day]) {
            scheduleMap.value[day] = {
                ...scheduleMap.value[day],
                enabled: false,
            };
        }
    }

    emitUpdate();
};

const copyFirstDayTimes = (): void => {
    const firstDay = availableDays.value.find(
        (day) => scheduleMap.value[day]?.enabled,
    );

    if (!firstDay) {
        return;
    }

    const source = scheduleMap.value[firstDay];

    for (const day of availableDays.value) {
        if (day === firstDay) {
            continue;
        }

        if (scheduleMap.value[day]?.enabled) {
            scheduleMap.value[day] = {
                ...scheduleMap.value[day],
                start_time: source.start_time,
                end_time: source.end_time,
            };
        }
    }

    emitUpdate();
};

rebuildFromClinic();
applySchedules(props.modelValue.schedules);

watch(
    () => props.selectedClinic,
    () => {
        if (isEmitting) {
            return;
        }

        rebuildFromClinic();
        emitUpdate();
    },
);

watch(
    () => props.modelValue.schedules,
    (newSchedules) => {
        if (isEmitting) {
            return;
        }

        if (schedulesMatchEmitted(newSchedules)) {
            return;
        }

        rebuildFromClinic();
        applySchedules(newSchedules);
    },
    { deep: true },
);
</script>

<template>
    <section class="space-y-3 rounded-lg border border-border bg-card p-4">
        <div class="flex items-center gap-2">
            <CalendarClock class="size-4 text-primary" />
            <h3 class="text-sm font-medium text-foreground">جدول الدوام</h3>
        </div>
        <p class="text-xs text-muted-foreground">
            يظهر دوام العيادة كمرجع. فعّل الأيام وحدد أوقات الطبيب ضمنها.
        </p>

        <div class="flex flex-wrap gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground"
                @click="selectAllAvailable"
            >
                <CheckCheck class="size-3.5" />
                تحديد المتاح
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground"
                @click="clearAll"
            >
                <XCircle class="size-3.5" />
                إلغاء الكل
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground"
                :disabled="activeDaysCount === 0"
                @click="copyFirstDayTimes"
            >
                <Copy class="size-3.5" />
                نسخ أوقات أول يوم
            </Button>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <div
                v-for="day in availableDays"
                :key="day"
                :class="[
                    'rounded-lg border p-3 transition-colors',
                    scheduleMap[day]?.enabled
                        ? 'border-blue-200 bg-blue-50 dark:border-blue-900/70 dark:bg-blue-950/30'
                        : 'border-border/70 bg-muted/40',
                ]"
            >
                <div class="mb-2 flex items-center justify-between gap-2">
                    <div>
                        <p class="font-bold text-foreground">{{ dayName(day) }}</p>
                        <p class="text-xs text-muted-foreground">
                            دوام العيادة: {{ clinicRefFor(day) }}
                        </p>
                    </div>
                    <Switch
                        :model-value="scheduleMap[day]?.enabled ?? false"
                        @update:model-value="toggleDay(day, $event)"
                    />
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <Label class="text-xs">بداية الدوام</Label>
                        <Input
                            :model-value="scheduleMap[day]?.start_time ?? ''"
                            type="time"
                            class="h-9 rounded-lg disabled:cursor-not-allowed disabled:opacity-45"
                            :disabled="!scheduleMap[day]?.enabled"
                            @update:model-value="
                                updateDayField(
                                    day,
                                    'start_time',
                                    String($event),
                                )
                            "
                        />
                        <InputError :message="errorForDay(day, 'start_time')" />
                    </div>
                    <div>
                        <Label class="text-xs">نهاية الدوام</Label>
                        <Input
                            :model-value="scheduleMap[day]?.end_time ?? ''"
                            type="time"
                            class="h-9 rounded-lg disabled:cursor-not-allowed disabled:opacity-45"
                            :disabled="!scheduleMap[day]?.enabled"
                            @update:model-value="
                                updateDayField(
                                    day,
                                    'end_time',
                                    String($event),
                                )
                            "
                        />
                        <InputError :message="errorForDay(day, 'end_time')" />
                    </div>
                </div>
                <InputError :message="errorForDay(day, 'day_of_week')" />
            </div>
        </div>

        <div
            class="flex items-center gap-4 rounded-lg bg-muted/60 px-3 py-2 text-sm"
        >
            <span class="font-semibold text-foreground">
                الأيام المفعّلة: {{ activeDaysCount }}
            </span>
            <span class="font-semibold text-foreground">
                إجمالي الساعات: {{ totalHours }}
            </span>
        </div>
        <InputError :message="props.errors?.schedules" />
    </section>
</template>
