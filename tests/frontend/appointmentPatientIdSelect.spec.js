import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h, inject, nextTick, provide } from 'vue';
import AppointmentCreateSheet from '@/pages/appointments/components/AppointmentCreateSheet.vue';
import AppointmentEditDialog from '@/pages/appointments/components/AppointmentEditDialog.vue';
import AppointmentQuickAddForm from '@/pages/appointments/components/AppointmentQuickAddForm.vue';

vi.mock('@/actions/App/Http/Controllers/Appointments/AppointmentController', () => ({
    default: {
        store: {
            form: () => ({ action: '/appointments', method: 'post' }),
        },
        update: {
            form: () => ({ action: '/appointments/1', method: 'put' }),
        },
        bookingOptions: {
            url: () => '/appointments/booking-options',
        },
    },
}));

vi.mock('@/actions/App/Http/Controllers/Patients/PatientController', () => ({
    default: {
        index: {
            url: () => '/patients',
        },
        store: {
            form: () => ({ action: '/patients', method: 'post' }),
        },
    },
}));

const formStub = defineComponent({
    setup(_, { slots }) {
        return () =>
            h('form', slots.default?.({ errors: {}, processing: false }));
    },
});

const selectStub = defineComponent({
    props: {
        modelValue: {
            type: [String, Number],
            default: '',
        },
        name: {
            type: String,
            default: undefined,
        },
    },
    emits: ['update:modelValue'],
    setup(props, { emit, slots }) {
        provide('select:update', (value) => emit('update:modelValue', value));

        return () =>
            h(
                'div',
                {
                    class: 'select-stub',
                    'data-name': props.name,
                    'data-model-value': props.modelValue,
                },
                [
                    h(
                        'button',
                        {
                            class: 'select-change',
                            type: 'button',
                            onClick: () => emit('update:modelValue', '2'),
                        },
                        'select',
                    ),
                    slots.default?.(),
                ],
            );
    },
});

const selectItemStub = defineComponent({
    name: 'SelectItem',
    props: {
        value: {
            type: String,
            required: true,
        },
    },
    setup(props, { slots }) {
        const update = inject('select:update', () => {});

        return () =>
            h(
                'button',
                {
                    class: 'select-item-stub',
                    'data-value': props.value,
                    type: 'button',
                    onClick: () => update(props.value),
                },
                slots.default?.(),
            );
    },
});

const stubs = {
    Form: formStub,
    Select: selectStub,
    SelectContent: { template: '<div><slot /></div>' },
    SelectItem: selectItemStub,
    SelectTrigger: { template: '<button type="button"><slot /></button>' },
    SelectValue: { template: '<span />' },
    Button: { template: '<button type="button"><slot /></button>' },
    Input: { template: '<input />' },
    InputError: { template: '<p />' },
    Label: { template: '<label><slot /></label>' },
    Dialog: { template: '<div><slot /></div>' },
    DialogBody: { template: '<div><slot /></div>' },
    DialogContent: { template: '<div><slot /></div>' },
    DialogDescription: { template: '<p><slot /></p>' },
    DialogFooter: { template: '<footer><slot /></footer>' },
    DialogHeader: { template: '<header><slot /></header>' },
    DialogTitle: { template: '<h2><slot /></h2>' },
    Spinner: { template: '<span />' },
    AppointmentWorkingHoursInput: { template: '<input name="scheduled_for" />' },
};

const props = {
    patients: [
        {
            id: 2,
            full_name: 'مريض تجريبي',
            file_number: 17,
        },
    ],
    doctors: [],
    clinics: [],
    clinicWorkingHours: [],
    todayAvailability: {
        date: '2026-06-28',
        clinics: [],
        clinic_options: [],
        doctors: [],
        clinic_periods: {},
    },
};

describe('appointment patient select payload', () => {
    it('submits the selected patient id from the create form', async () => {
        const wrapper = mount(AppointmentCreateSheet, {
            props: { ...props, open: true },
            global: { stubs },
        });

        await wrapper.findAll('.select-change')[0].trigger('click');
        await nextTick();

        const input = wrapper.find('input[name="patient_id"]');

        expect(input.element.value).toBe('2');
        expect(
            wrapper.find('.select-item-stub').attributes('data-value'),
        ).toBe('2');
    });

    it('submits the selected patient id from the quick add form', async () => {
        const wrapper = mount(AppointmentQuickAddForm, {
            props,
            global: { stubs },
        });

        await wrapper.findAll('.select-change')[0].trigger('click');
        await nextTick();

        const input = wrapper.find('input[name="patient_id"]');

        expect(input.element.value).toBe('2');
        expect(
            wrapper.find('.select-item-stub').attributes('data-value'),
        ).toBe('2');
    });

    it('submits the appointment patient id from the edit form', async () => {
        const wrapper = mount(AppointmentEditDialog, {
            props: {
                ...props,
                appointment: {
                    id: 1,
                    patient_id: 7,
                    doctor_id: null,
                    appointment_number: 'APT-1',
                    scheduled_for: '2026-06-28T10:00:00',
                    duration_minutes: 30,
                    status: 'scheduled',
                    cancel_reason: null,
                    notes: null,
                    patient: {
                        id: 7,
                        full_name: 'مريض حالي',
                        file_number: 99,
                    },
                },
            },
            global: { stubs },
        });

        const input = wrapper.find('input[name="patient_id"]');

        expect(input.element.value).toBe('7');

        await wrapper.findAll('.select-change')[1].trigger('click');
        await nextTick();

        expect(input.element.value).toBe('2');
    });

    it('submits the selected doctor clinic with the quick add form', async () => {
        const wrapper = mount(AppointmentQuickAddForm, {
            props: {
                ...props,
                todayAvailability: {
                    date: '2026-06-28',
                    current_date: '2026-06-28',
                    current_time: '09:00',
                    clinics: [2],
                    clinic_options: [{ id: 2, name: 'عيادة الأسنان' }],
                    doctors: [
                        {
                            id: 9,
                            doctor_id: 9,
                            doctor_profile_id: 19,
                            name: 'د. سامر',
                            full_name: 'د. سامر',
                            clinic_id: 2,
                            specialty: null,
                            start_time: '10:00',
                            end_time: '14:00',
                            clinic: { id: 2, name: 'عيادة الأسنان' },
                            available_periods: [
                                { start_time: '10:00', end_time: '14:00' },
                            ],
                        },
                    ],
                    clinic_periods: {
                        2: [{ start_time: '10:00', end_time: '14:00' }],
                    },
                },
            },
            global: { stubs },
        });

        await wrapper.find('[data-value="2:9"]').trigger('click');
        await nextTick();

        expect(wrapper.find('input[name="clinic_id"]').element.value).toBe('2');
        expect(wrapper.find('input[name="doctor_id"]').element.value).toBe('9');
    });
});
