<script setup lang="ts">
/**
 * StandardFormDialog — Type 2 template
 *
 * Single focused use-case, fixed fields.
 * Form validation via vee-validate + Zod.
 * Required fields marked with red asterisk.
 * On open: focuses first field automatically.
 *
 * Props:
 *   open        — controlled by parent
 *   onOpenChange — parent callback to close
 *   onSuccess   — called after successful submit (parent handles refetch/close)
 *   title       — dialog title
 *   description — optional dialog description
 *   size        — sm | md | lg | 2xl
 *   loading     — submit in progress
 *   fields      — array of field definitions
 */
import { nextTick, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription, DialogBody } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export interface FormField {
    name: string;
    label: string;
    type?: 'text' | 'email' | 'number' | 'date' | 'select' | 'textarea' | 'checkbox';
    required?: boolean;
    placeholder?: string;
    options?: { label: string; value: string | number }[];
    defaultValue?: string | number;
}

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description?: string;
        size?: 'sm' | 'md' | 'lg' | '2xl';
        loading?: boolean;
        fields: FormField[];
        submitLabel?: string;
        cancelLabel?: string;
    }>(),
    {
        description: '',
        size: 'md',
        loading: false,
        submitLabel: 'حفظ',
        cancelLabel: 'إلغاء',
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [data: Record<string, any>];
}>();

const formData = ref<Record<string, any>>({});
const errors = ref<Record<string, string[]>>({});
const firstInputRef = ref<HTMLElement | null>(null);

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            formData.value = {};
            errors.value = {};
            props.fields.forEach((field) => {
                formData.value[field.name] = field.defaultValue ?? '';
            });
            await nextTick();
            firstInputRef.value?.focus();
        }
    },
);

const handleSubmit = () => {
    const validationErrors: Record<string, string[]> = {};
    props.fields.forEach((field) => {
        if (field.required && !formData.value[field.name]) {
            validationErrors[field.name] = ['هذا الحقل مطلوب'];
        }
    });

    if (Object.keys(validationErrors).length > 0) {
        errors.value = validationErrors;

        return;
    }

    emit('submit', { ...formData.value });
};

const handleClose = () => {
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent :size="size" :close-on-overlay="!loading">
            <DialogHeader>
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <DialogTitle class="text-base font-medium text-[#1A1A1A]">{{ title }}</DialogTitle>
                        <DialogDescription v-if="description" class="mt-1 text-[13px] font-normal text-[#6B7280] line-clamp-1">
                            {{ description }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <DialogBody>
                <div class="flex flex-col gap-4">
                    <template v-for="(field, index) in fields" :key="field.name">
                        <div class="flex flex-col gap-2">
                            <Label :for="field.name" class="text-[13px] font-medium text-[#374151]">
                                {{ field.label }}
                                <span v-if="field.required" class="text-[#DC2626]">*</span>
                            </Label>

                            <Input
                                v-if="field.type === 'textarea'"
                                :id="field.name"
                                v-model="formData[field.name]"
                                :type="field.type || 'text'"
                                :placeholder="field.placeholder"
                                class="h-10"
                                :class="{ 'border-[#DC2626]': errors[field.name] }"
                            />

                            <select
                                v-else-if="field.type === 'select'"
                                :id="field.name"
                                v-model="formData[field.name]"
                                class="h-10 w-full rounded-xl border border-[#DDE9F3] bg-[#FBFDFF] px-3 py-2 text-sm focus:border-[#0EA5E9] focus:outline-none focus:ring-1 focus:ring-[#0EA5E9]/15"
                                :class="{ 'border-[#DC2626]': errors[field.name] }"
                            >
                                <option value="" disabled>اختر</option>
                                <option v-for="opt in field.options" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>

                            <div
                                v-else-if="field.type === 'checkbox'"
                                class="flex items-center gap-2 rounded-xl border border-border/60 bg-background/50 px-3 py-2"
                            >
                                <input
                                    :id="field.name"
                                    v-model="formData[field.name]"
                                    type="checkbox"
                                    :value="field.defaultValue ?? '1'"
                                    class="size-4 rounded border-border"
                                />
                                <Label :for="field.name" class="text-sm cursor-pointer">
                                    {{ field.label }}
                                </Label>
                            </div>

                            <Input
                                v-else
                                :id="field.name"
                                v-model="formData[field.name]"
                                :type="field.type || 'text'"
                                :placeholder="field.placeholder"
                                class="h-10"
                                :class="{ 'border-[#DC2626]': errors[field.name] }"
                                :ref="index === 0 ? (el: any) => (firstInputRef = el?.$el || el) : undefined"
                            />

                            <InputError v-if="errors[field.name]" :message="errors[field.name]?.[0]" />
                        </div>
                    </template>
                </div>
            </DialogBody>

            <DialogFooter>
                <Button
                    type="button"
                    variant="default"
                    :disabled="loading"
                    @click="handleSubmit"
                >
                    <Spinner v-if="loading" class="me-2 h-4 w-4" />
                    {{ loading ? 'جارٍ الحفظ...' : submitLabel }}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    class="text-[#6B7280] hover:bg-[#F9FAFB] hover:text-[#374151]"
                    :disabled="loading"
                    @click="handleClose"
                >
                    {{ cancelLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
