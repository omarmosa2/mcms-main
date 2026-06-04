<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, Kanban } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import VisitController from '@/actions/App/Http/Controllers/Visits/VisitController';
import { Button } from '@/components/ui/button';
import {
    FilterBar,
    FilterSearch,
    FilterSelect,
} from '@/components/ui/filter';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';
import { visitStatusClass, visitStatusDotClass, visitStatusLabel, formatDateTime } from './helpers';
import type { ActiveFilter, PaginatedResponse, Visit, VisitSortField, SortDirection } from './types';

const props = defineProps<{
    visits: PaginatedResponse<Visit>;
    status_options: string[];
    filters: {
        status: string | null;
        search: string | null;
        per_page: number;
        sort_by: VisitSortField | null;
        sort_direction: SortDirection | null;
    };
}>();

const emit = defineEmits<{
    deleteVisit: [visit: Visit];
    bulkDelete: [ids: number[]];
}>();

const { can } = usePermissions();

const localSearch = ref<string>(props.filters.search ?? '');
const localStatus = ref<string>(props.filters.status ?? '');
const localRowsPerPage = ref<number>(props.filters.per_page);
const localPage = ref<number>(props.visits.meta.current_page);

const allowedSortFields: VisitSortField[] = [
    'visit_number',
    'status',
    'started_at',
    'completed_at',
];

const resolveInitialSortBy = (): VisitSortField => {
    const sortBy = props.filters.sort_by;

    if (sortBy !== null && allowedSortFields.includes(sortBy as VisitSortField)) {
        return sortBy;
    }

    return 'started_at';
};

const localSortBy = ref<VisitSortField>(resolveInitialSortBy());
const localSortDirection = ref<SortDirection>(
    props.filters.sort_direction === 'asc' ? 'asc' : 'desc',
);

const visibleVisits = computed<Visit[]>(() => props.visits.data);
const totalLocalPages = computed<number>(() => {
    return Math.max(1, props.visits.meta.last_page);
});
const localVisibleFrom = computed<number>(() => {
    return props.visits.meta.from ?? 0;
});
const localVisibleTo = computed<number>(() => {
    return props.visits.meta.to ?? 0;
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
        props.filters.search,
        props.filters.status,
        props.filters.per_page,
        props.filters.sort_by,
        props.filters.sort_direction,
        props.visits.meta.current_page,
    ],
    () => {
        isSyncingFromServer.value = true;
        localSearch.value = props.filters.search ?? '';
        localStatus.value = props.filters.status ?? '';
        localRowsPerPage.value = props.filters.per_page;
        localSortBy.value = resolveInitialSortBy();
        localSortDirection.value = props.filters.sort_direction === 'asc' ? 'asc' : 'desc';
        localPage.value = props.visits.meta.current_page;
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

const activeFilters = computed<ActiveFilter[]>(() => {
    const f: ActiveFilter[] = [];

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

    return [...opts, ...props.status_options.map((s: string) => ({ label: visitStatusLabel(s), value: s }))];
});

const handleRemoveFilter = (key: string) => {
    if (key === 'search') {
        localSearch.value = '';
    } else if (key === 'status') {
        localStatus.value = '';
    }
};

const handleBulkDelete = (): void => {
    emit('bulkDelete', [...selectedVisitIds.value]);
};
</script>

<template>
    <div class="card-float p-5">
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
                        class="h-9 w-full rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-[#0EA5E9]/40 focus:outline-none focus:ring-1 focus:ring-[#0EA5E9]/20"
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
            <Button type="button" variant="destructive" size="sm" class="h-9 rounded-md" @click="handleBulkDelete">حذف المحدد ({{ selectedVisitIds.length }})</Button>
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
                                    @click="$emit('viewVisit', visit)"
                                >
                                    عرض
                                </Button>
                                <Button
                                    v-if="canEditVisit && visit.status !== 'completed'"
                                    type="button"
                                    variant="default"
                                    size="sm"
                                    class="h-8 px-2 text-xs rounded-md bg-[#0EA5E9] hover:bg-[#0284C7]"
                                    @click="$emit('editVisit', visit)"
                                >
                                    تعديل
                                </Button>
                                <Link
                                    v-if="visit.status === 'started' && canTransitionVisit"
                                    :href="VisitController.transitionStatus(visit.id)"
                                    method="patch"
                                    as="button"
                                    :data="{ status: 'in_progress' }"
                                    class="inline-flex h-8 items-center rounded-md border border-slate-200/80 bg-white px-2 text-xs font-medium text-slate-600 transition hover:border-[#0EA5E9]/30 hover:text-[#0EA5E9]"
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
                                    @click="emit('deleteVisit', visit)"
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
</template>