<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { FileText, Plus, Download, FileSpreadsheet } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import InvoiceController from '@/actions/App/Http/Controllers/Billing/InvoiceController';
import InvoiceExportController from '@/actions/App/Http/Controllers/Billing/InvoiceExportController';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import InvoiceCreateSheet from './components/InvoiceCreateSheet.vue';
import InvoiceEditDialog from './components/InvoiceEditDialog.vue';
import InvoiceTable from './components/InvoiceTable.vue';
import InvoiceViewDialog from './components/InvoiceViewDialog.vue';
import type {
    Invoice,
    InvoiceSortField,
    Option,
    PaginatedResponse,
    SortDirection,
} from './components/types';

const {
    invoices,
    patients,
    appointments,
    status_options,
    payment_method_options,
    filters,
} = defineProps<{
    invoices: PaginatedResponse<Invoice>;
    patients: Option[];
    appointments: Option[];
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
    return {
        status: localStatus.value.trim(),
        patient_id: patientId ?? '',
        search: localSearch.value.trim(),
        per_page: localRowsPerPage.value,
        page: localPage.value,
        sort_by: localSortBy.value,
        sort_direction: localSortDirection.value,
        ...overrides,
    };
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

const statusLabels: Record<string, string> = {
    draft: 'مسودة',
    issued: 'صادرة',
    paid: 'مدفوعة',
    overdue: 'متأخرة',
    canceled: 'ملغاة',
};

const statusOptions = computed(() => {
    const opts = [{ label: 'الكل', value: '' }];

    return [...opts, ...status_options.map((s: string) => ({ label: statusLabels[s] ?? s, value: s }))];
});

const activeFilters = computed(() => {
    const filtersList: { key: string; label: string; value: string | null }[] = [];

    if (localSearch.value.trim()) {
        filtersList.push({ key: 'search', label: 'بحث', value: localSearch.value.trim() });
    }

    if (localStatus.value) {
        filtersList.push({ key: 'status', label: 'الحالة', value: statusLabels[localStatus.value] ?? localStatus.value });
    }

    if (localPatientId.value) {
        const patient = patients.find(p => p.id === Number(localPatientId.value));
        filtersList.push({ key: 'patient_id', label: 'المريض', value: patient?.full_name ?? localPatientId.value });
    }

    return filtersList;
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

const handleSort = (field: InvoiceSortField): void => {
    toggleSort(field);
};

const handlePreviousPage = (): void => {
    goToPreviousPage();
};

const handleNextPage = (): void => {
    goToNextPage();
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
                    variant="default"
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

        <InvoiceTable
            :invoices="visibleInvoices"
            :selected-invoice-ids="selectedInvoiceIds"
            :deletable-invoice-ids="deletableInvoiceIds"
            :are-all-deletable-invoices-selected="areAllDeletableInvoicesSelected"
            :can-view-invoice="canViewInvoice"
            :can-edit-invoice="canEditInvoice"
            :local-search="localSearch"
            :local-status="localStatus"
            :local-rows-per-page="localRowsPerPage"
            :local-page="localPage"
            :local-sort-by="localSortBy"
            :local-sort-direction="localSortDirection"
            :total-local-pages="totalLocalPages"
            :local-visible-from="localVisibleFrom"
            :local-visible-to="localVisibleTo"
            :invoices-total="invoices.meta.total"
            :status-options="statusOptions"
            :active-filters="activeFilters"
            :payment-method-options="payment_method_options"
            @update:local-search="localSearch = $event"
            @update:local-status="localStatus = $event"
            @update:local-rows-per-page="localRowsPerPage = $event"
            @update:selected-invoice-ids="selectedInvoiceIds = $event"
            @toggle-all="toggleAllInvoicesSelection"
            @sort="handleSort"
            @previous-page="handlePreviousPage"
            @next-page="handleNextPage"
            @remove-filter="handleRemoveFilter"
            @reset-filters="resetLocalFilters"
            @view="openViewInvoice"
            @edit="openEditInvoice"
        />

        <InvoiceCreateSheet
            :open="isCreateSheetOpen"
            @update:open="isCreateSheetOpen = $event"
            :patients="patients"
            :appointments="appointments"
        />

        <InvoiceViewDialog
            :invoice="viewingInvoice"
            :patients="patients"
            :appointments="appointments"
            @close="closeViewInvoice"
        />

        <InvoiceEditDialog
            :invoice="editingInvoice"
            :patients="patients"
            :appointments="appointments"
            @close="closeEditInvoice"
        />
    </div>
</template>