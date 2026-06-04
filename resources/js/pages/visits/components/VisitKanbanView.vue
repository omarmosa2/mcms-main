<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import VisitController from '@/actions/App/Http/Controllers/Visits/VisitController';
import { Button } from '@/components/ui/button';
import { visitStatusClass, visitStatusDotClass, visitStatusLabel, formatDateTime } from './helpers';
import type { KanbanColumn, Visit } from './types';

const props = defineProps<{
    visits: Visit[];
    kanbanColumns: KanbanColumn[];
    canViewVisit: boolean;
    canEditVisit: boolean;
    canTransitionVisit: boolean;
    canStart: boolean;
}>();

const emit = defineEmits<{
    viewVisit: [visit: Visit];
    editVisit: [visit: Visit];
    deleteVisit: [visit: Visit];
}>();

const getKanbanVisits = (status: string) => {
    return props.visits.filter((v) => v.status === status);
};

const isDoctorAssigned = (visit: Visit): boolean => {
    return visit.doctor_id !== null;
};
</script>

<template>
    <div class="grid gap-4 md:grid-cols-3">
        <div
            v-for="col in kanbanColumns"
            :key="col.key"
            class="flex flex-col rounded-xl border border-slate-100/80 bg-slate-50/30"
        >
            <div class="sticky top-0 flex items-center justify-between rounded-t-xl border-b border-slate-100/80 bg-white px-3 py-2.5">
                <div class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full" :class="col.dotColor"></span>
                    <h3 class="text-sm font-semibold text-slate-700">{{ col.label }}</h3>
                </div>
                <span class="rounded-md bg-slate-50 px-2 py-0.5 text-xs font-bold tabular-nums text-slate-500">
                    {{ getKanbanVisits(col.key).length }}
                </span>
            </div>

            <div class="flex-1 space-y-2 p-3">
                <div
                    v-for="visit in getKanbanVisits(col.key)"
                    :key="visit.id"
                    class="rounded-lg border border-slate-100/80 bg-white p-3 transition-all hover:border-[#0EA5E9]/20 hover:shadow-sm"
                    :class="{ 'border-r-2 border-r-[#0EA5E9]': isDoctorAssigned(visit) }"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <span class="font-mono text-xs font-medium tracking-wide text-slate-600">{{ visit.visit_number }}</span>
                        <span
                            class="inline-flex items-center gap-1 rounded-full border border-slate-100/80 px-2 py-0.5 text-[0.65rem] font-medium capitalize"
                            :class="visitStatusClass(visit.status)"
                        >
                            <span class="size-1.5 rounded-full" :class="visitStatusDotClass(visit.status)"></span>
                            {{ visitStatusLabel(visit.status) }}
                        </span>
                    </div>

                    <p class="text-sm font-semibold text-slate-800">{{ visit.patient?.full_name ?? '-' }}</p>
                    <p class="mt-0.5 text-xs text-slate-400">
                        {{ visit.doctor?.name ?? 'بدون طبيب' }}
                    </p>

                    <div v-if="visit.chief_complaint" class="mt-2 rounded-md bg-slate-50/60 p-2 text-xs leading-5 text-slate-500">
                        {{ visit.chief_complaint.length > 80 ? visit.chief_complaint.slice(0, 80) + '...' : visit.chief_complaint }}
                    </div>

                    <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                        <span v-if="visit.queue_entry?.queue_number" class="tabular-nums">
                            طابور #{{ visit.queue_entry.queue_number }}
                        </span>
                        <span v-if="visit.started_at" class="tabular-nums">
                            {{ formatDateTime(visit.started_at) }}
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <Button
                            v-if="canViewVisit"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 px-2 text-[0.65rem] rounded-md border-slate-200/80"
                            @click="emit('viewVisit', visit)"
                        >
                            عرض
                        </Button>
                        <Button
                            v-if="canEditVisit && visit.status !== 'completed'"
                            type="button"
                            variant="default"
                            size="sm"
                            class="h-8 px-2 text-[0.65rem] rounded-md bg-[#0EA5E9] hover:bg-[#0284C7]"
                            @click="emit('editVisit', visit)"
                        >
                            تعديل
                        </Button>
                        <Link
                            v-if="visit.status === 'started' && canTransitionVisit"
                            :href="VisitController.transitionStatus(visit.id)"
                            method="patch"
                            as="button"
                            :data="{ status: 'in_progress' }"
                            class="inline-flex h-8 items-center rounded-md border border-slate-200/80 bg-white px-2 text-[0.65rem] font-medium text-slate-600 transition hover:border-[#0EA5E9]/30 hover:text-[#0EA5E9]"
                        >
                            قيد التنفيذ
                        </Link>
                        <Link
                            v-if="visit.status === 'in_progress' && canTransitionVisit"
                            :href="VisitController.transitionStatus(visit.id)"
                            method="patch"
                            as="button"
                            :data="{ status: 'completed' }"
                            class="inline-flex h-8 items-center rounded-md border border-slate-200/80 bg-white px-2 text-[0.65rem] font-medium text-[#10B981] transition hover:border-[#10B981]/30"
                        >
                            إكمال
                        </Link>
                        <Button
                            v-if="canStart && visit.status === 'started'"
                            type="button"
                            size="sm"
                            variant="destructive"
                            class="h-8 px-2 text-[0.65rem] rounded-md"
                            @click="emit('deleteVisit', visit)"
                        >
                            حذف
                        </Button>
                    </div>
                </div>

                <div v-if="getKanbanVisits(col.key).length === 0" class="py-8 text-center text-xs text-slate-400">
                    لا توجد زيارات
                </div>
            </div>
        </div>
    </div>
</template>