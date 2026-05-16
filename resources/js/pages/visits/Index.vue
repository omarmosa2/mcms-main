<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Kanban,
    Plus,
    Table2,
    Download,
    FileText,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import VisitController from '@/actions/App/Http/Controllers/Visits/VisitController';
import VisitExportController from '@/actions/App/Http/Controllers/Visits/VisitExportController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    FilterBar,
    FilterSearch,
    FilterSelect,
} from '@/components/ui/filter';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';

type Option = {
    id: number;
    full_name?: string;
    appointment_number?: string;
    label?: string;
    name?: string;
};

type Visit = {
    id: number;
    queue_entry_id: number | null;
    appointment_id: number | null;
    patient_id: number;
    doctor_id: number | null;
    visit_number: string;
    status: string;
    started_at: string | null;
    in_progress_at: string | null;
    completed_at: string | null;
    chief_complaint: string | null;
    clinical_notes: string | null;
    diagnosis_notes: string | null;
    treatment_plan: string | null;
    patient?: {
        id?: number;
        full_name?: string;
    };
    doctor?: {
        id?: number;
        name?: string;
    };
    appointment?: {
        id?: number;
        appointment_number?: string;
    };
    queue_entry?: {
        id?: number;
        queue_number?: number;
    };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginationMeta = {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

type PaginationNavigation = {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
};

type PaginatedResponse<T> = {
    data: T[];
    links: PaginationNavigation;
    meta: PaginationMeta;
};

type VisitSortField =
    | 'visit_number'
    | 'status'
    | 'started_at'
    | 'completed_at';

type SortDirection = 'asc' | 'desc';

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

const viewingVisit = ref<Visit | null>(null);
const editingVisit = ref<Visit | null>(null);
const isCreateSheetOpen = ref(false);
const localSearch = ref<string>(filters.search ?? '');
const localStatus = ref<string>(filters.status ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(visits.meta.current_page);
const allowedSortFields: VisitSortField[] = [
    'visit_number',
    'status',
    'started_at',
    'completed_at',
];
const resolveInitialSortBy = (): VisitSortField => {
    const sortBy = filters.sort_by;

    if (sortBy !== null && allowedSortFields.includes(sortBy as VisitSortField)) {
        return sortBy;
    }

    return 'started_at';
};
const localSortBy = ref<VisitSortField>(resolveInitialSortBy());
const localSortDirection = ref<SortDirection>(
    filters.sort_direction === 'asc' ? 'asc' : 'desc',
);
const visibleVisits = computed<Visit[]>(() => visits.data);
const totalLocalPages = computed<number>(() => {
    return Math.max(1, visits.meta.last_page);
});
const localVisibleFrom = computed<number>(() => {
    return visits.meta.from ?? 0;
});
const localVisibleTo = computed<number>(() => {
    return visits.meta.to ?? 0;
});
const defaultRowsPerPage = 15;
const isSyncingFromServer = ref(false);
let visitFiltersDebounceTimeout: ReturnType<typeof setTimeout> | null = null;

const buildIndexQuery = (
    overrides: Partial<{
        status: string;
        search: string;
        per_page: number;
        page: number;
        sort_by: VisitSortField;
        sort_direction: SortDirection;
    }> = {},
): {
    status?: string;
    search?: string;
    per_page: number;
    page: number;
    sort_by: VisitSortField;
    sort_direction: SortDirection;
} => {
    const query: {
        status?: string;
        search?: string;
        per_page: number;
        page: number;
        sort_by: VisitSortField;
        sort_direction: SortDirection;
    } = {
        status: localStatus.value.trim(),
        search: localSearch.value.trim(),
        per_page: localRowsPerPage.value,
        page: localPage.value,
        sort_by: localSortBy.value,
        sort_direction: localSortDirection.value,
        ...overrides,
    };

    return query;
};

const reloadVisits = (
    overrides: Partial<{
        status: string;
        search: string;
        per_page: number;
        page: number;
        sort_by: VisitSortField;
        sort_direction: SortDirection;
    }> = {},
    debounce = false,
): void => {
    if (isSyncingFromServer.value) {
        return;
    }

    const executeReload = (): void => {
        router.cancelAll();
        router.get(VisitController.index.url(), buildIndexQuery(overrides), {
            only: ['visits', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    if (debounce) {
        if (visitFiltersDebounceTimeout !== null) {
            clearTimeout(visitFiltersDebounceTimeout);
        }

        visitFiltersDebounceTimeout = setTimeout(executeReload, 320);

        return;
    }

    executeReload();
};

const sortIconFor = (field: VisitSortField) => {
    if (localSortBy.value !== field) {
        return ArrowUpDown;
    }

    return localSortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

const toggleSort = (field: VisitSortField): void => {
    if (localSortBy.value === field) {
        localSortDirection.value = localSortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        localSortBy.value = field;
        localSortDirection.value = 'asc';
    }
};

const resetLocalFilters = (): void => {
    isSyncingFromServer.value = true;
    localSearch.value = '';
    localStatus.value = '';
    localRowsPerPage.value = defaultRowsPerPage;
    localSortBy.value = 'started_at';
    localSortDirection.value = 'desc';
    localPage.value = 1;
    isSyncingFromServer.value = false;
    reloadVisits({
        status: '',
        search: '',
        per_page: defaultRowsPerPage,
        page: 1,
        sort_by: 'started_at',
        sort_direction: 'desc',
    });
};

const goToPreviousPage = (): void => {
    if (localPage.value <= 1) {
        return;
    }

    localPage.value -= 1;
    reloadVisits({ page: localPage.value });
};

const goToNextPage = (): void => {
    if (localPage.value >= totalLocalPages.value) {
        return;
    }

    localPage.value += 1;
    reloadVisits({ page: localPage.value });
};

watch(
    () => [
        filters.search,
        filters.status,
        filters.per_page,
        filters.sort_by,
        filters.sort_direction,
        visits.meta.current_page,
    ],
    () => {
        isSyncingFromServer.value = true;
        localSearch.value = filters.search ?? '';
        localStatus.value = filters.status ?? '';
        localRowsPerPage.value = filters.per_page;
        localSortBy.value = resolveInitialSortBy();
        localSortDirection.value = filters.sort_direction === 'asc' ? 'asc' : 'desc';
        localPage.value = visits.meta.current_page;
        isSyncingFromServer.value = false;
    },
    { immediate: true },
);

watch(
    () => localSearch.value,
    () => {
        localPage.value = 1;
        reloadVisits({ page: 1, search: localSearch.value.trim() }, true);
    },
);

watch(
    () => localStatus.value,
    () => {
        localPage.value = 1;
        reloadVisits({ page: 1, status: localStatus.value.trim() });
    },
);

watch(
    () => localRowsPerPage.value,
    () => {
        localPage.value = 1;
        reloadVisits({ page: 1, per_page: localRowsPerPage.value });
    },
);

watch(
    () => [localSortBy.value, localSortDirection.value],
    () => {
        localPage.value = 1;
        reloadVisits({
            page: 1,
            sort_by: localSortBy.value,
            sort_direction: localSortDirection.value,
        });
    },
);

onBeforeUnmount(() => {
    if (visitFiltersDebounceTimeout !== null) {
        clearTimeout(visitFiltersDebounceTimeout);
        visitFiltersDebounceTimeout = null;
    }
});

const selectedVisitIds = ref<number[]>([]);

const deletableVisitIds = computed<number[]>(() =>
    visibleVisits.value
        .filter((visit) => visit.status === 'started')
        .map((visit) => visit.id),
);

const areAllDeletableVisitsSelected = computed<boolean>(() => {
    if (deletableVisitIds.value.length === 0) {
        return false;
    }

    return deletableVisitIds.value.every((visitId) =>
        selectedVisitIds.value.includes(visitId),
    );
});

watch(
    () => deletableVisitIds.value,
    (ids) => {
        selectedVisitIds.value = selectedVisitIds.value.filter((id) =>
            ids.includes(id),
        );
    },
);

const toggleAllVisitsSelection = (event: Event): void => {
    const target = event.target as HTMLInputElement;
    selectedVisitIds.value = target.checked ? [...deletableVisitIds.value] : [];
};

const clearSelectedVisits = (): void => {
    selectedVisitIds.value = [];
};

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

const visitStatusClass = (status: string): string => {
    if (status === 'started') {
        return 'border-[var(--border-soft)] bg-[var(--accent-teal-soft)] text-[var(--accent-teal-strong)]';
    }

    if (status === 'in_progress') {
        return 'border-[var(--border-soft)] bg-[var(--accent-coral-soft)] text-[var(--accent-coral-strong)]';
    }

    if (status === 'completed') {
        return 'border-[var(--border-soft)] bg-[var(--accent-mint-soft)] text-[var(--accent-mint-strong)]';
    }

    if (status === 'canceled') {
        return 'border-[var(--border-soft)] bg-[var(--accent-coral-soft)] text-[var(--accent-coral-strong)]';
    }

    return 'border-[var(--border-soft)] bg-[var(--surface-secondary)] text-[var(--surface-contrast-soft)]';
};

const visitStatusDotClass = (status: string): string => {
    if (status === 'completed') {
        return 'bg-[var(--accent-mint)]';
    }

    if (status === 'started') {
        return 'bg-[var(--accent-teal)]';
    }

    if (status === 'in_progress') {
        return 'bg-[var(--accent-coral)]';
    }

    if (status === 'canceled') {
        return 'bg-[var(--accent-coral)]';
    }

    return 'bg-[var(--surface-contrast-soft)]';
};

const visitStatusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        started: 'بدأت',
        in_progress: 'قيد التنفيذ',
        completed: 'مكتملة',
        canceled: 'ملغاة',
    };

    return labels[status] ?? status;
};

const startedVisitsCount = computed<number>(
    () => visibleVisits.value.filter((visit) => visit.status === 'started').length,
);

const inProgressVisitsCount = computed<number>(
    () => visibleVisits.value.filter((visit) => visit.status === 'in_progress').length,
);

const completedVisitsCount = computed<number>(
    () => visibleVisits.value.filter((visit) => visit.status === 'completed').length,
);

const activeFilters = computed(() => {
    const f: { key: string; label: string; value: string | null }[] = [];

    if (localSearch.value.trim()) {
        f.push({ key: 'search', label: 'بحث', value: localSearch.value.trim() });
    }

    if (localStatus.value) {
        f.push({ key: 'status', label: 'الحالة', value: localStatus.value });
    }

    return f;
});

const statusOptions = computed(() => {
    const opts = [{ label: 'الكل', value: '' }];

    return [...opts, ...status_options.map((s: string) => ({ label: visitStatusLabel(s), value: s }))];
});

const handleRemoveFilter = (key: string) => {
    if (key === 'search') {
        localSearch.value = '';
    } else if (key === 'status') {
        localStatus.value = '';
    }
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

const handleBulkDelete = async () => {
    const confirmed = await confirm({
        title: 'حذف الزيارات',
        description: `هل أنت متأكد من حذف ${selectedVisitIds.value.length} زيارة؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(VisitController.bulkDestroy.url(), {
            data: { ids: selectedVisitIds.value },
            onSuccess: () => {
                clearSelectedVisits();
                toast.success(`تم حذف ${selectedVisitIds.value.length} زيارة بنجاح`);
            },
            onError: () => {
                toast.error('فشل حذف الزيارات');
            },
        });
    }
};

const viewMode = ref<'kanban' | 'table'>('kanban');

const kanbanColumns = [
    { key: 'started', label: 'بدأت', dotColor: 'bg-[var(--accent-teal)]', headerBg: 'bg-[var(--accent-teal-soft)]' },
    { key: 'in_progress', label: 'قيد التنفيذ', dotColor: 'bg-[var(--accent-coral)]', headerBg: 'bg-[var(--accent-coral-soft)]' },
    { key: 'completed', label: 'مكتملة', dotColor: 'bg-[var(--accent-mint)]', headerBg: 'bg-[var(--accent-mint-soft)]' },
];

const getKanbanVisits = (status: string) => {
    return visibleVisits.value.filter((v) => v.status === status);
};

const formatDateTime = (value: string | null): string => {
    if (value === null) {
return '-';
}

    return new Date(value).toLocaleString('ar-SA');
};

const isDoctorAssigned = (visit: Visit): boolean => {
    const user = page.props.auth as { user?: { id?: number } } | undefined;

    return visit.doctor_id !== null && user?.user?.id === visit.doctor_id;
};
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
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-100/80 bg-white px-3 py-2 text-xs font-medium text-slate-500 transition hover:text-[#0F9D7A] hover:border-[#0F9D7A]/20"
                >
                    <Download class="size-3.5" />
                    تصدير Excel
                </a>
                <a
                    :href="VisitExportController.exportPdf()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-100/80 bg-white px-3 py-2 text-xs font-medium text-slate-500 transition hover:text-[#0F9D7A] hover:border-[#0F9D7A]/20"
                >
                    <FileText class="size-3.5" />
                    تصدير PDF
                </a>

                <div class="inline-flex rounded-lg border border-slate-100/80 bg-white p-0.5">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all"
                        :class="viewMode === 'kanban' ? 'bg-[#0F9D7A] text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        @click="viewMode = 'kanban'"
                    >
                        <Kanban class="size-3.5" />
                        لوحة
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all"
                        :class="viewMode === 'table' ? 'bg-[#0F9D7A] text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'"
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
                    class="h-9 rounded-lg bg-[#0F9D7A] text-white hover:bg-[#0B7A5E] shadow-sm"
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
                    <span class="size-2.5 rounded-full bg-[#0F9D7A]" aria-hidden="true"></span>
                    <span class="text-sm text-slate-500">بدأت</span>
                    <span class="metric-value text-[#0F9D7A]">{{ startedVisitsCount }}</span>
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

        <div v-if="viewMode === 'kanban'" class="grid gap-4 md:grid-cols-3">
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
                        class="rounded-lg border border-slate-100/80 bg-white p-3 transition-all hover:border-[#0F9D7A]/20 hover:shadow-sm"
                        :class="{ 'border-r-2 border-r-[#0F9D7A]': isDoctorAssigned(visit) }"
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
                                @click="openViewVisit(visit)"
                            >
                                عرض
                            </Button>
                            <Button
                                v-if="canEditVisit && visit.status !== 'completed'"
                                type="button"
                                variant="default"
                                size="sm"
                                class="h-8 px-2 text-[0.65rem] rounded-md bg-[#0F9D7A] hover:bg-[#0B7A5E]"
                                @click="openEditVisit(visit)"
                            >
                                تعديل
                            </Button>
                            <Link
                                v-if="visit.status === 'started' && canTransitionVisit"
                                :href="VisitController.transitionStatus(visit.id)"
                                method="patch"
                                as="button"
                                :data="{ status: 'in_progress' }"
                                class="inline-flex h-8 items-center rounded-md border border-slate-200/80 bg-white px-2 text-[0.65rem] font-medium text-slate-600 transition hover:border-[#0F9D7A]/30 hover:text-[#0F9D7A]"
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
                                v-if="can('visit.start') && visit.status === 'started'"
                                type="button"
                                size="sm"
                                variant="destructive"
                                class="h-8 px-2 text-[0.65rem] rounded-md"
                                @click="handleDeleteVisit(visit)"
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

        <div v-if="viewMode === 'table'" class="card-float p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100/80 pb-3">
                <h3 class="text-sm font-semibold text-slate-700">جميع الزيارات</h3>
                <span class="text-xs text-slate-400">الإجمالي: {{ visits.meta.total }}</span>
            </div>

            <div class="space-y-3 rounded-lg border border-slate-100/80 bg-slate-50/40 p-4">
                <div class="grid gap-3 md:grid-cols-[repeat(3,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="visits_search_filter" class="text-xs font-medium text-slate-600">بحث</Label>
                        <FilterSearch
                            id="visits_search_filter"
                            v-model="localSearch"
                            placeholder="رقم الزيارة، المريض، الطبيب، رقم الطابور"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="visits_status_filter" class="text-xs font-medium text-slate-600">الحالة</Label>
                        <FilterSelect
                            id="visits_status_filter"
                            v-model="localStatus"
                            :options="statusOptions"
                            placeholder="جميع الحالات"
                        />
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-[repeat(2,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2 md:max-w-44">
                        <Label for="visits_per_page" class="text-xs font-medium text-slate-600">صفوف لكل صفحة</Label>
                        <select
                            id="visits_per_page"
                            v-model.number="localRowsPerPage"
                            class="h-9 w-full rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-[#0F9D7A]/40 focus:outline-none focus:ring-1 focus:ring-[#0F9D7A]/20"
                        >
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>

                <FilterBar
                    v-if="activeFilters.length > 0"
                    :active-filters="activeFilters"
                    @remove="handleRemoveFilter"
                    @clear-all="resetLocalFilters"
                />
            </div>

            <div
                v-if="can('visit.start') && selectedVisitIds.length > 0"
                class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-red-100/80 bg-red-50/40 p-3"
            >
                <Button type="button" variant="destructive" size="sm" class="h-9 rounded-md" @click="handleBulkDelete">
                    حذف المحدد ({{ selectedVisitIds.length }})
                </Button>
                <Button type="button" variant="ghost" size="sm" class="h-9 rounded-md" @click="clearSelectedVisits">إلغاء التحديد</Button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-100/80 bg-slate-50/50">
                            <th v-if="can('visit.start')" class="px-3 py-2.5 text-xs font-medium text-slate-500">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-slate-200/80"
                                    :checked="areAllDeletableVisitsSelected"
                                    @change="toggleAllVisitsSelection"
                                />
                            </th>
                            <th class="px-3 py-2.5 text-xs font-medium text-slate-500">
                                <button type="button" class="inline-flex items-center gap-1.5 font-medium transition hover:text-slate-700" @click="toggleSort('visit_number')">
                                    رقم الزيارة
                                    <component :is="sortIconFor('visit_number')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2.5 text-xs font-medium text-slate-500">المريض</th>
                            <th class="px-3 py-2.5 text-xs font-medium text-slate-500">الطبيب</th>
                            <th class="px-3 py-2.5 text-xs font-medium text-slate-500">الموعد</th>
                            <th class="px-3 py-2.5 text-xs font-medium text-slate-500">رقم الطابور</th>
                            <th class="px-3 py-2.5 text-xs font-medium text-slate-500">
                                <button type="button" class="inline-flex items-center gap-1.5 font-medium transition hover:text-slate-700" @click="toggleSort('status')">
                                    الحالة
                                    <component :is="sortIconFor('status')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2.5 text-xs font-medium text-slate-500">
                                <button type="button" class="inline-flex items-center gap-1.5 font-medium transition hover:text-slate-700" @click="toggleSort('started_at')">
                                    بدأت
                                    <component :is="sortIconFor('started_at')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2.5 text-xs font-medium text-slate-500 text-start">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="visit in visibleVisits" :key="visit.id" class="border-b border-slate-50 hover:bg-slate-50/40 transition-colors align-top">
                            <td v-if="can('visit.start')" class="px-3 py-2.5" data-label="تحديد">
                                <input
                                    v-if="visit.status === 'started'"
                                    v-model="selectedVisitIds"
                                    type="checkbox"
                                    class="size-4 rounded border-slate-200/80"
                                    :value="visit.id"
                                />
                            </td>
                            <td class="px-3 py-2.5 font-medium text-slate-700" data-label="رقم الزيارة">{{ visit.visit_number }}</td>
                            <td class="px-3 py-2.5 text-slate-600" data-label="المريض">{{ visit.patient?.full_name ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-slate-600" data-label="الطبيب">{{ visit.doctor?.name ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-slate-600" data-label="الموعد">{{ visit.appointment?.appointment_number ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-slate-600" data-label="رقم الطابور">{{ visit.queue_entry?.queue_number ?? '-' }}</td>
                            <td class="px-3 py-2.5" data-label="الحالة">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border border-slate-100/80 px-2.5 py-1 text-xs font-medium capitalize"
                                    :class="visitStatusClass(visit.status)"
                                >
                                    <span class="size-1.5 rounded-full" :class="visitStatusDotClass(visit.status)"></span>
                                    {{ visitStatusLabel(visit.status) }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-slate-500" data-label="بدأت">{{ formatDateTime(visit.started_at) }}</td>
                            <td class="px-3 py-2.5 md:text-start" data-label="الإجراءات">
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    <Button
                                        v-if="canViewVisit"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-8 px-2 text-xs rounded-md border-slate-200/80"
                                        @click="openViewVisit(visit)"
                                    >
                                        عرض
                                    </Button>
                                    <Button
                                        v-if="canEditVisit && visit.status !== 'completed'"
                                        type="button"
                                        variant="default"
                                        size="sm"
                                        class="h-8 px-2 text-xs rounded-md bg-[#0F9D7A] hover:bg-[#0B7A5E]"
                                        @click="openEditVisit(visit)"
                                    >
                                        تعديل
                                    </Button>
                                    <Link
                                        v-if="visit.status === 'started' && canTransitionVisit"
                                        :href="VisitController.transitionStatus(visit.id)"
                                        method="patch"
                                        as="button"
                                        :data="{ status: 'in_progress' }"
                                        class="inline-flex h-8 items-center rounded-md border border-slate-200/80 bg-white px-2 text-xs font-medium text-slate-600 transition hover:border-[#0F9D7A]/30 hover:text-[#0F9D7A]"
                                    >
                                        قيد التنفيذ
                                    </Link>
                                    <Link
                                        v-if="visit.status === 'in_progress' && canTransitionVisit"
                                        :href="VisitController.transitionStatus(visit.id)"
                                        method="patch"
                                        as="button"
                                        :data="{ status: 'completed' }"
                                        class="inline-flex h-8 items-center rounded-md border border-slate-200/80 bg-white px-2 text-xs font-medium text-[#10B981] transition hover:border-[#10B981]/30"
                                    >
                                        إكمال
                                    </Link>
                                    <Button
                                        v-if="can('visit.start') && visit.status === 'started'"
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        class="h-8 px-2 text-xs rounded-md"
                                        @click="handleDeleteVisit(visit)"
                                    >
                                        حذف
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="visibleVisits.length === 0">
                            <td :colspan="can('visit.start') ? 9 : 8" class="px-3 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-center">
                                    <Kanban class="size-10 text-slate-200 mb-3" />
                                    <h3 class="text-sm font-semibold text-slate-700 mb-1">لا توجد زيارات</h3>
                                    <p class="text-xs text-slate-400">جرب تغيير كلمة البحث أو أضف زيارة جديدة</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-100/80 bg-slate-50/40 px-4 py-2.5">
                <p class="text-xs text-slate-500">
                    عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من {{ visits.meta.total }} سجل
                </p>
                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" class="h-9 px-3 text-xs rounded-lg border-slate-200/80" :disabled="localPage === 1" @click="goToPreviousPage">السابق</Button>
                    <span class="text-xs font-semibold text-slate-600">صفحة {{ localPage }} / {{ totalLocalPages }}</span>
                    <Button type="button" variant="outline" size="sm" class="h-9 px-3 text-xs rounded-lg border-slate-200/80" :disabled="localPage >= totalLocalPages" @click="goToNextPage">التالي</Button>
                </div>
            </div>
        </div>

        <Sheet :open="isCreateSheetOpen" @update:open="isCreateSheetOpen = $event">
            <SheetContent side="left" class="w-full sm:max-w-lg">
                <SheetHeader class="text-right">
                    <SheetTitle>بدء زيارة</SheetTitle>
                    <SheetDescription>بدء زيارة سريرية جديدة.</SheetDescription>
                </SheetHeader>

                <Form v-bind="VisitController.store.form()" class="mt-6 space-y-4" v-slot="{ errors, processing }" @success="isCreateSheetOpen = false">
                    <div class="grid gap-2">
                        <Label for="patient_id" class="text-xs font-medium text-slate-600">المريض</Label>
                        <select id="patient_id" name="patient_id" required class="h-9 w-full rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-[#0F9D7A]/40 focus:outline-none focus:ring-1 focus:ring-[#0F9D7A]/20">
                            <option value="">اختر المريض</option>
                            <option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.full_name }}</option>
                        </select>
                        <InputError :message="errors.patient_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="queue_entry_id" class="text-xs font-medium text-slate-600">قائمة الانتظار</Label>
                        <select id="queue_entry_id" name="queue_entry_id" class="h-9 w-full rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-[#0F9D7A]/40 focus:outline-none focus:ring-1 focus:ring-[#0F9D7A]/20">
                            <option value="">بدون قائمة انتظار</option>
                            <option v-for="queueEntry in queue_entries" :key="queueEntry.id" :value="queueEntry.id">{{ queueEntry.label }}</option>
                        </select>
                        <InputError :message="errors.queue_entry_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="appointment_id" class="text-xs font-medium text-slate-600">الموعد</Label>
                        <select id="appointment_id" name="appointment_id" class="h-9 w-full rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-[#0F9D7A]/40 focus:outline-none focus:ring-1 focus:ring-[#0F9D7A]/20">
                            <option value="">بدون موعد</option>
                            <option v-for="appointment in appointments" :key="appointment.id" :value="appointment.id">{{ appointment.appointment_number }}</option>
                        </select>
                        <InputError :message="errors.appointment_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="doctor_id" class="text-xs font-medium text-slate-600">الطبيب</Label>
                        <select id="doctor_id" name="doctor_id" class="h-9 w-full rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-[#0F9D7A]/40 focus:outline-none focus:ring-1 focus:ring-[#0F9D7A]/20">
                            <option value="">غير محدد</option>
                            <option v-for="doctor in doctors" :key="doctor.id" :value="doctor.id">{{ doctor.name }}</option>
                        </select>
                        <InputError :message="errors.doctor_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="chief_complaint" class="text-xs font-medium text-slate-600">الشكوى الرئيسية</Label>
                        <textarea id="chief_complaint" name="chief_complaint" rows="3" class="h-auto w-full rounded-lg border border-slate-200/80 bg-white px-3 py-2 text-sm text-slate-700 focus:border-[#0F9D7A]/40 focus:outline-none focus:ring-1 focus:ring-[#0F9D7A]/20"></textarea>
                        <InputError :message="errors.chief_complaint" />
                    </div>

                    <Button :disabled="processing" variant="default" class="w-full h-9 rounded-lg bg-[#0F9D7A] hover:bg-[#0B7A5E] shadow-sm">بدء الزيارة</Button>
                </Form>
            </SheetContent>
        </Sheet>

        <Dialog :open="viewingVisit !== null" @update:open="(open) => !open && closeViewVisit()">
            <DialogContent class="sm:max-w-2xl" aria-label="تفاصيل الزيارة">
                <DialogHeader>
                    <DialogTitle>{{ viewingVisit?.visit_number ?? 'تفاصيل الزيارة' }}</DialogTitle>
                    <DialogDescription>لقطة سريعة للزيارة.</DialogDescription>
                </DialogHeader>

                <dl v-if="viewingVisit" class="grid gap-3 rounded-lg border border-slate-100/80 bg-slate-50/40 p-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-slate-400 uppercase">المريض</dt>
                        <dd class="text-sm text-slate-700">{{ viewingVisit.patient?.full_name ?? '-' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-slate-400 uppercase">الطبيب</dt>
                        <dd class="text-sm text-slate-700">{{ viewingVisit.doctor?.name ?? '-' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-slate-400 uppercase">الموعد</dt>
                        <dd class="text-sm text-slate-700">{{ viewingVisit.appointment?.appointment_number ?? '-' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-slate-400 uppercase">رقم الطابور</dt>
                        <dd class="text-sm text-slate-700">{{ viewingVisit.queue_entry?.queue_number ?? '-' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-slate-400 uppercase">الحالة</dt>
                        <dd class="text-sm">
                            <span
                                class="inline-flex items-center gap-1 rounded-full border border-slate-100/80 px-2.5 py-1 text-xs font-medium capitalize"
                                :class="visitStatusClass(viewingVisit.status)"
                            >
                                <span class="size-1.5 rounded-full" :class="visitStatusDotClass(viewingVisit.status)"></span>
                                {{ visitStatusLabel(viewingVisit.status) }}
                            </span>
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-slate-400 uppercase">بدأت في</dt>
                        <dd class="text-sm text-slate-700">{{ formatDateTime(viewingVisit.started_at) }}</dd>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-slate-400 uppercase">الشكوى الرئيسية</dt>
                        <dd class="text-sm leading-6 text-slate-500">{{ viewingVisit.chief_complaint ?? 'غير محددة' }}</dd>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-slate-400 uppercase">ملاحظات سريرية</dt>
                        <dd class="text-sm leading-6 text-slate-500">{{ viewingVisit.clinical_notes ?? 'غير موجودة' }}</dd>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-slate-400 uppercase">ملاحظات التشخيص</dt>
                        <dd class="text-sm leading-6 text-slate-500">{{ viewingVisit.diagnosis_notes ?? 'غير موجودة' }}</dd>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-slate-400 uppercase">خطة العلاج</dt>
                        <dd class="text-sm leading-6 text-slate-500">{{ viewingVisit.treatment_plan ?? 'غير موجودة' }}</dd>
                    </div>
                </dl>

                <DialogFooter>
                    <Button type="button" variant="ghost" class="h-9 rounded-md" @click="closeViewVisit">إغلاق</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="editingVisit !== null" @update:open="(open) => !open && closeEditVisit()">
            <DialogContent class="sm:max-w-3xl" aria-label="تعديل الزيارة">
                <DialogHeader>
                    <DialogTitle>تعديل الزيارة</DialogTitle>
                    <DialogDescription>تحديث تفاصيل الزيارة والملاحظات الطبية.</DialogDescription>
                </DialogHeader>

                <Form
                    v-if="editingVisit && canEditVisit"
                    v-bind="VisitController.update.form(editingVisit.id)"
                    class="space-y-4"
                    :options="{ preserveScroll: true }"
                    @success="closeEditVisit"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_visit_number">رقم الزيارة</Label>
                            <Input id="edit_visit_number" name="visit_number" :value="editingVisit.visit_number" class="pattern-field-clay" required />
                            <InputError :message="errors.visit_number" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_visit_patient">المريض</Label>
                            <select id="edit_visit_patient" name="patient_id" class="pattern-field-clay h-10 px-3 py-2" :value="String(editingVisit.patient_id)">
                                <option v-for="patient in patients" :key="`edit-visit-patient-${patient.id}`" :value="patient.id">{{ patient.full_name }}</option>
                            </select>
                            <InputError :message="errors.patient_id" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="edit_visit_doctor">الطبيب</Label>
                            <select id="edit_visit_doctor" name="doctor_id" class="pattern-field-clay h-10 px-3 py-2" :value="editingVisit.doctor_id !== null ? String(editingVisit.doctor_id) : ''">
                                <option value="">غير محدد</option>
                                <option v-for="doctor in doctors" :key="`edit-visit-doctor-${doctor.id}`" :value="doctor.id">{{ doctor.name }}</option>
                            </select>
                            <InputError :message="errors.doctor_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_visit_appointment">الموعد</Label>
                            <select id="edit_visit_appointment" name="appointment_id" class="pattern-field-clay h-10 px-3 py-2" :value="editingVisit.appointment_id !== null ? String(editingVisit.appointment_id) : ''">
                                <option value="">بدون موعد</option>
                                <option v-for="appointment in appointments" :key="`edit-visit-appointment-${appointment.id}`" :value="appointment.id">{{ appointment.appointment_number }}</option>
                            </select>
                            <InputError :message="errors.appointment_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_visit_queue_entry">قائمة الانتظار</Label>
                            <select id="edit_visit_queue_entry" name="queue_entry_id" class="pattern-field-clay h-10 px-3 py-2" :value="editingVisit.queue_entry_id !== null ? String(editingVisit.queue_entry_id) : ''">
                                <option value="">بدون قائمة انتظار</option>
                                <option v-for="queueEntry in queue_entries" :key="`edit-visit-queue-${queueEntry.id}`" :value="queueEntry.id">{{ queueEntry.label }}</option>
                            </select>
                            <InputError :message="errors.queue_entry_id" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_visit_complaint">الشكوى الرئيسية</Label>
                        <textarea id="edit_visit_complaint" name="chief_complaint" rows="2" class="pattern-field-clay" :value="editingVisit.chief_complaint ?? ''"></textarea>
                        <InputError :message="errors.chief_complaint" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_visit_clinical_notes">ملاحظات سريرية</Label>
                        <textarea id="edit_visit_clinical_notes" name="clinical_notes" rows="3" class="pattern-field-clay" :value="editingVisit.clinical_notes ?? ''"></textarea>
                        <InputError :message="errors.clinical_notes" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_visit_diagnosis_notes">ملاحظات التشخيص</Label>
                        <textarea id="edit_visit_diagnosis_notes" name="diagnosis_notes" rows="3" class="pattern-field-clay" :value="editingVisit.diagnosis_notes ?? ''"></textarea>
                        <InputError :message="errors.diagnosis_notes" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_visit_treatment_plan">خطة العلاج</Label>
                        <textarea id="edit_visit_treatment_plan" name="treatment_plan" rows="3" class="pattern-field-clay" :value="editingVisit.treatment_plan ?? ''"></textarea>
                        <InputError :message="errors.treatment_plan" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="ghost" :disabled="processing" class="min-h-[44px]" @click="closeEditVisit">إلغاء</Button>
                        <Button type="submit" variant="clay" :disabled="processing" class="min-h-[44px]">حفظ التغييرات</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <ConfirmationDialog
            :open="isConfirmOpen"
            :options="confirmOptions"
            @confirm="handleConfirmDelete"
            @cancel="handleConfirmCancel"
            @update:open="handleConfirmCancel"
        />
    </div>
</template>
