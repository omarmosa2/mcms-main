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
        durationMinutes?: number;
        noDoctorSelected?: boolean;
    }>(),
    {
        availablePeriods: null,
        availabilityDate: null,
        defaultValue: null,
        label: 'موعد',
        name: 'scheduled_for',
        durationMinutes: 30,
        noDoctorSelected: false,
    },
);

const todayDate = computed(() => new Date().toISOString().slice(0, 10));
const selectedDate = ref(todayDate.value);
const selectedTime = ref('');

const minimumTimeForToday = computed(() => {
    const now = new Date();
    const totalMinutes = now.getHours() * 60 + now.getMinutes();
    const interval = props.durationMinutes;
    const roundedMinutes = Math.ceil(totalMinutes / interval) * interval;
    const hour = Math.floor(roundedMinutes / 60);
    const minute = roundedMinutes % 60;

    if (hour >= 24) {
        return '23:59';
    }

    return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
});

const filterPastSlots = (slots: string[]): string[] => {
    const minimumTime = minimumTimeForToday.value;
    return slots.filter((slot) => slot >= minimumTime);
};

const timeSlots = computed<string[]>(() => {
    if (props.noDoctorSelected) {
        return [];
    }

    if (props.availablePeriods && props.availablePeriods.length > 0) {
        return filterPastSlots(
            props.availablePeriods.flatMap((period) =>
                buildSlots(period.start_time, period.end_time),
            ),
        );
    }

    return [];
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
    const interval = props.durationMinutes;

    while (current + interval <= end) {
        const hour = Math.floor(current / 60);
        const minute = current % 60;
        slots.push(
            `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`,
        );
        current += interval;
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
                :model-value="todayDate"
                class="h-10 rounded-xl bg-background text-sm"
                type="date"
                readonly
                disabled
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
                            noDoctorSelected
                                ? 'اختر طيبباً أولاً'
                                : timeSlots.length === 0
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
            v-if="noDoctorSelected"
            class="flex items-center gap-2 rounded-xl border border-muted bg-muted/30 px-3 py-2"
        >
            <AlertCircle class="size-4 shrink-0 text-muted-foreground" />
            <p class="text-[0.72rem] text-muted-foreground">
                اختر طيبباً لعرض الأوقات المتاحة
            </p>
        </div>

        <div
            v-else-if="timeSlots.length === 0"
            class="flex items-center gap-2 rounded-xl border border-destructive/20 bg-destructive/5 px-3 py-2"
        >
            <AlertCircle class="size-4 shrink-0 text-destructive" />
            <p class="text-[0.72rem] text-destructive">
                لا توجد أوقات متاحة لهذا الطبيب ضمن دوام العيادة اليوم
            </p>
        </div>

        <p
            v-else-if="timeSlots.length > 0"
            class="text-[0.68rem] text-muted-foreground"
        >
            الأوقات الماضية مخفية تلقائياً
        </p>
    </div>
</template>
