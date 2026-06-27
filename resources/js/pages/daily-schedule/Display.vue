<script setup lang="ts">
import { Head, usePage, usePoll } from '@inertiajs/vue3';
import { computed } from 'vue';
import DailySchedulePoster from './components/DailySchedulePoster.vue';

type DoctorSchedule = {
    doctor_id: number;
    doctor_name: string | null;
    specialty: string | null;
    start_time: string;
    end_time: string;
    available_periods: Array<{ start_time: string; end_time: string }>;
    unavailable_periods: Array<{
        start_time: string;
        end_time: string;
        reason: string | null;
    }>;
};

type ClinicData = {
    id: number;
    name: string;
    clinic_type: string | null;
    clinic_start_time: string | null;
    clinic_end_time: string | null;
    doctors: DoctorSchedule[];
};

type ScheduleData = {
    date: string;
    day_name: string;
    day_of_week: number;
    formatted_date: string;
    branding: {
        company_name: string | null;
        logo_path: string | null;
    };
    clinic_settings: {
        name: string | null;
        phone: string | null;
        address: string | null;
        logo_path: string | null;
    };
    clinics: ClinicData[];
};

const props = defineProps<{
    scheduleData: ScheduleData;
}>();

const page = usePage();

const branding = computed(
    () =>
        page.props.branding as {
            company_name: string | null;
            logo_path: string | null;
        },
);
const sharedClinicName = computed(() => {
    const clinicName = page.props.clinic_name;

    return typeof clinicName === 'string' && clinicName.trim() !== ''
        ? clinicName
        : null;
});
const clinicName = computed(
    () =>
        sharedClinicName.value ||
        props.scheduleData.branding.company_name ||
        branding.value.company_name ||
        'المجمع الطبي',
);
const logoPath = computed(
    () =>
        props.scheduleData.branding.logo_path ||
        props.scheduleData.clinic_settings.logo_path ||
        branding.value.logo_path,
);

usePoll(60000);
</script>

<template>
    <Head title="العيادات المناوبة اليوم - وضع العرض" />

    <main class="min-h-screen bg-[#eef5f8] p-4 sm:p-6 lg:p-8" dir="rtl">
        <DailySchedulePoster
            :schedule-data="scheduleData"
            :clinic-name="clinicName"
            :logo-path="logoPath"
            display-mode
        />
    </main>
</template>
