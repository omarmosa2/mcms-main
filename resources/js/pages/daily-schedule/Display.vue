<script setup lang="ts">
import { Head, usePoll, usePage } from '@inertiajs/vue3';
import { CalendarDays, Clock, Stethoscope } from 'lucide-vue-next';
import { computed } from 'vue';

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
const clinicName = computed(
    () =>
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

function formatTime(time: string): string {
    if (!time) {
        return '';
    }

    const [hours, minutes] = time.split(':');
    const h = parseInt(hours, 10);
    const m = minutes?.substring(0, 2) ?? '00';
    const period = h >= 12 ? 'مساءً' : 'صباحاً';
    const displayHours = h % 12 || 12;

    return `${displayHours}:${m} ${period}`;
}
</script>

<template>
    <Head title="العيادات المتوفرة اليوم - وضع العرض" />

    <div
        class="min-h-screen bg-gradient-to-br from-[#0F172A] via-[#1E293B] to-[#0F172A] p-8"
        dir="rtl"
    >
        <div class="mx-auto max-w-7xl">
            <header
                class="mb-10 overflow-hidden rounded-3xl bg-gradient-to-l from-[#0EA5E9] via-[#0284C7] to-[#075985] p-8 shadow-2xl"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-6">
                        <div
                            v-if="logoPath"
                            class="flex size-24 items-center justify-center overflow-hidden rounded-2xl border-4 border-white/20 bg-white shadow-xl"
                        >
                            <img
                                :src="`/storage/${logoPath}`"
                                :alt="clinicName"
                                class="size-full object-contain"
                            />
                        </div>
                        <div
                            v-else
                            class="flex size-24 items-center justify-center rounded-2xl border-4 border-white/20 bg-white/10 shadow-xl backdrop-blur-sm"
                        >
                            <Stethoscope class="size-12 text-white" />
                        </div>
                        <div>
                            <h1
                                class="text-4xl font-bold text-white drop-shadow-lg"
                            >
                                {{ clinicName }}
                            </h1>
                            <p
                                class="mt-2 text-2xl font-semibold text-white/90"
                            >
                                جدول العيادات المتوفرة اليوم
                            </p>
                        </div>
                    </div>
                    <div class="text-left">
                        <div
                            class="rounded-2xl border-2 border-white/20 bg-white/10 px-8 py-4 backdrop-blur-sm"
                        >
                            <div class="flex items-center gap-3">
                                <CalendarDays class="size-10 text-white" />
                                <div>
                                    <p class="text-3xl font-bold text-white">
                                        {{ scheduleData.day_name }}
                                    </p>
                                    <p class="text-lg text-white/80">
                                        {{ scheduleData.formatted_date }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div
                v-if="scheduleData.clinics.length > 0"
                class="grid gap-6 md:grid-cols-2 xl:grid-cols-3"
            >
                <div
                    v-for="clinic in scheduleData.clinics"
                    :key="clinic.id"
                    class="group overflow-hidden rounded-3xl border border-white/10 bg-white/5 shadow-2xl backdrop-blur-md transition-all duration-300 hover:scale-[1.02] hover:border-[#0EA5E9]/50 hover:bg-white/10"
                >
                    <div
                        class="bg-gradient-to-l from-[#0EA5E9] to-[#0284C7] p-6"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex size-14 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm"
                            >
                                <Stethoscope class="size-7 text-white" />
                            </div>
                            <div class="flex-1">
                                <h2 class="text-2xl font-bold text-white">
                                    {{ clinic.name }}
                                </h2>
                                <span
                                    v-if="clinic.clinic_type"
                                    class="text-sm text-white/80"
                                    >{{ clinic.clinic_type }}</span
                                >
                            </div>
                        </div>
                        <div
                            v-if="clinic.clinic_start_time"
                            class="mt-4 flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2 text-base text-white backdrop-blur-sm"
                        >
                            <Clock class="size-5" />
                            <span class="font-medium tabular-nums"
                                >{{ formatTime(clinic.clinic_start_time) }} -
                                {{ formatTime(clinic.clinic_end_time!) }}</span
                            >
                        </div>
                    </div>

                    <div class="p-5">
                        <div class="space-y-3">
                            <div
                                v-for="doctor in clinic.doctors"
                                :key="doctor.doctor_id"
                                class="rounded-2xl border border-white/10 bg-white/5 p-4 transition-all hover:border-[#0EA5E9]/30 hover:bg-white/10"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex size-12 items-center justify-center rounded-full bg-gradient-to-br from-[#0EA5E9] to-[#075985] shadow-lg"
                                        >
                                            <span
                                                class="text-lg font-bold text-white"
                                                >{{
                                                    doctor.doctor_name?.charAt(
                                                        0,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                        <div>
                                            <p
                                                class="text-lg font-bold text-white"
                                            >
                                                {{ doctor.doctor_name }}
                                            </p>
                                            <p
                                                v-if="doctor.specialty"
                                                class="text-sm text-white/70"
                                            >
                                                {{ doctor.specialty }}
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="flex items-center gap-2 rounded-xl bg-[#0EA5E9]/20 px-4 py-2 text-sm font-bold text-[#7DD3FC] backdrop-blur-sm"
                                    >
                                        <Clock class="size-4" />
                                        <span class="tabular-nums">
                                            {{
                                                formatTime(doctor.start_time)
                                            }}
                                            - {{ formatTime(doctor.end_time) }}
                                        </span>
                                    </div>
                                </div>
                                <div
                                    v-if="doctor.unavailable_periods.length > 0"
                                    class="mt-3 rounded-xl border border-amber-300/30 bg-amber-300/10 px-3 py-2 text-sm text-amber-100"
                                >
                                    <div
                                        v-for="period in doctor.unavailable_periods"
                                        :key="`${period.start_time}-${period.end_time}`"
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <span>إجازة ساعية</span>
                                        <span class="tabular-nums"
                                            >{{
                                                formatTime(period.start_time)
                                            }}
                                            -
                                            {{
                                                formatTime(period.end_time)
                                            }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="flex flex-col items-center justify-center rounded-3xl border border-white/10 bg-white/5 py-24 text-center shadow-2xl backdrop-blur-md"
            >
                <div
                    class="flex size-28 items-center justify-center rounded-full bg-gradient-to-br from-[#0EA5E9]/20 to-[#075985]/20 backdrop-blur-sm"
                >
                    <CalendarDays class="size-14 text-[#0EA5E9]" />
                </div>
                <h3 class="mt-6 text-3xl font-bold text-white">
                    لا توجد عيادات متوفرة اليوم
                </h3>
                <p class="mt-3 text-lg text-white/70">
                    لا توجد عيادات أو أطباء مسجلين للدوام في هذا اليوم.
                </p>
            </div>

            <footer
                v-if="
                    scheduleData.clinic_settings.phone ||
                    scheduleData.clinic_settings.address
                "
                class="mt-10 rounded-2xl border border-white/10 bg-white/5 p-6 text-center backdrop-blur-md"
            >
                <p
                    v-if="scheduleData.clinic_settings.phone"
                    class="text-lg font-medium text-white/90"
                >
                    هاتف: {{ scheduleData.clinic_settings.phone }}
                </p>
                <p
                    v-if="scheduleData.clinic_settings.address"
                    class="mt-1 text-white/70"
                >
                    {{ scheduleData.clinic_settings.address }}
                </p>
            </footer>
        </div>
    </div>
</template>
