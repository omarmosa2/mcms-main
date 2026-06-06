import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ClinicWorkingHoursSelector from '@/pages/departments/components/ClinicWorkingHoursSelector.vue';
import type { ClinicWorkingDay, ClinicWorkingHour } from '@/pages/departments/components/types';

vi.mock('lucide-vue-next', () => ({
    Clock: {
        template: '<svg />',
    },
}));

const weekDays: ClinicWorkingDay[] = [
    'saturday',
    'sunday',
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
];

const emptyWorkingHours = (): ClinicWorkingHour[] =>
    weekDays.map((day) => ({
        day_of_week: day,
        is_active: false,
        start_time: null,
        end_time: null,
    }));

describe('ClinicWorkingHoursSelector', () => {
    it('activates a working day with default hours', async () => {
        const wrapper = mount(ClinicWorkingHoursSelector, {
            props: {
                modelValue: emptyWorkingHours(),
                errors: {},
            },
        });

        await wrapper.find('input[type="checkbox"]').setValue(true);

        const updates = wrapper.emitted('update:modelValue');
        const updatedWorkingHours = updates?.[0]?.[0] as ClinicWorkingHour[];

        expect(updatedWorkingHours[0]).toMatchObject({
            day_of_week: 'saturday',
            is_active: true,
            start_time: '09:00',
            end_time: '17:00',
        });
    });

    it('clears working hours when a day is deactivated', async () => {
        const wrapper = mount(ClinicWorkingHoursSelector, {
            props: {
                modelValue: [
                    {
                        day_of_week: 'saturday',
                        is_active: true,
                        start_time: '10:00',
                        end_time: '16:00',
                    },
                    ...emptyWorkingHours().slice(1),
                ],
                errors: {},
            },
        });

        await wrapper.find('input[type="checkbox"]').setValue(false);

        const updates = wrapper.emitted('update:modelValue');
        const updatedWorkingHours = updates?.[0]?.[0] as ClinicWorkingHour[];

        expect(updatedWorkingHours[0]).toMatchObject({
            day_of_week: 'saturday',
            is_active: false,
            start_time: null,
            end_time: null,
        });
    });

    it('updates working day times dynamically', async () => {
        const wrapper = mount(ClinicWorkingHoursSelector, {
            props: {
                modelValue: [
                    {
                        day_of_week: 'saturday',
                        is_active: true,
                        start_time: '09:00',
                        end_time: '17:00',
                    },
                    ...emptyWorkingHours().slice(1),
                ],
                errors: {},
            },
        });

        const timeInputs = wrapper.findAll('input[type="time"]');

        await timeInputs[0].setValue('11:30');
        await timeInputs[1].setValue('18:45');

        const updates = wrapper.emitted('update:modelValue');
        const startTimeUpdate = updates?.[0]?.[0] as ClinicWorkingHour[];
        const endTimeUpdate = updates?.[1]?.[0] as ClinicWorkingHour[];

        expect(startTimeUpdate[0]).toMatchObject({
            day_of_week: 'saturday',
            start_time: '11:30',
            end_time: '17:00',
        });
        expect(endTimeUpdate[0]).toMatchObject({
            day_of_week: 'saturday',
            start_time: '09:00',
            end_time: '18:45',
        });
    });
});
