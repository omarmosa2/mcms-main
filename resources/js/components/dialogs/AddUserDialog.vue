<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { StandardFormDialog  } from '@/components/dialogs';
import type {FormField} from '@/components/dialogs';

const props = defineProps<{
    open: boolean;
    roles?: { id: number; name: string }[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    success: [];
}>();

const fields = computed<FormField[]>(() => [
    {
        name: 'name',
        label: 'اسم المستخدم',
        type: 'text',
        placeholder: 'أدخل اسم المستخدم',
        required: true,
    },
    {
        name: 'email',
        label: 'البريد الإلكتروني',
        type: 'email',
        placeholder: 'user@example.com',
        required: true,
    },
    {
        name: 'password',
        label: 'كلمة المرور',
        type: 'password',
        placeholder: 'أدخل كلمة المرور',
        required: true,
    },
    {
        name: 'password_confirmation',
        label: 'تأكيد كلمة المرور',
        type: 'password',
        placeholder: 'أعد إدخال كلمة المرور',
        required: true,
    },
    {
        name: 'role_id',
        label: 'الدور',
        type: 'select',
        placeholder: 'اختر الدور',
        options: props.roles?.map((r) => ({ value: r.id, label: r.name })) ?? [],
        required: true,
    },
    {
        name: 'is_active',
        label: 'الحالة',
        type: 'select',
        options: [
            { value: '1', label: 'نشط' },
            { value: '0', label: 'غير نشط' },
        ],
        default: '1',
    },
]);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role_id: '',
    is_active: '1',
});

const handleSubmit = () => {
    form.post(route('users.store'), {
        onSuccess: () => {
            form.reset();
            emit('success');
            emit('update:open', false);
        },
    });
};

const handleCancel = () => {
    form.reset();
    emit('update:open', false);
};
</script>

<template>
    <StandardFormDialog
        :open="open"
        title="إضافة مستخدم"
        description="إضافة مستخدم جديد إلى النظام"
        size="lg"
        :fields="fields"
        :form="form"
        :loading="form.processing"
        @confirm="handleSubmit"
        @cancel="handleCancel"
        @update:open="(value) => emit('update:open', value)"
    />
</template>
