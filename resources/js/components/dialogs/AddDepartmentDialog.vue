<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { StandardFormDialog, type FormField } from '@/components/dialogs';
import { useToast } from '@/composables/useToast';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    success: [];
}>();

const toast = useToast();

const fields = computed<FormField[]>(() => [
    {
        name: 'name',
        label: 'الاسم',
        type: 'text',
        placeholder: 'قسم القلب',
        required: true,
    },
    {
        name: 'code',
        label: 'الرمز',
        type: 'text',
        placeholder: 'CARD',
    },
    {
        name: 'description',
        label: 'الوصف',
        type: 'textarea',
        placeholder: 'ملاحظات ونطاق خدمات القسم',
    },
    {
        name: 'is_active',
        label: 'قسم نشط',
        type: 'checkbox',
        defaultValue: '1',
    },
]);

const loading = computed(() => false);

const handleSubmit = (data: Record<string, any>) => {
    router.post(route('departments.store'), data, {
        onSuccess: () => {
            toast.success('تم إنشاء القسم بنجاح');
            emit('success');
            emit('update:open', false);
        },
        onError: (errors) => {
            toast.error('فشل إنشاء القسم');
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
        title="إنشاء قسم"
        description="إضافة قسم جديد للعيادة"
        :fields="fields"
        :loading="loading"
        submit-label="إنشاء قسم"
        @submit="handleSubmit"
        @cancel="handleCancel"
        @update:open="(value) => emit('update:open', value)"
    />
</template>
