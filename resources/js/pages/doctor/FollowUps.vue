<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    Check,
    ClipboardList,
    Clock,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FilterBar, FilterSelect } from '@/components/ui/filter';
import { useToast } from '@/composables/useToast';

type Patient = {
    id: number;
    first_name: string;
    last_name: string;
    file_number: number;
};

type MedicalRecord = {
    id: number;
    primary_diagnosis: string | null;
};

type FollowUp = {
    id: number;
    follow_up_date: string;
    status: string;
    notes: string | null;
    recommended_action: string | null;
    patient: Patient;
    medical_record: MedicalRecord | null;
};

type PaginationData = {
    data: FollowUp[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
    next_page_url: string | null;
    prev_page_url: string | null;
};

const props = defineProps<{
    follow_ups: PaginationData;
    filters: {
        status: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'مساحة الطبيب', href: '/doctor/workspace' },
            { title: 'المتابعات', href: '/doctor/follow-ups' },
        ],
    },
});

const { success, error: showError } = useToast();

const statusFilter = ref(props.filters.status ?? '');

const statusOptions = [
    { label: 'الكل', value: '' },
    { label: 'مجدول', value: 'scheduled' },
    { label: 'مكتمل', value: 'completed' },
    { label: 'ملغي', value: 'cancelled' },
    { label: 'فائت', value: 'missed' },
];

const formatDate = (date: string) => {
    const d = new Date(date);

    return d.toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getStatusLabel = (status: string) => {
    const statusMap: Record<string, string> = {
        scheduled: 'مجدول',
        completed: 'مكتمل',
        cancelled: 'ملغي',
        missed: 'فائت',
    };

    return statusMap[status] ?? status;
};

const getStatusBadgeClass = (status: string) => {
    const statusMap: Record<string, string> = {
        scheduled: 'bg-blue-50 text-blue-700 border-blue-200',
        completed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        cancelled: 'bg-red-50 text-red-700 border-red-200',
        missed: 'bg-amber-50 text-amber-700 border-amber-200',
    };

    return statusMap[status] ?? 'bg-slate-50 text-slate-700 border-slate-200';
};

const isOverdue = (followUp: FollowUp) => {
    if (followUp.status !== 'scheduled') return false;

    return new Date(followUp.follow_up_date) < new Date();
};

const updateStatus = (followUpId: number, newStatus: string) => {
    router.put(
        `/medical-records/follow-ups/${followUpId}`,
        { status: newStatus },
        {
            preserveScroll: true,
            onSuccess: () => {
                success('تم تحديث حالة المتابعة');
            },
            onError: () => {
                showError('حدث خطأ أثناء تحديث الحالة');
            },
        },
    );
};

const applyFilter = () => {
    const params = new URLSearchParams();

    if (statusFilter.value) params.set('status', statusFilter.value);

    window.location.href = `/doctor/follow-ups?${params.toString()}`;
};

const goToPage = (page: number) => {
    const params = new URLSearchParams();

    if (statusFilter.value) params.set('status', statusFilter.value);
    params.set('page', String(page));

    window.location.href = `/doctor/follow-ups?${params.toString()}`;
};
</script>

<template>
    <Head title="المتابعات" />

    <div class="container-modern space-y-6 py-6" dir="rtl">
        <!-- Header -->
        <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-2 text-sm text-[#0284C7]">
                    <ClipboardList class="size-4" />
                    <span class="font-medium">المتابعات</span>
                </div>
                <h1 class="mt-1 text-2xl font-bold text-[#111827]">متابعاتي</h1>
                <p class="mt-1 text-sm text-slate-500">
                    إجمالي {{ follow_ups.total }} متابعة
                </p>
            </div>
        </section>

        <!-- Filters -->
        <FilterBar>
            <FilterSelect v-model="statusFilter" :options="statusOptions" placeholder="الحالة" @change="applyFilter" />
        </FilterBar>

        <!-- Follow-Ups List -->
        <section class="space-y-3">
            <div
                v-for="followUp in follow_ups.data"
                :key="followUp.id"
                class="card-float overflow-hidden"
                :class="{ 'border-amber-200/80 bg-amber-50/20': isOverdue(followUp) }"
            >
                <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <div
                            class="icon-container"
                            :class="isOverdue(followUp) ? 'bg-amber-50 text-amber-600' : 'bg-[#EAF7FE] text-[#0EA5E9]'"
                        >
                            <component :is="isOverdue(followUp) ? Clock : CalendarDays" class="size-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-slate-900">
                                    {{ followUp.patient.first_name }} {{ followUp.patient.last_name }}
                                </h3>
                                <span
                                    class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium"
                                    :class="getStatusBadgeClass(followUp.status)"
                                >
                                    {{ getStatusLabel(followUp.status) }}
                                </span>
                                <span v-if="isOverdue(followUp)" class="text-xs font-medium text-amber-600">
                                    (متأخر)
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">
                                ملف #{{ followUp.patient.file_number }}
                                <span v-if="followUp.medical_record?.primary_diagnosis" class="text-slate-400 mx-1">•</span>
                                <span v-if="followUp.medical_record?.primary_diagnosis" class="text-slate-500">
                                    {{ followUp.medical_record.primary_diagnosis }}
                                </span>
                            </p>
                            <div class="mt-2 flex items-center gap-1 text-xs text-slate-500">
                                <CalendarDays class="size-3" />
                                <span>{{ formatDate(followUp.follow_up_date) }}</span>
                            </div>
                            <p v-if="followUp.notes" class="mt-2 text-xs text-slate-500 max-w-md">
                                {{ followUp.notes }}
                            </p>
                            <p v-if="followUp.recommended_action" class="mt-1 text-xs text-[#0284C7]">
                                الإجراء: {{ followUp.recommended_action }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            v-if="followUp.status === 'scheduled'"
                            variant="ghost"
                            size="sm"
                            class="h-8 text-xs text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700"
                            @click="updateStatus(followUp.id, 'completed')"
                        >
                            <Check class="size-3.5" />
                            اكتمل
                        </Button>
                        <Button
                            v-if="followUp.status === 'scheduled'"
                            variant="ghost"
                            size="sm"
                            class="h-8 text-xs text-red-600 hover:bg-red-50 hover:text-red-700"
                            @click="updateStatus(followUp.id, 'cancelled')"
                        >
                            <X class="size-3.5" />
                            إلغاء
                        </Button>
                    </div>
                </div>
            </div>

            <div v-if="follow_ups.data.length === 0" class="py-16 text-center">
                <ClipboardList class="mx-auto size-12 text-slate-200 mb-4" />
                <p class="text-sm font-medium text-slate-500">لا توجد متابعات</p>
            </div>
        </section>

        <!-- Pagination -->
        <nav v-if="follow_ups.last_page > 1" class="flex items-center justify-center gap-1">
            <Button
                v-for="link in follow_ups.links"
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
