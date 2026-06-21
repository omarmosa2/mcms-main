<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { StandardFormDialog  } from '@/components/dialogs';
import type {FormField} from '@/components/dialogs';
import type { Doctor } from '@/types';

const props = defineProps<{
    open: boolean;
    doctor: Doctor | null;
    clinics?: { id: number; name: string }[];
    specialties?: { id: number; name: string }[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    success: [];
}>();

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
        type: 'tel',
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

const form = useForm({
    name: props.doctor?.name ?? '',
    email: props.doctor?.email ?? '',
    phone: props.doctor?.phone ?? '',
    specialty_id: props.doctor?.specialty_id?.toString() ?? '',
    clinic_id: props.doctor?.clinic_id?.toString() ?? '',
    license_number: props.doctor?.license_number ?? '',
    bio: props.doctor?.bio ?? '',
});

const handleSubmit = () => {
    if (!props.doctor) {
return;
}

    form.put(route('doctors.update', props.doctor.id), {
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
        title="تعديل بيانات الطبيب"
        description="تعديل بيانات الطبيب في النظام"
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
