<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    Eye,
    FileText,
    Plus,
    Printer,
    Search,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Input } from '@/components/ui/input';

type Patient = {
    id: number;
    first_name: string;
    last_name: string;
    file_number: number;
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

type Prescription = {
    id: number;
    prescription_number: string;
    status: string;
    created_at: string;
    issued_at: string | null;
    diagnosis: string | null;
    notes: string | null;
    patient: Patient;
    items: PrescriptionItem[];
};

type PaginationData = {
    data: Prescription[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
    next_page_url: string | null;
    prev_page_url: string | null;
};

const props = defineProps<{
    prescriptions: PaginationData;
    filters: {
        search: string | null;
        status: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'مساحة الطبيب', href: '/doctor/workspace' },
            { title: 'الوصفات الطبية', href: '/doctor/prescriptions' },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');

const statusOptions = [
    { label: 'الكل', value: '' },
    { label: 'مسودة', value: 'draft' },
    { label: 'صادرة', value: 'issued' },
    { label: 'مصروفة', value: 'dispensed' },
    { label: 'ملغاة', value: 'canceled' },
];

const applyFilters = () => {
    const params = new URLSearchParams();
    if (search.value) params.set('search', search.value);
    if (statusFilter.value) params.set('status', statusFilter.value);
    window.location.href = `/doctor/prescriptions?${params.toString()}`;
};

const formatDate = (date: string | null) => {
    if (!date) return '—';
    const d = new Date(date);
    return d.toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' });
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

const goToPage = (page: number) => {
    const params = new URLSearchParams();
    if (search.value) params.set('search', search.value);
    if (statusFilter.value) params.set('status', statusFilter.value);
    params.set('page', String(page));
    window.location.href = `/doctor/prescriptions?${params.toString()}`;
};
</script>

<template>
    <Head title="الوصفات الطبية" />

    <div class="container-modern space-y-6 py-6" dir="rtl">
        <!-- Header -->
        <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-2 text-sm text-[#0284C7]">
                    <FileText class="size-4" />
                    <span class="font-medium">الوصفات الطبية</span>
                </div>
                <h1 class="mt-1 text-2xl font-bold text-[#111827]">وصفاتي الطبية</h1>
                <p class="mt-1 text-sm text-slate-500">إجمالي {{ prescriptions.total }} وصفة</p>
            </div>

            <Link
                href="/doctor/prescriptions/create"
                class="inline-flex items-center gap-2 rounded-2xl bg-[#0EA5E9] px-4 py-2.5 text-sm font-medium text-white shadow-[0_10px_24px_-16px_rgb(14_165_233_/_0.75)] transition-all duration-200 hover:bg-[#0284C7]"
            >
                <Plus class="size-4" />
                إضافة وصفة طبية
            </Link>
        </section>

        <!-- Filters -->
        <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <select
                    v-model="statusFilter"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm"
                    @change="applyFilters"
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
                    placeholder="بحث برقم الوصفة أو اسم المريض..."
                    class="h-10 w-72 pr-9"
                    @keyup.enter="applyFilters"
                />
            </div>
        </section>

        <!-- Prescriptions List -->
        <section class="space-y-3">
            <div
                v-for="rx in prescriptions.data"
                :key="rx.id"
                class="card-float overflow-hidden"
            >
                <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="icon-container bg-[#EAF7FE] text-[#0EA5E9]">
                            <FileText class="size-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-slate-900">{{ rx.prescription_number }}</h3>
                                <span
                                    class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium"
                                    :class="getStatusBadgeClass(rx.status)"
                                >
                                    {{ getStatusLabel(rx.status) }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">
                                المريض: {{ rx.patient.first_name }} {{ rx.patient.last_name }}
                                <span class="text-slate-400 mx-1">•</span>
                                ملف #{{ rx.patient.file_number }}
                            </p>
                            <p v-if="rx.diagnosis" class="mt-1 text-xs text-slate-500">
                                التشخيص: {{ rx.diagnosis }}
                            </p>
                            <div class="mt-2 flex items-center gap-1 text-xs text-slate-500">
                                <CalendarDays class="size-3" />
                                <span>{{ formatDate(rx.issued_at ?? rx.created_at) }}</span>
                                <span class="mx-1">•</span>
                                <span>{{ rx.items.length }} أدوية</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Link
                            :href="`/doctor/prescriptions/${rx.id}`"
                            class="inline-flex items-center gap-1 rounded-lg bg-[#EAF7FE] px-3 py-1.5 text-xs font-medium text-[#0284C7] transition hover:bg-[#D7F1FE]"
                        >
                            <Eye class="size-3.5" />
                            عرض
                        </Link>
                        <Link
                            :href="`/doctor/prescriptions/${rx.id}/edit`"
                            class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 transition hover:bg-amber-100"
                        >
                            تعديل
                        </Link>
                        <Link
                            :href="`/doctor/prescriptions/${rx.id}/print`"
                            target="_blank"
                            class="inline-flex items-center gap-1 rounded-lg bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100"
                        >
                            <Printer class="size-3.5" />
                            طباعة
                        </Link>
                        <Link
                            :href="`/doctor/prescriptions/${rx.id}/pdf`"
                            class="inline-flex items-center gap-1 rounded-lg bg-teal-50 px-3 py-1.5 text-xs font-medium text-teal-700 transition hover:bg-teal-100"
                        >
                            PDF
                        </Link>
                    </div>
                </div>

                <!-- Medications Preview -->
                <div v-if="rx.items.length > 0" class="border-t border-[#F1F5F9] bg-[#FAFCFE] px-4 py-3">
                    <p class="mb-2 text-xs font-semibold text-slate-500">الأدوية ({{ rx.items.length }})</p>
                    <div class="space-y-1.5">
                        <div
                            v-for="item in rx.items.slice(0, 3)"
                            :key="item.id"
                            class="flex items-center justify-between rounded-lg bg-white px-3 py-2 text-xs"
                        >
                            <div>
                                <span class="font-medium text-slate-900">{{ item.medication_name }}</span>
                                <span class="text-slate-500 mx-1">—</span>
                                <span class="text-slate-600">{{ item.dosage }}</span>
                                <span class="text-slate-400 mx-1">•</span>
                                <span class="text-slate-600">{{ item.frequency }}</span>
                                <span v-if="item.duration" class="text-slate-400 mx-1">•</span>
                                <span v-if="item.duration" class="text-slate-600">{{ item.duration }}</span>
                            </div>
                            <span class="text-slate-400">×{{ item.quantity }}</span>
                        </div>
                        <p v-if="rx.items.length > 3" class="text-xs text-slate-400">
                            و {{ rx.items.length - 3 }} أدوية أخرى...
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="prescriptions.data.length === 0" class="py-16 text-center">
                <FileText class="mx-auto size-12 text-slate-200 mb-4" />
                <p class="text-sm font-medium text-slate-500">لا توجد وصفات طبية</p>
            </div>
        </section>

        <!-- Pagination -->
        <nav v-if="prescriptions.last_page > 1" class="flex items-center justify-center gap-1">
            <button
                v-for="link in prescriptions.links"
                :key="link.label"
                class="h-8 min-w-[2rem] rounded-lg px-2 text-xs transition-colors"
                :class="[
                    link.active
                        ? 'bg-[#0EA5E9] text-white'
                        : link.url
                            ? 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'
                            : 'bg-slate-50 text-slate-300 cursor-not-allowed border border-slate-100',
                ]"
                :disabled="!link.url"
                @click="link.url && goToPage(parseInt(new URL(link.url).searchParams.get('page') ?? '1'))"
                v-html="link.label"
            />
        </nav>
    </div>
</template>
