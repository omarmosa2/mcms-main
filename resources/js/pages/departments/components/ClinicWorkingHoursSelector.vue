<script setup lang="ts">
import { Clock } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import type { ClinicWorkingDay, ClinicWorkingHour } from './types';

const props = defineProps<{
    modelValue: ClinicWorkingHour[];
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: ClinicWorkingHour[]];
}>();

const weekDays: { value: ClinicWorkingDay; label: string }[] = [
    { value: 'saturday', label: 'السبت' },
    { value: 'sunday', label: 'الأحد' },
    { value: 'monday', label: 'الإثنين' },
    { value: 'tuesday', label: 'الثلاثاء' },
    { value: 'wednesday', label: 'الأربعاء' },
    { value: 'thursday', label: 'الخميس' },
    { value: 'friday', label: 'الجمعة' },
];

const normalizedWorkingHours = computed<ClinicWorkingHour[]>(() => {
    const currentRows = new Map(
        props.modelValue.map((row) => [row.day_of_week, row]),
    );

    return weekDays.map(({ value }) => ({
        day_of_week: value,
        is_active: currentRows.get(value)?.is_active ?? false,
        start_time: currentRows.get(value)?.start_time ?? null,
        end_time: currentRows.get(value)?.end_time ?? null,
    }));
});

const workingHours = normalizedWorkingHours;

const labelFor = (day: ClinicWorkingDay): string => {
    return weekDays.find((item) => item.value === day)?.label ?? '';
};

const updateDay = (day: ClinicWorkingDay, patch: Partial<ClinicWorkingHour>): void => {
    emit('update:modelValue', normalizedWorkingHours.value.map((row) =>
        row.day_of_week === day ? { ...row, ...patch } : row,
    ));
};

const toggleDay = (day: ClinicWorkingDay, isActive: boolean): void => {
    const current = normalizedWorkingHours.value.find((row) => row.day_of_week === day);

    updateDay(day, {
        is_active: isActive,
        start_time: isActive ? (current?.start_time ?? '09:00') : null,
        end_time: isActive ? (current?.end_time ?? '17:00') : null,
    });
};

const errorFor = (index: number, field: 'start_time' | 'end_time'): string | undefined => {
    return props.errors?.[`working_hours.${index}.${field}`];
};
</script>

<template>
    <section class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-foreground">دوام العيادة</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    فعّل الأيام المتاحة للحجز وحدد وقت البداية والنهاية.
                </p>
            </div>
            <Clock class="size-5 text-primary" />
        </div>

        <div class="space-y-3">
            <div
                v-for="(day, index) in normalizedWorkingHours"
                :key="day.day_of_week"
                class="rounded-2xl border border-border bg-muted p-4"
            >
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-3">
                        <label class="relative inline-flex h-7 w-12 shrink-0 cursor-pointer items-center">
                            <input
                                type="checkbox"
                                class="peer sr-only"
                                :checked="day.is_active"
                                @change="toggleDay(day.day_of_week, ($event.target as HTMLInputElement).checked)"
                            />
                            <span
                                class="absolute inset-0 rounded-full bg-muted transition peer-checked:bg-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/20 peer-focus-visible:ring-offset-2"
                            ></span>
                            <span
                                class="absolute right-1 size-5 rounded-full bg-card shadow-sm transition-transform peer-checked:-translate-x-5"
                            ></span>
                        </label>
                        <div>
                            <p class="text-sm font-bold text-foreground">{{ labelFor(day.day_of_week) }}</p>
                            <p class="text-xs font-medium text-muted-foreground">
                                {{ workingHours[index]?.is_active ? 'دوام' : 'لا يوجد دوام' }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="workingHours[index]?.is_active"
                        class="grid flex-1 gap-3 md:max-w-md md:grid-cols-2"
                    >
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-muted-foreground">بداية الدوام</label>
                            <input
                                :value="workingHours[index]?.start_time ?? ''"
                                type="time"
                                class="h-11 w-full rounded-2xl border border-input bg-card px-4 text-sm font-semibold text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                                @input="(event) => updateDay(day.day_of_week, { start_time: (event.target as HTMLInputElement).value })"
                            />
                            <InputError class="mt-1" :message="errorFor(index, 'start_time')" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-muted-foreground">نهاية الدوام</label>
                            <input
                                :value="workingHours[index]?.end_time ?? ''"
                                type="time"
                                class="h-11 w-full rounded-2xl border border-input bg-card px-4 text-sm font-semibold text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                                @input="(event) => updateDay(day.day_of_week, { end_time: (event.target as HTMLInputElement).value })"
                            />
                            <InputError class="mt-1" :message="errorFor(index, 'end_time')" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
