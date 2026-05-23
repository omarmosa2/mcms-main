<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description?: string;
        size?: 'sm' | 'md' | 'lg' | '2xl';
        destructive?: boolean;
        loading?: boolean;
        loadingText?: string;
        cancelText?: string;
        confirmText?: string;
        closeOnOverlay?: boolean;
        showFooter?: boolean;
    }>(),
    {
        description: '',
        size: 'md',
        destructive: false,
        loading: false,
        loadingText: 'جارٍ الحفظ...',
        cancelText: 'إلغاء',
        confirmText: 'حفظ',
        closeOnOverlay: true,
        showFooter: true,
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    cancel: [];
    confirm: [];
}>();

const effectiveCloseOnOverlay = computed(() => {
    if (props.destructive) {
        return false;
    }

    return props.closeOnOverlay;
});

const handleCancel = () => {
    emit('cancel');
    emit('update:open', false);
};

const handleConfirm = () => {
    if (!props.loading) {
        emit('confirm');
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent
            :size="size"
            :show-close-button="false"
            :close-on-overlay="effectiveCloseOnOverlay"
        >
            <DialogHeader class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <DialogTitle class="text-base font-medium text-[#1A1A1A]">
                        {{ title }}
                    </DialogTitle>
                    <DialogDescription
                        v-if="description"
                        class="mt-1 text-[13px] font-normal text-[#6B7280] line-clamp-1"
                    >
                        {{ description }}
                    </DialogDescription>
                </div>
                <DialogClose
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[#6B7280] transition-colors hover:bg-[#F9FAFB] hover:text-[#374151] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1D9E75]/50"
                    aria-label="إغلاق"
                >
                    <X class="h-4 w-4" />
                </DialogClose>
            </DialogHeader>

            <div class="max-h-[60vh] overflow-y-auto px-6 py-5">
                <slot />
            </div>

            <DialogFooter v-if="showFooter">
                <Button
                    type="button"
                    :variant="destructive ? 'destructive' : 'default'"
                    :disabled="loading"
                    @click="handleConfirm"
                >
                    <Spinner v-if="loading" class="me-2 h-4 w-4" />
                    {{ loading ? loadingText : confirmText }}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    class="text-[#6B7280] hover:bg-[#F9FAFB] hover:text-[#374151]"
                    :disabled="loading"
                    @click="handleCancel"
                >
                    {{ cancelText }}
                </Button>
            </DialogFooter>

            <slot name="footer" />
        </DialogContent>
    </Dialog>
</template>
