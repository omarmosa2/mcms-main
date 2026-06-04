<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { Download, FileText } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import QueueEntryController from '@/actions/App/Http/Controllers/Queue/QueueEntryController';
import QueueEntryExportController from '@/actions/App/Http/Controllers/Queue/QueueEntryExportController';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';
import QueueEntryCreateSheet from './components/QueueEntryCreateSheet.vue';
import QueueEntryTable from './components/QueueEntryTable.vue';
import QueueEntryViewDialog from './components/QueueEntryViewDialog.vue';
import type { Option, PaginatedResponse, QueueEntry, QueueSortField, SortDirection } from './components/types';

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
const selectedQueueEntryIds = ref<number[]>([]);
let pollInterval: ReturnType<typeof setInterval> | null = null;

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

onBeforeUnmount(() => {
    stopPolling();
});

const deleteQueueEntry = async (entry: QueueEntry) => {
    const confirmed = await confirm({
        title: 'إزالة من الطابور',
        description: `هل أنت متأكد من حذف رقم الانتظار "${entry.queue_number}" للمريض "${entry.patient?.full_name ?? '-'}"؟ لا يمكن التراجع عن هذا الإجراء.`,
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

        <QueueEntryTable
            :queue-entries="queue_entries"
            :status-options="status_options"
            :filters="filters"
            :is-live="isLive"
            :selected-entry-ids="selectedQueueEntryIds"
            @update:is-live="toggleLiveUpdates"
            @update:selected-entry-ids="selectedQueueEntryIds = $event"
            @view-entry="viewingQueueEntry = $event"
            @delete-entry="deleteQueueEntry"
        />

        <QueueEntryCreateSheet
            :open="isCreateSheetOpen"
            :patients="patients"
            :appointments="appointments"
            :doctors="doctors"
            @update:open="isCreateSheetOpen = $event"
        />

        <QueueEntryViewDialog
            :queue-entry="viewingQueueEntry"
            @close="viewingQueueEntry = null"
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