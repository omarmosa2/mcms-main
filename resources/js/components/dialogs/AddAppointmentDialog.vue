<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { StandardFormDialog  } from '@/components/dialogs';
import type {FormField} from '@/components/dialogs';

const props = defineProps<{
    open: boolean;
    patients?: { id: number; full_name: string }[];
    doctors?: { id: number; name: string }[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    success: [];
}>();

const fields = computed<FormField[]>(() => [
    {
        name: 'patient_id',
        label: 'المريض',
        type: 'select',
        placeholder: 'اختر المريض',
        options: props.patients?.map((p) => ({ value: p.id, label: p.full_name })) ?? [],
        required: true,
    },
    {
        name: 'doctor_id',
        label: 'الطبيب',
        type: 'select',
        placeholder: 'اختر الطبيب',
        options: props.doctors?.map((d) => ({ value: d.id, label: d.name })) ?? [],
        required: true,
    },
    {
        name: 'date',
        label: 'التاريخ',
        type: 'date',
        required: true,
    },
    {
        name: 'time',
        label: 'الوقت',
        type: 'time',
        required: true,
    },
    {
        name: 'type',
        label: 'نوع الموعد',
        type: 'select',
        placeholder: 'اختر النوع',
        options: [
            { value: 'consultation', label: 'استشارة' },
            { value: 'follow_up', label: 'متابعة' },
            { value: 'emergency', label: 'طوارئ' },
        ],
        required: true,
    },
    {
        name: 'notes',
        label: 'ملاحظات',
        type: 'textarea',
        placeholder: 'أدخل ملاحظات الموعد',
    },
]);

const form = useForm({
    patient_id: '',
    doctor_id: '',
    date: '',
    time: '',
    type: '',
    notes: '',
});

const handleSubmit = () => {
    form.post(route('appointments.store'), {
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
        title="حجز موعد"
        description="حجز موعد جديد للمريض"
        size="lg"
        :fields="fields"
        :form="form"
        :loading="form.processing"
        @confirm="handleSubmit"
        @cancel="handleCancel"
        @update:open="(value) => emit('update:open', value)"
    />
</template>
