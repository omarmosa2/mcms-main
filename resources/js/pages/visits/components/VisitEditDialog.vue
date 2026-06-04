<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import VisitController from '@/actions/App/Http/Controllers/Visits/VisitController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Option, Visit } from './types';

const props = defineProps<{
    visit: Visit | null;
    patients: Option[];
    doctors: Option[];
    appointments: Option[];
    queueEntries: Option[];
    canEdit: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <Dialog :open="visit !== null" @update:open="(open) => !open && emit('close')">
        <DialogContent class="sm:max-w-3xl" aria-label="تعديل الزيارة">
            <DialogHeader>
                <DialogTitle>تعديل الزيارة</DialogTitle>
                <DialogDescription>تحديث تفاصيل الزيارة والملاحظات الطبية.</DialogDescription>
            </DialogHeader>

            <Form
                v-if="visit && canEdit"
                v-bind="VisitController.update.form(visit.id)"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                @success="emit('close')"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="edit_visit_number">رقم الزيارة</Label>
                        <Input id="edit_visit_number" name="visit_number" :value="visit.visit_number" class="pattern-field-clay" required />
                        <InputError :message="errors.visit_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit_visit_patient">المريض</Label>
                        <select id="edit_visit_patient" name="patient_id" class="pattern-field-clay h-10 px-3 py-2" :value="String(visit.patient_id)">
                            <option v-for="patient in patients" :key="`edit-visit-patient-${patient.id}`" :value="patient.id">{{ patient.full_name }}</option>
                        </select>
                        <InputError :message="errors.patient_id" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="edit_visit_doctor">الطبيب</Label>
                        <select id="edit_visit_doctor" name="doctor_id" class="pattern-field-clay h-10 px-3 py-2" :value="visit.doctor_id !== null ? String(visit.doctor_id) : ''">
                            <option value="">غير محدد</option>
                            <option v-for="doctor in doctors" :key="`edit-visit-doctor-${doctor.id}`" :value="doctor.id">{{ doctor.name }}</option>
                        </select>
                        <InputError :message="errors.doctor_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit_visit_appointment">الموعد</Label>
                        <select id="edit_visit_appointment" name="appointment_id" class="pattern-field-clay h-10 px-3 py-2" :value="visit.appointment_id !== null ? String(visit.appointment_id) : ''">
                            <option value="">بدون موعد</option>
                            <option v-for="appointment in appointments" :key="`edit-visit-appointment-${appointment.id}`" :value="appointment.id">{{ appointment.appointment_number }}</option>
                        </select>
                        <InputError :message="errors.appointment_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit_visit_queue_entry">قائمة الانتظار</Label>
                        <select id="edit_visit_queue_entry" name="queue_entry_id" class="pattern-field-clay h-10 px-3 py-2" :value="visit.queue_entry_id !== null ? String(visit.queue_entry_id) : ''">
                            <option value="">بدون قائمة انتظار</option>
                            <option v-for="queueEntry in queueEntries" :key="`edit-visit-queue-${queueEntry.id}`" :value="queueEntry.id">{{ queueEntry.label }}</option>
                        </select>
                        <InputError :message="errors.queue_entry_id" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="edit_visit_complaint">الشكوى الرئيسية</Label>
                    <textarea id="edit_visit_complaint" name="chief_complaint" rows="2" class="pattern-field-clay" :value="visit.chief_complaint ?? ''"></textarea>
                    <InputError :message="errors.chief_complaint" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit_visit_clinical_notes">ملاحظات سريرية</Label>
                    <textarea id="edit_visit_clinical_notes" name="clinical_notes" rows="3" class="pattern-field-clay" :value="visit.clinical_notes ?? ''"></textarea>
                    <InputError :message="errors.clinical_notes" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit_visit_diagnosis_notes">ملاحظات التشخيص</Label>
                    <textarea id="edit_visit_diagnosis_notes" name="diagnosis_notes" rows="3" class="pattern-field-clay" :value="visit.diagnosis_notes ?? ''"></textarea>
                    <InputError :message="errors.diagnosis_notes" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit_visit_treatment_plan">خطة العلاج</Label>
                    <textarea id="edit_visit_treatment_plan" name="treatment_plan" rows="3" class="pattern-field-clay" :value="visit.treatment_plan ?? ''"></textarea>
                    <InputError :message="errors.treatment_plan" />
                </div>

                <DialogFooter class="gap-2">
                    <Button type="button" variant="ghost" :disabled="processing" class="min-h-[44px]" @click="emit('close')">إلغاء</Button>
                    <Button type="submit" variant="clay" :disabled="processing" class="min-h-[44px]">حفظ التغييرات</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>