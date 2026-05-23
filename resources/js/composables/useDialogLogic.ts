import { computed, ref, watch } from 'vue';

export interface DialogField {
    name: string;
    label: string;
    type?: 'text' | 'email' | 'number' | 'date' | 'select' | 'textarea';
    required?: boolean;
    placeholder?: string;
    options?: { label: string; value: string | number }[];
    conditionalOn?: { field: string; value: any };
}

export interface UseDialogLogicOptions<T extends Record<string, any>> {
    fields: DialogField[];
    initialData?: T | null;
    onSubmit: (data: T) => void | Promise<void>;
    onSuccess?: () => void;
}

export function useDialogLogic<T extends Record<string, any>>(options: UseDialogLogicOptions<T>) {
    const formData = ref<T>({} as T);
    const errors = ref<Record<string, string[]>>({});
    const isSubmitting = ref(false);

    const isEditMode = computed(() => options.initialData !== null && options.initialData !== undefined);

    const visibleFields = computed(() => {
        return options.fields.filter((field) => {
            if (!field.conditionalOn) return true;
            return formData.value[field.conditionalOn.field] === field.conditionalOn.value;
        });
    });

    function resetForm() {
        errors.value = {};
        if (isEditMode.value && options.initialData) {
            formData.value = { ...options.initialData } as T;
        } else {
            const initial: Record<string, any> = {};
            options.fields.forEach((field) => {
                initial[field.name] = '';
            });
            formData.value = initial as T;
        }
    }

    function validate(): boolean {
        const validationErrors: Record<string, string[]> = {};

        visibleFields.value.forEach((field) => {
            if (field.required && !formData.value[field.name]) {
                validationErrors[field.name] = ['هذا الحقل مطلوب'];
            }
        });

        errors.value = validationErrors;
        return Object.keys(validationErrors).length === 0;
    }

    async function submit() {
        if (!validate()) return;

        isSubmitting.value = true;
        try {
            await options.onSubmit(formData.value);
            resetForm();
            options.onSuccess?.();
        } catch (error) {
            console.error('Dialog submit error:', error);
        } finally {
            isSubmitting.value = false;
        }
    }

    return {
        formData,
        errors,
        isSubmitting,
        isEditMode,
        visibleFields,
        resetForm,
        validate,
        submit,
    };
}
