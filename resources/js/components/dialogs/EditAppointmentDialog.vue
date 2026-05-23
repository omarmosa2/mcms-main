<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { StandardFormDialog  } from '@/components/dialogs';
import type {FormField} from '@/components/dialogs';

interface Appointment {
    id: number;
    patient_id: number;
    doctor_id: number;
    department_id?: number;
    date: string;
    time: string;
    type: string;
    notes?: string;
    patient?: { full_name: string };
    doctor?: { name: string };
}

const props = defineProps<{
    open: boolean;
    appointment: Appointment | null;
    patients?: { id: number; full_name: string }[];
    doctors?: { id: number; name: string }[];
    departments?: { id: number; name: string }[];
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
        name: 'department_id',
        label: 'القسم',
        type: 'select',
        placeholder: 'اختر القسم',
        options: props.departments?.map((d) => ({ value: d.id, label: d.name })) ?? [],
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
    patient_id: props.appointment?.patient_id?.toString() ?? '',
    doctor_id: props.appointment?.doctor_id?.toString() ?? '',
    department_id: props.appointment?.department_id?.toString() ?? '',
    date: props.appointment?.date ?? '',
    time: props.appointment?.time ?? '',
    type: props.appointment?.type ?? '',
    notes: props.appointment?.notes ?? '',
});

const handleSubmit = () => {
    if (!props.appointment) {
return;
}

    form.put(route('appointments.update', props.appointment.id), {
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
        title="تعديل الموعد"
        description="تعديل بيانات الموعد"
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
