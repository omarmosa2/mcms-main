<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Calendar,
    CalendarDays,
    Plus,
    Table2,
    Eye,
    Pencil,
    Download,
    FileText,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import AppointmentExportController from '@/actions/App/Http/Controllers/Appointments/AppointmentExportController';
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
import AppointmentCreateSheet from './components/AppointmentCreateSheet.vue';
import AppointmentDayView from './components/AppointmentDayView.vue';
import AppointmentEditDialog from './components/AppointmentEditDialog.vue';
import AppointmentQuickAddForm from './components/AppointmentQuickAddForm.vue';
import AppointmentTable from './components/AppointmentTable.vue';
import AppointmentTodaySummary from './components/AppointmentTodaySummary.vue';
import AppointmentViewDialog from './components/AppointmentViewDialog.vue';
import type { Appointment, AppointmentSortField, ClinicWorkingHour, SortDirection } from './components/types';

type Option = {
    id: number;
    name?: string;
    full_name?: string;
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

const { appointments, patients, doctors, status_options, filters, clinic_working_hours, today_appointments } =
    defineProps<{
        appointments: PaginatedResponse<Appointment>;
        patients: Option[];
        doctors: Option[];
        status_options: string[];
        clinic_working_hours: ClinicWorkingHour[];
        filters: {
            status: string | null;
            search: string | null;
            per_page: number;
            sort_by: AppointmentSortField | null;
            sort_direction: SortDirection | null;
        };
        today_appointments?: Appointment[];
    }>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'المواعيد',
                href: AppointmentController.index(),
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

const viewingAppointment = ref<Appointment | null>(null);
const editingAppointment = ref<Appointment | null>(null);
const isCreateSheetOpen = ref(false);
const isQuickAddOpen = ref(true);
const localSearch = ref<string>(filters.search ?? '');
const localStatus = ref<string>(filters.status ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(appointments.meta.current_page);
const allowedSortFields: AppointmentSortField[] = [
    'appointment_number',
    'scheduled_for',
    'duration_minutes',
    'status',
];
const resolveInitialSortBy = (): AppointmentSortField => {
    const sortBy = filters.sort_by;

    if (
        sortBy !== null &&
        allowedSortFields.includes(sortBy as AppointmentSortField)
    ) {
        return sortBy;
    }

    return 'scheduled_for';
};
const localSortBy = ref<AppointmentSortField>(resolveInitialSortBy());
const localSortDirection = ref<SortDirection>(
    filters.sort_direction === 'asc' ? 'asc' : 'desc',
);
const visibleAppointments = computed<Appointment[]>(() => appointments.data);
const totalLocalPages = computed<number>(() => {
    return Math.max(1, appointments.meta.last_page);
});
const localVisibleFrom = computed<number>(() => {
    return appointments.meta.from ?? 0;
});
const localVisibleTo = computed<number>(() => {
    return appointments.meta.to ?? 0;
});
const defaultRowsPerPage = 15;
const isSyncingFromServer = ref(false);
let appointmentFiltersDebounceTimeout: ReturnType<typeof setTimeout> | null = null;

const buildIndexQuery = (
    overrides: Partial<{
        status: string;
        search: string;
        per_page: number;
        page: number;
        sort_by: AppointmentSortField;
        sort_direction: SortDirection;
    }> = {},
): {
    status?: string;
    search?: string;
    per_page: number;
    page: number;
    sort_by: AppointmentSortField;
    sort_direction: SortDirection;
} => {
    const query: {
        status?: string;
        search?: string;
        per_page: number;
        page: number;
        sort_by: AppointmentSortField;
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

const reloadAppointments = (
    overrides: Partial<{
        status: string;
        search: string;
        per_page: number;
        page: number;
        sort_by: AppointmentSortField;
        sort_direction: SortDirection;
    }> = {},
    debounce = false,
): void => {
    if (isSyncingFromServer.value) {
        return;
    }

    const executeReload = (): void => {
        router.cancelAll();
        router.get(AppointmentController.index.url(), buildIndexQuery(overrides), {
            only: ['appointments', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    if (debounce) {
        if (appointmentFiltersDebounceTimeout !== null) {
            clearTimeout(appointmentFiltersDebounceTimeout);
        }

        appointmentFiltersDebounceTimeout = setTimeout(executeReload, 320);

        return;
    }

    executeReload();
};

const sortIconFor = (field: AppointmentSortField) => {
    if (localSortBy.value !== field) {
        return ArrowUpDown;
    }

    return localSortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

const toggleSort = (field: AppointmentSortField): void => {
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
    localSortBy.value = 'scheduled_for';
    localSortDirection.value = 'desc';
    localPage.value = 1;
    isSyncingFromServer.value = false;
    reloadAppointments({
        status: '',
        search: '',
        per_page: defaultRowsPerPage,
        page: 1,
        sort_by: 'scheduled_for',
        sort_direction: 'desc',
    });
};

const goToPreviousPage = (): void => {
    if (localPage.value <= 1) {
        return;
    }

    localPage.value -= 1;
    reloadAppointments({ page: localPage.value });
};

const goToNextPage = (): void => {
    if (localPage.value >= totalLocalPages.value) {
        return;
    }

    localPage.value += 1;
    reloadAppointments({ page: localPage.value });
};

watch(
    () => [
        filters.search,
        filters.status,
        filters.per_page,
        filters.sort_by,
        filters.sort_direction,
        appointments.meta.current_page,
    ],
    () => {
        isSyncingFromServer.value = true;
        localSearch.value = filters.search ?? '';
        localStatus.value = filters.status ?? '';
        localRowsPerPage.value = filters.per_page;
        localSortBy.value = resolveInitialSortBy();
        localSortDirection.value = filters.sort_direction === 'asc' ? 'asc' : 'desc';
        localPage.value = appointments.meta.current_page;
        isSyncingFromServer.value = false;
    },
    { immediate: true },
);

watch(
    () => localSearch.value,
    () => {
        localPage.value = 1;
        reloadAppointments({ page: 1, search: localSearch.value.trim() }, true);
    },
);

watch(
    () => localStatus.value,
    () => {
        localPage.value = 1;
        reloadAppointments({ page: 1, status: localStatus.value.trim() });
    },
);

watch(
    () => localRowsPerPage.value,
    () => {
        localPage.value = 1;
        reloadAppointments({ page: 1, per_page: localRowsPerPage.value });
    },
);

watch(
    () => [localSortBy.value, localSortDirection.value],
    () => {
        localPage.value = 1;
        reloadAppointments({
            page: 1,
            sort_by: localSortBy.value,
            sort_direction: localSortDirection.value,
        });
    },
);

onBeforeUnmount(() => {
    if (appointmentFiltersDebounceTimeout !== null) {
        clearTimeout(appointmentFiltersDebounceTimeout);
        appointmentFiltersDebounceTimeout = null;
    }
});

const appointmentStatusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        scheduled: 'مجدول',
        confirmed: 'مؤكد',
        arrived: 'حاضر',
        completed: 'مكتمل',
        canceled: 'ملغي',
        no_show: 'لم يحضر',
    };

    return labels[status] ?? status;
};

const selectedAppointmentIds = ref<number[]>([]);

const deletableAppointmentIds = computed<number[]>(() =>
    visibleAppointments.value
        .filter((appointment) => appointment.status === 'scheduled')
        .map((appointment) => appointment.id),
);

const areAllDeletableAppointmentsSelected = computed<boolean>(() => {
    if (deletableAppointmentIds.value.length === 0) {
        return false;
    }

    return deletableAppointmentIds.value.every((appointmentId) =>
        selectedAppointmentIds.value.includes(appointmentId),
    );
});

watch(
    () => deletableAppointmentIds.value,
    (ids) => {
        selectedAppointmentIds.value = selectedAppointmentIds.value.filter(
            (id) => ids.includes(id),
        );
    },
);

const toggleAllAppointmentsSelection = (event: Event): void => {
    const target = event.target as HTMLInputElement;
    selectedAppointmentIds.value = target.checked
        ? [...deletableAppointmentIds.value]
        : [];
};

const clearSelectedAppointments = (): void => {
    selectedAppointmentIds.value = [];
};

const canEditAppointment = computed<boolean>(() => can('appointment.update'));

const openViewAppointment = (appointment: Appointment): void => {
    viewingAppointment.value = appointment;
};

const closeViewAppointment = (): void => {
    viewingAppointment.value = null;
};

const openEditAppointment = (appointment: Appointment): void => {
    editingAppointment.value = appointment;
};

const closeEditAppointment = (): void => {
    editingAppointment.value = null;
};

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

    return [...opts, ...status_options.map((s: string) => ({ label: appointmentStatusLabel(s), value: s }))];
});

const handleRemoveFilter = (key: string) => {
    if (key === 'search') {
        localSearch.value = '';
    } else if (key === 'status') {
        localStatus.value = '';
    }
};

const defaultScheduledFor = computed(() => {
    const now = new Date();
    now.setHours(now.getHours() + 1);
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
});

const resetQuickAdd = () => {
    // quickAddFormSuccess ref was not declared in original code
};

const handleQuickAddSuccess = () => {
    toast.success('تم إضافة الموعد بنجاح');
    resetQuickAdd();
    reloadAppointments({ page: 1 });
};

const handleQuickAddError = () => {
    toast.error('فشل إضافة الموعد');
};

const deleteAppointment = async (appointment: Appointment) => {
    const confirmed = await confirm({
        title: 'حذف الموعد',
        description: `هل أنت متأكد من حذف موعد "${appointment.appointment_number}" للمريض "${appointment.patient?.full_name ?? '-'}"؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(AppointmentController.destroy(appointment.id), {
            onSuccess: () => {
                toast.success('تم حذف الموعد بنجاح');
            },
            onError: () => {
                toast.error('فشل حذف الموعد');
            },
        });
    }
};

const handleBulkDelete = async () => {
    const confirmed = await confirm({
        title: 'حذف المواعيد',
        description: `هل أنت متأكد من حذف ${selectedAppointmentIds.value.length} موعد؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(AppointmentController.bulkDestroy.url(), {
            data: { ids: selectedAppointmentIds.value },
            onSuccess: () => {
                clearSelectedAppointments();
                toast.success(`تم حذف ${selectedAppointmentIds.value.length} موعد بنجاح`);
            },
            onError: () => {
                toast.error('فشل حذف المواعيد');
            },
        });
    }
};

const viewMode = ref<'day' | 'list'>('day');

const today = new Date();

const todayAppointments = computed<Appointment[]>(() => {
    return (today_appointments ?? [])
        .slice()
        .sort((a, b) => new Date(a.scheduled_for).getTime() - new Date(b.scheduled_for).getTime());
});

const groupedByHour = computed(() => {
    const groups: Record<string, Appointment[]> = {};
    todayAppointments.value.forEach((apt) => {
        const hour = new Date(apt.scheduled_for).getHours();
        const hourKey = `${String(hour).padStart(2, '0')}:00`;

        if (!groups[hourKey]) {
            groups[hourKey] = [];
        }

        groups[hourKey].push(apt);
    });
    const sortedKeys = Object.keys(groups).sort();
    const result: { hour: string; appointments: Appointment[] }[] = [];
    sortedKeys.forEach((key) => {
        result.push({ hour: key, appointments: groups[key] });
    });

    return result;
});

const formatArabicDate = (iso: string): string => {
    const d = new Date(iso);

    return d.toLocaleDateString('ar-SA', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
};

const todaySummary = computed(() => ({
    total: todayAppointments.value.length,
    scheduled: todayAppointments.value.filter((a) => a.status === 'scheduled').length,
    arrived: todayAppointments.value.filter((a) => a.status === 'arrived').length,
    completed: todayAppointments.value.filter((a) => a.status === 'completed').length,
    canceled: todayAppointments.value.filter((a) => a.status === 'canceled' || a.status === 'no_show').length,
}));
</script>

<template>
    <Head title="المواعيد" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="page-title">المواعيد</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ formatArabicDate(today.toISOString()) }}
                    </p>
                </div>
                <span class="inline-flex items-center rounded-full border border-border/60 bg-muted/40 px-2.5 py-0.5 text-[0.7rem] font-medium text-muted-foreground">
                    {{ activeRoleLabel }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <a
                    :href="AppointmentExportController.export.url()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 bg-background/60 px-3 py-2 text-xs font-medium text-muted-foreground transition hover:text-foreground min-h-[44px]"
                >
                    <Download class="size-3.5" />
                    تصدير Excel
                </a>
                <a
                    :href="AppointmentExportController.exportPdf.url()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 bg-background/60 px-3 py-2 text-xs font-medium text-muted-foreground transition hover:text-foreground min-h-[44px]"
                >
                    <FileText class="size-3.5" />
                    تصدير PDF
                </a>

                <div class="inline-flex rounded-lg border border-border/60 bg-background/60 p-0.5">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all min-h-[44px]"
                        :class="viewMode === 'day' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="viewMode = 'day'"
                    >
                        <CalendarDays class="size-3.5" />
                        اليوم
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all min-h-[44px]"
                        :class="viewMode === 'list' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="viewMode = 'list'"
                    >
                        <Table2 class="size-3.5" />
                        عرض الكل
                    </button>
                </div>

                <Button
                    v-if="can('appointment.create')"
                    variant="ghost"
                    size="sm"
                    class="h-10 rounded-lg px-3 text-xs"
                    @click="isQuickAddOpen = !isQuickAddOpen"
                >
                    {{ isQuickAddOpen ? 'إخفاء السريع' : 'إضافة سريعة' }}
                </Button>
                <Button
                    v-if="can('appointment.create')"
                    variant="default"
                    size="sm"
                    class="h-10 rounded-lg px-3 text-xs"
                    @click="isCreateSheetOpen = true"
                >
                    <Plus class="size-3.5" />
                    إضافة متقدمة
                </Button>
            </div>
        </div>

        <AppointmentQuickAddForm
            v-if="can('appointment.create') && isQuickAddOpen"
            :patients="patients"
            :doctors="doctors"
            :clinic-working-hours="clinic_working_hours"
            @success="handleQuickAddSuccess"
            @reset="resetQuickAdd"
        />

        <AppointmentTodaySummary
            :today-summary="todaySummary"
            :can-create-appointment="can('appointment.create')"
        />

        <AppointmentDayView
            v-if="viewMode === 'day'"
            :grouped-by-hour="groupedByHour"
            :today-summary="todaySummary"
            :can-edit-appointment="canEditAppointment"
            :can-create-appointment="can('appointment.create')"
            @view="openViewAppointment"
            @edit="openEditAppointment"
            @create="isCreateSheetOpen = true"
        />

        <AppointmentTable
            v-if="viewMode === 'list'"
            :appointments="appointments"
            :local-search="localSearch"
            :local-status="localStatus"
            :local-rows-per-page="localRowsPerPage"
            :local-page="localPage"
            :total-local-pages="totalLocalPages"
            :local-visible-from="localVisibleFrom"
            :local-visible-to="localVisibleTo"
            :total="appointments.meta.total"
            :sortBy="localSortBy"
            :sortDirection="localSortDirection"
            :selected-ids="selectedAppointmentIds"
            :deletable-appointment-ids="deletableAppointmentIds"
            :are-all-selected="areAllDeletableAppointmentsSelected"
            :active-filters="activeFilters"
            :status-options="statusOptions"
            :can-delete="can('appointment.delete')"
            :can-edit="canEditAppointment"
            :can-update-status="can('appointment.update') || can('appointment.arrival')"
            :patients="patients"
            :doctors="doctors"
            @search="localSearch = $event"
            @status="localStatus = $event"
            @rows-per-page="localRowsPerPage = $event"
            @page="localPage = $event"
            @sort="toggleSort"
            @remove-filter="handleRemoveFilter"
            @clear-filters="resetLocalFilters"
            @toggle-select-all="toggleAllAppointmentsSelection"
            @update:selectedAppointmentIds="selectedAppointmentIds = $event"
            @delete="deleteAppointment"
            @edit="openEditAppointment"
            @view="openViewAppointment"
            @bulk-delete="handleBulkDelete"
            @clear-selection="clearSelectedAppointments"
            @success="() => reloadAppointments({ page: 1 })"
            @error="handleQuickAddError"
        />

        <AppointmentCreateSheet
            :open="isCreateSheetOpen"
            :patients="patients"
            :doctors="doctors"
            :clinic-working-hours="clinic_working_hours"
            @update:open="isCreateSheetOpen = $event"
        />

        <AppointmentViewDialog
            :appointment="viewingAppointment"
            @close="viewingAppointment = null"
        />

        <AppointmentEditDialog
            :appointment="editingAppointment"
            :patients="patients"
            :doctors="doctors"
            :clinic-working-hours="clinic_working_hours"
            @close="closeEditAppointment"
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
