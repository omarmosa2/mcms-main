<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronDown,
    Download,
    FileText,
    Filter,
    ListFilter,
    Plus,
    Table2,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import AppointmentExportController from '@/actions/App/Http/Controllers/Appointments/AppointmentExportController';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
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
import type {
    Appointment,
    AppointmentSortField,
    ClinicWorkingHour,
    DepartmentOption,
    Option,
    SortDirection,
    TodayAvailability,
} from './components/types';

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

const {
    appointments,
    patients,
    doctors,
    departments,
    status_options,
    filters,
    clinic_working_hours,
    today_availability,
    today_appointments,
    is_doctor,
} = defineProps<{
    appointments: PaginatedResponse<Appointment>;
    patients: Option[];
    doctors: Option[];
    departments: DepartmentOption[];
    status_options: string[];
    clinic_working_hours: ClinicWorkingHour[];
    today_availability: TodayAvailability;
    filters: {
        status: string | null;
        search: string | null;
        doctor_id: number | null;
        department_id: number | null;
        date_from: string | null;
        date_to: string | null;
        per_page: number;
        sort_by: AppointmentSortField | null;
        sort_direction: SortDirection | null;
    };
    today_appointments?: Appointment[];
    is_doctor?: boolean;
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
const {
    isOpen: isConfirmOpen,
    options: confirmOptions,
    confirm,
    close: closeConfirm,
    handleConfirm: handleConfirmDelete,
    handleCancel: handleConfirmCancel,
} = useConfirm();
const toast = useToast();
const page = usePage();

const roleNames = computed<string[]>(() => {
    return (
        (page.props.auth as { roles?: string[] } | undefined)?.roles ?? []
    ).filter((value): value is string => typeof value === 'string');
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

    return (
        rolePriority.find((role) => roleNames.value.includes(role)) ?? 'staff'
    );
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

const activeRoleLabel = computed<string>(
    () => roleLabels[primaryRole.value] ?? roleLabels.staff,
);

const viewingAppointment = ref<Appointment | null>(null);
const editingAppointment = ref<Appointment | null>(null);
const isCreateSheetOpen = ref(false);
const isQuickAddOpen = ref(true);
const localSearch = ref<string>(filters.search ?? '');
const localStatus = ref<string>(filters.status ?? '');
const localDoctorId = ref<string>(
    filters.doctor_id !== null ? String(filters.doctor_id) : '',
);
const localDepartmentId = ref<string>(
    filters.department_id !== null ? String(filters.department_id) : '',
);
const localDateFrom = ref<string>(filters.date_from ?? '');
const localDateTo = ref<string>(filters.date_to ?? '');
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
let appointmentFiltersDebounceTimeout: ReturnType<typeof setTimeout> | null =
    null;

const buildIndexQuery = (
    overrides: Partial<{
        status: string;
        search: string;
        doctor_id: string;
        department_id: string;
        date_from: string;
        date_to: string;
        per_page: number;
        page: number;
        sort_by: AppointmentSortField;
        sort_direction: SortDirection;
    }> = {},
): {
    status?: string;
    search?: string;
    doctor_id?: string;
    department_id?: string;
    date_from?: string;
    date_to?: string;
    per_page: number;
    page: number;
    sort_by: AppointmentSortField;
    sort_direction: SortDirection;
} => {
    const query: {
        status?: string;
        search?: string;
        doctor_id?: string;
        department_id?: string;
        date_from?: string;
        date_to?: string;
        per_page: number;
        page: number;
        sort_by: AppointmentSortField;
        sort_direction: SortDirection;
    } = {
        status: localStatus.value.trim(),
        search: localSearch.value.trim(),
        doctor_id: localDoctorId.value.trim(),
        department_id: localDepartmentId.value.trim(),
        date_from: localDateFrom.value.trim(),
        date_to: localDateTo.value.trim(),
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
        doctor_id: string;
        department_id: string;
        date_from: string;
        date_to: string;
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
        router.get(
            AppointmentController.index.url(),
            buildIndexQuery(overrides),
            {
                only: ['appointments', 'filters'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
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

const toggleSort = (field: AppointmentSortField): void => {
    if (localSortBy.value === field) {
        localSortDirection.value =
            localSortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        localSortBy.value = field;
        localSortDirection.value = 'asc';
    }
};

const resetLocalFilters = (): void => {
    isSyncingFromServer.value = true;
    localSearch.value = '';
    localStatus.value = '';
    localDoctorId.value = '';
    localDepartmentId.value = '';
    localDateFrom.value = '';
    localDateTo.value = '';
    localRowsPerPage.value = defaultRowsPerPage;
    localSortBy.value = 'scheduled_for';
    localSortDirection.value = 'desc';
    localPage.value = 1;
    isSyncingFromServer.value = false;
    reloadAppointments({
        status: '',
        search: '',
        doctor_id: '',
        department_id: '',
        date_from: '',
        date_to: '',
        per_page: defaultRowsPerPage,
        page: 1,
        sort_by: 'scheduled_for',
        sort_direction: 'desc',
    });
};

watch(
    () => [
        filters.search,
        filters.status,
        filters.doctor_id,
        filters.department_id,
        filters.date_from,
        filters.date_to,
        filters.per_page,
        filters.sort_by,
        filters.sort_direction,
        appointments.meta.current_page,
    ],
    () => {
        isSyncingFromServer.value = true;
        localSearch.value = filters.search ?? '';
        localStatus.value = filters.status ?? '';
        localDoctorId.value =
            filters.doctor_id !== null ? String(filters.doctor_id) : '';
        localDepartmentId.value =
            filters.department_id !== null ? String(filters.department_id) : '';
        localDateFrom.value = filters.date_from ?? '';
        localDateTo.value = filters.date_to ?? '';
        localRowsPerPage.value = filters.per_page;
        localSortBy.value = resolveInitialSortBy();
        localSortDirection.value =
            filters.sort_direction === 'asc' ? 'asc' : 'desc';
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
    () => localDoctorId.value,
    () => {
        localPage.value = 1;
        reloadAppointments({ page: 1, doctor_id: localDoctorId.value.trim() });
    },
);

watch(
    () => localDepartmentId.value,
    () => {
        localPage.value = 1;
        reloadAppointments({
            page: 1,
            department_id: localDepartmentId.value.trim(),
        });
    },
);

watch(
    () => [localDateFrom.value, localDateTo.value],
    () => {
        localPage.value = 1;
        reloadAppointments({
            page: 1,
            date_from: localDateFrom.value.trim(),
            date_to: localDateTo.value.trim(),
        });
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
        completed: 'تم تحويله إلى زيارة',
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

const openEditAppointment = (appointment: Appointment): void => {
    editingAppointment.value = appointment;
};

const closeEditAppointment = (): void => {
    editingAppointment.value = null;
};

const selectedDoctorName = computed<string | null>(() => {
    const doctorId = Number(localDoctorId.value);

    if (!Number.isFinite(doctorId) || doctorId <= 0) {
        return null;
    }

    return doctors.find((doctor) => doctor.id === doctorId)?.name ?? null;
});

const selectedDepartmentName = computed<string | null>(() => {
    const departmentId = Number(localDepartmentId.value);

    if (!Number.isFinite(departmentId) || departmentId <= 0) {
        return null;
    }

    return (
        departments.find((department) => department.id === departmentId)
            ?.name ?? null
    );
});

const activeFilters = computed(() => {
    const f: { key: string; label: string; value: string | null }[] = [];

    if (localSearch.value.trim()) {
        f.push({
            key: 'search',
            label: 'بحث',
            value: localSearch.value.trim(),
        });
    }

    if (localStatus.value) {
        f.push({ key: 'status', label: 'الحالة', value: localStatus.value });
    }

    if (localDoctorId.value) {
        f.push({
            key: 'doctor_id',
            label: 'الطبيب',
            value: selectedDoctorName.value ?? localDoctorId.value,
        });
    }

    if (localDepartmentId.value) {
        f.push({
            key: 'department_id',
            label: 'العيادة',
            value: selectedDepartmentName.value ?? localDepartmentId.value,
        });
    }

    if (localDateFrom.value) {
        f.push({
            key: 'date_from',
            label: 'من تاريخ',
            value: localDateFrom.value,
        });
    }

    if (localDateTo.value) {
        f.push({
            key: 'date_to',
            label: 'إلى تاريخ',
            value: localDateTo.value,
        });
    }

    return f;
});

const statusOptions = computed(() => {
    const opts = [{ label: 'الكل', value: '' }];

    return [
        ...opts,
        ...status_options.map((s: string) => ({
            label: appointmentStatusLabel(s),
            value: s,
        })),
    ];
});

const doctorOptions = computed(() => [
    { label: 'كل الأطباء', value: '' },
    ...doctors.map((doctor) => ({
        label: doctor.department?.name
            ? `${doctor.name} - ${doctor.department.name}`
            : (doctor.name ?? `#${doctor.id}`),
        value: String(doctor.id),
    })),
]);

const departmentOptions = computed(() => [
    { label: 'كل العيادات', value: '' },
    ...departments.map((department) => ({
        label: department.name,
        value: String(department.id),
    })),
]);

const handleRemoveFilter = (key: string) => {
    if (key === 'search') {
        localSearch.value = '';
    } else if (key === 'status') {
        localStatus.value = '';
    } else if (key === 'doctor_id') {
        localDoctorId.value = '';
    } else if (key === 'department_id') {
        localDepartmentId.value = '';
    } else if (key === 'date_from') {
        localDateFrom.value = '';
    } else if (key === 'date_to') {
        localDateTo.value = '';
    }
};

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
                closeConfirm();
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
                closeConfirm();
                clearSelectedAppointments();
                toast.success(
                    `تم حذف ${selectedAppointmentIds.value.length} موعد بنجاح`,
                );
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
        .sort(
            (a, b) =>
                new Date(a.scheduled_for).getTime() -
                new Date(b.scheduled_for).getTime(),
        );
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

    return d.toLocaleDateString('ar-SA', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const todaySummary = computed(() => ({
    total: todayAppointments.value.length,
    scheduled: todayAppointments.value.filter((a) => a.status === 'scheduled')
        .length,
    arrived: todayAppointments.value.filter((a) => a.status === 'arrived')
        .length,
    completed: todayAppointments.value.filter((a) => a.status === 'completed')
        .length,
    canceled: todayAppointments.value.filter(
        (a) => a.status === 'canceled' || a.status === 'no_show',
    ).length,
}));
</script>

<template>
    <Head title="المواعيد" />

    <div class="mx-auto w-full max-w-[1680px] space-y-4 p-4 md:p-5" dir="rtl">
        <section
            class="glass-panel-soft flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-center gap-4">
                <div
                    class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                >
                    <CalendarDays class="size-7" />
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="page-title leading-tight">المواعيد</h1>
                        <span
                            class="inline-flex items-center rounded-full border border-primary/20 bg-primary/10 px-2.5 py-0.5 text-[0.72rem] font-semibold text-primary"
                        >
                            {{ activeRoleLabel }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ formatArabicDate(today.toISOString()) }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div
                    class="inline-flex rounded-xl border border-border bg-secondary/60 p-1"
                >
                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-xs font-semibold transition-all"
                        :class="
                            viewMode === 'day'
                                ? 'bg-primary text-primary-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="viewMode = 'day'"
                    >
                        <CalendarDays class="size-3.5" />
                        اليوم
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-xs font-semibold transition-all"
                        :class="
                            viewMode === 'list'
                                ? 'bg-primary text-primary-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="viewMode = 'list'"
                    >
                        <Table2 class="size-3.5" />
                        عرض الكل
                    </button>
                </div>

                <a
                    :href="AppointmentExportController.export.url()"
                    class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-border bg-background px-3 text-xs font-medium text-muted-foreground transition hover:border-primary/30 hover:text-foreground"
                >
                    <Download class="size-3.5" />
                    تصدير Excel
                </a>
                <a
                    :href="AppointmentExportController.exportPdf.url()"
                    class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-border bg-background px-3 text-xs font-medium text-muted-foreground transition hover:border-primary/30 hover:text-foreground"
                >
                    <FileText class="size-3.5" />
                    تصدير PDF
                </a>

                <Button
                    v-if="can('appointment.create')"
                    variant="ghost"
                    size="sm"
                    class="h-9 gap-1.5 rounded-xl px-3 text-xs"
                    @click="isQuickAddOpen = !isQuickAddOpen"
                >
                    <ListFilter class="size-3.5" />
                    {{ isQuickAddOpen ? 'إخفاء السريع' : 'إضافة سريعة' }}
                </Button>
                <Button
                    v-if="can('appointment.create')"
                    variant="default"
                    size="sm"
                    class="h-9 gap-1.5 rounded-xl px-4 text-xs"
                    @click="isCreateSheetOpen = true"
                >
                    <Plus class="size-3.5" />
                    إضافة موعد
                </Button>
            </div>
        </section>

        <AppointmentQuickAddForm
            v-if="can('appointment.create') && isQuickAddOpen"
            :patients="patients"
            :doctors="doctors"
            :departments="departments"
            :clinic-working-hours="clinic_working_hours"
            :today-availability="today_availability"
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
            :can-delete-appointment="can('appointment.delete')"
            @view="openViewAppointment"
            @edit="openEditAppointment"
            @delete="deleteAppointment"
            @create="isCreateSheetOpen = true"
        />

        <AppointmentTable
            v-if="viewMode === 'list'"
            :appointments="appointments"
            :local-search="localSearch"
            :local-status="localStatus"
            :local-doctor-id="localDoctorId"
            :local-department-id="localDepartmentId"
            :local-date-from="localDateFrom"
            :local-date-to="localDateTo"
            :local-rows-per-page="localRowsPerPage"
            :local-page="localPage"
            :total-local-pages="totalLocalPages"
            :local-visible-from="localVisibleFrom"
            :local-visible-to="localVisibleTo"
            :total="appointments.meta.total"
            :sortBy="localSortBy"
            :sortDirection="localSortDirection"
            :active-filters="activeFilters"
            :status-options="statusOptions"
            :doctor-options="doctorOptions"
            :department-options="departmentOptions"
            :can-delete-appointment="can('appointment.delete')"
            :can-edit-appointment="canEditAppointment"
            :can-update-status="
                can('appointment.update') || can('appointment.arrival')
            "
            :patients="patients"
            :doctors="doctors"
            :is-doctor="is_doctor ?? false"
            @search="localSearch = $event"
            @status="localStatus = $event"
            @doctor="localDoctorId = $event"
            @department="localDepartmentId = $event"
            @date-from="localDateFrom = $event"
            @date-to="localDateTo = $event"
            @rows-per-page="localRowsPerPage = $event"
            @page="localPage = $event"
            @sort="toggleSort"
            @remove-filter="handleRemoveFilter"
            @clear-filters="resetLocalFilters"
            @delete="deleteAppointment"
            @edit="openEditAppointment"
            @view="openViewAppointment"
            @status-transition-success="() => reloadAppointments({ page: 1 })"
            @status-transition-error="handleQuickAddError"
        />

        <AppointmentCreateSheet
            :open="isCreateSheetOpen"
            :patients="patients"
            :doctors="doctors"
            :departments="departments"
            :clinic-working-hours="clinic_working_hours"
            :today-availability="today_availability"
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
            :departments="departments"
            :clinic-working-hours="clinic_working_hours"
            :today-availability="today_availability"
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
