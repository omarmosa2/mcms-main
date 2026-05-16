<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, FileText, Plus, Download, FileSpreadsheet } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import InvoiceController from '@/actions/App/Http/Controllers/Billing/InvoiceController';
import InvoiceExportController from '@/actions/App/Http/Controllers/Billing/InvoiceExportController';
import PaymentController from '@/actions/App/Http/Controllers/Billing/PaymentController';
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
    visit_number?: string;
};

type InvoiceItem = {
    id: number;
    description: string;
    line_total: number;
};

type Payment = {
    id: number;
    status: string;
    method: string | null;
    amount: number;
    refund_amount: number | null;
    paid_at?: string | null;
    refunded_at?: string | null;
};

type Invoice = {
    id: number;
    patient_id: number;
    visit_id: number | null;
    appointment_id: number | null;
    invoice_number: string;
    status: string;
    issued_at: string | null;
    due_at: string | null;
    subtotal_amount: number;
    discount_amount: number;
    tax_amount: number;
    total_amount: number;
    paid_amount: number;
    balance_amount: number;
    notes: string | null;
    items?: InvoiceItem[];
    patient?: {
        id?: number;
        full_name?: string;
    };
    payments?: Payment[];
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

type InvoiceSortField =
    | 'invoice_number'
    | 'status'
    | 'issued_at'
    | 'due_at'
    | 'total_amount'
    | 'balance_amount';

type SortDirection = 'asc' | 'desc';

const {
    invoices,
    patients,
    appointments,
    visits,
    status_options,
    payment_method_options,
    filters,
} = defineProps<{
    invoices: PaginatedResponse<Invoice>;
    patients: Option[];
    appointments: Option[];
    visits: Option[];
    status_options: string[];
    payment_method_options: string[];
    filters: {
        status: string | null;
        patient_id: number | null;
        search: string | null;
        per_page: number;
        sort_by: InvoiceSortField | null;
        sort_direction: SortDirection | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'الفواتير',
                href: InvoiceController.index(),
            },
        ],
    },
});

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

const { can } = usePermissions();
const { isOpen: isConfirmOpen, options: confirmOptions, confirm, handleConfirm: handleConfirmDelete, handleCancel: handleConfirmCancel } = useConfirm();
const toast = useToast();
const viewingInvoice = ref<Invoice | null>(null);
const editingInvoice = ref<Invoice | null>(null);
const isCreateSheetOpen = ref(false);
const localSearch = ref<string>(filters.search ?? '');
const localStatus = ref<string>(filters.status ?? '');
const localPatientId = ref<string>(
    filters.patient_id !== null ? String(filters.patient_id) : '',
);
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(invoices.meta.current_page);

const allowedSortFields: InvoiceSortField[] = [
    'invoice_number',
    'status',
    'issued_at',
    'due_at',
    'total_amount',
    'balance_amount',
];

const resolveInitialSortBy = (): InvoiceSortField => {
    const sortBy = filters.sort_by;

    if (
        sortBy !== null &&
        allowedSortFields.includes(sortBy as InvoiceSortField)
    ) {
        return sortBy;
    }

    return 'issued_at';
};
const localSortBy = ref<InvoiceSortField>(resolveInitialSortBy());
const localSortDirection = ref<SortDirection>(
    filters.sort_direction === 'asc' ? 'asc' : 'desc',
);
const visibleInvoices = computed<Invoice[]>(() => invoices.data);
const totalLocalPages = computed<number>(() => {
    return Math.max(1, invoices.meta.last_page);
});
const localVisibleFrom = computed<number>(() => {
    return invoices.meta.from ?? 0;
});
const localVisibleTo = computed<number>(() => {
    return invoices.meta.to ?? 0;
});
const defaultRowsPerPage = 15;
const isSyncingFromServer = ref(false);
let billingFiltersDebounceTimeout: ReturnType<typeof setTimeout> | null = null;
const buildIndexQuery = (
    overrides: Partial<{
        status: string;
        patient_id: number | '';
        search: string;
        per_page: number;
        page: number;
        sort_by: InvoiceSortField;
        sort_direction: SortDirection;
    }> = {},
): {
    status: string;
    patient_id: number | '';
    search: string;
    per_page: number;
    page: number;
    sort_by: InvoiceSortField;
    sort_direction: SortDirection;
} => {
    const patientId =
        localPatientId.value.trim() !== '' ? Number(localPatientId.value) : null;
    const query: {
        status: string;
        patient_id: number | '';
        search: string;
        per_page: number;
        page: number;
        sort_by: InvoiceSortField;
        sort_direction: SortDirection;
    } = {
        status: localStatus.value.trim(),
        patient_id: patientId ?? '',
        search: localSearch.value.trim(),
        per_page: localRowsPerPage.value,
        page: localPage.value,
        sort_by: localSortBy.value,
        sort_direction: localSortDirection.value,
        ...overrides,
    };

    return query;
};
const reloadInvoices = (
    overrides: Partial<{
        status: string;
        patient_id: number | '';
        search: string;
        per_page: number;
        page: number;
        sort_by: InvoiceSortField;
        sort_direction: SortDirection;
    }> = {},
    debounce = false,
): void => {
    if (isSyncingFromServer.value) {
        return;
    }

    const executeReload = (): void => {
        router.cancelAll();
        router.get(InvoiceController.index.url(), buildIndexQuery(overrides), {
            only: ['invoices', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    if (debounce) {
        if (billingFiltersDebounceTimeout !== null) {
            clearTimeout(billingFiltersDebounceTimeout);
        }

        billingFiltersDebounceTimeout = setTimeout(executeReload, 320);

        return;
    }

    executeReload();
};
const sortIconFor = (field: InvoiceSortField) => {
    if (localSortBy.value !== field) {
        return ArrowUpDown;
    }

    return localSortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};
const toggleSort = (field: InvoiceSortField): void => {
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
    localPatientId.value = '';
    localRowsPerPage.value = defaultRowsPerPage;
    localSortBy.value = 'issued_at';
    localSortDirection.value = 'desc';
    localPage.value = 1;
    isSyncingFromServer.value = false;
    reloadInvoices({
        status: '',
        search: '',
        per_page: defaultRowsPerPage,
        page: 1,
        sort_by: 'issued_at',
        sort_direction: 'desc',
    });
};
const goToPreviousPage = (): void => {
    if (localPage.value <= 1) {
        return;
    }

    localPage.value -= 1;
    reloadInvoices({ page: localPage.value });
};
const goToNextPage = (): void => {
    if (localPage.value >= totalLocalPages.value) {
        return;
    }

    localPage.value += 1;
    reloadInvoices({ page: localPage.value });
};
watch(
    () => [
        filters.search,
        filters.status,
        filters.patient_id,
        filters.per_page,
        filters.sort_by,
        filters.sort_direction,
        invoices.meta.current_page,
    ],
    () => {
        isSyncingFromServer.value = true;
        localSearch.value = filters.search ?? '';
        localStatus.value = filters.status ?? '';
        localPatientId.value =
            filters.patient_id !== null ? String(filters.patient_id) : '';
        localRowsPerPage.value = filters.per_page;
        localSortBy.value = resolveInitialSortBy();
        localSortDirection.value = filters.sort_direction === 'asc' ? 'asc' : 'desc';
        localPage.value = invoices.meta.current_page;
        isSyncingFromServer.value = false;
    },
    { immediate: true },
);
watch(
    () => localSearch.value,
    () => {
        localPage.value = 1;
        reloadInvoices({ page: 1, search: localSearch.value.trim() }, true);
    },
);
watch(
    () => localStatus.value,
    () => {
        localPage.value = 1;
        reloadInvoices({ page: 1, status: localStatus.value.trim() });
    },
);
watch(
    () => localPatientId.value,
    () => {
        localPage.value = 1;
        const patientId =
            localPatientId.value.trim() !== '' ? Number(localPatientId.value) : '';
        reloadInvoices({ page: 1, patient_id: patientId });
    },
);
watch(
    () => localRowsPerPage.value,
    () => {
        localPage.value = 1;
        reloadInvoices({ page: 1, per_page: localRowsPerPage.value });
    },
);
watch(
    () => [localSortBy.value, localSortDirection.value],
    () => {
        localPage.value = 1;
        reloadInvoices({
            page: 1,
            sort_by: localSortBy.value,
            sort_direction: localSortDirection.value,
        });
    },
);
onBeforeUnmount(() => {
    if (billingFiltersDebounceTimeout !== null) {
        clearTimeout(billingFiltersDebounceTimeout);
        billingFiltersDebounceTimeout = null;
    }
});

const selectedInvoiceIds = ref<number[]>([]);

const deletableInvoiceIds = computed<number[]>(() =>
    visibleInvoices.value
        .filter(
            (invoice) =>
                invoice.status === 'draft' &&
                (invoice.payments?.length ?? 0) === 0,
        )
        .map((invoice) => invoice.id),
);

const areAllDeletableInvoicesSelected = computed<boolean>(() => {
    if (deletableInvoiceIds.value.length === 0) {
        return false;
    }

    return deletableInvoiceIds.value.every((invoiceId) =>
        selectedInvoiceIds.value.includes(invoiceId),
    );
});

watch(
    () => deletableInvoiceIds.value,
    (ids) => {
        selectedInvoiceIds.value = selectedInvoiceIds.value.filter((id) =>
            ids.includes(id),
        );
    },
);

const toggleAllInvoicesSelection = (event: Event): void => {
    const target = event.target as HTMLInputElement;

    selectedInvoiceIds.value = target.checked
        ? [...deletableInvoiceIds.value]
        : [];
};

const clearSelectedInvoices = (): void => {
    selectedInvoiceIds.value = [];
};

const canViewInvoice = computed<boolean>(() => can('billing.view'));
const canEditInvoice = computed<boolean>(() => can('billing.generate'));

const openViewInvoice = (invoice: Invoice): void => {
    viewingInvoice.value = invoice;
};

const closeViewInvoice = (): void => {
    viewingInvoice.value = null;
};

const openEditInvoice = (invoice: Invoice): void => {
    editingInvoice.value = invoice;
};

const closeEditInvoice = (): void => {
    editingInvoice.value = null;
};

const resolvePatientName = (patientId: number): string => {
    return (
        patients.find((patient) => patient.id === patientId)?.full_name ?? '-'
    );
};

const resolveAppointmentNumber = (appointmentId: number | null): string => {
    if (appointmentId === null) {
        return '-';
    }

    return (
        appointments.find((appointment) => appointment.id === appointmentId)
            ?.appointment_number ?? '-'
    );
};

const resolveVisitNumber = (visitId: number | null): string => {
    if (visitId === null) {
        return '-';
    }

    return visits.find((visit) => visit.id === visitId)?.visit_number ?? '-';
};

const invoiceStatusClass = (status: string): string => {
    if (status === 'draft') {
        return 'border-[var(--border-soft)] bg-[var(--accent-amber-soft)] text-[var(--accent-amber-strong)]';
    }

    if (status === 'issued') {
        return 'border-[var(--border-soft)] bg-[var(--accent-teal-soft)] text-[var(--accent-teal-strong)]';
    }

    if (status === 'paid') {
        return 'border-[var(--border-soft)] bg-[var(--accent-mint-soft)] text-[var(--accent-mint-strong)]';
    }

    if (status === 'overdue' || status === 'canceled') {
        return 'border-[var(--border-soft)] bg-[var(--accent-coral-soft)] text-[var(--accent-coral-strong)]';
    }

    return 'border-[var(--border-soft)] bg-[var(--surface-secondary)] text-[var(--surface-contrast-soft)]';
};

const invoiceStatusDotClass = (status: string): string => {
    if (status === 'paid') {
        return 'bg-[var(--accent-mint)]';
    }

    if (status === 'issued') {
        return 'bg-[var(--accent-teal)]';
    }

    if (status === 'draft') {
        return 'bg-[var(--accent-amber)]';
    }

    if (status === 'overdue' || status === 'canceled') {
        return 'bg-[var(--accent-coral)]';
    }

    return 'bg-[var(--surface-contrast-soft)]';
};

const statusLabels: Record<string, string> = {
    draft: 'مسودة',
    issued: 'صادرة',
    paid: 'مدفوعة',
    overdue: 'متأخرة',
    canceled: 'ملغاة',
};

const outstandingInvoicesCount = computed<number>(
    () => visibleInvoices.value.filter((invoice) => invoice.balance_amount > 0).length,
);

const visibleOutstandingAmount = computed<number>(() =>
    visibleInvoices.value.reduce(
        (carry, invoice) => carry + Number(invoice.balance_amount),
        0,
    ),
);

const visibleCollectedAmount = computed<number>(() =>
    visibleInvoices.value.reduce(
        (carry, invoice) => carry + Number(invoice.paid_amount),
        0,
    ),
);

const statusOptions = computed(() => {
    const opts = [{ label: 'الكل', value: '' }];

    return [...opts, ...status_options.map((s: string) => ({ label: statusLabels[s] ?? s, value: s }))];
});

const activeFilters = computed(() => {
    const filters: { key: string; label: string; value: string | null }[] = [];

    if (localSearch.value.trim()) {
        filters.push({ key: 'search', label: 'بحث', value: localSearch.value.trim() });
    }

    if (localStatus.value) {
        filters.push({ key: 'status', label: 'الحالة', value: statusLabels[localStatus.value] ?? localStatus.value });
    }

    if (localPatientId.value) {
        const patient = patients.find(p => p.id === Number(localPatientId.value));
        filters.push({ key: 'patient_id', label: 'المريض', value: patient?.full_name ?? localPatientId.value });
    }

    return filters;
});

const handleRemoveFilter = (key: string) => {
    if (key === 'search') {
        localSearch.value = '';
    } else if (key === 'status') {
        localStatus.value = '';
    } else if (key === 'patient_id') {
        localPatientId.value = '';
    }
};

const deleteInvoice = async (invoice: Invoice) => {
    const confirmed = await confirm({
        title: 'حذف الفاتورة',
        description: `هل أنت متأكد من حذف الفاتورة "${invoice.invoice_number || invoice.id}" للمريض "${invoice.patient?.full_name || invoice.patient?.first_name + ' ' + invoice.patient?.last_name}"؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(InvoiceController.destroy(invoice.id), {
            onSuccess: () => {
                toast.success('تم حذف الفاتورة بنجاح');
            },
            onError: () => {
                toast.error('فشل حذف الفاتورة');
            },
        });
    }
};
</script>

<template>
    <Head title="الفواتير" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="page-title">الفواتير</h1>
                    <p class="mt-1 text-sm text-muted-foreground">إدارة الفواتير والمدفوعات والتحصيل.</p>
                </div>
                <span class="inline-flex items-center rounded-full border border-border/60 bg-muted/40 px-2.5 py-0.5 text-[0.7rem] font-medium text-muted-foreground">
                    {{ activeRoleLabel }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <a
                    :href="InvoiceExportController.export()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 bg-background/60 px-3 py-2 text-xs font-medium text-muted-foreground transition hover:text-foreground min-h-[44px]"
                >
                    <FileSpreadsheet class="size-3.5" />
                    تصدير Excel
                </a>
                <a
                    :href="InvoiceExportController.exportPdf()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 bg-background/60 px-3 py-2 text-xs font-medium text-muted-foreground transition hover:text-foreground min-h-[44px]"
                >
                    <FileText class="size-3.5" />
                    تصدير PDF
                </a>

                <Button
                    v-if="can('billing.generate')"
                    variant="clay"
                    size="sm"
                    class="h-10 rounded-lg px-3 text-sm"
                    @click="isCreateSheetOpen = true"
                >
                    <Plus class="size-4" />
                    إنشاء فاتورة
                </Button>
            </div>
        </div>

        <section class="rounded-xl border border-border/70 bg-card px-4 py-3">
            <div class="flex flex-wrap items-center gap-4 md:gap-6">
                <div class="flex items-center gap-2">
                    <FileText class="size-4 text-muted-foreground" />
                    <span class="text-sm text-muted-foreground">إجمالي الفواتير</span>
                    <span class="text-lg font-bold tabular-nums text-foreground">{{ invoices.meta.total }}</span>
                </div>
                <div class="hidden h-5 w-px bg-border/60 md:block" aria-hidden="true"></div>
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-[var(--accent-coral)]" aria-hidden="true"></span>
                    <span class="text-sm text-muted-foreground">فواتير مستحقة</span>
                    <span class="text-lg font-bold tabular-nums text-[var(--accent-coral-strong)]">{{ outstandingInvoicesCount }}</span>
                </div>
                <div class="hidden h-5 w-px bg-border/60 md:block" aria-hidden="true"></div>
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-[var(--accent-amber)]" aria-hidden="true"></span>
                    <span class="text-sm text-muted-foreground">المبلغ المستحق</span>
                    <span class="text-lg font-bold tabular-nums text-[var(--accent-amber-strong)]">{{ visibleOutstandingAmount.toFixed(2) }}</span>
                </div>
                <div v-if="visibleCollectedAmount > 0" class="hidden h-5 w-px bg-border/60 md:block" aria-hidden="true"></div>
                <div v-if="visibleCollectedAmount > 0" class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-[var(--accent-mint)]" aria-hidden="true"></span>
                    <span class="text-sm text-muted-foreground">المبلغ المحصّل</span>
                    <span class="text-lg font-bold tabular-nums text-[var(--accent-mint-strong)]">{{ visibleCollectedAmount.toFixed(2) }}</span>
                </div>
            </div>
        </section>

        <div class="glass-panel-soft p-5">
            <div
                class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3"
            >
                <h3 class="pattern-typographic-title text-[0.76rem]">
                    قائمة الفواتير
                </h3>
                <span class="text-xs text-muted-foreground">
                    الإجمالي: {{ invoices.meta.total }}
                </span>
            </div>

            <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
                <div class="grid gap-3 md:grid-cols-[repeat(3,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="billing_search_filter">بحث</Label>
                        <FilterSearch
                            id="billing_search_filter"
                            v-model="localSearch"
                            placeholder="رقم الفاتورة، اسم المريض"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="billing_status_filter">الحالة</Label>
                        <FilterSelect
                            id="billing_status_filter"
                            v-model="localStatus"
                            :options="statusOptions"
                            placeholder="الكل"
                        />
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-[repeat(2,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2 md:max-w-44">
                        <Label for="billing_per_page">صفوف لكل صفحة</Label>
                        <select
                            id="billing_per_page"
                            v-model.number="localRowsPerPage"
                            class="pattern-field-clay h-10 px-3 py-2"
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

                <Form
                    v-if="
                        can('billing.generate') && selectedInvoiceIds.length > 0
                    "
                    v-bind="InvoiceController.bulkDestroy.form()"
                    class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3"
                    v-slot="{ processing }"
                >
                    <input
                        v-for="invoiceId in selectedInvoiceIds"
                        :key="`selected-invoice-${invoiceId}`"
                        type="hidden"
                        name="ids[]"
                        :value="invoiceId"
                    />

                    <Button
                        type="submit"
                        variant="destructive"
                        size="sm"
                        class="h-10"
                        :disabled="processing"
                    >
                        حذف المحدد ({{ selectedInvoiceIds.length }})
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-10"
                        @click="clearSelectedInvoices"
                    >
                        إلغاء التحديد
                    </Button>
                </Form>

                <div class="ui-table-shell">
                    <table class="ui-table md:min-w-[1080px]">
                        <thead>
                            <tr>
                                <th
                                    v-if="can('billing.generate')"
                                    class="px-3 py-2"
                                >
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-border"
                                        :checked="
                                            areAllDeletableInvoicesSelected
                                        "
                                        @change="toggleAllInvoicesSelection"
                                    />
                                </th>
                                <th class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                        @click="toggleSort('invoice_number')"
                                    >
                                        رقم الفاتورة
                                        <component
                                            :is="
                                                sortIconFor(
                                                    'invoice_number',
                                                )
                                            "
                                            class="size-3.5"
                                        />
                                    </button>
                                </th>
                                <th class="px-3 py-2">المريض</th>
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
                                <th class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                        @click="toggleSort('due_at')"
                                    >
                                        تاريخ الاستحقاق
                                        <component
                                            :is="sortIconFor('due_at')"
                                            class="size-3.5"
                                        />
                                    </button>
                                </th>
                                <th class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                        @click="toggleSort('total_amount')"
                                    >
                                        الإجمالي
                                        <component
                                            :is="sortIconFor('total_amount')"
                                            class="size-3.5"
                                        />
                                    </button>
                                </th>
                                <th class="px-3 py-2">المدفوع</th>
                                <th class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                        @click="toggleSort('balance_amount')"
                                    >
                                        الرصيد
                                        <component
                                            :is="
                                                sortIconFor(
                                                    'balance_amount',
                                                )
                                            "
                                            class="size-3.5"
                                        />
                                    </button>
                                </th>
                                <th class="px-3 py-2 text-right">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="invoice in visibleInvoices"
                                :key="invoice.id"
                                class="ui-table-row align-top"
                            >
                                <td
                                    v-if="can('billing.generate')"
                                    class="px-3 py-2"
                                    data-label="تحديد"
                                >
                                    <input
                                        v-if="
                                            invoice.status === 'draft' &&
                                            (invoice.payments ?? []).length ===
                                                0
                                        "
                                        v-model="selectedInvoiceIds"
                                        type="checkbox"
                                        class="size-4 rounded border-border"
                                        :value="invoice.id"
                                    />
                                </td>
                                <td
                                    class="px-3 py-2 font-medium"
                                    data-label="رقم الفاتورة"
                                >
                                    {{ invoice.invoice_number }}
                                </td>
                                <td class="px-3 py-2" data-label="المريض">
                                    {{ invoice.patient?.full_name ?? '-' }}
                                </td>
                                <td class="px-3 py-2" data-label="الحالة">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium capitalize"
                                        :class="
                                            invoiceStatusClass(invoice.status)
                                        "
                                    >
                                        <span
                                            class="w-1.5 h-1.5 rounded-full"
                                            :class="invoiceStatusDotClass(invoice.status)"
                                        ></span>
                                        {{ statusLabels[invoice.status] ?? invoice.status }}
                                    </span>
                                </td>
                                <td class="px-3 py-2" data-label="تاريخ الاستحقاق">
                                    {{ invoice.due_at ?? '-' }}
                                </td>
                                <td class="px-3 py-2" data-label="الإجمالي">
                                    {{ invoice.total_amount.toFixed(2) }}
                                </td>
                                <td class="px-3 py-2" data-label="المدفوع">
                                    {{ invoice.paid_amount.toFixed(2) }}
                                </td>
                                <td class="px-3 py-2" data-label="الرصيد">
                                    {{ invoice.balance_amount.toFixed(2) }}
                                </td>
                                <td
                                    class="table-cell-actions px-3 py-2"
                                    data-label="الإجراءات"
                                >
                                    <div
                                        class="flex flex-wrap items-start justify-end gap-2"
                                    >
                                        <Button
                                            v-if="canViewInvoice"
                                            type="button"
                                            variant="neumorphic"
                                            size="sm"
                                            class="h-10 px-3 text-xs"
                                            @click="openViewInvoice(invoice)"
                                        >
                                            عرض
                                        </Button>
                                        <Button
                                            v-if="
                                                canEditInvoice &&
                                                invoice.status === 'draft'
                                            "
                                            type="button"
                                            variant="clay"
                                            size="sm"
                                            class="h-10 px-3 text-xs"
                                            @click="openEditInvoice(invoice)"
                                        >
                                            تعديل
                                        </Button>
<Form
                                            v-if="
                                                can('billing.generate') &&
                                                invoice.status === 'draft'
                                            "
                                            v-bind="
                                                InvoiceController.issue.form(
                                                    invoice.id,
                                                )
                                            "
                                            class="flex items-center gap-2"
                                            v-slot="{ processing }"
                                            @success="() => toast.success('تم إصدار الفاتورة بنجاح')"
                                            @error="() => toast.error('فشل إصدار الفاتورة')"
                                        >
                                            <Input
                                                name="due_at"
                                                type="date"
                                                aria-label="تاريخ الاستحقاق"
                                                class="pattern-field-clay h-10 w-32 px-2 py-2 text-xs"
                                            />
                                            <Button
                                                type="submit"
                                                variant="clay"
                                                size="sm"
                                                class="h-10 px-2 text-xs"
                                                :disabled="processing"
                                            >
                                                إصدار
                                            </Button>
                                        </Form>

<Form
                                            v-if="
                                                can('payment.record') &&
                                                invoice.balance_amount > 0
                                            "
                                            v-bind="
                                                PaymentController.store.form(
                                                    invoice.id,
                                                )
                                            "
                                            class="flex items-center gap-2"
                                            v-slot="{ processing }"
                                            @success="() => toast.success('تم تسجيل الدفع بنجاح')"
                                            @error="() => toast.error('فشل تسجيل الدفع')"
                                        >
                                            <Input
                                                name="amount"
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                class="pattern-field-clay h-10 w-24 px-2 py-2 text-xs"
                                                placeholder="المبلغ"
                                                required
                                            />
                                            <select
                                                name="method"
                                                class="pattern-field-clay h-10 px-2 py-2 text-xs"
                                            >
                                                <option
                                                    v-for="method in payment_method_options"
                                                    :key="method"
                                                    :value="method"
                                                >
                                                    {{ method }}
                                                </option>
                                            </select>
                                            <Button
                                                type="submit"
                                                variant="clay"
                                                size="sm"
                                                class="h-10 px-2 text-xs"
                                                :disabled="processing"
                                            >
                                                دفع
                                            </Button>
                                        </Form>

                                        <Button
                                            v-if="
                                                can('billing.generate') &&
                                                invoice.status === 'draft'
                                            "
                                            type="button"
                                            size="sm"
                                            variant="destructive"
                                            class="h-10 px-3 text-xs"
                                            @click="deleteInvoice(invoice)"
                                        >
                                            حذف
                                        </Button>
                                    </div>

                                    <div
                                        v-if="
                                            can('payment.refund') &&
                                            (invoice.payments ?? []).length > 0
                                        "
                                        class="mt-2 space-y-2 rounded-md border border-border/60 bg-muted/50 p-2"
                                    >
                                        <p
                                            class="pattern-typographic-title text-[0.68rem]"
                                        >
                                            استرداد المدفوعات
                                        </p>
                                        <Form
                                            v-for="payment in invoice.payments"
                                            :key="payment.id"
                                            v-bind="
                                                PaymentController.refund.form(
                                                    payment.id,
                                                )
                                            "
                                            class="flex items-center gap-2"
                                            v-slot="{ processing }"
                                        >
                                            <span
                                                class="w-28 text-xs text-muted-foreground capitalize"
                                            >
                                                {{ payment.method }}
                                                ({{
                                                    payment.amount.toFixed(2)
                                                }})
                                            </span>
                                            <Input
                                                name="amount"
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                class="pattern-field-clay h-10 w-24 px-2 py-2 text-xs"
                                                placeholder="استرداد"
                                                required
                                            />
                                            <Button
                                                type="submit"
                                                variant="clay"
                                                size="sm"
                                                class="h-10 px-2 text-xs"
                                                :disabled="
                                                    processing ||
                                                    payment.status !==
                                                        'recorded'
                                                "
                                            >
                                                استرداد
                                            </Button>
                                        </Form>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-if="visibleInvoices.length === 0"
                                class="table-empty-state"
                            >
                                <td
                                    :colspan="can('billing.generate') ? 9 : 8"
                                    class="px-3 py-10 text-center text-muted-foreground"
                                >
                                    لا توجد فواتير.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2"
                >
                    <p class="text-xs text-muted-foreground">
                        عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من
                        {{ invoices.meta.total }} سجل
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

        <Sheet :open="isCreateSheetOpen" @update:open="isCreateSheetOpen = $event">
            <SheetContent side="left" class="w-full sm:max-w-lg">
                <SheetHeader class="text-right">
                    <SheetTitle>إنشاء فاتورة</SheetTitle>
                    <SheetDescription>تسجيل فاتورة جديدة.</SheetDescription>
                </SheetHeader>

                <Form
                    v-bind="InvoiceController.store.form()"
                    class="mt-6 space-y-4"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="patient_id">المريض</Label>
                        <select
                            id="patient_id"
                            name="patient_id"
                            required
                            class="pattern-field-clay h-10 px-3 py-2"
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
                            class="pattern-field-clay h-10 px-3 py-2"
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
                        <Label for="visit_id">الزيارة</Label>
                        <select
                            id="visit_id"
                            name="visit_id"
                            class="pattern-field-clay h-10 px-3 py-2"
                        >
                            <option value="">بدون زيارة</option>
                            <option
                                v-for="visit in visits"
                                :key="visit.id"
                                :value="visit.id"
                            >
                                {{ visit.visit_number }}
                            </option>
                        </select>
                        <InputError :message="errors.visit_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="invoice_number">رقم الفاتورة</Label>
                        <Input
                            id="invoice_number"
                            name="invoice_number"
                            placeholder="INV-1001"
                            class="pattern-field-clay h-10"
                        />
                        <InputError :message="errors.invoice_number" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="due_at">تاريخ الاستحقاق</Label>
                        <Input id="due_at" name="due_at" type="date" class="pattern-field-clay h-10" />
                        <InputError :message="errors.due_at" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">ملاحظات</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="2"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <div class="pattern-surface-flat border-dashed p-3">
                        <p class="pattern-typographic-title mb-3 text-[0.7rem]">
                            البند الأول
                        </p>
                        <div class="space-y-3">
                            <div class="grid gap-2">
                                <Label for="items_0_description">
                                    الوصف
                                </Label>
                                <Input
                                    id="items_0_description"
                                    name="items[0][description]"
                                    required
                                    placeholder="استشارة"
                                    class="h-10"
                                />
                                <InputError
                                    :message="errors['items.0.description']"
                                />
                            </div>

                            <div class="grid gap-2 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="items_0_quantity">الكمية</Label>
                                    <Input
                                        id="items_0_quantity"
                                        name="items[0][quantity]"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value="1"
                                        required
                                        class="h-10"
                                    />
                                    <InputError
                                        :message="errors['items.0.quantity']"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="items_0_unit_price">
                                        سعر الوحدة
                                    </Label>
                                    <Input
                                        id="items_0_unit_price"
                                        name="items[0][unit_price]"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value="0"
                                        required
                                        class="h-10"
                                    />
                                    <InputError
                                        :message="errors['items.0.unit_price']"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-2 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="items_0_discount_amount">
                                        الخصم
                                    </Label>
                                    <Input
                                        id="items_0_discount_amount"
                                        name="items[0][discount_amount]"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value="0"
                                        class="h-10"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="items_0_tax_amount">الضريبة</Label>
                                    <Input
                                        id="items_0_tax_amount"
                                        name="items[0][tax_amount]"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value="0"
                                        class="h-10"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <Button
                        :disabled="processing"
                        variant="clay"
                        class="w-full h-10"
                    >
                        إنشاء فاتورة
                    </Button>
                </Form>
            </SheetContent>
        </Sheet>

        <Dialog
            :open="viewingInvoice !== null"
            @update:open="(open) => !open && closeViewInvoice()"
            aria-label="عرض تفاصيل الفاتورة"
        >
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            viewingInvoice?.invoice_number ?? 'تفاصيل الفاتورة'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        ملخص الفاتورة وحالة الدفع.
                    </DialogDescription>
                </DialogHeader>

                <dl
                    v-if="viewingInvoice"
                    class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4 sm:grid-cols-2"
                >
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            المريض
                        </dt>
                        <dd class="text-sm">
                            {{
                                viewingInvoice.patient?.full_name ??
                                resolvePatientName(viewingInvoice.patient_id)
                            }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            الحالة
                        </dt>
                        <dd class="text-sm capitalize">
                            {{ statusLabels[viewingInvoice.status] ?? viewingInvoice.status }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            الزيارة
                        </dt>
                        <dd class="text-sm">
                            {{ resolveVisitNumber(viewingInvoice.visit_id) }}
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
                                resolveAppointmentNumber(
                                    viewingInvoice.appointment_id,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            تاريخ الاستحقاق
                        </dt>
                        <dd class="text-sm">
                            {{ viewingInvoice.due_at ?? 'غير محدد' }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            تاريخ الإصدار
                        </dt>
                        <dd class="text-sm">
                            {{
                                viewingInvoice.issued_at
                                    ? new Date(
                                          viewingInvoice.issued_at,
                                      ).toLocaleString()
                                    : 'غير صادرة'
                            }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            المجموع الفرعي
                        </dt>
                        <dd class="text-sm">
                            {{ viewingInvoice.subtotal_amount.toFixed(2) }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            الخصم / الضريبة
                        </dt>
                        <dd class="text-sm">
                            -{{ viewingInvoice.discount_amount.toFixed(2) }} /
                            +{{ viewingInvoice.tax_amount.toFixed(2) }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            الإجمالي
                        </dt>
                        <dd class="text-sm">
                            {{ viewingInvoice.total_amount.toFixed(2) }}
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            المدفوع / الرصيد
                        </dt>
                        <dd class="text-sm">
                            {{ viewingInvoice.paid_amount.toFixed(2) }} /
                            {{ viewingInvoice.balance_amount.toFixed(2) }}
                        </dd>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            ملاحظات
                        </dt>
                        <dd class="text-sm leading-6 text-muted-foreground">
                            {{ viewingInvoice.notes ?? 'لا توجد ملاحظات' }}
                        </dd>
                    </div>
                    <div
                        v-if="(viewingInvoice.items ?? []).length > 0"
                        class="space-y-2 sm:col-span-2"
                    >
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            البنود
                        </dt>
                        <ul class="space-y-1 text-sm">
                            <li
                                v-for="item in viewingInvoice.items"
                                :key="item.id"
                                class="flex items-center justify-between gap-3 rounded-lg border border-border/60 bg-background/60 px-3 py-2"
                            >
                                <span class="text-muted-foreground">
                                    {{ item.description }}
                                </span>
                                <span class="font-medium">
                                    {{ item.line_total.toFixed(2) }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </dl>

                <div v-if="viewingInvoice && viewingInvoice.balance_amount > 0 && can('payment.record')" class="rounded-xl border-2 border-dashed border-success-300/50 bg-success-50/50 p-4">
                    <h4 class="text-sm font-semibold text-success-700 dark:text-success-400 mb-3">تسجيل دفعة سريعة</h4>
                    <Form
                        v-bind="PaymentController.store.form(viewingInvoice.id)"
                        class="space-y-3"
                        reset-on-success
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="grid gap-1">
                                <Label for="quick_payment_amount" class="text-xs">المبلغ *</Label>
                                <Input
                                    id="quick_payment_amount"
                                    name="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    :max="viewingInvoice.balance_amount"
                                    :placeholder="`الرصيد: ${viewingInvoice.balance_amount.toFixed(2)}`"
                                    class="pattern-field-clay h-9 text-sm"
                                    required
                                />
                                <InputError :message="errors.amount" />
                            </div>
                            <div class="grid gap-1">
                                <Label for="quick_payment_method" class="text-xs">طريقة الدفع</Label>
                                <select id="quick_payment_method" name="method" class="pattern-field-clay h-9 px-2 py-1 text-sm">
                                    <option value="cash">نقد</option>
                                    <option value="card">بطاقة</option>
                                    <option value="bank_transfer">تحويل بنكي</option>
                                    <option value="other">أخرى</option>
                                </select>
                            </div>
                            <div class="grid gap-1">
                                <Label for="quick_payment_notes" class="text-xs">ملاحظات</Label>
                                <Input id="quick_payment_notes" name="notes" placeholder="اختياري" class="pattern-field-clay h-9 text-sm" />
                                <InputError :message="errors.notes" />
                            </div>
                        </div>
                        <Button :disabled="processing" variant="clay" size="sm" class="h-9 px-4 text-xs">
                            تسجيل الدفعة
                        </Button>
                    </Form>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="neumorphic"
                        class="h-10"
                        @click="closeViewInvoice"
                    >
                        إغلاق
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="editingInvoice !== null"
            @update:open="(open) => !open && closeEditInvoice()"
            aria-label="تعديل الفاتورة"
        >
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>تعديل الفاتورة</DialogTitle>
                    <DialogDescription>
                        تحديث بيانات الفاتورة المسودة والروابط.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-if="
                        editingInvoice &&
                        canEditInvoice &&
                        editingInvoice.status === 'draft'
                    "
                    v-bind="InvoiceController.update.form(editingInvoice.id)"
                    class="space-y-4"
                    :options="{ preserveScroll: true }"
                    @success="closeEditInvoice"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_invoice_number">رقم الفاتورة</Label>
                            <Input
                                id="edit_invoice_number"
                                name="invoice_number"
                                :value="editingInvoice.invoice_number"
                                class="pattern-field-clay h-10"
                                required
                            />
                            <InputError :message="errors.invoice_number" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_invoice_due_at">تاريخ الاستحقاق</Label>
                            <Input
                                id="edit_invoice_due_at"
                                name="due_at"
                                type="date"
                                :value="editingInvoice.due_at ?? ''"
                                class="pattern-field-clay h-10"
                            />
                            <InputError :message="errors.due_at" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="edit_invoice_patient">المريض</Label>
                            <select
                                id="edit_invoice_patient"
                                name="patient_id"
                                class="pattern-field-clay h-10 px-3 py-2"
                                :value="String(editingInvoice.patient_id)"
                                required
                            >
                                <option
                                    v-for="patient in patients"
                                    :key="`edit-invoice-patient-${patient.id}`"
                                    :value="patient.id"
                                >
                                    {{ patient.full_name }}
                                </option>
                            </select>
                            <InputError :message="errors.patient_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_invoice_visit">الزيارة</Label>
                            <select
                                id="edit_invoice_visit"
                                name="visit_id"
                                class="pattern-field-clay h-10 px-3 py-2"
                                :value="
                                    editingInvoice.visit_id !== null
                                        ? String(editingInvoice.visit_id)
                                        : ''
                                "
                            >
                                <option value="">بدون زيارة</option>
                                <option
                                    v-for="visit in visits"
                                    :key="`edit-invoice-visit-${visit.id}`"
                                    :value="visit.id"
                                >
                                    {{ visit.visit_number }}
                                </option>
                            </select>
                            <InputError :message="errors.visit_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_invoice_appointment">
                                الموعد
                            </Label>
                            <select
                                id="edit_invoice_appointment"
                                name="appointment_id"
                                class="pattern-field-clay h-10 px-3 py-2"
                                :value="
                                    editingInvoice.appointment_id !== null
                                        ? String(editingInvoice.appointment_id)
                                        : ''
                                "
                            >
                                <option value="">بدون موعد</option>
                                <option
                                    v-for="appointment in appointments"
                                    :key="`edit-invoice-appointment-${appointment.id}`"
                                    :value="appointment.id"
                                >
                                    {{ appointment.appointment_number }}
                                </option>
                            </select>
                            <InputError :message="errors.appointment_id" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_invoice_notes">ملاحظات</Label>
                        <textarea
                            id="edit_invoice_notes"
                            name="notes"
                            rows="3"
                            class="pattern-field-clay"
                            :value="editingInvoice.notes ?? ''"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="neumorphic"
                            class="h-10"
                            :disabled="processing"
                            @click="closeEditInvoice"
                        >
                            إلغاء
                        </Button>
                        <Button
                            type="submit"
                            variant="clay"
                            class="h-10"
                            :disabled="processing"
                        >
                            حفظ التغييرات
                        </Button>
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
