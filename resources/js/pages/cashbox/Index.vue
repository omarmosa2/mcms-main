<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { Lock, Unlock, Calendar, Plus, Pencil, Trash2, Eye } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CashboxController from '@/actions/App/Http/Controllers/Cashbox/CashboxController';
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

type CashboxOpener = {
    id: number;
    name: string;
};

type Cashbox = {
    id: number;
    opening_balance: number;
    total_income: number;
    total_expenses: number;
    closing_balance: number;
    box_date: string;
    status: 'open' | 'closed';
    opener: CashboxOpener | null;
    opened_at: string | null;
    closer: CashboxOpener | null;
    closed_at: string | null;
    notes: string | null;
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

type CashboxSortField = 'box_date' | 'opening_balance' | 'closing_balance' | 'status';
type SortDirection = 'asc' | 'desc';

const { today_box, daily_income, daily_expenses, current_balance, recent_boxes, filters } = defineProps<{
    today_box: Cashbox | null;
    daily_income: number;
    daily_expenses: number;
    current_balance: number;
    recent_boxes: PaginatedResponse<Cashbox>;
    filters: {
        per_page: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'الصندوق',
                href: CashboxController.index(),
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

const selectedBoxIds = ref<number[]>([]);
const viewingBox = ref<Cashbox | null>(null);
const editingBox = ref<Cashbox | null>(null);
const isCreateSheetOpen = ref(false);

const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(recent_boxes.meta.current_page);

const allowedSortFields: CashboxSortField[] = ['box_date', 'opening_balance', 'closing_balance', 'status'];

const resolveInitialSortBy = (): CashboxSortField => {
    return 'box_date';
};

const localSortBy = ref<CashboxSortField>(resolveInitialSortBy());
const localSortDirection = ref<SortDirection>('desc');

const visibleBoxes = computed<Cashbox[]>(() => recent_boxes.data);
const totalLocalPages = computed<number>(() => Math.max(1, recent_boxes.meta.last_page));
const localVisibleFrom = computed<number>(() => recent_boxes.meta.from ?? 0);
const localVisibleTo = computed<number>(() => recent_boxes.meta.to ?? 0);

const defaultRowsPerPage = 15;
const isSyncingFromServer = ref(false);

const buildIndexQuery = (
    overrides: Partial<{
        per_page: number;
        page: number;
        sort_by: CashboxSortField;
        sort_direction: SortDirection;
    }> = {},
) => {
    return {
        per_page: overrides.per_page ?? localRowsPerPage.value,
        page: overrides.page ?? localPage.value,
        sort_by: overrides.sort_by ?? localSortBy.value,
        sort_direction: overrides.sort_direction ?? localSortDirection.value,
    };
};

const reloadBoxes = (
    overrides: Partial<{
        per_page: number;
        page: number;
        sort_by: CashboxSortField;
        sort_direction: SortDirection;
    }> = {},
) => {
    if (isSyncingFromServer.value) {
        return;
    }

    router.cancelAll();
    router.get(CashboxController.index.url(), buildIndexQuery(overrides), {
        only: ['recent_boxes', 'filters'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const goToPreviousPage = () => {
    if (localPage.value <= 1) {
        return;
    }

    localPage.value -= 1;
    reloadBoxes({ page: localPage.value });
};

const goToNextPage = () => {
    if (localPage.value >= totalLocalPages.value) {
        return;
    }

    localPage.value += 1;
    reloadBoxes({ page: localPage.value });
};

watch(
    () => [filters.per_page, recent_boxes.meta.current_page],
    () => {
        isSyncingFromServer.value = true;
        localRowsPerPage.value = filters.per_page;
        localPage.value = recent_boxes.meta.current_page;
        isSyncingFromServer.value = false;
    },
    { immediate: true },
);

watch(localRowsPerPage, () => {
    localPage.value = 1;
    reloadBoxes({ page: 1, per_page: localRowsPerPage.value });
});

const selectableBoxIds = computed<number[]>(() =>
    visibleBoxes.value.map((box) => box.id),
);

const areAllBoxesSelected = computed<boolean>(() => {
    if (selectableBoxIds.value.length === 0) {
        return false;
    }

    return selectableBoxIds.value.every((id) =>
        selectedBoxIds.value.includes(id),
    );
});

watch(selectableBoxIds, (ids) => {
    selectedBoxIds.value = selectedBoxIds.value.filter((id) =>
        ids.includes(id),
    );
});

const toggleAllBoxesSelection = (event: Event) => {
    const target = event.target as HTMLInputElement;
    selectedBoxIds.value = target.checked
        ? [...selectableBoxIds.value]
        : [];
};

const clearSelectedBoxes = () => {
    selectedBoxIds.value = [];
};

const formatAmount = (amount: number | string): string => {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;

    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(num);
};

const statusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        open: 'مفتوح',
        closed: 'مغلق',
    };

    return labels[status] ?? status;
};

const statusClass = (status: string): string => {
    if (status === 'open') {
        return 'border-warning-300/70 bg-warning-50 text-warning-800 dark:border-warning-500/40 dark:bg-warning-500/15 dark:text-warning-100';
    }

    return 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/40 dark:bg-success-500/15 dark:text-success-100';
};

const isOpen = computed(() => today_box !== null && today_box.status === 'open');

const openViewBox = async (box: Cashbox) => {
    viewingBox.value = box;
};

const closeViewBox = () => {
    viewingBox.value = null;
};

const openEditBox = (box: Cashbox) => {
    editingBox.value = box;
};

const closeEditBox = () => {
    editingBox.value = null;
};

const handleDeleteBox = async (box: Cashbox) => {
    const confirmed = await confirm({
        title: 'حذف الصندوق',
        description: `هل أنت متأكد من حذف صندوق يوم "${box.box_date}"؟ يجب أن يكون الصندوق مغلقاً أولاً.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(CashboxController.destroy(box.id), {
            onSuccess: () => {
                toast.success('تم حذف الصندوق بنجاح');
            },
            onError: () => {
                toast.error('فشل حذف الصندوق');
            },
        });
    }
};

const handleBulkDelete = async () => {
    const confirmed = await confirm({
        title: 'حذف الصناديق',
        description: `هل أنت متأكد من حذف ${selectedBoxIds.value.length} صندوق؟ يجب أن تكون الصناديق مغلقة أولاً.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(CashboxController.bulkDestroy.url(), {
            data: { ids: selectedBoxIds.value },
            onSuccess: () => {
                clearSelectedBoxes();
                toast.success('تم حذف الصناديق المحددة بنجاح');
            },
            onError: () => {
                toast.error('فشل حذف الصناديق');
            },
        });
    }
};
</script>

<template>
    <Head title="الصندوق" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="page-title">الصندوق</h1>
                    <p class="mt-1 text-sm text-muted-foreground">إدارة الصندوق اليومي وتدفق النقد.</p>
                </div>
                <span class="inline-flex items-center rounded-full border border-border/60 bg-muted/40 px-2.5 py-0.5 text-[0.7rem] font-medium text-muted-foreground">
                    {{ activeRoleLabel }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="can('cashbox.open') && !today_box"
                    variant="clay"
                    size="sm"
                    class="h-10 rounded-lg px-3 text-xs"
                    @click="isCreateSheetOpen = true"
                >
                    <Plus class="size-3.5" />
                    فتح الصندوق
                </Button>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-3">
            <section v-if="can('cashbox.open') && !today_box" class="glass-panel-soft p-5 lg:col-span-1">
                <h3 class="pattern-typographic-title mb-4 text-[0.76rem]">
                    فتح الصندوق
                </h3>
                <Form v-bind="CashboxController.store.form()" class="space-y-4" reset-on-success v-slot="{ errors, processing }">
                    <div class="grid gap-2">
                        <Label for="opening_balance">رصيد الافتتاح</Label>
                        <Input
                            id="opening_balance"
                            name="opening_balance"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.opening_balance" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="notes">ملاحظات</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="3"
                            class="pattern-field-clay"
                            placeholder="ملاحظات اختيارية..."
                        />
                    </div>
                    <Button :disabled="processing" variant="clay" class="w-full">
                        <Unlock class="ml-2 size-4" />
                        فتح الصندوق
                    </Button>
                </Form>
            </section>

            <section v-if="can('cashbox.close') && isOpen" class="glass-panel-soft p-5 lg:col-span-1">
                <h3 class="pattern-typographic-title mb-4 text-[0.76rem]">
                    إغلاق الصندوق
                </h3>
                <div class="rounded-xl border border-border/70 bg-background/55 p-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">رصيد الافتتاح</span>
                        <span class="font-mono font-semibold">{{ formatAmount(today_box!.opening_balance) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">+ الإيرادات</span>
                        <span class="font-mono font-semibold text-success-600 dark:text-success-400">+{{ formatAmount(daily_income) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">- المصروفات</span>
                        <span class="font-mono font-semibold text-destructive">-{{ formatAmount(daily_expenses) }}</span>
                    </div>
                    <div class="border-t pt-2 flex justify-between font-semibold">
                        <span>رصيد الإغلاق</span>
                        <span class="font-mono">{{ formatAmount(current_balance) }}</span>
                    </div>
                </div>
                <Form :action="CashboxController.close.url(today_box!.id)" method="post" class="mt-4 space-y-4" v-slot="{ processing }">
                    <div class="grid gap-2">
                        <Label for="notes">ملاحظات الإغلاق</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="2"
                            class="pattern-field-clay"
                            placeholder="ملاحظات اختيارية..."
                        />
                    </div>
                    <Button :disabled="processing" variant="clay" class="w-full">
                        <Lock class="ml-2 size-4" />
                        إغلاق الصندوق
                    </Button>
                </Form>
            </section>

            <section class="glass-panel-soft p-5 lg:col-span-2">
                <h3 class="pattern-typographic-title mb-4 text-[0.76rem]">
                    سجل الصناديق
                </h3>

                <div
                    v-if="can('cashbox.open') && selectedBoxIds.length > 0"
                    class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3"
                >
                    <Button type="button" variant="destructive" size="sm" @click="handleBulkDelete">
                        حذف المحدد ({{ selectedBoxIds.length }})
                    </Button>
                    <Button type="button" variant="ghost" size="sm" @click="clearSelectedBoxes">إلغاء التحديد</Button>
                </div>

                <div class="ui-table-shell">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th v-if="can('cashbox.open')" class="px-3 py-2">
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-border"
                                        :checked="areAllBoxesSelected"
                                        @change="toggleAllBoxesSelection"
                                    />
                                </th>
                                <th class="px-3 py-2">التاريخ</th>
                                <th class="px-3 py-2">الحالة</th>
                                <th class="px-3 py-2">الافتتاح</th>
                                <th class="px-3 py-2">الإيرادات</th>
                                <th class="px-3 py-2">المصروفات</th>
                                <th class="px-3 py-2">الإغلاق</th>
                                <th class="px-3 py-2">فتح بواسطة</th>
                                <th class="px-3 py-2 text-right">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="box in visibleBoxes" :key="box.id" class="ui-table-row">
                                <td v-if="can('cashbox.open')" class="px-3 py-2" data-label="تحديد">
                                    <input
                                        v-model="selectedBoxIds"
                                        type="checkbox"
                                        class="size-4 rounded border-border"
                                        :value="box.id"
                                    />
                                </td>
                                <td class="px-3 py-2" data-label="التاريخ">
                                    <div class="flex items-center gap-2">
                                        <Calendar class="size-4 text-muted-foreground" />
                                        {{ box.box_date }}
                                    </div>
                                </td>
                                <td class="px-3 py-2" data-label="الحالة">
                                    <span
                                        :class="statusClass(box.status)"
                                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                                    >
                                        <span
                                            class="w-1.5 h-1.5 rounded-full"
                                            :class="box.status === 'open' ? 'bg-warning-500' : 'bg-success-500'"
                                        ></span>
                                        <Unlock v-if="box.status === 'open'" class="size-3" />
                                        <Lock v-else class="size-3" />
                                        {{ statusLabel(box.status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-mono" data-label="الافتتاح">
                                    {{ formatAmount(box.opening_balance) }}
                                </td>
                                <td class="px-3 py-2 font-mono text-success-600 dark:text-success-400" data-label="الإيرادات">
                                    +{{ formatAmount(box.total_income) }}
                                </td>
                                <td class="px-3 py-2 font-mono text-destructive" data-label="المصروفات">
                                    -{{ formatAmount(box.total_expenses) }}
                                </td>
                                <td class="px-3 py-2 font-mono font-bold" data-label="الإغلاق">
                                    {{ formatAmount(box.closing_balance) }}
                                </td>
                                <td class="px-3 py-2" data-label="فتح بواسطة">
                                    {{ box.opener?.name ?? '-' }}
                                </td>
                                <td class="table-cell-actions px-3 py-2 md:text-right" data-label="الإجراءات">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <Button
                                            v-if="can('cashbox.view')"
                                            type="button"
                                            variant="neumorphic"
                                            size="sm"
                                            class="h-8 px-3 text-xs"
                                            @click="openViewBox(box)"
                                        >
                                            <Eye class="size-3" />
                                            عرض
                                        </Button>
                                        <Button
                                            v-if="can('cashbox.open') && box.status === 'open'"
                                            type="button"
                                            variant="clay"
                                            size="sm"
                                            class="h-8 px-3 text-xs"
                                            @click="openEditBox(box)"
                                        >
                                            <Pencil class="size-3" />
                                            تعديل
                                        </Button>
                                        <Button
                                            v-if="can('cashbox.open') && box.status === 'closed'"
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            class="h-8 px-3 text-xs"
                                            @click="handleDeleteBox(box)"
                                        >
                                            <Trash2 class="size-3" />
                                            حذف
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="visibleBoxes.length === 0" class="table-empty-state">
                                <td :colspan="can('cashbox.open') ? 9 : 8" class="px-3 py-10 text-center text-muted-foreground">
                                    لا توجد سجلات صندوق.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2">
                    <p class="text-xs text-muted-foreground">
                        عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من {{ recent_boxes.meta.total }} سجل
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
                    <SheetTitle>فتح الصندوق</SheetTitle>
                    <SheetDescription>افتح صندوق اليوم برصيد افتتاحي.</SheetDescription>
                </SheetHeader>

                <Form
                    v-bind="CashboxController.store.form()"
                    class="mt-6 space-y-4"
                    reset-on-success
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="opening_balance">رصيد الافتتاح</Label>
                        <Input
                            id="opening_balance"
                            name="opening_balance"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.opening_balance" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="notes">ملاحظات</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="3"
                            class="pattern-field-clay"
                            placeholder="ملاحظات اختيارية..."
                        />
                        <InputError :message="errors.notes" />
                    </div>
                    <Button :disabled="processing" variant="clay" class="w-full">
                        <Unlock class="ml-2 size-4" />
                        فتح الصندوق
                    </Button>
                </Form>
            </SheetContent>
        </Sheet>

        <Dialog :open="viewingBox !== null" @update:open="(open) => !open && closeViewBox()">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>تفاصيل الصندوق</DialogTitle>
                    <DialogDescription>صندوق يوم {{ viewingBox?.box_date }}</DialogDescription>
                </DialogHeader>

                <div v-if="viewingBox" class="grid gap-4">
                    <dl class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4 sm:grid-cols-2">
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">التاريخ</dt>
                            <dd class="text-sm">{{ viewingBox.box_date }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">الحالة</dt>
                            <dd>
                                <span :class="statusClass(viewingBox.status)" class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize">
                                    {{ statusLabel(viewingBox.status) }}
                                </span>
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">رصيد الافتتاح</dt>
                            <dd class="font-mono text-sm">{{ formatAmount(viewingBox.opening_balance) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">الإيرادات</dt>
                            <dd class="font-mono text-sm text-success-600 dark:text-success-400">+{{ formatAmount(viewingBox.total_income) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">المصروفات</dt>
                            <dd class="font-mono text-sm text-destructive">-{{ formatAmount(viewingBox.total_expenses) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">رصيد الإغلاق</dt>
                            <dd class="font-mono text-sm font-bold">{{ formatAmount(viewingBox.closing_balance) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">فتح بواسطة</dt>
                            <dd class="text-sm">{{ viewingBox.opener?.name ?? '-' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">وقت الفتح</dt>
                            <dd class="text-sm">{{ viewingBox.opened_at ?? '-' }}</dd>
                        </div>
                        <div v-if="viewingBox.closer" class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">إغلاق بواسطة</dt>
                            <dd class="text-sm">{{ viewingBox.closer.name }}</dd>
                        </div>
                        <div v-if="viewingBox.closed_at" class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">وقت الإغلاق</dt>
                            <dd class="text-sm">{{ viewingBox.closed_at }}</dd>
                        </div>
                        <div v-if="viewingBox.notes" class="space-y-1 sm:col-span-2">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">ملاحظات</dt>
                            <dd class="text-sm leading-6 text-muted-foreground">{{ viewingBox.notes }}</dd>
                        </div>
                    </dl>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="closeViewBox()">إغلاق</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="editingBox !== null" @update:open="(open) => !open && closeEditBox()">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>تعديل الصندوق</DialogTitle>
                    <DialogDescription>تعديل رصيد الافتتاح والملاحظات.</DialogDescription>
                </DialogHeader>

                <Form
                    v-if="editingBox"
                    v-bind="CashboxController.update.form(editingBox.id)"
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="edit_opening_balance">رصيد الافتتاح</Label>
                        <Input
                            id="edit_opening_balance"
                            name="opening_balance"
                            type="number"
                            step="0.01"
                            min="0"
                            :default-value="editingBox.opening_balance"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.opening_balance" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit_notes">ملاحظات</Label>
                        <textarea
                            id="edit_notes"
                            name="notes"
                            rows="3"
                            class="pattern-field-clay"
                            placeholder="ملاحظات اختيارية..."
                        >{{ editingBox.notes ?? '' }}</textarea>
                        <InputError :message="errors.notes" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" @click="closeEditBox()">إلغاء</Button>
                        <Button type="submit" variant="clay" :disabled="processing">حفظ التغييرات</Button>
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
