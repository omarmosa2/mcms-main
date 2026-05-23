<script setup lang="ts">
/**
 * ComplexDialog — Type 3 template
 *
 * Handles create AND edit based on presence of initialData prop.
 * May contain dynamic rows (add/remove items).
 * May change available fields based on a select value (conditional fields).
 * Business logic extracted to composable — dialog is UI only.
 *
 * Props:
 *   open         — controlled by parent
 *   onOpenChange — parent callback to close
 *   onSuccess    — called after successful submit
 *   initialData? — presence triggers edit mode
 *   title        — dialog title (auto-computed if not provided)
 *   description  — optional dialog description
 *   size         — sm | md | lg | 2xl
 */
import { Plus, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription, DialogBody } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export interface ComplexField {
    name: string;
    label: string;
    type?: 'text' | 'email' | 'number' | 'date' | 'select' | 'textarea';
    required?: boolean;
    placeholder?: string;
    options?: { label: string; value: string | number }[];
    conditionalOn?: { field: string; value: any };
}

export interface DynamicRowField {
    name: string;
    label: string;
    placeholder?: string;
}

const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        description?: string;
        size?: 'sm' | 'md' | 'lg' | '2xl';
        loading?: boolean;
        initialData?: Record<string, any> | null;
        fields: ComplexField[];
        dynamicRows?: DynamicRowField[];
        submitLabel?: string;
        cancelLabel?: string;
    }>(),
    {
        title: '',
        description: '',
        size: 'lg',
        loading: false,
        initialData: null,
        dynamicRows: () => [],
        submitLabel: 'حفظ',
        cancelLabel: 'إلغاء',
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [data: Record<string, any>];
}>();

const isEditMode = computed(() => props.initialData !== null);

const computedTitle = computed(() => {
    if (props.title) {
return props.title;
}

    return isEditMode.value ? 'تعديل' : 'إضافة جديدة';
});

const formData = ref<Record<string, any>>({});
const errors = ref<Record<string, string[]>>({});
const dynamicRowValues = ref<Record<string, string[]>>({});
const firstInputRef = ref<HTMLElement | null>(null);

const visibleFields = computed(() => {
    return props.fields.filter((field) => {
        if (!field.conditionalOn) {
return true;
}

        return formData.value[field.conditionalOn.field] === field.conditionalOn.value;
    });
});

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            errors.value = {};

            if (isEditMode.value && props.initialData) {
                formData.value = { ...props.initialData };
            } else {
                formData.value = {};
                props.fields.forEach((field) => {
                    formData.value[field.name] = '';
                });
            }

            props.dynamicRows.forEach((rowField) => {
                if (isEditMode.value && props.initialData?.[rowField.name]) {
                    dynamicRowValues.value[rowField.name] = [...props.initialData[rowField.name]];
                } else {
                    dynamicRowValues.value[rowField.name] = [''];
                }
            });

            await nextTick();
            firstInputRef.value?.focus();
        }
    },
);

const addDynamicRow = (fieldName: string) => {
    if (!dynamicRowValues.value[fieldName]) {
        dynamicRowValues.value[fieldName] = [];
    }

    dynamicRowValues.value[fieldName].push('');
};

const removeDynamicRow = (fieldName: string, index: number) => {
    if (dynamicRowValues.value[fieldName].length <= 1) {
        dynamicRowValues.value[fieldName][0] = '';

        return;
    }

    dynamicRowValues.value[fieldName].splice(index, 1);
};

const handleSubmit = () => {
    const validationErrors: Record<string, string[]> = {};

    visibleFields.value.forEach((field) => {
        if (field.required && !formData.value[field.name]) {
            validationErrors[field.name] = ['هذا الحقل مطلوب'];
        }
    });

    if (Object.keys(validationErrors).length > 0) {
        errors.value = validationErrors;

        return;
    }

    const payload = {
        ...formData.value,
    };

    props.dynamicRows.forEach((rowField) => {
        payload[rowField.name] = (dynamicRowValues.value[rowField.name] || []).filter((v) => v.trim() !== '');
    });

    emit('submit', payload);
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
                        <DialogTitle class="text-base font-medium text-[#1A1A1A]">{{ computedTitle }}</DialogTitle>
                        <DialogDescription v-if="description" class="mt-1 text-[13px] font-normal text-[#6B7280] line-clamp-1">
                            {{ description }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <DialogBody>
                <div class="flex flex-col gap-4">
                    <template v-for="(field, index) in visibleFields" :key="field.name">
                        <div class="flex flex-col gap-2">
                            <Label :for="field.name" class="text-[13px] font-medium text-[#374151]">
                                {{ field.label }}
                                <span v-if="field.required" class="text-[#DC2626]">*</span>
                            </Label>

                            <textarea
                                v-if="field.type === 'textarea'"
                                :id="field.name"
                                v-model="formData[field.name]"
                                :placeholder="field.placeholder"
                                rows="3"
                                class="w-full rounded-lg border border-[#E5E7EB] px-3 py-2 text-sm focus:border-[#1D9E75] focus:outline-none focus:ring-1 focus:ring-[#1D9E75]/15"
                                :class="{ 'border-[#DC2626]': errors[field.name] }"
                            />

                            <select
                                v-else-if="field.type === 'select'"
                                :id="field.name"
                                v-model="formData[field.name]"
                                class="h-10 w-full rounded-lg border border-[#E5E7EB] px-3 py-2 text-sm focus:border-[#1D9E75] focus:outline-none focus:ring-1 focus:ring-[#1D9E75]/15"
                                :class="{ 'border-[#DC2626]': errors[field.name] }"
                            >
                                <option value="" disabled>اختر</option>
                                <option v-for="opt in field.options" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>

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

                    <template v-for="rowField in dynamicRows" :key="rowField.name">
                        <div class="flex flex-col gap-2 rounded-xl border border-border/60 bg-background/40 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <Label class="text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                                    {{ rowField.label }}
                                </Label>
                                <Button type="button" size="sm" variant="neumorphic" class="h-8 px-2 text-xs" @click="addDynamicRow(rowField.name)">
                                    <Plus class="me-1 h-3 w-3" />
                                    إضافة
                                </Button>
                            </div>
                            <div v-for="(value, index) in dynamicRowValues[rowField.name]" :key="`${rowField.name}-${index}`" class="flex items-center gap-2">
                                <Input
                                    v-model="dynamicRowValues[rowField.name][index]"
                                    :placeholder="rowField.placeholder"
                                    class="h-9"
                                />
                                <Button type="button" size="sm" variant="ghost" class="h-9 px-2 text-xs" @click="removeDynamicRow(rowField.name, index)">
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>
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
