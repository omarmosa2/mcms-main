<script setup lang="ts">
import { computed } from 'vue';
import { Form } from '@inertiajs/vue3';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Option } from './types';

const props = defineProps<{
    open: boolean;
    patients: Option[];
    doctors: Option[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const defaultScheduledFor = computed(() => {
    const now = new Date();
    now.setHours(now.getHours() + 1);
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-[520px] p-0 overflow-hidden">
            <DialogHeader class="p-6 pb-4 border-b border-border/60">
                <DialogTitle>موعد جديد</DialogTitle>
                <DialogDescription>إضافة موعد جديد بسرعة.</DialogDescription>
            </DialogHeader>

            <Form
                id="appointment-create-form"
                v-bind="AppointmentController.store.form()"
                class="px-6 py-4 space-y-4 max-h-[60vh] overflow-y-auto"
                v-slot="{ errors, processing }"
                @success="emit('update:open', false)"
            >
                <div class="grid gap-2">
                    <Label for="appointment_number">
                        رقم الموعد
                        <span class="text-xs text-muted-foreground">(يُولّد تلقائياً إذا ترك فارغاً)</span>
                    </Label>
                    <Input
                        id="appointment_number"
                        name="appointment_number"
                        placeholder="APT-20250421-0001"
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.appointment_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="patient_id">المريض</Label>
                    <select
                        id="patient_id"
                        name="patient_id"
                        required
                        class="pattern-field-clay h-10 px-3 py-1.5"
                    >
                        <option value="">اختر المريض</option>
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
                    <Label for="doctor_id">الطبيب</Label>
                    <select
                        id="doctor_id"
                        name="doctor_id"
                        class="pattern-field-clay h-10 px-3 py-1.5"
                    >
                        <option value="">يُحدد لاحقاً</option>
                        <option
                            v-for="doctor in props.doctors"
                            :key="doctor.id"
                            :value="doctor.id"
                        >
                            {{ doctor.name }}
                        </option>
                    </select>
                    <InputError :message="errors.doctor_id" />
                </div>

                <div class="grid gap-2 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="scheduled_for">موعد</Label>
                        <Input
                            id="scheduled_for"
                            name="scheduled_for"
                            type="datetime-local"
                            required
                            :value="defaultScheduledFor"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.scheduled_for" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="duration_minutes">المدة (دقيقة)</Label>
                        <Input
                            id="duration_minutes"
                            name="duration_minutes"
                            type="number"
                            min="5"
                            required
                            value="30"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.duration_minutes" />
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
                <Button form="appointment-create-form" type="submit" variant="default">إنشاء الموعد</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>