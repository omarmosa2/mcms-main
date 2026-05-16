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

type Option = {
    id: number;
    name?: string;
    full_name?: string;
};

type Appointment = {
    id: number;
    patient_id: number;
    doctor_id: number | null;
    appointment_number: string;
    scheduled_for: string;
    duration_minutes: number;
    status: string;
    cancel_reason: string | null;
    notes: string | null;
    patient?: {
        id?: number;
        full_name?: string;
    };
    doctor?: {
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

type AppointmentSortField =
    | 'appointment_number'
    | 'scheduled_for'
    | 'duration_minutes'
    | 'status';

type SortDirection = 'asc' | 'desc';

const { appointments, patients, doctors, status_options, filters, today_appointments } =
    defineProps<{
        appointments: PaginatedResponse<Appointment>;
        patients: Option[];
        doctors: Option[];
        status_options: string[];
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

const transitionStatuses = [
    'confirmed',
    'arrived',
    'completed',
    'canceled',
    'no_show',
];

const appointmentStatusClass = (status: string): string => {
    if (status === 'completed' || status === 'arrived') {
        return 'border-[var(--border-soft)] bg-[var(--accent-mint-soft)] text-[var(--accent-mint-strong)]';
    }

    if (status === 'scheduled' || status === 'confirmed') {
        return 'border-[var(--border-soft)] bg-[var(--accent-teal-soft)] text-[var(--accent-teal-strong)]';
    }

    if (status === 'canceled' || status === 'no_show') {
        return 'border-[var(--border-soft)] bg-[var(--accent-coral-soft)] text-[var(--accent-coral-strong)]';
    }

    return 'border-[var(--border-soft)] bg-[var(--surface-secondary)] text-[var(--surface-contrast-soft)]';
};

const appointmentStatusDotClass = (status: string): string => {
    if (status === 'completed' || status === 'arrived') {
        return 'bg-[var(--accent-mint)]';
    }

    if (status === 'scheduled' || status === 'confirmed') {
        return 'bg-[var(--accent-teal)]';
    }

    if (status === 'canceled' || status === 'no_show') {
        return 'bg-[var(--accent-coral)]';
    }

    return 'bg-[var(--surface-contrast-soft)]';
};

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

const toDatetimeLocalValue = (isoValue: string): string => {
    const parsedDate = new Date(isoValue);

    if (Number.isNaN(parsedDate.getTime())) {
        return '';
    }

    const timezoneOffsetInMs = parsedDate.getTimezoneOffset() * 60_000;
    const localDate = new Date(parsedDate.getTime() - timezoneOffsetInMs);

    return localDate.toISOString().slice(0, 16);
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
    quickAddFormSuccess.value = false;
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
        description: `هل أنت متأكد من حذف موعد "${appointment.appointment_number}" للمريض "${appointment.patient?.full_name || appointment.patient?.first_name + ' ' + appointment.patient?.last_name}"؟ لا يمكن التراجع عن هذا الإجراء.`,
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

const formatTime = (iso: string): string => {
    const d = new Date(iso);

    return d.toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit', hour12: true });
};

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
                    :href="AppointmentExportController.export()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 bg-background/60 px-3 py-2 text-xs font-medium text-muted-foreground transition hover:text-foreground min-h-[44px]"
                >
                    <Download class="size-3.5" />
                    تصدير Excel
                </a>
                <a
                    :href="AppointmentExportController.exportPdf()"
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
                    variant="clay"
                    size="sm"
                    class="h-10 rounded-lg px-3 text-xs"
                    @click="isCreateSheetOpen = true"
                >
                    <Plus class="size-3.5" />
                    إضافة متقدمة
                </Button>
            </div>
        </div>

        <section v-if="can('appointment.create') && isQuickAddOpen" class="rounded-xl border-2 border-dashed border-primary/30 bg-primary/5 p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-primary">إضافة سريعة - موعد جديد</h3>
                <span class="text-xs text-muted-foreground">Enter = حفظ وإضافة التالي</span>
            </div>

            <Form
                v-bind="AppointmentController.store.form()"
                class="grid gap-3 md:grid-cols-5 md:items-end"
                v-slot="{ errors, processing }"
                @success="handleQuickAddSuccess"
                @error="handleQuickAddError"
                reset-on-success
            >
                <div class="grid gap-1">
                    <Label for="quick_patient" class="text-xs">المريض *</Label>
                    <select
                        id="quick_patient"
                        name="patient_id"
                        required
                        class="pattern-field-clay h-9 px-2 py-1 text-sm"
                    >
                        <option value="">اختر مريضاً</option>
                        <option v-for="p in patients" :key="p.id" :value="p.id">
                            {{ p.full_name ?? p.name }}
                        </option>
                    </select>
                    <p v-if="errors.patient_id" class="text-xs text-destructive">{{ errors.patient_id }}</p>
                </div>
                <div class="grid gap-1">
                    <Label for="quick_doctor" class="text-xs">الطبيب</Label>
                    <select
                        id="quick_doctor"
                        name="doctor_id"
                        class="pattern-field-clay h-9 px-2 py-1 text-sm"
                    >
                        <option value="">اختر طبيباً</option>
                        <option v-for="d in doctors" :key="d.id" :value="d.id">
                            {{ d.name }}
                        </option>
                    </select>
                    <p v-if="errors.doctor_id" class="text-xs text-destructive">{{ errors.doctor_id }}</p>
                </div>
                <div class="grid gap-1">
                    <Label for="quick_datetime" class="text-xs">التاريخ والوقت *</Label>
                    <Input
                        id="quick_datetime"
                        name="scheduled_for"
                        type="datetime-local"
                        required
                        class="pattern-field-clay h-9 text-sm"
                    />
                    <p v-if="errors.scheduled_for" class="text-xs text-destructive">{{ errors.scheduled_for }}</p>
                </div>
                <div class="grid gap-1">
                    <Label for="quick_duration" class="text-xs">المدة (دقيقة) *</Label>
                    <Input
                        id="quick_duration"
                        name="duration_minutes"
                        type="number"
                        min="5"
                        max="480"
                        value="15"
                        required
                        class="pattern-field-clay h-9 text-sm"
                    />
                    <p v-if="errors.duration_minutes" class="text-xs text-destructive">{{ errors.duration_minutes }}</p>
                </div>
                <div class="flex gap-2">
                    <Button
                        type="submit"
                        variant="clay"
                        size="sm"
                        class="h-9 px-4 text-xs"
                        :disabled="processing"
                    >
                        {{ processing ? 'جاري الحفظ...' : 'حفظ' }}
                    </Button>
                    <Button type="button" variant="ghost" size="sm" class="h-9 px-3 text-xs" @click="resetQuickAdd">مسح</Button>
                </div>
            </Form>
        </section>

        <section class="rounded-xl border border-border/70 bg-card px-4 py-3">
            <div class="flex flex-wrap items-center gap-4 md:gap-6">
                <div class="flex items-center gap-2">
                    <Calendar class="size-4 text-muted-foreground" />
                    <span class="text-sm text-muted-foreground">اليوم</span>
                    <span class="text-lg font-bold tabular-nums text-foreground">{{ todaySummary.total }}</span>
                </div>
                <div class="hidden h-5 w-px bg-border/60 md:block" aria-hidden="true"></div>
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-[var(--accent-teal)]" aria-hidden="true"></span>
                    <span class="text-sm text-muted-foreground">مجدول</span>
                    <span class="text-lg font-bold tabular-nums text-[var(--accent-teal-strong)]">{{ todaySummary.scheduled }}</span>
                </div>
                <div class="hidden h-5 w-px bg-border/60 md:block" aria-hidden="true"></div>
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-[var(--accent-mint)]" aria-hidden="true"></span>
                    <span class="text-sm text-muted-foreground">حاضر</span>
                    <span class="text-lg font-bold tabular-nums text-[var(--accent-mint-strong)]">{{ todaySummary.arrived }}</span>
                </div>
                <div class="hidden h-5 w-px bg-border/60 md:block" aria-hidden="true"></div>
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-[var(--accent-mint)]" aria-hidden="true"></span>
                    <span class="text-sm text-muted-foreground">مكتمل</span>
                    <span class="text-lg font-bold tabular-nums text-[var(--accent-mint-strong)]">{{ todaySummary.completed }}</span>
                </div>
                <div v-if="todaySummary.canceled > 0" class="hidden h-5 w-px bg-border/60 md:block" aria-hidden="true"></div>
                <div v-if="todaySummary.canceled > 0" class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-[var(--accent-coral)]" aria-hidden="true"></span>
                    <span class="text-sm text-muted-foreground">ملغي</span>
                    <span class="text-lg font-bold tabular-nums text-[var(--accent-coral-strong)]">{{ todaySummary.canceled }}</span>
                </div>
            </div>
        </section>

        <div v-if="viewMode === 'day'" class="glass-panel-soft p-5">
            <div v-if="groupedByHour.length === 0" class="py-16 text-center">
                <CalendarDays class="mx-auto mb-4 size-12 text-muted-foreground/40" />
                <p class="text-sm font-medium text-muted-foreground">لا توجد مواعيد اليوم</p>
                <Button
                    v-if="can('appointment.create')"
                    variant="clay"
                    size="sm"
                    class="mt-4 min-h-[44px]"
                    @click="isCreateSheetOpen = true"
                >
                    <Plus class="size-3.5" />
                    إضافة موعد
                </Button>
            </div>

            <div v-else class="space-y-6">
                <div
                    v-for="group in groupedByHour"
                    :key="group.hour"
                >
                    <div class="mb-2 flex items-center gap-2">
                        <span class="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-bold tabular-nums text-muted-foreground">
                            {{ group.hour }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ group.appointments.length }} {{ group.appointments.length === 1 ? 'موعد' : 'مواعيد' }}
                        </span>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="apt in group.appointments"
                            :key="apt.id"
                            class="flex flex-col gap-3 rounded-xl border border-border/60 bg-background/50 p-3 transition-colors hover:border-[var(--accent-mint-soft)] hover:bg-[var(--accent-mint-soft)]/30 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-bold tabular-nums text-muted-foreground">
                                    {{ formatTime(apt.scheduled_for).split(' ')[0] }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold">{{ apt.patient?.full_name ?? '-' }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ apt.doctor?.name ?? 'بدون طبيب' }}
                                        <span class="mx-1">·</span>
                                        {{ apt.duration_minutes }} دقيقة
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium capitalize"
                                    :class="appointmentStatusClass(apt.status)"
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="appointmentStatusDotClass(apt.status)"
                                    ></span>
                                    {{ appointmentStatusLabel(apt.status) }}
                                </span>

                                <div class="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="neumorphic"
                                        size="icon-sm"
                                        class="h-9 w-9"
                                        @click="openViewAppointment(apt)"
                                        aria-label="عرض الموعد"
                                    >
                                        <Eye class="size-3.5" />
                                    </Button>
                                    <Button
                                        v-if="canEditAppointment"
                                        type="button"
                                        variant="clay"
                                        size="icon-sm"
                                        class="h-9 w-9"
                                        @click="openEditAppointment(apt)"
                                        aria-label="تعديل الموعد"
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="viewMode === 'list'" class="space-y-5">
            <div class="glass-panel-soft p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                    <h3 class="pattern-typographic-title text-[0.76rem]">
                        جميع المواعيد
                    </h3>
                    <span class="text-xs text-muted-foreground">
                        الإجمالي: {{ appointments.meta.total }}
                    </span>
                </div>

                <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
                    <div class="grid gap-3 md:grid-cols-[repeat(3,minmax(0,1fr))] md:items-end">
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="appointments_search">بحث</Label>
                            <FilterSearch
                                id="appointments_search"
                                v-model="localSearch"
                                placeholder="رقم الموعد، المريض، الطبيب"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="appointments_status">الحالة</Label>
                            <FilterSelect
                                id="appointments_status"
                                v-model="localStatus"
                                :options="statusOptions"
                                placeholder="جميع الحالات"
                            />
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-[repeat(2,minmax(0,1fr))] md:items-end">
                        <div class="grid gap-2 md:max-w-44">
                            <Label for="appointments_per_page">صفوف لكل صفحة</Label>
                            <select
                                id="appointments_per_page"
                                v-model.number="localRowsPerPage"
                                class="pattern-field-clay h-10 px-3 py-1.5"
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
                    v-if="can('appointment.delete') && selectedAppointmentIds.length > 0"
                    class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3"
                >
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        class="min-h-[44px]"
                        @click="handleBulkDelete"
                    >
                        حذف المحدد ({{ selectedAppointmentIds.length }})
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="min-h-[44px]"
                        @click="clearSelectedAppointments"
                    >
                        إلغاء التحديد
                    </Button>
                </div>

                <div class="ui-table-shell">
                    <table class="ui-table md:min-w-[920px]">
                        <thead>
                            <tr>
                                <th
                                    v-if="can('appointment.delete')"
                                    class="px-3 py-2"
                                >
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-border"
                                        :checked="areAllDeletableAppointmentsSelected"
                                        @change="toggleAllAppointmentsSelection"
                                    />
                                </th>
                                <th class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                        @click="toggleSort('appointment_number')"
                                    >
                                        رقم الموعد
                                        <component :is="sortIconFor('appointment_number')" class="size-3.5" />
                                    </button>
                                </th>
                                <th class="px-3 py-2">المريض</th>
                                <th class="px-3 py-2">الطبيب</th>
                                <th class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                        @click="toggleSort('scheduled_for')"
                                    >
                                        التاريخ
                                        <component :is="sortIconFor('scheduled_for')" class="size-3.5" />
                                    </button>
                                </th>
                                <th class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                        @click="toggleSort('duration_minutes')"
                                    >
                                        المدة
                                        <component :is="sortIconFor('duration_minutes')" class="size-3.5" />
                                    </button>
                                </th>
                                <th class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                        @click="toggleSort('status')"
                                    >
                                        الحالة
                                        <component :is="sortIconFor('status')" class="size-3.5" />
                                    </button>
                                </th>
                                <th class="px-3 py-2 text-right">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="appointment in visibleAppointments"
                                :key="appointment.id"
                                class="ui-table-row align-top"
                            >
                                <td
                                    v-if="can('appointment.delete')"
                                    class="px-3 py-2"
                                    data-label="تحديد"
                                >
                                    <input
                                        v-if="appointment.status === 'scheduled'"
                                        v-model="selectedAppointmentIds"
                                        type="checkbox"
                                        class="size-4 rounded border-border"
                                        :value="appointment.id"
                                    />
                                </td>
                                <td class="px-3 py-2 font-medium" data-label="رقم الموعد">
                                    {{ appointment.appointment_number }}
                                </td>
                                <td class="px-3 py-2" data-label="المريض">
                                    {{ appointment.patient?.full_name ?? '-' }}
                                </td>
                                <td class="px-3 py-2" data-label="الطبيب">
                                    {{ appointment.doctor?.name ?? '-' }}
                                </td>
                                <td class="px-3 py-2" data-label="التاريخ">
                                    {{ new Date(appointment.scheduled_for).toLocaleString('ar-SA') }}
                                </td>
                                <td class="px-3 py-2" data-label="المدة">
                                    {{ appointment.duration_minutes }} دقيقة
                                </td>
                                <td class="px-3 py-2" data-label="الحالة">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium capitalize"
                                        :class="appointmentStatusClass(appointment.status)"
                                    >
                                        <span
                                            class="size-1.5 rounded-full"
                                            :class="appointmentStatusDotClass(appointment.status)"
                                        ></span>
                                        {{ appointmentStatusLabel(appointment.status) }}
                                    </span>
                                </td>
                                <td class="table-cell-actions px-3 py-2 md:text-right" data-label="الإجراءات">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="neumorphic"
                                            size="sm"
                                            class="h-10 px-3 text-xs"
                                            @click="openViewAppointment(appointment)"
                                        >
                                            عرض
                                        </Button>
                                        <Button
                                            v-if="canEditAppointment"
                                            type="button"
                                            variant="clay"
                                            size="sm"
                                            class="h-10 px-3 text-xs"
                                            @click="openEditAppointment(appointment)"
                                        >
                                            تعديل
                                        </Button>
                                        <Form
                                            v-if="can('appointment.update') || can('appointment.arrival')"
                                            v-bind="AppointmentController.transitionStatus.form(appointment.id)"
                                            class="flex items-center gap-2"
                                            v-slot="{ processing }"
                                            @success="() => {
                                                toast.success('تم تحديث الحالة بنجاح');
                                                closeEditAppointment();
                                            }"
                                            @error="() => {
                                                toast.error('فشل تحديث الحالة');
                                            }"
                                        >
                                            <select
                                                name="status"
                                                class="pattern-field-clay h-10 px-2 py-1 text-xs"
                                            >
                                                <option value="">تغيير الحالة</option>
                                                <option
                                                    v-for="status in transitionStatuses"
                                                    :key="status"
                                                    :value="status"
                                                >
                                                    {{ appointmentStatusLabel(status) }}
                                                </option>
                                            </select>
                                            <Input
                                                name="cancel_reason"
                                                placeholder="سبب الإلغاء"
                                                class="pattern-field-clay h-10 w-36 px-2 py-1 text-xs"
                                            />
                                            <Button
                                                type="submit"
                                                variant="clay"
                                                size="sm"
                                                class="h-10 px-2 text-xs"
                                                :disabled="processing"
                                            >
                                                تطبيق
                                            </Button>
                                        </Form>
                                        <Button
                                            v-if="can('appointment.delete')"
                                            type="button"
                                            size="sm"
                                            variant="destructive"
                                            class="h-10 px-3 text-xs"
                                            @click="deleteAppointment(appointment)"
                                        >
                                            حذف
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="visibleAppointments.length === 0" class="table-empty-state">
                                <td :colspan="can('appointment.delete') ? 8 : 7" class="px-3 py-10 text-center text-muted-foreground">
                                    لا توجد مواعيد تطابق عوامل التصفية الحالية.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2">
                    <p class="text-xs text-muted-foreground">
                        عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من {{ appointments.meta.total }} سجل
                    </p>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="neumorphic"
                            size="sm"
                            class="h-10 px-3 text-xs"
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
                            class="h-10 px-3 text-xs"
                            :disabled="localPage >= totalLocalPages"
                            @click="goToNextPage"
                        >
                            التالي
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <Sheet :open="isCreateSheetOpen" @update:open="isCreateSheetOpen = $event">
            <SheetContent side="left" class="w-full sm:max-w-lg">
                <SheetHeader class="text-right">
                    <SheetTitle>موعد جديد</SheetTitle>
                    <SheetDescription>إضافة موعد جديد بسرعة.</SheetDescription>
                </SheetHeader>

                <Form
                    v-bind="AppointmentController.store.form()"
                    class="mt-6 space-y-4"
                    v-slot="{ errors, processing }"
                    @success="isCreateSheetOpen = false"
                >
                    <div class="grid gap-2">
                        <Label for="appointment_number">
                            رقم الموعد
                            <span class="text-xs text-muted-foreground">(يُولّد تلقائياً إذا ترك فارغاً)</span>
                        </Label>
                        <Input
                            id="appointment_number"
                            name="appointment_number"
                            placeholder="APT-20250421-0001"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.appointment_number" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="patient_id">المريض</Label>
                        <select
                            id="patient_id"
                            name="patient_id"
                            required
                            class="pattern-field-clay h-10 px-3 py-1.5"
                        >
                            <option value="">اختر المريض</option>
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
                        <Label for="doctor_id">الطبيب</Label>
                        <select
                            id="doctor_id"
                            name="doctor_id"
                            class="pattern-field-clay h-10 px-3 py-1.5"
                        >
                            <option value="">يُحدد لاحقاً</option>
                            <option
                                v-for="doctor in doctors"
                                :key="doctor.id"
                                :value="doctor.id"
                            >
                                {{ doctor.name }}
                            </option>
                        </select>
                        <InputError :message="errors.doctor_id" />
                    </div>

                    <div class="grid gap-2 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="scheduled_for">موعد</Label>
                            <Input
                                id="scheduled_for"
                                name="scheduled_for"
                                type="datetime-local"
                                required
                                :value="defaultScheduledFor"
                                class="pattern-field-clay"
                            />
                            <InputError :message="errors.scheduled_for" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="duration_minutes">المدة (دقيقة)</Label>
                            <Input
                                id="duration_minutes"
                                name="duration_minutes"
                                type="number"
                                min="5"
                                required
                                value="30"
                                class="pattern-field-clay"
                            />
                            <InputError :message="errors.duration_minutes" />
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
                        class="w-full min-h-[44px]"
                    >
                        إنشاء الموعد
                    </Button>
                </Form>
            </SheetContent>
        </Sheet>

        <Dialog :open="viewingAppointment !== null" @update:open="(open) => !open && closeViewAppointment()">
            <DialogContent class="sm:max-w-xl" aria-label="تفاصيل الموعد">
                <DialogHeader>
                    <DialogTitle>
                        {{ viewingAppointment?.appointment_number ?? 'تفاصيل الموعد' }}
                    </DialogTitle>
                    <DialogDescription>تفاصيل الموعد.</DialogDescription>
                </DialogHeader>

                <dl v-if="viewingAppointment" class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">المريض</dt>
                        <dd class="text-sm">{{ viewingAppointment.patient?.full_name ?? '-' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">الطبيب</dt>
                        <dd class="text-sm">{{ viewingAppointment.doctor?.name ?? '-' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">التاريخ</dt>
                        <dd class="text-sm">{{ new Date(viewingAppointment.scheduled_for).toLocaleString('ar-SA') }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">المدة</dt>
                        <dd class="text-sm">{{ viewingAppointment.duration_minutes }} دقيقة</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">الحالة</dt>
                        <dd class="text-sm">
                            <span
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium capitalize"
                                :class="appointmentStatusClass(viewingAppointment.status)"
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :class="appointmentStatusDotClass(viewingAppointment.status)"
                                ></span>
                                {{ appointmentStatusLabel(viewingAppointment.status) }}
                            </span>
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">سبب الإلغاء</dt>
                        <dd class="text-sm">{{ viewingAppointment.cancel_reason ?? 'غير محدد' }}</dd>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">ملاحظات</dt>
                        <dd class="text-sm leading-6 text-muted-foreground">{{ viewingAppointment.notes ?? 'لا توجد ملاحظات' }}</dd>
                    </div>
                </dl>

                <DialogFooter>
                    <Button type="button" variant="ghost" class="min-h-[44px]" @click="closeViewAppointment">إغلاق</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="editingAppointment !== null" @update:open="(open) => !open && closeEditAppointment()">
            <DialogContent class="sm:max-w-2xl" aria-label="تعديل الموعد">
                <DialogHeader>
                    <DialogTitle>تعديل الموعد</DialogTitle>
                    <DialogDescription>تحديث تفاصيل الجدولة بسرعة.</DialogDescription>
                </DialogHeader>

                <Form
                    v-if="editingAppointment && canEditAppointment"
                    v-bind="AppointmentController.update.form(editingAppointment.id)"
                    class="space-y-4"
                    :options="{ preserveScroll: true }"
                    @success="closeEditAppointment"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_appointment_number">رقم الموعد</Label>
                            <Input
                                id="edit_appointment_number"
                                name="appointment_number"
                                :value="editingAppointment.appointment_number"
                                class="pattern-field-clay"
                                required
                            />
                            <InputError :message="errors.appointment_number" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_appointment_duration">المدة (دقيقة)</Label>
                            <Input
                                id="edit_appointment_duration"
                                name="duration_minutes"
                                type="number"
                                min="5"
                                :value="String(editingAppointment.duration_minutes)"
                                class="pattern-field-clay"
                                required
                            />
                            <InputError :message="errors.duration_minutes" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_appointment_patient">المريض</Label>
                            <select
                                id="edit_appointment_patient"
                                name="patient_id"
                                class="pattern-field-clay h-10 px-3 py-2"
                                :value="String(editingAppointment.patient_id)"
                            >
                                <option
                                    v-for="patient in patients"
                                    :key="`edit-appointment-patient-${patient.id}`"
                                    :value="patient.id"
                                >
                                    {{ patient.full_name }}
                                </option>
                                <option
                                    v-if="!patients.some(p => p.id === editingAppointment.patient_id)"
                                    :key="`edit-appointment-patient-current-${editingAppointment.patient_id}`"
                                    :value="editingAppointment.patient_id"
                                    selected
                                >
                                    {{ editingAppointment.patient?.full_name ?? 'مريض حالي' }}
                                </option>
                            </select>
                            <InputError :message="errors.patient_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_appointment_doctor">الطبيب</Label>
                            <select
                                id="edit_appointment_doctor"
                                name="doctor_id"
                                class="pattern-field-clay h-10 px-3 py-2"
                                :value="editingAppointment.doctor_id !== null ? String(editingAppointment.doctor_id) : ''"
                            >
                                <option value="">غير محدد</option>
                                <option
                                    v-for="doctor in doctors"
                                    :key="`edit-appointment-doctor-${doctor.id}`"
                                    :value="doctor.id"
                                >
                                    {{ doctor.name }}
                                </option>
                            </select>
                            <InputError :message="errors.doctor_id" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_appointment_scheduled_for">موعد</Label>
                        <Input
                            id="edit_appointment_scheduled_for"
                            name="scheduled_for"
                            type="datetime-local"
                            :value="toDatetimeLocalValue(editingAppointment.scheduled_for)"
                            class="pattern-field-clay"
                            required
                        />
                        <InputError :message="errors.scheduled_for" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_appointment_notes">ملاحظات</Label>
                        <textarea
                            id="edit_appointment_notes"
                            name="notes"
                            rows="3"
                            class="pattern-field-clay"
                            :value="editingAppointment.notes ?? ''"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="ghost" :disabled="processing" class="min-h-[44px]" @click="closeEditAppointment">إلغاء</Button>
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
