import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import DoctorFormModal from '@/pages/doctors/components/DoctorFormModal.vue';
import type {
    ClinicWorkingHour,
    DepartmentOption,
    WorkingHour,
} from '@/pages/doctors/components/types';

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
        store: {
            url: () => '/doctors',
        },
        update: {
            url: (id: number) => `/doctors/${id}`,
        },
    }),
);

vi.mock('lucide-vue-next', () => ({
    KeyRound: {
        template: '<svg />',
    },
    Save: {
        template: '<svg />',
    },
    UserPlus: {
        template: '<svg />',
    },
    X: {
        template: '<svg />',
    },
}));

const clinicHours = (
    dayOfWeek: ClinicWorkingHour['day_of_week'],
    startTime: string,
    endTime: string,
): ClinicWorkingHour => ({
    day_of_week: dayOfWeek,
    is_active: true,
    start_time: startTime,
    end_time: endTime,
});

const departments: DepartmentOption[] = [
    {
        id: 1,
        name: 'Cardiology',
        code: 'CARD',
        is_active: true,
        working_hours: [clinicHours('sunday', '09:00', '17:00')],
    },
    {
        id: 2,
        name: 'Dermatology',
        code: 'DERM',
        is_active: true,
        working_hours: [clinicHours('tuesday', '10:00', '16:00')],
    },
];

const mountModal = () =>
    mount(DoctorFormModal, {
        props: {
            open: true,
            profile: null,
            clinic: {
                id: 1,
                name: 'Main clinic',
            },
            departments,
        },
        global: {
            stubs: {
                Button: {
                    props: ['type'],
                    template:
                        '<button :type="type ?? \'button\'"><slot /></button>',
                },
                Dialog: {
                    props: ['open'],
                    template: '<div><slot /></div>',
                },
                DialogContent: {
                    template: '<div><slot /></div>',
                },
                DialogDescription: {
                    template: '<p><slot /></p>',
                },
                DialogFooter: {
                    template: '<footer><slot /></footer>',
                },
                DialogHeader: {
                    template: '<header><slot /></header>',
                },
                DialogTitle: {
                    template: '<h2><slot /></h2>',
                },
                DoctorWorkingHoursSelector: {
                    props: [
                        'modelValue',
                        'clinicWorkingHours',
                        'hasSelectedDepartment',
                    ],
                    template:
                        '<div data-testid="doctor-working-hours-selector" />',
                },
                Input: {
                    emits: ['update:modelValue'],
                    props: ['modelValue'],
                    template:
                        '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                },
                InputError: {
                    template: '<p />',
                },
                Label: {
                    template: '<label><slot /></label>',
                },
            },
        },
    });

describe('DoctorFormModal', () => {
    it('resets doctor working hours when changing the selected clinic', async () => {
        capturedForms.length = 0;
        const wrapper = mountModal();
        const form = capturedForms[0] as { working_hours: WorkingHour[] };

        await wrapper.find('#doctor_department').setValue('1');
        await nextTick();

        expect(form.working_hours).toEqual([
            {
                day_of_week: 0,
                is_active: false,
                start_time: null,
                end_time: null,
            },
        ]);

        form.working_hours = [
            {
                day_of_week: 0,
                is_active: true,
                start_time: '10:00',
                end_time: '14:00',
            },
        ];

        await wrapper.find('#doctor_department').setValue('2');
        await nextTick();

        expect(form.working_hours).toEqual([
            {
                day_of_week: 2,
                is_active: false,
                start_time: null,
                end_time: null,
            },
        ]);
    });
});
