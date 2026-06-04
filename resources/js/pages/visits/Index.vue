<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Kanban, Plus, Table2, Download, FileText } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import VisitController from '@/actions/App/Http/Controllers/Visits/VisitController';
import VisitExportController from '@/actions/App/Http/Controllers/Visits/VisitExportController';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';
import { usePage } from '@inertiajs/vue3';
import VisitCreateSheet from './components/VisitCreateSheet.vue';
import VisitEditDialog from './components/VisitEditDialog.vue';
import VisitKanbanView from './components/VisitKanbanView.vue';
import VisitTable from './components/VisitTable.vue';
import VisitViewDialog from './components/VisitViewDialog.vue';
import type { KanbanColumn, Option, PaginatedResponse, Visit, VisitSortField, SortDirection } from './components/types';

const {
    visits,
    patients,
    appointments,
    queue_entries,
    doctors,
    status_options,
    filters,
} = defineProps<{
    visits: PaginatedResponse<Visit>;
    patients: Option[];
    appointments: Option[];
    queue_entries: Option[];
    doctors: Option[];
    status_options: string[];
    filters: {
        status: string | null;
        search: string | null;
        per_page: number;
        sort_by: VisitSortField | null;
        sort_direction: SortDirection | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'الزيارات',
                href: VisitController.index(),
            },
        ],
    },
});

const { can } = usePermissions();
const { isOpen: isConfirmOpen, options: confirmOptions, confirm, handleConfirm: handleConfirmDelete, handleCancel: handleConfirmCancel } = useConfirm();
const toast = useToast();
const page = usePage();

const roleNames = computed<string[]>(() => {
    return (
        ((page.props.auth as { roles?: string[] } | undefined)?.roles ?? [])
            .filter((value): value is string => typeof value === 'string')
    );
});

const primaryRole = computed<string>(() => {
    const rolePriority = [
        'super_admin',
        'admin',
        'clinic_admin',
        'doctor',
        'receptionist',
        'accountant',
    ];

    return rolePriority.find((role) => roleNames.value.includes(role)) ?? 'staff';
});

const roleLabels: Record<string, string> = {
    super_admin: 'مدير النظام',
    admin: 'مدير',
    clinic_admin: 'مدير العيادة',
    doctor: 'طبيب',
    receptionist: 'استقبال',
    accountant: 'محاسب',
    staff: 'موظف',
};

const activeRoleLabel = computed<string>(() => roleLabels[primaryRole.value] ?? roleLabels.staff);

const visibleVisits = computed<Visit[]>(() => visits.data);

const startedVisitsCount = computed<number>(
    () => visibleVisits.value.filter((visit) => visit.status === 'started').length,
);

const inProgressVisitsCount = computed<number>(
    () => visibleVisits.value.filter((visit) => visit.status === 'in_progress').length,
);

const completedVisitsCount = computed<number>(
    () => visibleVisits.value.filter((visit) => visit.status === 'completed').length,
);

const viewingVisit = ref<Visit | null>(null);
const editingVisit = ref<Visit | null>(null);
const isCreateSheetOpen = ref(false);

const canViewVisit = computed<boolean>(
    () => can('visit.start') || can('visit.update') || can('visit.complete'),
);

const canEditVisit = computed<boolean>(
    () => can('visit.update') || can('medical.notes.create'),
);

const canTransitionVisit = computed<boolean>(
    () => can('visit.update') || can('visit.complete'),
);

const openViewVisit = (visit: Visit): void => {
    viewingVisit.value = visit;
};

const closeViewVisit = (): void => {
    viewingVisit.value = null;
};

const openEditVisit = (visit: Visit): void => {
    editingVisit.value = visit;
};

const closeEditVisit = (): void => {
    editingVisit.value = null;
};

const handleDeleteVisit = async (visit: Visit) => {
    const confirmed = await confirm({
        title: 'حذف الزيارة',
        description: `هل أنت متأكد من حذف الزيارة رقم "${visit.visit_number || visit.id}" للمريض "${visit.patient?.full_name || visit.patient?.first_name + ' ' + visit.patient?.last_name}"؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(VisitController.destroy(visit.id), {
            onSuccess: () => {
                toast.success('تم حذف الزيارة بنجاح');
            },
            onError: () => {
                toast.error('فشل حذف الزيارة');
            },
        });
    }
};

const handleBulkDelete = async (ids: number[]) => {
    const confirmed = await confirm({
        title: 'حذف الزيارات',
        description: `هل أنت متأكد من حذف ${ids.length} زيارة؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(VisitController.bulkDestroy.url(), {
            data: { ids },
            onSuccess: () => {
                toast.success(`تم حذف ${ids.length} زيارة بنجاح`);
            },
            onError: () => {
                toast.error('فشل حذف الزيارات');
            },
        });
    }
};

const viewMode = ref<'kanban' | 'table'>('kanban');

const kanbanColumns: KanbanColumn[] = [
    { key: 'started', label: 'بدأت', dotColor: 'bg-[var(--accent-teal)]', headerBg: 'bg-[var(--accent-teal-soft)]' },
    { key: 'in_progress', label: 'قيد التنفيذ', dotColor: 'bg-[var(--accent-coral)]', headerBg: 'bg-[var(--accent-coral-soft)]' },
    { key: 'completed', label: 'مكتملة', dotColor: 'bg-[var(--accent-mint)]', headerBg: 'bg-[var(--accent-mint-soft)]' },
];
</script>

<template>
    <Head title="الزيارات" />

    <div class="container-modern space-y-5" dir="rtl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="page-title">الزيارات</h1>
                    <p class="mt-1 text-sm text-slate-500">إدارة الزيارات السريرية وتحويل الحالات.</p>
                </div>
                <span class="inline-flex items-center rounded-full border border-slate-100/80 bg-slate-50/60 px-2.5 py-0.5 text-[0.7rem] font-medium text-slate-500">
                    {{ activeRoleLabel }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <a
                    :href="VisitExportController.export()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-100/80 bg-white px-3 py-2 text-xs font-medium text-slate-500 transition hover:text-[#0EA5E9] hover:border-[#0EA5E9]/20"
                >
                    <Download class="size-3.5" />
                    تصدير Excel
                </a>
                <a
                    :href="VisitExportController.exportPdf()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-100/80 bg-white px-3 py-2 text-xs font-medium text-slate-500 transition hover:text-[#0EA5E9] hover:border-[#0EA5E9]/20"
                >
                    <FileText class="size-3.5" />
                    تصدير PDF
                </a>

                <div class="inline-flex rounded-lg border border-slate-100/80 bg-white p-0.5">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all"
                        :class="viewMode === 'kanban' ? 'bg-[#0EA5E9] text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        @click="viewMode = 'kanban'"
                    >
                        <Kanban class="size-3.5" />
                        لوحة
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all"
                        :class="viewMode === 'table' ? 'bg-[#0EA5E9] text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        @click="viewMode = 'table'"
                    >
                        <Table2 class="size-3.5" />
                        جدول
                    </button>
                </div>

                <Button
                    v-if="can('visit.start')"
                    variant="default"
                    size="sm"
                    class="h-9 rounded-lg bg-[#0EA5E9] text-white hover:bg-[#0284C7] shadow-sm"
                    @click="isCreateSheetOpen = true"
                >
                    <Plus class="size-3.5" />
                    بدء زيارة
                </Button>
            </div>
        </div>

        <section class="card-float px-4 py-3">
            <div class="flex flex-wrap items-center gap-4 md:gap-6">
                <div class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full bg-[#0EA5E9]" aria-hidden="true"></span>
                    <span class="text-sm text-slate-500">بدأت</span>
                    <span class="metric-value text-[#0EA5E9]">{{ startedVisitsCount }}</span>
                </div>
                <div class="hidden h-5 w-px bg-slate-100/80 md:block" aria-hidden="true"></div>
                <div class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full bg-[#F59E0B]" aria-hidden="true"></span>
                    <span class="text-sm text-slate-500">قيد التنفيذ</span>
                    <span class="metric-value text-[#F59E0B]">{{ inProgressVisitsCount }}</span>
                </div>
                <div class="hidden h-5 w-px bg-slate-100/80 md:block" aria-hidden="true"></div>
                <div class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full bg-[#10B981]" aria-hidden="true"></span>
                    <span class="text-sm text-slate-500">مكتملة</span>
                    <span class="metric-value text-[#10B981]">{{ completedVisitsCount }}</span>
                </div>
            </div>
        </section>

        <VisitKanbanView
            v-if="viewMode === 'kanban'"
            :visits="visibleVisits"
            :kanban-columns="kanbanColumns"
            :can-view-visit="canViewVisit"
            :can-edit-visit="canEditVisit"
            :can-transition-visit="canTransitionVisit"
            :can-start="can('visit.start')"
            @view-visit="openViewVisit"
            @edit-visit="openEditVisit"
            @delete-visit="handleDeleteVisit"
        />

        <VisitTable
            v-if="viewMode === 'table'"
            :visits="visits"
            :status_options="status_options"
            :filters="filters"
            @delete-visit="handleDeleteVisit"
            @bulk-delete="handleBulkDelete"
            @view-visit="openViewVisit"
            @edit-visit="openEditVisit"
        />

        <VisitCreateSheet
            :open="isCreateSheetOpen"
            :patients="patients"
            :queue-entries="queue_entries"
            :appointments="appointments"
            :doctors="doctors"
            @update:open="isCreateSheetOpen = $event"
        />

        <VisitViewDialog
            :visit="viewingVisit"
            @close="closeViewVisit"
        />

        <VisitEditDialog
            :visit="editingVisit"
            :patients="patients"
            :doctors="doctors"
            :appointments="appointments"
            :queue-entries="queue_entries"
            :can-edit="canEditVisit"
            @close="closeEditVisit"
        />

        <ConfirmationDialog
            :open="isConfirmOpen"
            :options="confirmOptions"
            @confirm="handleConfirmDelete"
            @cancel="handleConfirmCancel"
            @update:open="handleConfirmCancel"
        />
    </div>
</template>