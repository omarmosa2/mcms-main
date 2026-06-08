<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarClock,
    Clock,
    FileText,
    Search,
    Stethoscope,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Input } from '@/components/ui/input';

type Patient = {
    id: number;
    first_name: string;
    last_name: string;
    file_number: number;
    phone: string | null;
    date_of_birth: string | null;
    gender: string | null;
};

type Appointment = {
    id: number;
    scheduled_for: string;
    status: string;
    appointment_type: string | null;
    duration_minutes: number;
    cost: string | null;
    notes: string | null;
    patient: Patient;
    medical_record_id: number | null;
};

const props = defineProps<{
    appointments: Appointment[];
    date: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'مساحة الطبيب', href: '/doctor/workspace' },
            { title: 'مواعيد اليوم', href: '/doctor/appointments/today' },
        ],
    },
});

const search = ref('');
const statusFilter = ref('');

const statusOptions = [
    { label: 'الكل', value: '' },
    { label: 'قيد الانتظار', value: 'pending' },
    { label: 'مكتمل', value: 'completed' },
];

const filteredAppointments = computed(() => {
    let results = props.appointments;

    if (statusFilter.value === 'pending') {
        results = results.filter((a) => ['scheduled', 'confirmed', 'arrived'].includes(a.status));
    } else if (statusFilter.value === 'completed') {
        results = results.filter((a) => a.status === 'completed');
    }

    if (!search.value) return results;

    const term = search.value.toLowerCase();

    return results.filter((apt) => {
        const fullName = `${apt.patient.first_name} ${apt.patient.last_name}`.toLowerCase();

        return fullName.includes(term) || String(apt.patient.file_number).includes(term);
    });
});

const formatTime = (dateTime: string) => {
    const date = new Date(dateTime);

    return date.toLocaleTimeString('ar-SA', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusLabel = (status: string) => {
    const statusMap: Record<string, string> = {
        scheduled: 'مجدول',
        confirmed: 'مؤكد',
        arrived: 'حضر',
        completed: 'مكتمل',
        canceled: 'ملغي',
        no_show: 'لم يحضر',
    };

    return statusMap[status] ?? status;
};

const getStatusBadgeClass = (status: string) => {
    const statusMap: Record<string, string> = {
        scheduled: 'bg-blue-50 text-blue-700 border-blue-200',
        confirmed: 'bg-sky-50 text-sky-700 border-sky-200',
        arrived: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        completed: 'bg-teal-50 text-teal-700 border-teal-200',
        canceled: 'bg-red-50 text-red-700 border-red-200',
        no_show: 'bg-amber-50 text-amber-700 border-amber-200',
    };

    return statusMap[status] ?? 'bg-slate-50 text-slate-700 border-slate-200';
};

const getVisitTypeLabel = (type: string | null) => {
    if (type === 'first_visit') return 'زيارة أولى';
    if (type === 'review') return 'متابعة';

    return '—';
};

const getGenderLabel = (gender: string | null) => {
    if (gender === 'male') return 'ذكر';
    if (gender === 'female') return 'أنثى';

    return '—';
};

const calculateAge = (dob: string | null) => {
    if (!dob) return null;
    const birthDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }

    return age;
};

const displayDate = computed(() => {
    const date = new Date(props.date);

    return date.toLocaleDateString('ar-SA', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
});

const stats = computed(() => ({
    total: props.appointments.length,
    completed: props.appointments.filter((a) => a.status === 'completed').length,
    pending: props.appointments.filter((a) => ['scheduled', 'confirmed', 'arrived'].includes(a.status)).length,
}));
</script>

<template>
    <Head title="مواعيد اليوم" />

    <div class="container-modern space-y-6 py-6" dir="rtl">
        <!-- Header -->
        <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-2 text-sm text-[#0284C7]">
                    <CalendarClock class="size-4" />
                    <span class="font-medium">مواعيد اليوم</span>
                </div>
                <h1 class="mt-1 text-2xl font-bold text-[#111827]">
                    {{ displayDate }}
                </h1>
            </div>

        <!-- Filters -->
        <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <select
                    v-model="statusFilter"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm"
                >
                    <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
            </div>
            <div class="relative">
                <Search class="absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <Input
                    v-model="search"
                    placeholder="بحث بالاسم أو رقم الملف..."
                    class="h-10 w-64 pr-9"
                />
            </div>
        </section>
        </section>

        <!-- Stats -->
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-[#E2ECF6] bg-white p-3">
                <p class="text-xs text-slate-500">الإجمالي</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ stats.total }}</p>
            </div>
            <div class="rounded-xl border border-[#E2ECF6] bg-white p-3">
                <p class="text-xs text-slate-500">في الانتظار</p>
                <p class="mt-1 text-xl font-bold text-blue-600">{{ stats.pending }}</p>
            </div>
            <div class="rounded-xl border border-[#E2ECF6] bg-white p-3">
                <p class="text-xs text-slate-500">حضر</p>
                <p class="mt-1 text-xl font-bold text-emerald-600">{{ stats.arrived }}</p>
            </div>
            <div class="rounded-xl border border-[#E2ECF6] bg-white p-3">
                <p class="text-xs text-slate-500">مكتمل</p>
                <p class="mt-1 text-xl font-bold text-teal-600">{{ stats.completed }}</p>
            </div>
        </section>

        <!-- Appointments Table -->
        <section class="card-float overflow-hidden">
            <div v-if="filteredAppointments.length > 0" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[#E2ECF6] bg-[#F8FAFC]">
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500">الوقت</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500">المريض</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500">نوع الزيارة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500">الحالة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="apt in filteredAppointments"
                            :key="apt.id"
                            class="border-b border-[#F1F5F9] transition-colors hover:bg-[#F8FAFC]"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Clock class="size-3.5 text-slate-400" />
                                    <span class="font-semibold tabular-nums text-slate-900">{{ formatTime(apt.scheduled_for) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div>
                                    <p class="font-medium text-slate-900">
                                        {{ apt.patient.first_name }} {{ apt.patient.last_name }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        ملف #{{ apt.patient.file_number }}
                                        <span v-if="apt.patient.gender" class="mx-1">•</span>
                                        <span v-if="apt.patient.gender">{{ getGenderLabel(apt.patient.gender) }}</span>
                                        <template v-if="calculateAge(apt.patient.date_of_birth)">
                                            <span class="mx-1">•</span>
                                            {{ calculateAge(apt.patient.date_of_birth) }} سنة
                                        </template>
                                    </p>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-slate-600">{{ getVisitTypeLabel(apt.appointment_type) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                    :class="getStatusBadgeClass(apt.status)"
                                >
                                    {{ getStatusLabel(apt.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <Link
                                        :href="apt.medical_record_id ? `/medical-records/${apt.medical_record_id}` : `/medical-records/create?patient_id=${apt.patient.id}`"
                                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium transition"
                                        :class="apt.status === 'completed'
                                            ? 'bg-teal-50 text-teal-700 hover:bg-teal-100'
                                            : 'bg-[#EAF7FE] text-[#0284C7] hover:bg-[#D7F1FE]'"
                                    >
                                        <component :is="apt.status === 'completed' ? FileText : Stethoscope" class="size-3.5" />
                                        {{ apt.status === 'completed' ? 'عرض السجل' : 'فتح السجل' }}
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="py-16 text-center">
                <CalendarClock class="mx-auto size-12 text-slate-200 mb-4" />
                <p class="text-sm font-medium text-slate-500">
                    {{ search ? 'لا توجد نتائج مطابقة' : 'لا توجد مواعيد اليوم' }}
                </p>
            </div>
        </section>
    </div>
</template>
