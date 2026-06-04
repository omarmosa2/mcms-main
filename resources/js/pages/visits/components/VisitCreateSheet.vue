<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import VisitController from '@/actions/App/Http/Controllers/Visits/VisitController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Option } from './types';

const props = defineProps<{
    open: boolean;
    patients: Option[];
    queueEntries: Option[];
    appointments: Option[];
    doctors: Option[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-[520px] p-0 overflow-hidden">
            <DialogHeader class="p-6 pb-4 border-b border-border/60">
                <DialogTitle>بدء زيارة</DialogTitle>
                <DialogDescription>بدء زيارة سريرية جديدة.</DialogDescription>
            </DialogHeader>

            <Form id="visit-create-form" v-bind="VisitController.store.form()" class="px-6 py-4 space-y-4 max-h-[60vh] overflow-y-auto" v-slot="{ errors, processing }" @success="emit('update:open', false)">
                <div class="grid gap-2">
                    <Label for="patient_id" class="text-xs font-medium text-slate-600">المريض</Label>
                    <select id="patient_id" name="patient_id" required class="h-9 w-full rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-[#0EA5E9]/40 focus:outline-none focus:ring-1 focus:ring-[#0EA5E9]/20">
                        <option value="">اختر المريض</option>
                        <option v-for="patient in props.patients" :key="patient.id" :value="patient.id">{{ patient.full_name }}</option>
                    </select>
                    <InputError :message="errors.patient_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="queue_entry_id" class="text-xs font-medium text-slate-600">قائمة الانتظار</Label>
                    <select id="queue_entry_id" name="queue_entry_id" class="h-9 w-full rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-[#0EA5E9]/40 focus:outline-none focus:ring-1 focus:ring-[#0EA5E9]/20">
                        <option value="">بدون قائمة انتظار</option>
                        <option v-for="queueEntry in props.queueEntries" :key="queueEntry.id" :value="queueEntry.id">{{ queueEntry.label }}</option>
                    </select>
                    <InputError :message="errors.queue_entry_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="appointment_id" class="text-xs font-medium text-slate-600">الموعد</Label>
                    <select id="appointment_id" name="appointment_id" class="h-9 w-full rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-[#0EA5E9]/40 focus:outline-none focus:ring-1 focus:ring-[#0EA5E9]/20">
                        <option value="">بدون موعد</option>
                        <option v-for="appointment in props.appointments" :key="appointment.id" :value="appointment.id">{{ appointment.appointment_number }}</option>
                    </select>
                    <InputError :message="errors.appointment_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="doctor_id" class="text-xs font-medium text-slate-600">الطبيب</Label>
                    <select id="doctor_id" name="doctor_id" class="h-9 w-full rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-[#0EA5E9]/40 focus:outline-none focus:ring-1 focus:ring-[#0EA5E9]/20">
                        <option value="">غير محدد</option>
                        <option v-for="doctor in props.doctors" :key="doctor.id" :value="doctor.id">{{ doctor.name }}</option>
                    </select>
                    <InputError :message="errors.doctor_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="chief_complaint" class="text-xs font-medium text-slate-600">الشكوى الرئيسية</Label>
                    <textarea id="chief_complaint" name="chief_complaint" rows="3" class="h-auto w-full rounded-lg border border-slate-200/80 bg-white px-3 py-2 text-sm text-slate-700 focus:border-[#0EA5E9]/40 focus:outline-none focus:ring-1 focus:ring-[#0EA5E9]/20"></textarea>
                    <InputError :message="errors.chief_complaint" />
                </div>

                <Button :disabled="processing" variant="default" class="w-full h-9 rounded-lg bg-[#0EA5E9] hover:bg-[#0284C7] shadow-sm">بدء الزيارة</Button>
            </Form>

            <DialogFooter class="p-6 pt-4 border-t border-border/60">
                <Button
                    type="button"
                    variant="outline"
                    @click="emit('update:open', false)"
                >
                    إلغاء
                </Button>
                <Button
                    form="visit-create-form"
                    type="submit"
                    variant="default"
                >
                    بدء الزيارة
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>