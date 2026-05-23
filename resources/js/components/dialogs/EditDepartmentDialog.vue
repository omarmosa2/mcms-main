<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { StandardFormDialog  } from '@/components/dialogs';
import type {FormField} from '@/components/dialogs';

interface Department {
    id: number;
    name: string;
    description?: string;
    parent_id?: number;
    is_active: boolean;
}

const props = defineProps<{
    open: boolean;
    department: Department | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    success: [];
}>();

const fields = computed<FormField[]>(() => [
    {
        name: 'name',
        label: 'اسم القسم',
        type: 'text',
        placeholder: 'أدخل اسم القسم',
        required: true,
    },
    {
        name: 'description',
        label: 'الوصف',
        type: 'textarea',
        placeholder: 'أدخل وصف القسم',
    },
    {
        name: 'is_active',
        label: 'الحالة',
        type: 'select',
        options: [
            { value: '1', label: 'نشط' },
            { value: '0', label: 'غير نشط' },
        ],
    },
]);

const form = useForm({
    name: props.department?.name ?? '',
    description: props.department?.description ?? '',
    is_active: props.department?.is_active ? '1' : '0',
});

const handleSubmit = () => {
    if (!props.department) {
return;
}

    form.put(route('departments.update', props.department.id), {
        onSuccess: () => {
            emit('success');
            emit('update:open', false);
        },
    });
};

const handleCancel = () => {
    emit('update:open', false);
};
</script>

<template>
    <StandardFormDialog
        :open="open"
        title="تعديل القسم"
        description="تعديل بيانات القسم"
        :fields="fields"
        :form="form"
        :loading="form.processing"
        confirm-text="تحديث"
        loading-text="جارٍ التحديث..."
        @confirm="handleSubmit"
        @cancel="handleCancel"
        @update:open="(value) => emit('update:open', value)"
    />
</template>
