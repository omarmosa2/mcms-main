import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import DoctorWorkingHoursSelector from '@/pages/doctors/components/DoctorWorkingHoursSelector.vue';
import type {
    ClinicWorkingHour,
    WorkingHour,
} from '@/pages/doctors/components/types';

vi.mock('lucide-vue-next', () => ({
    Clock: {
        template: '<svg />',
    },
}));

const clinicWorkingHours: ClinicWorkingHour[] = [
    {
        day_of_week: 0,
        is_active: true,
        start_time: '09:00',
        end_time: '17:00',
    },
    {
        day_of_week: 2,
        is_active: true,
        start_time: '10:00',
        end_time: '16:00',
    },
];

const availableDoctorDays = (): WorkingHour[] => [
    { day_of_week: 0, is_active: false, start_time: null, end_time: null },
    { day_of_week: 2, is_active: false, start_time: null, end_time: null },
];

const mountSelector = (props: {
    modelValue: WorkingHour[];
    clinicWorkingHours?: ClinicWorkingHour[];
    hasSelectedClinic?: boolean;
}) =>
    mount(DoctorWorkingHoursSelector, {
        props: {
            errors: {},
            clinicWorkingHours: props.clinicWorkingHours ?? clinicWorkingHours,
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
            clinicWorkingHours: [],
            hasSelectedClinic: false,
        });

        expect(wrapper.text()).toContain(
            'يرجى اختيار العيادة أولاً لعرض أيام الدوام المتاحة.',
        );
    });

    it('renders only available clinic days and uses clinic hours as limits', async () => {
        const wrapper = mountSelector({
            modelValue: availableDoctorDays(),
        });

        expect(wrapper.text()).toContain('الأحد');
        expect(wrapper.text()).toContain('الثلاثاء');
        expect(wrapper.text()).not.toContain('الإثنين');

        await wrapper.find('input[type="checkbox"]').setValue(true);

        const updates = wrapper.emitted('update:modelValue');
        const updatedWorkingHours = updates?.[0]?.[0] as WorkingHour[];

        expect(updatedWorkingHours[0]).toMatchObject({
            day_of_week: 0,
            is_active: true,
            start_time: '09:00',
            end_time: '17:00',
        });
    });

    it('adds min and max attributes from clinic working hours', () => {
        const wrapper = mountSelector({
            modelValue: [
                {
                    day_of_week: 0,
                    is_active: true,
                    start_time: '10:00',
                    end_time: '14:00',
                },
            ],
        });

        const timeInputs = wrapper.findAll('input[type="time"]');

        expect(timeInputs[0].attributes('min')).toBe('09:00');
        expect(timeInputs[0].attributes('max')).toBe('17:00');
        expect(timeInputs[1].attributes('min')).toBe('09:00');
        expect(timeInputs[1].attributes('max')).toBe('17:00');
    });
});
