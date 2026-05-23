<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

const props = defineProps<{
    open: boolean;
    entityName: string;
    entityType?: string;
    onConfirm: () => void | Promise<void>;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const loading = ref(false);

const entityTypeLabel = computed(() => props.entityType || 'العنصر');

const descriptionText = computed(() => {
    return `هل أنت متأكد من حذف ${entityTypeLabel.value} "${props.entityName}"؟ لا يمكن التراجع عن هذا الإجراء.`;
});

const handleConfirm = async () => {
    loading.value = true;

    try {
        await props.onConfirm();
        emit('update:open', false);
    } catch (error) {
        console.error('DeleteDialog error:', error);
    } finally {
        loading.value = false;
    }
};

const handleCancel = () => {
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent size="sm" :close-on-overlay="false">
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#DC2626]/10">
                        <AlertTriangle class="h-5 w-5 text-[#DC2626]" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <DialogTitle class="text-base font-medium text-[#1A1A1A]">حذف {{ entityTypeLabel }}</DialogTitle>
                        <DialogDescription class="text-[13px] font-normal text-[#6B7280]">
                            {{ descriptionText }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <DialogFooter>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="loading"
                    @click="handleConfirm"
                >
                    <Spinner v-if="loading" class="me-2 h-4 w-4" />
                    {{ loading ? 'جارٍ الحذف...' : 'حذف' }}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    class="text-[#6B7280] hover:bg-[#F9FAFB] hover:text-[#374151]"
                    :disabled="loading"
                    @click="handleCancel"
                >
                    إلغاء
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
