<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import QueueEntryController from '@/actions/App/Http/Controllers/Queue/QueueEntryController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
                <DialogTitle>إضافة إلى الطابور</DialogTitle>
                <DialogDescription>تسجيل مريض جديد في قائمة الانتظار.</DialogDescription>
            </DialogHeader>

            <Form
                id="queue-entry-create-form"
                v-bind="QueueEntryController.store.form()"
                class="px-6 py-4 space-y-4 max-h-[60vh] overflow-y-auto"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="patient_id">المريض</Label>
                    <select
                        id="patient_id"
                        name="patient_id"
                        required
                        class="pattern-field-clay h-9 px-3 py-1.5"
                    >
                        <option value="">اختر مريض</option>
                        <option
                            v-for="patient in props.patients"
                            :key="patient.id"
                            :value="patient.id"
                        >
                            {{ patient.full_name }}
                        </option>
                    </select>
                    <InputError :message="errors.patient_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="appointment_id">الموعد</Label>
                    <select
                        id="appointment_id"
                        name="appointment_id"
                        class="pattern-field-clay h-9 px-3 py-1.5"
                    >
                        <option value="">بدون موعد</option>
                        <option
                            v-for="appointment in props.appointments"
                            :key="appointment.id"
                            :value="appointment.id"
                        >
                            {{ appointment.appointment_number }}
                        </option>
                    </select>
                    <InputError :message="errors.appointment_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="assigned_doctor_id">الطبيب المعين</Label>
                    <select
                        id="assigned_doctor_id"
                        name="assigned_doctor_id"
                        class="pattern-field-clay h-9 px-3 py-1.5"
                    >
                        <option value="">غير معين</option>
                        <option
                            v-for="doctor in props.doctors"
                            :key="doctor.id"
                            :value="doctor.id"
                        >
                            {{ doctor.name }}
                        </option>
                    </select>
                    <InputError :message="errors.assigned_doctor_id" />
                </div>

                <div class="grid gap-2 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="queue_date">تاريخ الطابور</Label>
                        <Input
                            id="queue_date"
                            name="queue_date"
                            type="date"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.queue_date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="priority">الأولوية (0-9)</Label>
                        <Input
                            id="priority"
                            name="priority"
                            type="number"
                            min="0"
                            max="9"
                            value="0"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.priority" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="notes">ملاحظات</Label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="3"
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.notes" />
                </div>
            </Form>
            <DialogFooter class="p-6 pt-4 border-t border-border/60">
                <Button type="button" variant="outline" @click="emit('update:open', false)">إلغاء</Button>
                <Button form="queue-entry-create-form" type="submit" variant="default">إضافة إلى الطابور</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>