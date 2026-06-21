<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { StandardFormDialog, type FormField } from '@/components/dialogs';
import { useToast } from '@/composables/useToast';

const props = defineProps<{
    open: boolean;
    clinics?: { id: number; name: string }[];
    specialties?: { id: number; name: string }[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    success: [];
}>();

const toast = useToast();

const fields = computed<FormField[]>(() => [
    {
        name: 'name',
        label: 'اسم الطبيب',
        type: 'text',
        placeholder: 'أدخل اسم الطبيب',
        required: true,
    },
    {
        name: 'email',
        label: 'البريد الإلكتروني',
        type: 'email',
        placeholder: 'doctor@example.com',
        required: true,
    },
    {
        name: 'phone',
        label: 'رقم الهاتف',
        type: 'text',
        placeholder: '+966 5XX XXX XXXX',
    },
    {
        name: 'specialty_id',
        label: 'التخصص',
        type: 'select',
        placeholder: 'اختر التخصص',
        options: props.specialties?.map((s) => ({ value: s.id, label: s.name })) ?? [],
        required: true,
    },
    {
        name: 'clinic_id',
        label: 'العيادة',
        type: 'select',
        placeholder: 'اختر العيادة',
        options: props.clinics?.map((c) => ({ value: c.id, label: c.name })) ?? [],
        required: true,
    },
    {
        name: 'license_number',
        label: 'رقم الترخيص',
        type: 'text',
        placeholder: 'أدخل رقم الترخيص',
    },
    {
        name: 'bio',
        label: 'نبذة مختصرة',
        type: 'textarea',
        placeholder: 'أدخل نبذة مختصرة عن الطبيب',
    },
]);

const loading = computed(() => false);

const handleSubmit = (data: Record<string, any>) => {
    router.post(route('doctors.store'), data, {
        onSuccess: () => {
            toast.success('تم إضافة الطبيب بنجاح');
            emit('success');
            emit('update:open', false);
        },
        onError: () => {
            toast.error('فشل إضافة الطبيب');
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
        title="إضافة طبيب"
        description="إضافة طبيب جديد إلى النظام"
        :fields="fields"
        :loading="loading"
        @submit="handleSubmit"
        @cancel="handleCancel"
        @update:open="(value) => emit('update:open', value)"
    />
</template>
