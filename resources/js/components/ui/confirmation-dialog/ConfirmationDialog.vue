<script setup lang="ts">
import { AlertTriangle, CheckCircle2, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
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
            color: 'text-destructive',
            bgColor: 'bg-destructive/10',
        },
        create: {
            icon: Plus,
            color: 'text-primary',
            bgColor: 'bg-primary/10',
        },
        edit: {
            icon: Pencil,
            color: 'text-info-500',
            bgColor: 'bg-info-500/10',
        },
        success: {
            icon: CheckCircle2,
            color: 'text-success-500',
            bgColor: 'bg-success-500/10',
        },
    };

    return configs[props.options.variant] || configs.destructive;
});

const IconComponent = computed(() => iconConfig.value.icon);
</script>

<template>
    <Dialog :open="open" @update:open="(value) => !value && emit('cancel')">
        <DialogContent
            :class="[
                options.variant === 'destructive' ? 'border-destructive/50' : '',
                'sm:max-w-md',
            ]"
            :show-close-button="!loading"
        >
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl"
                        :class="[iconConfig.bgColor]"
                    >
                        <component
                            :is="IconComponent"
                            class="h-6 w-6"
                            :class="iconConfig.color"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <DialogTitle class="text-lg">{{ options.title }}</DialogTitle>
                        <DialogDescription class="text-sm">{{ options.description }}</DialogDescription>
                    </div>
                </div>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button
                    variant="ghost"
                    :disabled="loading"
                    @click="emit('cancel')"
                >
                    {{ options.cancelText }}
                </Button>
                <Button
                    :variant="options.variant === 'destructive' ? 'destructive' : 'clay'"
                    :disabled="loading"
                    @click="emit('confirm')"
                >
                    <Spinner v-if="loading" class="ms-2 h-4 w-4" />
                    {{ options.confirmText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>