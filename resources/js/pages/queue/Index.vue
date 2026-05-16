<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, SlidersHorizontal, Download, FileText } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import QueueEntryController from '@/actions/App/Http/Controllers/Queue/QueueEntryController';
import QueueEntryExportController from '@/actions/App/Http/Controllers/Queue/QueueEntryExportController';
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
    name?: string;
};

type QueueEntry = {
    id: number;
    appointment_id: number | null;
    patient_id: number;
    assigned_doctor_id: number | null;
    queue_date: string;
    queue_number: number;
    priority: number;
    status: string;
    notes: string | null;
    checked_in_at: string | null;
    called_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    patient?: {
        id?: number;
        full_name?: string;
    };
    appointment?: {
        id?: number;
        appointment_number?: string;
    };
    assigned_doctor?: {
        id?: number;
        name?: string;
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

type QueueSortField =
    | 'queue_number'
    | 'queue_date'
    | 'priority'
    | 'status'
    | 'checked_in_at';

type SortDirection = 'asc' | 'desc';

const {
    queue_entries,
    patients,
    appointments,
    doctors,
    status_options,
    filters,
} = defineProps<{
    queue_entries: PaginatedResponse<QueueEntry>;
    patients: Option[];
    appointments: Option[];
    doctors: Option[];
    status_options: string[];
    filters: {
        status: string | null;
        queue_date: string | null;
        search: string | null;
        per_page: number;
        sort_by: QueueSortField | null;
        sort_direction: SortDirection | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'قائمة الانتظار',
                href: QueueEntryController.index(),
            },
        ],
    },
});

const { can } = usePermissions();
const { isOpen: isConfirmOpen, options: confirmOptions, confirm, handleConfirm: handleConfirmDelete, handleCancel: handleConfirmCancel } = useConfirm();
const toast = useToast();
const page = usePage();
const viewingQueueEntry = ref<QueueEntry | null>(null);
const isLive = ref(false);
const isCreateSheetOpen = ref(false);
let pollInterval: ReturnType<typeof setInterval> | null = null;
const localSearch = ref<string>(filters.search ?? '');
const localStatus = ref<string>(filters.status ?? '');
const localQueueDate = ref<string>(filters.queue_date ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(queue_entries.meta.current_page);
const allowedSortFields: QueueSortField[] = [
    'queue_number',
    'queue_date',
    'priority',
    'status',
    'checked_in_at',
];
const resolveInitialSortBy = (): QueueSortField => {
    const sortBy = filters.sort_by;

    if (sortBy !== null && allowedSortFields.includes(sortBy as QueueSortField)) {
        return sortBy;
    }

    return 'queue_date';
};
const localSortBy = ref<QueueSortField>(resolveInitialSortBy());
const localSortDirection = ref<SortDirection>(
    filters.sort_direction === 'asc' ? 'asc' : 'desc',
);
const visibleQueueEntries = computed<QueueEntry[]>(() => queue_entries.data);
const totalLocalPages = computed<number>(() => {
    return Math.max(1, queue_entries.meta.last_page);
});
const localVisibleFrom = computed<number>(() => {
    return queue_entries.meta.from ?? 0;
});
const localVisibleTo = computed<number>(() => {
    return queue_entries.meta.to ?? 0;
});
const defaultRowsPerPage = 15;
const isSyncingFromServer = ref(false);
let queueFiltersDebounceTimeout: ReturnType<typeof setTimeout> | null = null;
const buildIndexQuery = (
    overrides: Partial<{
        status: string;
        queue_date: string;
        search: string;
        per_page: number;
        page: number;
        sort_by: QueueSortField;
        sort_direction: SortDirection;
    }> = {},
): {
    status?: string;
    queue_date?: string;
    search?: string;
    per_page: number;
    page: number;
    sort_by: QueueSortField;
    sort_direction: SortDirection;
} => {
    const query: {
        status?: string;
        queue_date?: string;
        search?: string;
        per_page: number;
        page: number;
        sort_by: QueueSortField;
        sort_direction: SortDirection;
    } = {
        status: localStatus.value.trim(),
        queue_date: localQueueDate.value.trim(),
        search: localSearch.value.trim(),
        per_page: localRowsPerPage.value,
        page: localPage.value,
        sort_by: localSortBy.value,
        sort_direction: localSortDirection.value,
        ...overrides,
    };

    return query;
};
const reloadQueueEntries = (
    overrides: Partial<{
        status: string;
        queue_date: string;
        search: string;
        per_page: number;
        page: number;
        sort_by: QueueSortField;
        sort_direction: SortDirection;
    }> = {},
    debounce = false,
): void => {
    if (isSyncingFromServer.value) {
        return;
    }

    const executeReload = (): void => {
        router.cancelAll();
        router.get(QueueEntryController.index.url(), buildIndexQuery(overrides), {
            only: ['queue_entries', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    if (debounce) {
        if (queueFiltersDebounceTimeout !== null) {
            clearTimeout(queueFiltersDebounceTimeout);
        }

        queueFiltersDebounceTimeout = setTimeout(executeReload, 320);

        return;
    }

    executeReload();
};
const sortIconFor = (field: QueueSortField) => {
    if (localSortBy.value !== field) {
        return ArrowUpDown;
    }

    return localSortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};
const toggleSort = (field: QueueSortField): void => {
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
    localQueueDate.value = '';
    localRowsPerPage.value = defaultRowsPerPage;
    localSortBy.value = 'queue_date';
    localSortDirection.value = 'desc';
    localPage.value = 1;
    isSyncingFromServer.value = false;
    reloadQueueEntries({
        status: '',
        queue_date: '',
        search: '',
        per_page: defaultRowsPerPage,
        page: 1,
        sort_by: 'queue_date',
        sort_direction: 'desc',
    });
};
const goToPreviousPage = (): void => {
    if (localPage.value <= 1) {
        return;
    }

    localPage.value -= 1;
    reloadQueueEntries({ page: localPage.value });
};
const goToNextPage = (): void => {
    if (localPage.value >= totalLocalPages.value) {
        return;
    }

    localPage.value += 1;
    reloadQueueEntries({ page: localPage.value });
};
watch(
    () => [
        filters.search,
        filters.status,
        filters.queue_date,
        filters.per_page,
        filters.sort_by,
        filters.sort_direction,
        queue_entries.meta.current_page,
    ],
    () => {
        isSyncingFromServer.value = true;
        localSearch.value = filters.search ?? '';
        localStatus.value = filters.status ?? '';
        localQueueDate.value = filters.queue_date ?? '';
        localRowsPerPage.value = filters.per_page;
        localSortBy.value = resolveInitialSortBy();
        localSortDirection.value = filters.sort_direction === 'asc' ? 'asc' : 'desc';
        localPage.value = queue_entries.meta.current_page;
        isSyncingFromServer.value = false;
    },
    { immediate: true },
);
watch(
    () => localSearch.value,
    () => {
        localPage.value = 1;
        reloadQueueEntries({ page: 1, search: localSearch.value.trim() }, true);
    },
);
watch(
    () => localStatus.value,
    () => {
        localPage.value = 1;
        reloadQueueEntries({ page: 1, status: localStatus.value.trim() });
    },
);
watch(
    () => localQueueDate.value,
    () => {
        localPage.value = 1;
        reloadQueueEntries({ page: 1, queue_date: localQueueDate.value.trim() });
    },
);
watch(
    () => localRowsPerPage.value,
    () => {
        localPage.value = 1;
        reloadQueueEntries({ page: 1, per_page: localRowsPerPage.value });
    },
);
watch(
    () => [localSortBy.value, localSortDirection.value],
    () => {
        localPage.value = 1;
        reloadQueueEntries({
            page: 1,
            sort_by: localSortBy.value,
            sort_direction: localSortDirection.value,
        });
    },
);
onBeforeUnmount(() => {
    if (queueFiltersDebounceTimeout !== null) {
        clearTimeout(queueFiltersDebounceTimeout);
        queueFiltersDebounceTimeout = null;
    }

    stopPolling();
});

const startPolling = (): void => {
    if (pollInterval !== null) {
        clearInterval(pollInterval);
    }

    isLive.value = true;
    pollInterval = setInterval(() => {
        router.reload({
            only: ['queue_entries'],
            preserveUrl: true,
        });
    }, 5000);
};

const stopPolling = (): void => {
    if (pollInterval !== null) {
        clearInterval(pollInterval);
        pollInterval = null;
    }

    isLive.value = false;
};

const toggleLiveUpdates = (): void => {
    if (isLive.value) {
        stopPolling();
    } else {
        startPolling();
    }
};

onMounted(() => {
    startPolling();
});
const selectedQueueEntryIds = ref<number[]>([]);
const deletableQueueEntryIds = computed<number[]>(() =>
    visibleQueueEntries.value
        .filter(
            (entry) => entry.status === 'waiting' || entry.status === 'skipped',
        )
        .map((entry) => entry.id),
);

const areAllDeletableQueueEntriesSelected = computed<boolean>(() => {
    if (deletableQueueEntryIds.value.length === 0) {
        return false;
    }

    return deletableQueueEntryIds.value.every((entryId) =>
        selectedQueueEntryIds.value.includes(entryId),
    );
});

watch(
    () => deletableQueueEntryIds.value,
    (ids) => {
        selectedQueueEntryIds.value = selectedQueueEntryIds.value.filter((id) =>
            ids.includes(id),
        );
    },
);

const toggleAllQueueEntriesSelection = (event: Event): void => {
    const target = event.target as HTMLInputElement;

    selectedQueueEntryIds.value = target.checked
        ? [...deletableQueueEntryIds.value]
        : [];
};

const clearSelectedQueueEntries = (): void => {
    selectedQueueEntryIds.value = [];
};

const openViewQueueEntry = (entry: QueueEntry): void => {
    viewingQueueEntry.value = entry;
};

const closeViewQueueEntry = (): void => {
    viewingQueueEntry.value = null;
};

const queueStatusClass = (status: string): string => {
    if (status === 'waiting') {
        return 'border-warning-300/70 bg-warning-50 text-warning-800 dark:border-warning-500/35 dark:bg-warning-500/15 dark:text-warning-100';
    }

    if (status === 'in_service') {
        return 'border-info-300/70 bg-info-50 text-info-800 dark:border-info-500/35 dark:bg-info-500/15 dark:text-info-100';
    }

    if (status === 'completed') {
        return 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/35 dark:bg-success-500/15 dark:text-success-100';
    }

    if (status === 'skipped' || status === 'canceled') {
        return 'border-destructive/70 bg-destructive/10 text-destructive dark:border-destructive/35 dark:bg-destructive/15 dark:text-destructive-foreground';
    }

    return 'border-border/70 bg-background/80';
};

const queueStatusDotClass = (status: string): string => {
    if (status === 'completed') {
        return 'bg-success-500';
    }

    if (status === 'in_service') {
        return 'bg-info-500';
    }

    if (status === 'waiting') {
        return 'bg-warning-500';
    }

    if (status === 'skipped' || status === 'canceled') {
        return 'bg-destructive';
    }

    return 'bg-muted-foreground';
};

const queueStatusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        waiting: 'في الانتظار',
        in_service: 'قيد الخدمة',
        completed: 'مكتمل',
        skipped: 'تم التخطي',
        canceled: 'ملغي',
    };

    return labels[status] ?? status;
};

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

const deleteQueueEntry = async (entry: QueueEntry) => {
    const confirmed = await confirm({
        title: 'إزالة من الطابور',
        description: `هل أنت متأكد من حذف رقم الانتظار "${entry.queue_number}" للمريض "${entry.patient?.full_name || entry.patient?.first_name + ' ' + entry.patient?.last_name}"؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'إزالة',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(QueueEntryController.destroy(entry.id), {
            onSuccess: () => {
                toast.success('تم إزالة المريض من الطابور');
            },
            onError: () => {
                toast.error('فشل إزالة المريض من الطابور');
            },
        });
    }
};
</script>

<template>
    <Head title="قائمة الانتظار" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="page-title">قائمة الانتظار</h1>
                    <p class="mt-1 text-sm text-muted-foreground">إدارة تدفق المرضى والانتظار الفوري.</p>
                </div>
                <span class="inline-flex items-center rounded-full border border-border/60 bg-muted/40 px-2.5 py-0.5 text-[0.7rem] font-medium text-muted-foreground">
                    {{ activeRoleLabel }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <a
                    :href="QueueEntryExportController.export()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 bg-background/60 px-3 py-2 text-xs font-medium text-muted-foreground transition hover:text-foreground min-h-[44px]"
                >
                    <Download class="size-3.5" />
                    تصدير Excel
                </a>
                <a
                    :href="QueueEntryExportController.exportPdf()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 bg-background/60 px-3 py-2 text-xs font-medium text-muted-foreground transition hover:text-foreground min-h-[44px]"
                >
                    <FileText class="size-3.5" />
                    تصدير PDF
                </a>

                <Form
                    v-if="can('queue.call_next')"
                    v-bind="QueueEntryController.callNext.form()"
                    class="flex items-center gap-2"
                    v-slot="{ processing }"
                >
                    <input
                        v-if="filters.queue_date"
                        type="hidden"
                        name="queue_date"
                        :value="filters.queue_date"
                    />
                    <Button
                        type="submit"
                        variant="clay"
                        size="sm"
                        :disabled="processing"
                    >
                        استدعاء التالي
                    </Button>
                </Form>
                <Button
                    v-if="can('queue.manage')"
                    variant="clay"
                    size="sm"
                    class="h-8 rounded-lg px-3 text-xs"
                    @click="isCreateSheetOpen = true"
                >
                    إضافة إلى الطابور
                </Button>
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-3">
            <section
                :class="[
                    'glass-panel-soft p-5',
                    can('queue.manage') ? 'xl:col-span-3' : 'xl:col-span-3',
                ]"
            >
                <div
                    class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3"
                >
                    <h3 class="pattern-typographic-title text-[0.76rem]">
                        سجلات قائمة الانتظار
                    </h3>
                    <span class="text-xs text-muted-foreground">
                        الإجمالي: {{ queue_entries.meta.total }}
                    </span>
                </div>

                <div
                    class="mb-4 grid gap-3 rounded-2xl border border-border/70 bg-background/60 p-4 md:grid-cols-4 md:items-end"
                >
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="queue_search_filter">بحث</Label>
                        <Input
                            id="queue_search_filter"
                            v-model="localSearch"
                            placeholder="مريض، طبيب، موعد، رقم الطابور"
                            class="pattern-field-clay"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="queue_status_filter">الحالة</Label>
                        <select
                            id="queue_status_filter"
                            v-model="localStatus"
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="">كل الحالات</option>
                            <option
                                v-for="status in status_options"
                                :key="`queue-filter-${status}`"
                                :value="status"
                            >
                                {{ queueStatusLabel(status) }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="queue_date_filter">التاريخ</Label>
                        <Input
                            id="queue_date_filter"
                            v-model="localQueueDate"
                            type="date"
                            class="pattern-field-clay"
                        />
                    </div>

                    <div class="grid gap-2 md:max-w-40">
                        <Label for="queue_per_page">صفوف لكل صفحة</Label>
                        <select
                            id="queue_per_page"
                            v-model.number="localRowsPerPage"
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>

                    <div
                        class="flex flex-wrap gap-2 md:col-span-4 md:justify-end"
                    >
                        <Button
                            type="button"
                            :variant="isLive ? 'clay' : 'neumorphic'"
                            size="sm"
                            class="h-9 px-4 text-sm"
                            @click="toggleLiveUpdates"
                        >
                            <span
                                :class="[
                                    'ms-2 inline-block size-2 rounded-full',
                                    isLive ? 'animate-pulse motion-reduce:animate-none bg-success-500' : 'bg-muted-foreground',
                                ]"
                            />
                            {{ isLive ? 'مباشر' : 'غير متصل' }}
                        </Button>
                        <span
                            class="inline-flex items-center gap-1 rounded-full border border-border/70 bg-background/80 px-2.5 py-1 text-[0.68rem] font-semibold tracking-[0.08em] text-muted-foreground uppercase"
                        >
                            <SlidersHorizontal class="size-3.5" />
                            فلترة مباشرة
                        </span>
                        <Button
                            type="button"
                            variant="neumorphic"
                            size="sm"
                            class="h-9 px-4 text-sm"
                            @click="resetLocalFilters"
                        >
                            إعادة تعيين
                        </Button>
                    </div>
                </div>

                <Form
                    v-if="
                        can('queue.manage') && selectedQueueEntryIds.length > 0
                    "
                    v-bind="QueueEntryController.bulkDestroy.form()"
                    class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3"
                    v-slot="{ processing }"
                >
                    <input
                        v-for="entryId in selectedQueueEntryIds"
                        :key="`selected-queue-entry-${entryId}`"
                        type="hidden"
                        name="ids[]"
                        :value="entryId"
                    />

                    <Button
                        type="submit"
                        variant="destructive"
                        size="sm"
                        :disabled="processing"
                    >
                        حذف المحدد ({{ selectedQueueEntryIds.length }})
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="clearSelectedQueueEntries"
                    >
                        إلغاء التحديد
                    </Button>
                </Form>

                <div class="ui-table-shell">
                    <table class="ui-table md:min-w-[980px]">
                        <thead>
                            <tr>
                                <th
                                    v-if="can('queue.manage')"
                                    class="px-3 py-2"
                                >
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-border"
                                        :checked="
                                            areAllDeletableQueueEntriesSelected
                                        "
                                        @change="toggleAllQueueEntriesSelection"
                                    />
                                </th>
                                <th class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                        @click="toggleSort('queue_number')"
                                    >
                                        رقم الطابور
                                        <component
                                            :is="sortIconFor('queue_number')"
                                            class="size-3.5"
                                        />
                                    </button>
                                </th>
                                <th class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                        @click="toggleSort('queue_date')"
                                    >
                                        التاريخ
                                        <component
                                            :is="sortIconFor('queue_date')"
                                            class="size-3.5"
                                        />
                                    </button>
                                </th>
                                <th class="px-3 py-2">المريض</th>
                                <th class="px-3 py-2">الموعد</th>
                                <th class="px-3 py-2">الطبيب</th>
                                <th class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                        @click="toggleSort('priority')"
                                    >
                                        الأولوية
                                        <component
                                            :is="sortIconFor('priority')"
                                            class="size-3.5"
                                        />
                                    </button>
                                </th>
                                <th class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                        @click="toggleSort('status')"
                                    >
                                        الحالة
                                        <component
                                            :is="sortIconFor('status')"
                                            class="size-3.5"
                                        />
                                    </button>
                                </th>
                                <th class="px-3 py-2 text-right">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="entry in visibleQueueEntries"
                                :key="entry.id"
                                class="ui-table-row align-top"
                                :class="{
                                    'motion-reduce:animate-none animate-pulse-glow': isLive && entry.status === 'called',
                                }"
                            >
                                <td
                                    v-if="can('queue.manage')"
                                    class="px-3 py-2"
                                    data-label="تحديد"
                                >
                                    <input
                                        v-if="
                                            entry.status === 'waiting' ||
                                            entry.status === 'skipped'
                                        "
                                        v-model="selectedQueueEntryIds"
                                        type="checkbox"
                                        class="size-4 rounded border-border"
                                        :value="entry.id"
                                    />
                                </td>
                                <td
                                    class="px-3 py-2 font-medium"
                                    data-label="رقم الطابور"
                                >
                                    #{{ entry.queue_number }}
                                </td>
                                <td class="px-3 py-2" data-label="التاريخ">
                                    {{ entry.queue_date }}
                                </td>
                                <td class="px-3 py-2" data-label="المريض">
                                    {{ entry.patient?.full_name ?? '-' }}
                                </td>
                                <td class="px-3 py-2" data-label="الموعد">
                                    {{
                                        entry.appointment?.appointment_number ??
                                        '-'
                                    }}
                                </td>
                                <td class="px-3 py-2" data-label="الطبيب">
                                    {{ entry.assigned_doctor?.name ?? '-' }}
                                </td>
                                <td class="px-3 py-2" data-label="الأولوية">
                                    <span
                                        class="inline-flex rounded-full border border-border/70 bg-background/80 px-2.5 py-1 text-xs font-medium"
                                    >
                                        {{ entry.priority }}
                                    </span>
                                </td>
                                <td class="px-3 py-2" data-label="الحالة">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium capitalize"
                                        :class="queueStatusClass(entry.status)"
                                    >
                                        <span
                                            class="w-1.5 h-1.5 rounded-full"
                                            :class="queueStatusDotClass(entry.status)"
                                        ></span>
                                        {{ queueStatusLabel(entry.status) }}
                                    </span>
                                </td>
                                <td
                                    class="table-cell-actions px-3 py-2 md:text-right"
                                    data-label="الإجراءات"
                                >
                                    <div
                                        class="flex flex-wrap justify-end gap-2"
                                    >
                                        <Button
                                            type="button"
                                            variant="neumorphic"
                                            size="sm"
                                            class="h-8 px-3 text-xs"
                                            @click="openViewQueueEntry(entry)"
                                        >
                                            عرض
                                        </Button>
                                        <Form
                                            v-if="can('queue.manage')"
                                            v-bind="
                                                QueueEntryController.updateStatus.form(
                                                    entry.id,
                                                )
                                            "
                                            class="flex items-center gap-2"
                                            v-slot="{ processing }"
                                        >
                                            <select
                                                name="status"
                                                class="pattern-field-clay h-8 px-2 py-1 text-xs"
                                            >
                                                <option value="">
                                                    تعيين الحالة
                                                </option>
                                                <option
                                                    v-for="status in status_options"
                                                    :key="status"
                                                    :value="status"
                                                >
                                                    {{ queueStatusLabel(status) }}
                                                </option>
                                            </select>
                                            <Button
                                                type="submit"
                                                variant="clay"
                                                size="sm"
                                                class="h-8 px-2 text-xs"
                                                :disabled="processing"
                                            >
                                                تحديث
                                            </Button>
                                        </Form>

                                        <Button
                                            v-if="can('queue.manage')"
                                            type="button"
                                            size="sm"
                                            variant="destructive"
                                            class="h-8 px-3 text-xs"
                                            @click="deleteQueueEntry(entry)"
                                        >
                                            حذف
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-if="visibleQueueEntries.length === 0"
                                class="table-empty-state"
                            >
                                <td
                                    :colspan="can('queue.manage') ? 9 : 8"
                                    class="px-3 py-10 text-center text-muted-foreground"
                                >
                                    لا توجد سجلات في قائمة الانتظار.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2"
                >
                    <p class="text-xs text-muted-foreground">
                        عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من {{ queue_entries.meta.total }} سجل
                    </p>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="neumorphic"
                            size="sm"
                            class="h-8 px-3 text-xs"
                            :disabled="localPage === 1"
                            @click="goToPreviousPage"
                        >
                            السابق
                        </Button>
                        <span class="text-xs font-semibold text-foreground/85">
                            صفحة {{ localPage }} / {{ totalLocalPages }}
                        </span>
                        <Button
                            type="button"
                            variant="neumorphic"
                            size="sm"
                            class="h-8 px-3 text-xs"
                            :disabled="localPage >= totalLocalPages"
                            @click="goToNextPage"
                        >
                            التالي
                        </Button>
                    </div>
                </div>
            </section>
        </div>

        <Sheet :open="isCreateSheetOpen" @update:open="isCreateSheetOpen = $event">
            <SheetContent side="left" class="w-full sm:max-w-lg">
                <SheetHeader class="text-right">
                    <SheetTitle>إضافة إلى الطابور</SheetTitle>
                    <SheetDescription>تسجيل مريض جديد في قائمة الانتظار.</SheetDescription>
                </SheetHeader>

                <Form
                    v-bind="QueueEntryController.store.form()"
                    class="mt-6 space-y-4"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="patient_id">المريض</Label>
                        <select
                            id="patient_id"
                            name="patient_id"
                            required
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="">اختر مريض</option>
                            <option
                                v-for="patient in patients"
                                :key="patient.id"
                                :value="patient.id"
                            >
                                {{ patient.full_name }}
                            </option>
                        </select>
                        <InputError :message="errors.patient_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="appointment_id">الموعد</Label>
                        <select
                            id="appointment_id"
                            name="appointment_id"
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="">بدون موعد</option>
                            <option
                                v-for="appointment in appointments"
                                :key="appointment.id"
                                :value="appointment.id"
                            >
                                {{ appointment.appointment_number }}
                            </option>
                        </select>
                        <InputError :message="errors.appointment_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="assigned_doctor_id">الطبيب المعين</Label>
                        <select
                            id="assigned_doctor_id"
                            name="assigned_doctor_id"
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="">غير معين</option>
                            <option
                                v-for="doctor in doctors"
                                :key="doctor.id"
                                :value="doctor.id"
                            >
                                {{ doctor.name }}
                            </option>
                        </select>
                        <InputError :message="errors.assigned_doctor_id" />
                    </div>

                    <div class="grid gap-2 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="queue_date">تاريخ الطابور</Label>
                            <Input
                                id="queue_date"
                                name="queue_date"
                                type="date"
                                class="pattern-field-clay"
                            />
                            <InputError :message="errors.queue_date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="priority">الأولوية (0-9)</Label>
                            <Input
                                id="priority"
                                name="priority"
                                type="number"
                                min="0"
                                max="9"
                                value="0"
                                class="pattern-field-clay"
                            />
                            <InputError :message="errors.priority" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">ملاحظات</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="3"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <Button
                        :disabled="processing"
                        variant="clay"
                        class="w-full"
                    >
                        إضافة إلى الطابور
                    </Button>
                </Form>
            </SheetContent>
        </Sheet>

        <Dialog
            :open="viewingQueueEntry !== null"
            @update:open="(open) => !open && closeViewQueueEntry()"
        >
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            viewingQueueEntry
                                ? `رقم الطابور #${viewingQueueEntry.queue_number}`
                                : 'تفاصيل سجل الطابور'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        تفاصيل سجل الطابور الكاملة.
                    </DialogDescription>
                </DialogHeader>

                <dl
                    v-if="viewingQueueEntry"
                    class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4 sm:grid-cols-2"
                >
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            المريض
                        </dt>
                        <dd class="text-sm">
                            {{ viewingQueueEntry.patient?.full_name ?? '-' }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            الطبيب
                        </dt>
                        <dd class="text-sm">
                            {{ viewingQueueEntry.assigned_doctor?.name ?? '-' }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            تاريخ الطابور
                        </dt>
                        <dd class="text-sm">
                            {{ viewingQueueEntry.queue_date }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            الأولوية
                        </dt>
                        <dd class="text-sm">
                            {{ viewingQueueEntry.priority }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            الحالة
                        </dt>
                        <dd class="text-sm capitalize">
                            {{ queueStatusLabel(viewingQueueEntry.status) }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            الموعد
                        </dt>
                        <dd class="text-sm">
                            {{
                                viewingQueueEntry.appointment
                                    ?.appointment_number ?? '-'
                            }}
                        </dd>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            ملاحظات
                        </dt>
                        <dd class="text-sm leading-6 text-muted-foreground">
                            {{ viewingQueueEntry.notes ?? 'لا توجد ملاحظات' }}
                        </dd>
                    </div>
                </dl>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="neumorphic"
                        @click="closeViewQueueEntry"
                    >
                        إغلاق
                    </Button>
                </DialogFooter>
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
