<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, SlidersHorizontal } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import QueueEntryController from '@/actions/App/Http/Controllers/Queue/QueueEntryController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';
import type { PaginatedResponse, QueueEntry, QueueSortField, SortDirection } from './types';

const props = defineProps<{
    queueEntries: PaginatedResponse<QueueEntry>;
    statusOptions: string[];
    filters: {
        status: string | null;
        queue_date: string | null;
        search: string | null;
        per_page: number;
        sort_by: QueueSortField | null;
        sort_direction: SortDirection | null;
    };
    isLive: boolean;
    selectedEntryIds: number[];
}>();

const emit = defineEmits<{
    'update:isLive': [value: boolean];
    'update:selectedEntryIds': [value: number[]];
    viewEntry: [entry: QueueEntry];
    deleteEntry: [entry: QueueEntry];
}>();

const { can } = usePermissions();
const toast = useToast();
const localSearch = ref<string>(props.filters.search ?? '');
const localStatus = ref<string>(props.filters.status ?? '');
const localQueueDate = ref<string>(props.filters.queue_date ?? '');
const localRowsPerPage = ref<number>(props.filters.per_page);
const localPage = ref<number>(props.queueEntries.meta.current_page);
const allowedSortFields: QueueSortField[] = [
    'queue_number',
    'queue_date',
    'priority',
    'status',
    'checked_in_at',
];

const resolveInitialSortBy = (): QueueSortField => {
    const sortBy = props.filters.sort_by;

    if (sortBy !== null && allowedSortFields.includes(sortBy as QueueSortField)) {
        return sortBy;
    }

    return 'queue_date';
};

const localSortBy = ref<QueueSortField>(resolveInitialSortBy());
const localSortDirection = ref<SortDirection>(
    props.filters.sort_direction === 'asc' ? 'asc' : 'desc',
);
const visibleQueueEntries = computed<QueueEntry[]>(() => props.queueEntries.data);
const totalLocalPages = computed<number>(() => {
    return Math.max(1, props.queueEntries.meta.last_page);
});
const localVisibleFrom = computed<number>(() => {
    return props.queueEntries.meta.from ?? 0;
});
const localVisibleTo = computed<number>(() => {
    return props.queueEntries.meta.to ?? 0;
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
        props.filters.search,
        props.filters.status,
        props.filters.queue_date,
        props.filters.per_page,
        props.filters.sort_by,
        props.filters.sort_direction,
        props.queueEntries.meta.current_page,
    ],
    () => {
        isSyncingFromServer.value = true;
        localSearch.value = props.filters.search ?? '';
        localStatus.value = props.filters.status ?? '';
        localQueueDate.value = props.filters.queue_date ?? '';
        localRowsPerPage.value = props.filters.per_page;
        localSortBy.value = resolveInitialSortBy();
        localSortDirection.value = props.filters.sort_direction === 'asc' ? 'asc' : 'desc';
        localPage.value = props.queueEntries.meta.current_page;
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
});

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
        props.selectedEntryIds.includes(entryId),
    );
});

watch(
    () => deletableQueueEntryIds.value,
    (ids) => {
        const filtered = props.selectedEntryIds.filter((id) =>
            ids.includes(id),
        );
        if (filtered.length !== props.selectedEntryIds.length) {
            emit('update:selectedEntryIds', filtered);
        }
    },
);

const toggleAllQueueEntriesSelection = (event: Event): void => {
    const target = event.target as HTMLInputElement;

    emit('update:selectedEntryIds', target.checked ? [...deletableQueueEntryIds.value] : []);
};

const clearSelectedQueueEntries = (): void => {
    emit('update:selectedEntryIds', []);
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

const handleDelete = (entry: QueueEntry): void => {
    emit('deleteEntry', entry);
};
</script>

<template>
    <section class="glass-panel-soft p-5 xl:col-span-3">
        <div
            class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3"
        >
            <h3 class="pattern-typographic-title text-[0.76rem]">
                سجلات قائمة الانتظار
            </h3>
            <span class="text-xs text-muted-foreground">
                الإجمالي: {{ queueEntries.meta.total }}
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
                        v-for="status in statusOptions"
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
                    @click="emit('update:isLive', !isLive)"
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
                    class="inline-flex items-center gap-1 rounded-full border border-border/70 bg-background/80 px-2.5 py-1 text-[0.68rem] font-semibold tracking-normal text-muted-foreground uppercase"
                >
                    <SlidersHorizontal class="size-3.5" />
                    فلترة مباشرة
                </span>
                <Button
                    type="button"
                    variant="outline"
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
                can('queue.manage') && selectedEntryIds.length > 0
            "
            v-bind="QueueEntryController.bulkDestroy.form()"
            class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3"
            v-slot="{ processing }"
        >
            <input
                v-for="entryId in selectedEntryIds"
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
                حذف المحدد ({{ selectedEntryIds.length }})
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
                                :checked="selectedEntryIds.includes(entry.id)"
                                type="checkbox"
                                class="size-4 rounded border-border"
                                :value="entry.id"
                                @change="
                                    ($event) => {
                                        const checked = ($event.target as HTMLInputElement).checked;
                                        if (checked) {
                                            emit('update:selectedEntryIds', [...selectedEntryIds, entry.id]);
                                        } else {
                                            emit('update:selectedEntryIds', selectedEntryIds.filter((id) => id !== entry.id));
                                        }
                                    }
                                "
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
                                    variant="outline"
                                    size="sm"
                                    class="h-8 px-3 text-xs"
                                    @click="emit('viewEntry', entry)"
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
                                    v-slot="{ processing: statusProcessing }"
                                >
                                    <select
                                        name="status"
                                        class="pattern-field-clay h-8 px-2 py-1 text-xs"
                                    >
                                        <option value="">
                                            تعيين الحالة
                                        </option>
                                        <option
                                            v-for="status in statusOptions"
                                            :key="status"
                                            :value="status"
                                        >
                                            {{ queueStatusLabel(status) }}
                                        </option>
                                    </select>
                                    <Button
                                        type="submit"
                                        variant="default"
                                        size="sm"
                                        class="h-8 px-2 text-xs"
                                        :disabled="statusProcessing"
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
                                    @click="handleDelete(entry)"
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
                عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من {{ queueEntries.meta.total }} سجل
            </p>
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
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
                    variant="outline"
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
</template>