<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    FileText,
    Printer,
    Stethoscope,
} from 'lucide-vue-next';
import { computed } from 'vue';

type Patient = {
    id: number;
    first_name: string;
    last_name: string;
    file_number: number;
    phone: string | null;
    date_of_birth: string | null;
    gender: string | null;
};

type PrescriptionItem = {
    id: number;
    medication_name: string;
    dosage: string;
    frequency: string;
    duration: string | null;
    quantity: number;
    instructions: string | null;
};

type Prescriber = {
    name: string;
    specialty: string | null;
    license_number: string | null;
};

type PrescriptionData = {
    id: number;
    prescription_number: string;
    status: string;
    issued_at: string | null;
    diagnosis: string | null;
    notes: string | null;
    patient: Patient;
    prescriber: Prescriber;
    items: PrescriptionItem[];
    medical_record: { id: number; primary_diagnosis: string | null } | null;
};

type ClinicInfo = {
    name: string | null;
    logo_path: string | null;
};

const props = defineProps<{
    prescription: PrescriptionData;
    clinic: ClinicInfo;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'مساحة الطبيب', href: '/doctor/workspace' },
            { title: 'الوصفات الطبية', href: '/doctor/prescriptions' },
        ],
    },
});

const formatDate = (date: string | null) => {
    if (!date) return '—';
    const d = new Date(date);
    return d.toLocaleDateString('ar-SA', { year: 'numeric', month: 'long', day: 'numeric' });
};

const formatDateTime = (date: string | null) => {
    if (!date) return '—';
    const d = new Date(date);
    return d.toLocaleString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const getStatusLabel = (status: string) => {
    const statusMap: Record<string, string> = {
        draft: 'مسودة',
        issued: 'صادرة',
        dispensed: 'مصروفة',
        canceled: 'ملغاة',
    };
    return statusMap[status] ?? status;
};

const getStatusBadgeClass = (status: string) => {
    const statusMap: Record<string, string> = {
        draft: 'bg-slate-50 text-slate-700 border-slate-200',
        issued: 'bg-blue-50 text-blue-700 border-blue-200',
        dispensed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        canceled: 'bg-red-50 text-red-700 border-red-200',
    };
    return statusMap[status] ?? 'bg-slate-50 text-slate-700 border-slate-200';
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
</script>

<template>
    <Head :title="`وصفة ${prescription.prescription_number}`" />

    <div class="container-modern space-y-6 py-6" dir="rtl">
        <!-- Header -->
        <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Link href="/doctor/prescriptions" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" />
                    العودة
                </Link>
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10">
                        <FileText class="size-5 text-primary" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-[#111827]">{{ prescription.prescription_number }}</h1>
                        <p class="text-sm text-muted-foreground">{{ formatDateTime(prescription.issued_at) }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Link
                    :href="`/doctor/prescriptions/${prescription.id}/edit`"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-100"
                >
                    تعديل
                </Link>
                <Link
                    :href="`/doctor/prescriptions/${prescription.id}/print`"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    <Printer class="size-4" />
                    طباعة
                </Link>
                <Link
                    :href="`/doctor/prescriptions/${prescription.id}/pdf`"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-[#0EA5E9] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#0284C7]"
                >
                    تصدير PDF
                </Link>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Patient Info -->
                <div class="rounded-2xl border border-[#E2ECF6] bg-white p-5">
                    <h3 class="text-sm font-semibold text-slate-900 border-b border-border/50 pb-3 mb-4">بيانات المريض</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-500">الاسم:</span>
                            <span class="text-sm font-medium">{{ prescription.patient.first_name }} {{ prescription.patient.last_name }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-500">رقم الملف:</span>
                            <span class="text-sm font-medium">#{{ prescription.patient.file_number }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-500">العمر:</span>
                            <span class="text-sm font-medium">{{ calculateAge(prescription.patient.date_of_birth) ? `${calculateAge(prescription.patient.date_of_birth)} سنة` : '—' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-500">الجنس:</span>
                            <span class="text-sm font-medium">{{ getGenderLabel(prescription.patient.gender) }}</span>
                        </div>
                        <div v-if="prescription.patient.phone" class="flex items-center gap-2">
                            <span class="text-xs text-slate-500">الهاتف:</span>
                            <span class="text-sm font-medium">{{ prescription.patient.phone }}</span>
                        </div>
                    </div>
                </div>

                <!-- Diagnosis -->
                <div v-if="prescription.diagnosis" class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5">
                    <h3 class="text-sm font-semibold text-amber-800 border-b border-amber-200 pb-3 mb-3">التشخيص</h3>
                    <p class="text-sm text-amber-900">{{ prescription.diagnosis }}</p>
                </div>

                <!-- Medications Table -->
                <div class="rounded-2xl border border-[#E2ECF6] bg-white p-5">
                    <h3 class="text-sm font-semibold text-slate-900 border-b border-border/50 pb-3 mb-4">
                        الأدوية ({{ prescription.items.length }})
                    </h3>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#E2ECF6] bg-[#F8FAFC]">
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">#</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">اسم الدواء</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">الجرعة</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">التكرار</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">المدة</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">الكمية</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">تعليمات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, index) in prescription.items"
                                    :key="item.id"
                                    class="border-b border-[#F1F5F9] hover:bg-[#F8FAFC]"
                                >
                                    <td class="px-3 py-2.5 text-slate-500">{{ index + 1 }}</td>
                                    <td class="px-3 py-2.5 font-medium text-slate-900">{{ item.medication_name }}</td>
                                    <td class="px-3 py-2.5 text-slate-600">{{ item.dosage }}</td>
                                    <td class="px-3 py-2.5 text-slate-600">{{ item.frequency }}</td>
                                    <td class="px-3 py-2.5 text-slate-600">{{ item.duration ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-slate-600">{{ item.quantity }}</td>
                                    <td class="px-3 py-2.5 text-slate-500 text-xs">{{ item.instructions ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Notes -->
                <div v-if="prescription.notes" class="rounded-2xl border border-[#E2ECF6] bg-white p-5">
                    <h3 class="text-sm font-semibold text-slate-900 border-b border-border/50 pb-3 mb-3">ملاحظات الطبيب</h3>
                    <p class="text-sm text-slate-600">{{ prescription.notes }}</p>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Status Card -->
                <div class="rounded-2xl border border-[#E2ECF6] bg-white p-5">
                    <h3 class="text-sm font-semibold text-slate-900 mb-3">الحالة</h3>
                    <span
                        class="inline-flex items-center rounded-full border px-3 py-1 text-sm font-medium"
                        :class="getStatusBadgeClass(prescription.status)"
                    >
                        {{ getStatusLabel(prescription.status) }}
                    </span>
                </div>

                <!-- Doctor Info -->
                <div class="rounded-2xl border border-[#E2ECF6] bg-white p-5">
                    <h3 class="text-sm font-semibold text-slate-900 mb-3">الطبيب</h3>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <Stethoscope class="size-4 text-slate-400" />
                            <span class="text-sm font-medium">{{ prescription.prescriber.name }}</span>
                        </div>
                        <p v-if="prescription.prescriber.specialty" class="text-xs text-slate-500 ms-6">
                            {{ prescription.prescriber.specialty }}
                        </p>
                        <p v-if="prescription.prescriber.license_number" class="text-xs text-slate-500 ms-6">
                            ترخيص: {{ prescription.prescriber.license_number }}
                        </p>
                    </div>
                </div>

                <!-- Clinic Info -->
                <div class="rounded-2xl border border-[#E2ECF6] bg-white p-5">
                    <h3 class="text-sm font-semibold text-slate-900 mb-3">العيادة</h3>
                    <p class="text-sm font-medium text-slate-700">{{ clinic.name ?? '—' }}</p>
                </div>

                <!-- Medical Record Link -->
                <div v-if="prescription.medical_record" class="rounded-2xl border border-[#E2ECF6] bg-white p-5">
                    <h3 class="text-sm font-semibold text-slate-900 mb-3">السجل الطبي</h3>
                    <Link
                        :href="`/medical-records/${prescription.medical_record.id}`"
                        class="inline-flex items-center gap-1.5 text-sm text-[#0284C7] hover:underline"
                    >
                        <FileText class="size-4" />
                        عرض السجل الطبي
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
