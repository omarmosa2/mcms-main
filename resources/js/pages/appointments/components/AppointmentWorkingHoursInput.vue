<script setup lang="ts">
import { Calendar } from 'lucide-vue-next';
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
import type { ClinicWorkingDay, ClinicWorkingHour } from './types';

const props = withDefaults(
    defineProps<{
        workingHours: ClinicWorkingHour[];
        defaultValue?: string | null;
        label?: string;
        name?: string;
    }>(),
    {
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

const workingHourForSelectedDate = computed(() => {
    const date = new Date(`${selectedDate.value}T00:00:00`);
    const day = jsDayToClinicDay[date.getDay()];

    return props.workingHours.find((row) => row.day_of_week === day) ?? null;
});

const timeSlots = computed<string[]>(() => {
    if (!configuredHours.value) {
        return buildSlots('07:00', '22:00');
    }

    const row = workingHourForSelectedDate.value;

    if (!row?.is_active || !row.start_time || !row.end_time) {
        return [];
    }

    return buildSlots(row.start_time, row.end_time);
});

const selectedValue = computed(() => {
    if (!selectedDate.value || !selectedTime.value) {
        return '';
    }

    return `${selectedDate.value}T${selectedTime.value}`;
});

watch(
    () => timeSlots.value,
    (slots) => {
        if (selectedTime.value && !slots.includes(selectedTime.value)) {
            selectedTime.value = '';
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
        slots.push(`${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`);
        current += 15;
    }

    return slots;
}
</script>

<template>
    <div class="grid gap-1.5">
        <Label v-if="label" class="flex items-center gap-1.5 text-xs font-medium">
            <Calendar class="size-3.5 text-muted-foreground" />
            {{ label }}
            <span class="text-destructive">*</span>
        </Label>
        <input :name="name" type="hidden" :value="selectedValue" />

        <div class="grid gap-2 md:grid-cols-2">
            <Input
                v-model="selectedDate"
                type="date"
                required
            />

            <Select
                :model-value="selectedTime"
                @update:model-value="selectedTime = String($event ?? '')"
                :disabled="timeSlots.length === 0"
            >
                <SelectTrigger>
                    <SelectValue
                        :placeholder="
                            timeSlots.length === 0
                                ? 'لا توجد أوقات متاحة'
                                : 'اختر الوقت'
                        "
                    />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="slot in timeSlots"
                        :key="slot"
                        :value="slot"
                    >
                        {{ slot }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
    </div>
</template>
