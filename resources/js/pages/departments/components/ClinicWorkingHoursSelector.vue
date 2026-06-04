<script setup lang="ts">
import { Clock } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Switch } from '@/components/ui/switch';
import type { ClinicWorkingDay, ClinicWorkingHour } from './types';

const workingHours = defineModel<ClinicWorkingHour[]>({ required: true });

defineProps<{
    errors?: Record<string, string>;
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

const ensureRows = (): void => {
    const currentRows = new Map(
        workingHours.value.map((row) => [row.day_of_week, row]),
    );

    workingHours.value = weekDays.map(({ value }) => ({
        day_of_week: value,
        is_active: currentRows.get(value)?.is_active ?? false,
        start_time: currentRows.get(value)?.start_time ?? null,
        end_time: currentRows.get(value)?.end_time ?? null,
    }));
};

const updateDay = (day: ClinicWorkingDay, patch: Partial<ClinicWorkingHour>) => {
    workingHours.value = workingHours.value.map((row) =>
        row.day_of_week === day ? { ...row, ...patch } : row,
    );
};

const toggleDay = (day: ClinicWorkingDay, isActive: boolean) => {
    updateDay(day, {
        is_active: isActive,
        start_time: isActive ? '09:00' : null,
        end_time: isActive ? '17:00' : null,
    });
};

const errorFor = (index: number, field: 'start_time' | 'end_time') => {
    return errors?.[`working_hours.${index}.${field}`];
};

ensureRows();
</script>

<template>
    <section class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-[#111827]">دوام العيادة</h3>
                <p class="mt-1 text-sm text-[#6C7F95]">
                    فعّل الأيام المتاحة للحجز وحدد وقت البداية والنهاية.
                </p>
            </div>
            <Clock class="size-5 text-[#0EA5E9]" />
        </div>

        <div class="space-y-3">
            <div
                v-for="(day, index) in weekDays"
                :key="day.value"
                class="rounded-2xl border border-[#E2ECF6] bg-[#F8FCFF] p-4"
            >
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-3">
                        <Switch
                            :model-value="workingHours[index]?.is_active ?? false"
                            @update:model-value="(value) => toggleDay(day.value, Boolean(value))"
                        />
                        <div>
                            <p class="text-sm font-bold text-[#111827]">{{ day.label }}</p>
                            <p class="text-xs font-medium text-[#6C7F95]">
                                {{ workingHours[index]?.is_active ? 'دوام' : 'لا يوجد دوام' }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="workingHours[index]?.is_active"
                        class="grid flex-1 gap-3 md:max-w-md md:grid-cols-2"
                    >
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-[#6C7F95]">بداية الدوام</label>
                            <input
                                :value="workingHours[index]?.start_time ?? ''"
                                type="time"
                                class="h-11 w-full rounded-2xl border border-[#DDE9F3] bg-white px-4 text-sm font-semibold text-[#111827] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] focus:border-[#0EA5E9] focus:outline-none focus:ring-2 focus:ring-[#0EA5E9]/10"
                                @input="(event) => updateDay(day.value, { start_time: (event.target as HTMLInputElement).value })"
                            />
                            <InputError class="mt-1" :message="errorFor(index, 'start_time')" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-[#6C7F95]">نهاية الدوام</label>
                            <input
                                :value="workingHours[index]?.end_time ?? ''"
                                type="time"
                                class="h-11 w-full rounded-2xl border border-[#DDE9F3] bg-white px-4 text-sm font-semibold text-[#111827] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] focus:border-[#0EA5E9] focus:outline-none focus:ring-2 focus:ring-[#0EA5E9]/10"
                                @input="(event) => updateDay(day.value, { end_time: (event.target as HTMLInputElement).value })"
                            />
                            <InputError class="mt-1" :message="errorFor(index, 'end_time')" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
