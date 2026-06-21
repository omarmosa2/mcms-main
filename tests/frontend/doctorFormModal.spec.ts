import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import DoctorFormModal from '@/pages/doctors/components/DoctorFormModal.vue';
import type { DoctorProfile, WorkingHour } from '@/pages/doctors/components/types';

const capturedForms = vi.hoisted(() => [] as Record<string, unknown>[]);

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        useForm: vi.fn((defaults: Record<string, unknown>) => {
            const form = reactive({
                ...defaults,
                errors: {},
                processing: false,
                clearErrors: vi.fn(),
                defaults: vi.fn(),
                reset: vi.fn(),
                post: vi.fn(),
                put: vi.fn(),
            });

            capturedForms.push(form);

            return form;
        }),
    };
});

vi.mock(
    '@/actions/App/Http/Controllers/Doctors/DoctorProfileController',
    () => ({
        store: { url: () => '/doctors' },
        update: { url: (id: number) => `/doctors/${id}` },
    }),
);

vi.mock('lucide-vue-next', () => ({
    KeyRound: { template: '<svg />' },
    Save: { template: '<svg />' },
    UserPlus: { template: '<svg />' },
    X: { template: '<svg />' },
}));

const profile: DoctorProfile = {
    id: 7,
    clinic_id: 6,
    user_id: 7,
    gender: 'male',
    phone: null,
    work_start_date: null,
    license_number: null,
    specialty: 'Cardiology',
    consultation_duration_minutes: 30,
    status: 'active',
    compensation_type: 'percentage',
    compensation_value: 30,
    work_schedule: null,
    working_hours: [],
    clinic_working_days: [
        {
            day_of_week: 3,
            is_active: true,
            start_time: '09:00',
            end_time: '18:00',
        },
    ],
    doctor_schedules: [
        {
            day_of_week: 3,
            is_available: true,
            start_time: '11:00:00',
            end_time: '17:00:00',
        },
    ],
    user: { id: 7, name: 'د. أحمد', email: null, is_active: true },
    created_at: null,
    updated_at: null,
};

describe('DoctorFormModal', () => {
    it('maps the current doctor schedule and submits an update request when editing', async () => {
        capturedForms.length = 0;

        const wrapper = mount(DoctorFormModal, {
            props: {
                open: true,
                profile,
                clinic: { id: 6, name: 'العيادة' },
                clinics: [
                    {
                        id: 6,
                        name: 'العيادة',
                        code: null,
                        is_active: true,
                        working_hours: profile.clinic_working_days ?? [],
                    },
                ],
            },
            global: {
                stubs: {
                    Button: { template: '<button><slot /></button>' },
                    Dialog: { template: '<div><slot /></div>' },
                    DialogContent: { template: '<div><slot /></div>' },
                    DialogDescription: { template: '<p><slot /></p>' },
                    DialogFooter: { template: '<footer><slot /></footer>' },
                    DialogHeader: { template: '<header><slot /></header>' },
                    DialogTitle: { template: '<h2><slot /></h2>' },
                    DoctorWorkingHoursSelector: { template: '<div />' },
                    Input: { template: '<input />' },
                    InputError: { template: '<p />' },
                    Label: { template: '<label><slot /></label>' },
                },
            },
        });

        expect((capturedForms[0].working_hours as WorkingHour[])).toEqual([
            {
                day_of_week: 3,
                is_active: true,
                start_time: '11:00',
                end_time: '17:00',
            },
        ]);

        await wrapper.find('form').trigger('submit');

        expect(capturedForms[0].put).toHaveBeenCalledWith('/doctors/7', expect.any(Object));
        expect(capturedForms[0].post).not.toHaveBeenCalled();
    });
});
