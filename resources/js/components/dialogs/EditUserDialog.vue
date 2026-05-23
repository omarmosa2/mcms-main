<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { StandardFormDialog  } from '@/components/dialogs';
import type {FormField} from '@/components/dialogs';

interface User {
    id: number;
    name: string;
    email: string;
    role_id?: number;
    is_active: boolean;
}

const props = defineProps<{
    open: boolean;
    user: User | null;
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
        placeholder: 'اتركه فارغاً إذا لم ترد التغيير',
    },
    {
        name: 'password_confirmation',
        label: 'تأكيد كلمة المرور',
        type: 'password',
        placeholder: 'أعد إدخال كلمة المرور',
    },
    {
        name: 'role_id',
        label: 'الدور',
        type: 'select',
        placeholder: 'اختر الدور',
        options: props.roles?.map((r) => ({ value: r.id, label: r.name })) ?? [],
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
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    role_id: props.user?.role_id?.toString() ?? '',
    is_active: props.user?.is_active ? '1' : '0',
});

const handleSubmit = () => {
    if (!props.user) {
return;
}

    form.put(route('users.update', props.user.id), {
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
        title="تعديل المستخدم"
        description="تعديل بيانات المستخدم"
        size="lg"
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
