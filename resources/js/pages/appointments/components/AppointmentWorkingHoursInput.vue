<script setup lang="ts">
import { AlertCircle, Calendar, Clock } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    AvailabilityPeriod,
    ClinicWorkingDay,
    ClinicWorkingHour,
} from './types';

const props = withDefaults(
    defineProps<{
        workingHours: ClinicWorkingHour[];
        availablePeriods?: AvailabilityPeriod[] | null;
        availabilityDate?: string | null;
        defaultValue?: string | null;
        label?: string;
        name?: string;
    }>(),
    {
        availablePeriods: null,
        availabilityDate: null,
        defaultValue: null,
        label: 'موعد',
        name: 'scheduled_for',
    },
);

const jsDayToClinicDay: Record<number, ClinicWorkingDay> = {
    0: 'sunday',
    1: 'monday',
    2: 'tuesday',
    3: 'wednesday',
    4: 'thursday',
    5: 'friday',
    6: 'saturday',
};

const toDatePart = (value: string | null | undefined): string => {
    if (!value) {
        return new Date().toISOString().slice(0, 10);
    }

    if (/^\d{4}-\d{2}-\d{2}T/.test(value)) {
        return value.slice(0, 10);
    }

    return new Date(value).toISOString().slice(0, 10);
};

const toTimePart = (value: string | null | undefined): string => {
    if (!value) {
        return '';
    }

    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(value)) {
        return value.slice(11, 16);
    }

    return new Date(value).toTimeString().slice(0, 5);
};

const selectedDate = ref(toDatePart(props.defaultValue));
const selectedTime = ref(toTimePart(props.defaultValue));

watch(
    () => props.defaultValue,
    (value) => {
        selectedDate.value = toDatePart(value);
        selectedTime.value = toTimePart(value);
    },
);

const configuredHours = computed(() => props.workingHours.length > 0);

const todayDate = computed(() => new Date().toISOString().slice(0, 10));

const minimumTimeForSelectedDate = computed(() => {
    if (selectedDate.value !== todayDate.value) {
        return null;
    }

    const now = new Date();
    const totalMinutes = now.getHours() * 60 + now.getMinutes();
    const roundedMinutes = Math.ceil(totalMinutes / 15) * 15;
    const hour = Math.floor(roundedMinutes / 60);
    const minute = roundedMinutes % 60;

    if (hour >= 24) {
        return '23:59';
    }

    return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
});

const filterPastSlots = (slots: string[]): string[] => {
    const minimumTime = minimumTimeForSelectedDate.value;

    if (minimumTime === null) {
        return slots;
    }

    return slots.filter((slot) => slot >= minimumTime);
};

const workingHourForSelectedDate = computed(() => {
    const date = new Date(`${selectedDate.value}T00:00:00`);
    const day = jsDayToClinicDay[date.getDay()];

    return props.workingHours.find((row) => row.day_of_week === day) ?? null;
});

const timeSlots = computed<string[]>(() => {
    if (
        props.availabilityDate !== null &&
        props.availabilityDate === selectedDate.value
    ) {
        return filterPastSlots(
            (props.availablePeriods ?? []).flatMap((period) =>
                buildSlots(period.start_time, period.end_time),
            ),
        );
    }

    if (!configuredHours.value) {
        return filterPastSlots(buildSlots('07:00', '22:00'));
    }

    const row = workingHourForSelectedDate.value;

    if (!row?.is_active || !row.start_time || !row.end_time) {
        return [];
    }

    return filterPastSlots(buildSlots(row.start_time, row.end_time));
});

const selectedValue = computed(() => {
    if (!selectedDate.value || !selectedTime.value) {
        return '';
    }

    return `${selectedDate.value}T${selectedTime.value}`;
});

const isToday = computed(() => selectedDate.value === todayDate.value);

const isDayOff = computed(() => {
    if (!configuredHours.value) {
        return false;
    }

    const row = workingHourForSelectedDate.value;

    return row !== null && !row.is_active;
});

watch(
    () => timeSlots.value,
    (slots) => {
        if (selectedTime.value && !slots.includes(selectedTime.value)) {
            selectedTime.value = '';
        }

        if (slots.length > 0 && !selectedTime.value) {
            selectedTime.value = slots[0];
        }
    },
    { immediate: true },
);

function buildSlots(startTime: string, endTime: string): string[] {
    const [startHour, startMinute] = startTime.split(':').map(Number);
    const [endHour, endMinute] = endTime.split(':').map(Number);
    const slots: string[] = [];
    let current = startHour * 60 + startMinute;
    const end = endHour * 60 + endMinute;

    while (current < end) {
        const hour = Math.floor(current / 60);
        const minute = current % 60;
        slots.push(
            `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`,
        );
        current += 15;
    }

    return slots;
}

const formatSlotLabel = (slot: string): string => {
    const [hours, minutes] = slot.split(':').map(Number);
    const period = hours >= 12 ? 'م' : 'ص';
    const displayHours = hours > 12 ? hours - 12 : hours === 0 ? 12 : hours;

    return `${String(displayHours).padStart(2, '0')}:${String(minutes).padStart(2, '0')} ${period}`;
};
</script>

<template>
    <div class="grid gap-1.5">
        <Label
            v-if="label"
            class="flex items-center gap-1.5 text-xs font-semibold text-foreground"
        >
            <Calendar class="size-3.5 text-primary" />
            {{ label }}
            <span class="text-xs text-destructive">*</span>
        </Label>
        <input :name="name" type="hidden" :value="selectedValue" />

        <div class="grid gap-2 sm:grid-cols-2">
            <Input
                v-model="selectedDate"
                class="h-10 rounded-xl bg-background text-sm"
                type="date"
                :min="todayDate"
                required
            />

            <Select
                :model-value="selectedTime"
                :disabled="timeSlots.length === 0"
                @update:model-value="selectedTime = String($event ?? '')"
            >
                <SelectTrigger
                    class="h-10 rounded-xl bg-background"
                    :class="{
                        'border-destructive/50 bg-destructive/5':
                            timeSlots.length === 0,
                    }"
                >
                    <SelectValue
                        :placeholder="
                            timeSlots.length === 0
                                ? 'لا توجد أوقات'
                                : formatSlotLabel(selectedTime || timeSlots[0])
                        "
                    />
                </SelectTrigger>
                <SelectContent class="max-h-64">
                    <SelectItem
                        v-for="slot in timeSlots"
                        :key="slot"
                        :value="slot"
                    >
                        <span class="flex items-center gap-2" dir="ltr">
                            <Clock class="size-3 text-muted-foreground" />
                            {{ formatSlotLabel(slot) }}
                        </span>
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div
            v-if="timeSlots.length === 0"
            class="flex items-center gap-2 rounded-xl border border-destructive/20 bg-destructive/5 px-3 py-2"
        >
            <AlertCircle class="size-4 shrink-0 text-destructive" />
            <p class="text-[0.72rem] text-destructive">
                <template v-if="isDayOff">
                    هذا اليوم إجازة - لا توجد أوقات متاحة
                </template>
                <template v-else-if="isToday">
                    لا توجد أوقات متاحة اليوم - اختر تاريخا آخر
                </template>
                <template v-else>
                    لا توجد أوقات متاحة لهذا التاريخ
                </template>
            </p>
        </div>

        <p
            v-else-if="isToday && timeSlots.length > 0"
            class="text-[0.68rem] text-muted-foreground"
        >
            الأوقات الماضية مخفية تلقائياً
        </p>
    </div>
</template>
