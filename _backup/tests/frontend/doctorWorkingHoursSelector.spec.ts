import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import DoctorWorkingHoursSelector from '@/pages/doctors/components/DoctorWorkingHoursSelector.vue';
import type { WorkingHour } from '@/pages/doctors/components/types';

vi.mock('lucide-vue-next', () => ({
    Clock: {
        template: '<svg />',
    },
    AlarmClock: { template: '<svg />' },
    CalendarDays: { template: '<svg />' },
    Copy: { template: '<svg />' },
    RotateCcw: { template: '<svg />' },
}));

const mountSelector = (props: {
    modelValue: WorkingHour[];
    hasSelectedClinic?: boolean;
}) =>
    mount(DoctorWorkingHoursSelector, {
        props: {
            errors: {},
            hasSelectedClinic: props.hasSelectedClinic ?? true,
            modelValue: props.modelValue,
        },
        global: {
            stubs: {
                Input: {
                    emits: ['update:modelValue'],
                    props: ['id', 'max', 'min', 'modelValue'],
                    template:
                        '<input type="time" :id="id" :value="modelValue" :min="min" :max="max" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                },
                InputError: {
                    template: '<p />',
                },
                Label: {
                    template: '<label><slot /></label>',
                },
                Switch: {
                    emits: ['update:modelValue'],
                    props: ['modelValue'],
                    template:
                        '<input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
                },
            },
        },
    });

describe('DoctorWorkingHoursSelector', () => {
    it('shows a prompt before selecting a clinic', () => {
        const wrapper = mountSelector({
            modelValue: [],
            hasSelectedClinic: false,
        });

        expect(wrapper.text()).toContain(
            'يرجى اختيار العيادة أولاً لعرض أيام الدوام المتاحة.',
        );
    });

    it('uses doctor schedule rows as the only source for the displayed days', async () => {
        const wrapper = mountSelector({
            modelValue: [
                {
                    day_of_week: 0,
                    is_available: false,
                    start_time: null,
                    end_time: null,
                },
                {
                    day_of_week: 2,
                    is_available: false,
                    start_time: null,
                    end_time: null,
                },
            ],
        });

        expect(wrapper.text()).toContain('الأحد');
        expect(wrapper.text()).toContain('الثلاثاء');
        expect(wrapper.text()).not.toContain('الإثنين');

        await wrapper.find('input[type="checkbox"]').setValue(true);

        const updates = wrapper.emitted('update:modelValue');
        const updatedWorkingHours = updates?.[0]?.[0] as WorkingHour[];

        expect(updatedWorkingHours[0]).toMatchObject({
            day_of_week: 0,
            is_available: true,
            start_time: null,
            end_time: null,
        });
    });

    it('does not set input bounds from clinic working hours', () => {
        const wrapper = mountSelector({
            modelValue: [
                {
                    day_of_week: 0,
                    is_available: true,
                    start_time: '10:00',
                    end_time: '14:00',
                },
            ],
        });

        const timeInputs = wrapper.findAll('input[type="time"]');

        expect(timeInputs[0].attributes('min')).toBeUndefined();
        expect(timeInputs[0].attributes('max')).toBeUndefined();
        expect(timeInputs[1].attributes('min')).toBeUndefined();
        expect(timeInputs[1].attributes('max')).toBeUndefined();
    });

    it('shows an existing doctor schedule as active', () => {
        const wrapper = mountSelector({
            modelValue: [
                {
                    day_of_week: 4,
                    is_available: true,
                    start_time: '10:00',
                    end_time: '14:00',
                },
            ],
        });

        expect(wrapper.find('input[type="checkbox"]').element.checked).toBe(
            true,
        );
    });
});
