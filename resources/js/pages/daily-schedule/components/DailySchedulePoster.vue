<script setup lang="ts">
import {
    Activity,
    Bone,
    CalendarCheck2,
    CalendarDays,
    Clock,
    Ear,
    Eye,
    HeartPulse,
    MapPin,
    Phone,
    ShieldCheck,
    SmilePlus,
    Sparkles,
    Stethoscope,
    UsersRound,
} from 'lucide-vue-next';
import type { Component } from 'vue';

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

withDefaults(
    defineProps<{
        scheduleData: ScheduleData;
        clinicName: string;
        logoPath: string | null | undefined;
        displayMode?: boolean;
    }>(),
    {
        displayMode: false,
    },
);

const clinicIcons: Array<{ keywords: string[]; icon: Component }> = [
    { keywords: ['داخل', 'باطن'], icon: Stethoscope },
    { keywords: ['صدر', 'تنفس'], icon: Activity },
    { keywords: ['عظم', 'مفصل', 'جراح'], icon: Bone },
    { keywords: ['هضم', 'معدة'], icon: Activity },
    { keywords: ['نساء', 'نسائ', 'ولادة'], icon: UsersRound },
    { keywords: ['عين', 'عيني'], icon: Eye },
    { keywords: ['جلد'], icon: SmilePlus },
    { keywords: ['اذن', 'أذن', 'انف', 'أنف'], icon: Ear },
    { keywords: ['قلب'], icon: HeartPulse },
    { keywords: ['بول', 'كلية', 'كلى'], icon: Activity },
    { keywords: ['تجميل'], icon: Sparkles },
];

function iconForClinic(clinic: ClinicData): Component {
    const searchableName = `${clinic.name} ${clinic.clinic_type ?? ''}`;
    const match = clinicIcons.find(({ keywords }) =>
        keywords.some((keyword) => searchableName.includes(keyword)),
    );

    return match?.icon ?? Stethoscope;
}

function formatTime(time: string): string {
    if (!time) {
        return '';
    }

    const [hours, minutes] = time.split(':');
    const h = parseInt(hours, 10);
    const m = minutes?.substring(0, 2) ?? '00';
    const period = h >= 12 ? 'م' : 'ص';
    const displayHours = h % 12 || 12;

    return `${displayHours}:${m} ${period}`;
}
</script>

<template>
    <section dir="rtl"
        class="relative isolate overflow-hidden rounded-[2rem] border border-[#d7e4ec] bg-[#fbfdff] text-[#172033] shadow-[0_24px_70px_rgba(20,55,85,0.10)] print:rounded-none print:border-0 print:shadow-none"
        :class="displayMode ? 'min-h-[calc(100vh-4rem)]' : ''">
        <div
            class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_12%_18%,rgba(224,47,137,0.10),transparent_26%),radial-gradient(circle_at_88%_12%,rgba(29,125,233,0.12),transparent_24%),linear-gradient(180deg,#fbfdff_0%,#f3f8fb_100%)]" />
        <div class="pointer-events-none absolute inset-x-0 top-28 -z-10 h-24 opacity-[0.09]" aria-hidden="true">
            <svg viewBox="0 0 1200 160" class="h-full w-full">
                <path
                    d="M0 82 H130 L156 82 L180 42 L220 122 L252 82 H396 L430 82 L454 57 L482 103 L512 82 H724 L756 82 L782 35 L828 129 L860 82 H1200"
                    fill="none" stroke="#1d7de9" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>
        <div class="pointer-events-none absolute top-10 left-8 -z-10 text-7xl font-black text-[#1d7de9]/[0.06]">
            +
        </div>
        <div class="pointer-events-none absolute right-10 bottom-24 -z-10 text-8xl font-black text-[#e02f89]/[0.06]">
            +
        </div>

        <div class="mx-auto flex w-full max-w-7xl flex-col gap-8 px-5 py-6 sm:px-8 lg:px-10"
            :class="displayMode ? 'justify-center lg:py-10' : 'lg:py-8'">
            <header
                class="grid gap-6 rounded-[1.75rem] border border-[#dbe8ef] bg-[#f7fbfd]/90 px-5 py-5 shadow-[0_12px_32px_rgba(29,125,233,0.07)] sm:px-7 lg:grid-cols-[1fr_auto_1fr] lg:items-center">
                <div class="flex items-center gap-4">
                    <div v-if="logoPath"
                        class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-3xl border border-[#d5e4ed] bg-[#fbfdff] p-2 shadow-[0_10px_24px_rgba(23,32,51,0.08)]">
                        <img :src="`/storage/${logoPath}`" :alt="clinicName" class="size-full object-contain" />
                    </div>
                    <div v-else
                        class="flex size-20 shrink-0 items-center justify-center rounded-3xl bg-gradient-to-br from-[#1d7de9] to-[#e02f89] shadow-[0_14px_30px_rgba(29,125,233,0.20)]">
                        <Stethoscope class="size-10 text-[#fbfdff]" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-[#e02f89]">
                            رعايتك أولويتنا لأن صحتك تهمنا
                        </p>
                        <h2 class="mt-1 text-2xl leading-tight font-extrabold text-[#12345a] sm:text-3xl">
                            {{ clinicName }}
                        </h2>
                        <p v-if="scheduleData.clinic_settings.name" class="mt-1 text-sm font-medium text-[#66768a]">
                            {{ scheduleData.clinic_settings.name }}
                        </p>
                    </div>
                </div>

                <div class="text-center">
                    <h1 class="text-4xl leading-tight font-black text-[#12345a] sm:text-5xl">
                        العيادات المناوبة
                    </h1>
                    <div
                        class="mt-3 inline-flex items-center justify-center gap-2 rounded-full border border-[#f0b7d3] bg-[#fff2f8] px-5 py-2 text-base font-bold text-[#b01f67] shadow-[0_8px_18px_rgba(224,47,137,0.10)]">
                        <CalendarDays class="size-5" />
                        <span>ليوم {{ scheduleData.day_name }}</span>
                    </div>
                </div>

                <div class="flex justify-start lg:justify-end">
                    <div class="rounded-3xl border border-[#cfe2f3] bg-[#eef7ff] px-5 py-4 text-start">
                        <p class="text-xs font-bold text-[#5f7891]">
                            تاريخ اليوم
                        </p>
                        <p class="mt-1 text-lg font-extrabold text-[#12345a] tabular-nums">
                            {{ scheduleData.formatted_date }}
                        </p>
                    </div>
                </div>
            </header>

            <div v-if="scheduleData.clinics.length > 0" class="grid grid-cols-1 gap-5 lg:grid-cols-2"
                :class="displayMode ? 'xl:gap-6' : ''">
                <article v-for="clinic in scheduleData.clinics" :key="clinic.id"
                    class="flex min-h-[220px] flex-col rounded-[1.5rem] border border-[#d9e6ee] bg-[#fbfdff] p-5 shadow-[0_14px_34px_rgba(18,52,90,0.08)]">
                    <div class="flex items-start gap-4">
                        <div
                            class="flex size-16 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#1d7de9] via-[#2a9ce8] to-[#e02f89] shadow-[0_14px_28px_rgba(29,125,233,0.18)]">
                            <component :is="iconForClinic(clinic)" class="size-8 text-[#fbfdff]" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-2xl leading-tight font-extrabold text-[#12345a]">
                                        {{ clinic.name }}
                                    </h3>
                                    <p v-if="clinic.clinic_type" class="mt-1 text-sm font-semibold text-[#6a7a8c]">
                                        {{ clinic.clinic_type }}
                                    </p>
                                </div>
                                <div v-if="clinic.clinic_start_time"
                                    class="inline-flex items-center gap-2 rounded-full border border-[#cae2f7] bg-[#eff8ff] px-3 py-2 text-sm font-bold text-[#195c9d] tabular-nums">
                                    <Clock class="size-4" />
                                    <span>
                                        {{
                                            formatTime(clinic.clinic_start_time)
                                        }}
                                        -
                                        {{
                                            formatTime(clinic.clinic_end_time!)
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-1 flex-col gap-3">
                        <div v-for="doctor in clinic.doctors" :key="doctor.doctor_id"
                            class="rounded-2xl border border-[#e1ebf1] bg-[#f7fbfd] p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div
                                        class="flex size-11 shrink-0 items-center justify-center rounded-full bg-[#12345a] text-base font-extrabold text-[#fbfdff]">
                                        {{ doctor.doctor_name?.charAt(0) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-lg leading-tight font-extrabold text-[#172033]">
                                            {{ doctor.doctor_name }}
                                        </p>
                                        <p v-if="doctor.specialty" class="mt-1 text-sm font-semibold text-[#6b7888]">
                                            {{ doctor.specialty }}
                                        </p>
                                    </div>
                                </div>
                                <div
                                    class="inline-flex w-fit items-center gap-2 rounded-full border border-[#f2bfd8] bg-[#fff3f8] px-4 py-2 text-sm font-extrabold text-[#af1e66] tabular-nums">
                                    <Clock class="size-4" />
                                    <span>
                                        {{ formatTime(doctor.start_time) }}
                                        -
                                        {{ formatTime(doctor.end_time) }}
                                    </span>
                                </div>
                            </div>
                            <div v-if="doctor.unavailable_periods.length > 0"
                                class="mt-3 space-y-1 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                                <div v-for="period in doctor.unavailable_periods"
                                    :key="`${period.start_time}-${period.end_time}`"
                                    class="flex items-center justify-between gap-2">
                                    <span>إجازة ساعية</span>
                                    <span class="tabular-nums">
                                        {{ formatTime(period.start_time) }}
                                        -
                                        {{ formatTime(period.end_time) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else
                class="flex flex-col items-center justify-center rounded-[1.5rem] border border-dashed border-[#c6d7e2] bg-[#f7fbfd] py-20 text-center">
                <div class="flex size-20 items-center justify-center rounded-full bg-[#eff8ff]">
                    <CalendarDays class="size-10 text-[#1d7de9]" />
                </div>
                <h3 class="mt-4 text-2xl font-extrabold text-[#12345a]">
                    لا توجد عيادات مناوبة اليوم
                </h3>
                <p class="mt-2 text-base font-medium text-[#6a7a8c]">
                    لا توجد عيادات أو أطباء مسجلين للدوام في هذا اليوم.
                </p>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <div class="flex items-center gap-3 rounded-2xl border border-[#dbe8ef] bg-[#f7fbfd] px-4 py-3">
                    <CalendarCheck2 class="size-6 text-[#1d7de9]" />
                    <span class="font-bold text-[#12345a]">احجز موعدك الآن</span>
                </div>
                <div class="flex items-center gap-3 rounded-2xl border border-[#dbe8ef] bg-[#f7fbfd] px-4 py-3">
                    <ShieldCheck class="size-6 text-[#1a9e7a]" />
                    <span class="font-bold text-[#12345a]">رعايتك بأمان</span>
                </div>
                <div class="flex items-center gap-3 rounded-2xl border border-[#dbe8ef] bg-[#f7fbfd] px-4 py-3">
                    <UsersRound class="size-6 text-[#e02f89]" />
                    <span class="font-bold text-[#12345a]">
                        أوقات الدوام حسب دوام كل طبيب
                    </span>
                </div>
            </div>

            <footer class="grid gap-3 rounded-[1.5rem] bg-[#12345a] p-4 text-[#fbfdff] md:grid-cols-2">
                <div class="flex items-center gap-3 rounded-2xl bg-[#fbfdff]/10 px-4 py-3">
                    <Phone class="size-6 text-[#ffb7d6]" />
                    <div>
                        <p class="text-xs font-bold text-[#d9e6ee]">
                            للتواصل والاستفسار
                        </p>
                        <p dir="ltr" class="text-lg font-extrabold tabular-nums text-center">
                            {{
                                scheduleData.clinic_settings.phone || '+963 968 842 338'
                            }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-2xl bg-[#fbfdff]/10 px-4 py-3">
                    <MapPin class="size-6 text-[#9dd3ff]" />
                    <div>
                        <p class="text-xs font-bold text-[#d9e6ee]">العنوان</p>
                        <p class="text-lg font-extrabold">
                            {{
                                scheduleData.clinic_settings.address ||
                                'الأتارب الطريق العام مفرق المدرسة الشمالية'
                            }}
                        </p>
                    </div>
                </div>
            </footer>
        </div>
    </section>
</template>
