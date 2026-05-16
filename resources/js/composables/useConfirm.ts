import { ref, shallowRef } from 'vue';

export interface ConfirmOptions {
    title?: string;
    description?: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'default' | 'destructive';
}

export function useConfirm() {
    const isOpen = ref(false);
    const isLoading = ref(false);
    const options = shallowRef<ConfirmOptions>({
        title: 'Confirm Action',
        description: 'Are you sure you want to proceed?',
        confirmText: 'Confirm',
        cancelText: 'Cancel',
        variant: 'default',
    });

    const resolveRef = shallowRef<((value: boolean) => void) | null>(null);

    const confirm = (opts: ConfirmOptions = {}): Promise<boolean> => {
        options.value = {
            title: opts.title ?? 'Confirm Action',
            description: opts.description ?? 'Are you sure you want to proceed?',
            confirmText: opts.confirmText ?? 'Confirm',
            cancelText: opts.cancelText ?? 'Cancel',
            variant: opts.variant ?? 'default',
        };
        isOpen.value = true;
        isLoading.value = false;

        return new Promise((resolve) => {
            resolveRef.value = resolve;
        });
    };

    const setLoading = (loading: boolean) => {
        isLoading.value = loading;
    };

    const handleConfirm = () => {
        isLoading.value = true;
        resolveRef.value?.(true);
    };

    const handleCancel = () => {
        isOpen.value = false;
        isLoading.value = false;
        resolveRef.value?.(false);
        resolveRef.value = null;
    };

    return {
        isOpen,
        isLoading,
        options,
        confirm,
        setLoading,
        handleConfirm,
        handleCancel,
    };
}
