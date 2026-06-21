<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Building2,
    CalendarDays,
    Clock,
    Mail,
    Phone,
    Shield,
    Stethoscope,
    UserCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';

type DoctorInfo = {
    name: string;
    email: string;
    specialty: string | null;
    license_number: string | null;
    phone: string | null;
    gender: string | null;
    bio: string | null;
    status: string | null;
    consultation_duration_minutes: number | null;
};

type ClinicInfo = {
    name: string | null;
    specialty: string | null;
};

type WorkSchedule = Array<{
    day_of_week: number;
    start_time: string;
    end_time: string;
    is_available: boolean;
}>;

const props = defineProps<{
    doctor: DoctorInfo;
    clinic: ClinicInfo;
    work_schedule: WorkSchedule;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'مساحة الطبيب', href: '/doctor/workspace' },
            { title: 'ملفي الشخصي', href: '/doctor/profile' },
        ],
    },
});

const dayNames = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

const statusLabel = computed(() => {
    const statusMap: Record<string, string> = {
        active: 'نشط',
        on_leave: 'في إجازة',
        inactive: 'غير نشط',
    };

    return props.doctor.status ? (statusMap[props.doctor.status] ?? props.doctor.status) : '—';
});

const statusBadgeClass = computed(() => {
    const statusMap: Record<string, string> = {
        active: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        on_leave: 'bg-amber-50 text-amber-700 border-amber-200',
        inactive: 'bg-red-50 text-red-700 border-red-200',
    };

    return props.doctor.status ? (statusMap[props.doctor.status] ?? 'bg-slate-50 text-slate-700 border-slate-200') : 'bg-slate-50 text-slate-700 border-slate-200';
});

const genderLabel = computed(() => {
    if (props.doctor.gender === 'male') return 'ذكر';
    if (props.doctor.gender === 'female') return 'أنثى';

    return '—';
});

const sortedSchedule = computed(() => {
    if (!props.work_schedule || !Array.isArray(props.work_schedule)) return [];

    return [...props.work_schedule].sort((a, b) => a.day_of_week - b.day_of_week);
});

const formatTime = (time: string) => {
    return time.substring(0, 5);
};
</script>

<template>
    <Head title="ملفي الشخصي" />

    <div class="container-modern space-y-6 py-6" dir="rtl">
        <!-- Header -->
        <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-2 text-sm text-[#0284C7]">
                    <UserCircle class="size-4" />
                    <span class="font-medium">الملف الشخصي</span>
                </div>
                <h1 class="mt-1 text-2xl font-bold text-[#111827]">د. {{ doctor.name }}</h1>
            </div>

            <Link
                href="/settings/security"
                class="inline-flex items-center gap-1.5 rounded-2xl border border-[#DDE9F3] bg-white px-4 py-2 text-sm font-medium text-[#47677F] transition-all duration-200 hover:bg-[#F7FBFE] hover:text-[#075985]"
            >
                <Shield class="size-4" />
                تغيير كلمة المرور
            </Link>
        </section>

        <section class="grid gap-6 lg:grid-cols-3">
            <!-- Personal Info -->
            <div class="card-float lg:col-span-2">
                <h2 class="mb-4 text-sm font-semibold text-slate-900">المعلومات الشخصية</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex items-center gap-3 rounded-xl bg-[#F8FAFC] p-3">
                        <UserCircle class="size-5 text-slate-400" />
                        <div>
                            <p class="text-xs text-slate-500">الاسم</p>
                            <p class="text-sm font-medium text-slate-900">{{ doctor.name }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl bg-[#F8FAFC] p-3">
                        <Mail class="size-5 text-slate-400" />
                        <div>
                            <p class="text-xs text-slate-500">البريد الإلكتروني</p>
                            <p class="text-sm font-medium text-slate-900">{{ doctor.email }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl bg-[#F8FAFC] p-3">
                        <Stethoscope class="size-5 text-slate-400" />
                        <div>
                            <p class="text-xs text-slate-500">التخصص</p>
                            <p class="text-sm font-medium text-slate-900">{{ doctor.specialty ?? '—' }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl bg-[#F8FAFC] p-3">
                        <Shield class="size-5 text-slate-400" />
                        <div>
                            <p class="text-xs text-slate-500">رقم الترخيص</p>
                            <p class="text-sm font-medium text-slate-900">{{ doctor.license_number ?? '—' }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl bg-[#F8FAFC] p-3">
                        <Phone class="size-5 text-slate-400" />
                        <div>
                            <p class="text-xs text-slate-500">الهاتف</p>
                            <p class="text-sm font-medium text-slate-900">{{ doctor.phone ?? '—' }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl bg-[#F8FAFC] p-3">
                        <UserCircle class="size-5 text-slate-400" />
                        <div>
                            <p class="text-xs text-slate-500">الجنس</p>
                            <p class="text-sm font-medium text-slate-900">{{ genderLabel }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl bg-[#F8FAFC] p-3">
                        <Clock class="size-5 text-slate-400" />
                        <div>
                            <p class="text-xs text-slate-500">مدة الاستشارة</p>
                            <p class="text-sm font-medium text-slate-900">
                                {{ doctor.consultation_duration_minutes ? `${doctor.consultation_duration_minutes} دقيقة` : '—' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl bg-[#F8FAFC] p-3">
                        <div>
                            <p class="text-xs text-slate-500">الحالة</p>
                            <span
                                class="mt-0.5 inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                :class="statusBadgeClass"
                            >
                                {{ statusLabel }}
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="doctor.bio" class="mt-4 rounded-xl bg-[#F8FAFC] p-3">
                    <p class="text-xs text-slate-500 mb-1">نبذة</p>
                    <p class="text-sm text-slate-700">{{ doctor.bio }}</p>
                </div>
            </div>

            <!-- Clinic Info -->
            <div class="card-float">
                <h2 class="mb-4 text-sm font-semibold text-slate-900">العيادة</h2>
                <div class="space-y-3">
                    <div class="flex items-center gap-3 rounded-xl bg-[#EAF7FE] p-3">
                        <Building2 class="size-5 text-[#0EA5E9]" />
                        <div>
                            <p class="text-xs text-[#0284C7]">اسم العيادة</p>
                            <p class="text-sm font-semibold text-[#075985]">{{ clinic.name ?? '—' }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl bg-[#F8FAFC] p-3">
                        <Stethoscope class="size-5 text-slate-400" />
                        <div>
                            <p class="text-xs text-slate-500">التخصص</p>
                            <p class="text-sm font-medium text-slate-900">{{ clinic.specialty ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Work Schedule -->
        <section v-if="sortedSchedule.length > 0" class="card-float">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">جدول العمل</h2>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="schedule in sortedSchedule"
                    :key="schedule.day_of_week"
                    class="rounded-xl border p-3"
                    :class="schedule.is_available ? 'border-[#E2ECF6] bg-white' : 'border-slate-100 bg-slate-50 opacity-60'"
                >
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-900">{{ dayNames[schedule.day_of_week] }}</p>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="schedule.is_available ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                        >
                            {{ schedule.is_available ? 'متاح' : 'غير متاح' }}
                        </span>
                    </div>
                    <p v-if="schedule.is_available" class="mt-1 text-xs text-slate-500 tabular-nums">
                        {{ formatTime(schedule.start_time) }} — {{ formatTime(schedule.end_time) }}
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
