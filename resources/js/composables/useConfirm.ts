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
        title: 'تأكيد الإجراء',
        description: 'هل أنت متأكد من المتابعة؟',
        confirmText: 'تأكيد',
        cancelText: 'إلغاء',
        variant: 'default',
    });

    const resolveRef = shallowRef<((value: boolean) => void) | null>(null);

    const confirm = (opts: ConfirmOptions = {}): Promise<boolean> => {
        options.value = {
            title: opts.title ?? 'تأكيد الإجراء',
            description: opts.description ?? 'هل أنت متأكد من المتابعة؟',
            confirmText: opts.confirmText ?? 'تأكيد',
            cancelText: opts.cancelText ?? 'إلغاء',
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

    const close = () => {
        isOpen.value = false;
        isLoading.value = false;
        resolveRef.value = null;
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
        close,
        handleConfirm,
        handleCancel,
    };
}
