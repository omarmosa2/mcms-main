import { toast } from 'vue-sonner';

export type ToastType = 'success' | 'info' | 'warning' | 'error';

export function useToast() {
    const success = (message: string, options?: { description?: string }) => {
        toast.success(message, options);
    };

    const error = (message: string, options?: { description?: string }) => {
        toast.error(message, options);
    };

    const info = (message: string, options?: { description?: string }) => {
        toast.info(message, options);
    };

    const warning = (message: string, options?: { description?: string }) => {
        toast.warning(message, options);
    };

    return {
        success,
        error,
        info,
        warning,
    };
}
