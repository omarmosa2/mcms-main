import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import DailyScheduleDisplay from '@/pages/daily-schedule/Display.vue';
import DailyScheduleIndex from '@/pages/daily-schedule/Index.vue';

const pageState = vi.hoisted(() => ({
    props: {
        clinic_name: 'Settings Clinic Name',
        branding: {
            company_name: 'Administration Clinic',
            logo_path: null,
        },
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        template: '<span />',
    },
    router: {
        get: vi.fn(),
        reload: vi.fn(),
    },
    usePage: () => pageState,
    usePoll: vi.fn(),
}));

vi.mock('html-to-image', () => ({
    toPng: vi.fn(),
}));

vi.mock('vue-sonner', () => ({
    toast: {
        error: vi.fn(),
        success: vi.fn(),
    },
}));

vi.mock('lucide-vue-next', () => {
    const Icon = {
        template: '<svg />',
    };

    return new Proxy(
        {},
        {
            get: () => Icon,
        },
    );
});

const scheduleData = {
    date: '2026-06-27',
    day_name: 'Saturday',
    day_of_week: 6,
    formatted_date: '27 June 2026',
    branding: {
        company_name: 'Administration Clinic',
        logo_path: null,
    },
    clinic_settings: {
        name: null,
        phone: null,
        address: null,
        logo_path: null,
    },
    clinics: [],
};

const posterStub = {
    props: ['clinicName'],
    template: '<section data-testid="daily-schedule-poster">{{ clinicName }}</section>',
};

describe('daily schedule clinic name', () => {
    it('uses the shared settings clinic name on the index page', () => {
        const wrapper = mount(DailyScheduleIndex, {
            props: {
                scheduleData,
                clinics: [],
                doctors: [],
                filters: {
                    date: '2026-06-27',
                    clinic_id: null,
                    doctor_id: null,
                },
            },
            global: {
                stubs: {
                    Button: true,
                    CalendarDays: true,
                    Card: true,
                    CardContent: true,
                    CardHeader: true,
                    CardTitle: true,
                    DailySchedulePoster: posterStub,
                    Input: true,
                    Label: true,
                },
            },
        });

        expect(wrapper.text()).toContain('Settings Clinic Name');
        expect(wrapper.text()).not.toContain('Administration Clinic');
    });

    it('uses the shared settings clinic name in display mode', () => {
        const wrapper = mount(DailyScheduleDisplay, {
            props: {
                scheduleData,
            },
            global: {
                stubs: {
                    DailySchedulePoster: posterStub,
                },
            },
        });

        expect(wrapper.find('[data-testid="daily-schedule-poster"]').text()).toBe(
            'Settings Clinic Name',
        );
    });
});
