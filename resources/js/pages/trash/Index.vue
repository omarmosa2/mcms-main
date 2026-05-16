<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    RotateCcw,
    Trash2,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';

type TrashItem = {
    id: number;
    name: string;
    number: string | null;
    deleted_at: string | null;
};

type TrashSection = {
    label: string;
    count: number;
    items: TrashItem[];
};

type TrashData = Record<string, TrashSection>;

const { trashData } = defineProps<{
    trashData: TrashData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'سلة المحذوفات',
                href: '/trash',
            },
        ],
    },
});

const toast = useToast();
const {
    isOpen: isConfirmOpen,
    options: confirmOptions,
    confirm,
    handleConfirm: handleConfirmAction,
    handleCancel: handleConfirmCancel,
} = useConfirm();

const expandedSections = ref<Set<string>>(new Set());

const toggleSection = (type: string) => {
    const newSet = new Set(expandedSections.value);

    if (newSet.has(type)) {
        newSet.delete(type);
    } else {
        newSet.add(type);
    }

    expandedSections.value = newSet;
};

const restoreItem = async (type: string, id: number, name: string) => {
    const confirmed = await confirm({
        title: 'استرجاع عنصر',
        description: `هل أنت متأكد من استرجاع "${name}"؟`,
        confirmText: 'استرجاع',
        cancelText: 'إلغاء',
        variant: 'default',
    });

    if (confirmed) {
        router.post(`/trash/${type}/${id}/restore`, {}, {
            onSuccess: () => {
                toast.success('تم استرجاع العنصر بنجاح');
            },
            onError: () => {
                toast.error('فشل استرجاع العنصر');
            },
        });
    }
};

const forceDeleteItem = async (type: string, id: number, name: string) => {
    const confirmed = await confirm({
        title: 'حذف نهائي',
        description: `هل أنت متأكد من حذف "${name}" نهائياً؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف نهائي',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(`/trash/${type}/${id}/force`, {
            onSuccess: () => {
                toast.success('تم حذف العنصر نهائياً');
            },
            onError: () => {
                toast.error('فشل حذف العنصر');
            },
        });
    }
};

const emptyTrash = async (type: string, label: string) => {
    const confirmed = await confirm({
        title: 'إفراغ سلة المحذوفات',
        description: `هل أنت متأكد من حذف جميع العناصر في "${label}" نهائياً؟`,
        confirmText: 'إفراغ الكل',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(`/trash/${type}/empty`, {
            onSuccess: () => {
                toast.success('تم إفراغ سلة المحذوفات');
            },
            onError: () => {
                toast.error('فشل إفراغ سلة المحذوفات');
            },
        });
    }
};

const totalDeletedItems = Object.values(trashData).reduce(
    (sum, section) => sum + section.count,
    0,
);
</script>

<template>
    <Head title="سلة المحذوفات" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <div class="flex items-center gap-3">
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="router.visit('/dashboard')"
            >
                <ArrowLeft class="size-4" />
            </Button>
            <div>
                <h1 class="page-title">سلة المحذوفات</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    استرجاع أو حذف العناصر نهائياً.
                </p>
            </div>
        </div>

        <div
            v-if="totalDeletedItems === 0"
            class="flex flex-col items-center justify-center rounded-xl border border-border/70 bg-card py-16 text-center"
        >
            <Trash2 class="mb-4 size-12 text-muted-foreground/40" />
            <h3 class="text-lg font-semibold">سلة المحذوفات فارغة</h3>
            <p class="mt-1 text-sm text-muted-foreground">
                لا توجد عناصر محذوفة حالياً.
            </p>
        </div>

        <div v-else class="space-y-4">
            <div
                class="rounded-xl border border-destructive/30 bg-destructive/5 p-4"
            >
                <div class="flex items-center gap-2">
                    <AlertTriangle class="size-5 text-destructive" />
                    <span class="font-medium text-destructive">
                        إجمالي العناصر المحذوفة: {{ totalDeletedItems }}
                    </span>
                </div>
            </div>

            <div
                v-for="(section, type) in trashData"
                :key="type"
                class="rounded-xl border border-border/70 bg-card"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between p-4 transition hover:bg-muted/50"
                    @click="toggleSection(type)"
                >
                    <div class="flex items-center gap-3">
                        <span class="font-medium">{{ section.label }}</span>
                        <span
                            v-if="section.count > 0"
                            class="inline-flex items-center justify-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                        >
                            {{ section.count }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            v-if="section.count > 0 && expandedSections.has(type)"
                            type="button"
                            variant="destructive"
                            size="sm"
                            class="h-7 px-2 text-xs"
                            @click.stop="emptyTrash(type, section.label)"
                        >
                            إفراغ الكل
                        </Button>
                        <svg
                            class="size-4 transition-transform"
                            :class="{ 'rotate-180': expandedSections.has(type) }"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="M6 9l6 6 6-6" />
                        </svg>
                    </div>
                </button>

                <div
                    v-if="expandedSections.has(type) && section.items.length > 0"
                    class="border-t border-border/70"
                >
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-border/70">
                                <th class="px-4 py-2 text-start text-xs font-medium text-muted-foreground">
                                    النوع
                                </th>
                                <th class="px-4 py-2 text-start text-xs font-medium text-muted-foreground">
                                    حذف
                                </th>
                                <th class="px-4 py-2 text-start text-xs font-medium text-muted-foreground">
                                    الإجراءات
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in section.items"
                                :key="item.id"
                                class="border-b border-border/50 transition hover:bg-muted/30"
                            >
                                <td class="px-4 py-3">
                                    <span class="font-medium">{{ item.name }}</span>
                                    <span
                                        v-if="item.number"
                                        class="me-2 text-xs text-muted-foreground"
                                    >
                                        ({{ item.number }})
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-muted-foreground">
                                    {{ item.deleted_at ?? '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="default"
                                            size="sm"
                                            class="h-7 px-2 text-xs"
                                            @click="
                                                restoreItem(type, item.id, item.name)
                                            "
                                        >
                                            <RotateCcw class="me-1 size-3" />
                                            استرجاع
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            class="h-7 px-2 text-xs"
                                            @click="
                                                forceDeleteItem(type, item.id, item.name)
                                            "
                                        >
                                            <X class="me-1 size-3" />
                                            حذف نهائي
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="expandedSections.has(type) && section.items.length === 0 && section.count > 0"
                    class="border-t border-border/70 p-6 text-center text-sm text-muted-foreground"
                >
                    عرض أول 10 عناصر فقط من أصل {{ section.count }}
                </div>
            </div>
        </div>
    </div>
</template>
