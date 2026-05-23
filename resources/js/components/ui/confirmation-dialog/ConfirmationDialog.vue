<script setup lang="ts">
import { AlertTriangle, CheckCircle2, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription, DialogBody } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { ConfirmOptions } from '@/composables/useConfirm';

const props = defineProps<{
    open: boolean;
    loading?: boolean;
    options: ConfirmOptions;
}>();

const emit = defineEmits<{
    confirm: [];
    cancel: [];
    'update:open': [value: boolean];
}>();

const iconConfig = computed(() => {
    const configs: Record<string, { icon: any; color: string; bgColor: string }> = {
        destructive: {
            icon: Trash2,
            color: 'text-[#DC2626]',
            bgColor: 'bg-[#DC2626]/10',
        },
        create: {
            icon: Plus,
            color: 'text-[#1D9E75]',
            bgColor: 'bg-[#1D9E75]/10',
        },
        edit: {
            icon: Pencil,
            color: 'text-[#3B82F6]',
            bgColor: 'bg-[#3B82F6]/10',
        },
        success: {
            icon: CheckCircle2,
            color: 'text-[#16A34A]',
            bgColor: 'bg-[#16A34A]/10',
        },
    };

    return configs[props.options.variant] || configs.destructive;
});

const IconComponent = computed(() => iconConfig.value.icon);

const isDestructive = computed(() => props.options.variant === 'destructive');
</script>

<template>
    <Dialog :open="open" @update:open="(value) => !value && emit('cancel')">
        <DialogContent
            size="sm"
            :show-close-button="!loading"
            :close-on-overlay="!isDestructive"
        >
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                        :class="[iconConfig.bgColor]"
                    >
                        <component
                            :is="IconComponent"
                            class="h-5 w-5"
                            :class="iconConfig.color"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <DialogTitle class="text-base font-medium text-[#1A1A1A]">{{ options.title }}</DialogTitle>
                        <DialogDescription class="text-[13px] font-normal text-[#6B7280]">{{ options.description }}</DialogDescription>
                    </div>
                </div>
            </DialogHeader>
            <DialogFooter>
                <Button
                    :variant="options.variant === 'destructive' ? 'destructive' : 'default'"
                    :disabled="loading"
                    @click="emit('confirm')"
                >
                    <Spinner v-if="loading" class="me-2 h-4 w-4" />
                    {{ loading ? 'جارٍ التنفيذ...' : options.confirmText }}
                </Button>
                <Button
                    variant="ghost"
                    class="text-[#6B7280] hover:bg-[#F9FAFB] hover:text-[#374151]"
                    :disabled="loading"
                    @click="emit('cancel')"
                >
                    {{ options.cancelText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
