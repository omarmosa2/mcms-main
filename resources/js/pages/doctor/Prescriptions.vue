<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    Eye,
    FileText,
    Printer,
    Search,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { FilterBar, FilterSearch, FilterSelect } from '@/components/ui/filter';

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
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'مساحة الطبيب', href: '/doctor/workspace' },
            { title: 'الوصفات الطبية', href: '/doctor/prescriptions' },
        ],
    },
});

const search = ref('');
const statusFilter = ref('');

const statusOptions = [
    { label: 'الكل', value: '' },
    { label: 'مسودة', value: 'draft' },
    { label: 'صادرة', value: 'issued' },
    { label: 'مصروفة', value: 'dispensed' },
    { label: 'ملغاة', value: 'canceled' },
];

const formatDate = (date: string | null) => {
    if (!date) return '—';
    const d = new Date(date);

    return d.toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
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
                <p class="mt-1 text-sm text-slate-500">
                    إجمالي {{ prescriptions.total }} وصفة
                </p>
            </div>
        </section>

        <!-- Filters -->
        <FilterBar>
            <FilterSearch v-model="search" placeholder="بحث برقم الوصفة أو اسم المريض..." />
            <FilterSelect v-model="statusFilter" :options="statusOptions" placeholder="الحالة" />
        </FilterBar>

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
                            <div class="mt-2 flex items-center gap-1 text-xs text-slate-500">
                                <CalendarDays class="size-3" />
                                <span>{{ formatDate(rx.issued_at ?? rx.created_at) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 text-xs"
                            @click="window.print()"
                        >
                            <Printer class="size-3.5" />
                            طباعة
                        </Button>
                    </div>
                </div>

                <!-- Medications -->
                <div v-if="rx.items.length > 0" class="border-t border-[#F1F5F9] bg-[#FAFCFE] px-4 py-3">
                    <p class="mb-2 text-xs font-semibold text-slate-500">الأدوية ({{ rx.items.length }})</p>
                    <div class="space-y-1.5">
                        <div
                            v-for="item in rx.items"
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
            <Button
                v-for="link in prescriptions.links"
                :key="link.label"
                variant="ghost"
                size="sm"
                :class="[
                    'h-8 min-w-[2rem] px-2 text-xs',
                    link.active ? 'bg-[#0EA5E9] text-white hover:bg-[#0284C7]' : '',
                ]"
                :disabled="!link.url"
                @click="link.url && goToPage(parseInt(new URL(link.url).searchParams.get('page') ?? '1'))"
                v-html="link.label"
            />
        </nav>
    </div>
</template>
