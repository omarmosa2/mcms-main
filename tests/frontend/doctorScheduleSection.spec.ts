import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DoctorScheduleSection from '@/pages/doctors/components/DoctorScheduleSection.vue';
import type { Clinic, DoctorFormData } from '@/pages/doctors/types';

const clinic: Clinic = {
    id: 1,
    name: 'العيادة',
    code: 'CL-1',
    is_active: true,
    working_hours: [
        { day_of_week: 1, is_active: true, start_time: '09:00', end_time: '17:00' },
        { day_of_week: 3, is_active: true, start_time: '10:00', end_time: '16:00' },
    ],
};

const form: DoctorFormData = {
    clinic_id: 1,
    user_id: null,
    full_name: 'د. أحمد',
    gender: 'male',
    specialty: 'قلبية',
    phone: '',
    email: '',
    username: '',
    employment_start_date: '',
    compensation_type: 'percentage',
    compensation_value: '',
    is_active: true,
    notes: '',
    schedules: [
        { day_of_week: 1, is_available: true, start_time: '09:00', end_time: '13:00' },
    ],
};

describe('DoctorScheduleSection', () => {
    it('hydrates doctor schedules and fills clinic times when an available day is enabled', async () => {
        const wrapper = mount(DoctorScheduleSection, {
            props: { modelValue: form, selectedClinic: clinic, errors: {} },
            global: {
                stubs: {
                    Button: { template: '<button><slot /></button>' },
                    InputError: { template: '<p><slot /></p>' },
                    Input: {
                        props: ['modelValue'],
                        template: '<input :value="modelValue" />',
                    },
                    Label: { template: '<label><slot /></label>' },
                    Switch: {
                        template: '<button class="switch" @click="$emit(\'update:modelValue\', true)" />',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('09:00 – 17:00');
        expect(wrapper.text()).toContain('10:00 – 16:00');
        expect(wrapper.findAll('input')[0].element.value).toBe('09:00');
        expect(wrapper.findAll('input')[1].element.value).toBe('13:00');

        await wrapper.findAll('.switch')[1].trigger('click');

        expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toMatchObject({
            schedules: [
                { day_of_week: 1, is_available: true, start_time: '09:00', end_time: '13:00' },
                { day_of_week: 3, is_available: true, start_time: '10:00', end_time: '16:00' },
            ],
        });
    });
});
