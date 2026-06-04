<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppointmentWorkingHoursInput from './AppointmentWorkingHoursInput.vue';
import type { ClinicWorkingHour, Option } from './types';

defineProps<{
    patients: Option[];
    doctors: Option[];
    clinicWorkingHours: ClinicWorkingHour[];
}>();

const emit = defineEmits<{
    success: [];
    error: [];
    reset: [];
}>();
</script>

<template>
    <section class="rounded-xl border-2 border-dashed border-primary/30 bg-primary/5 p-4">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-primary">إضافة سريعة - موعد جديد</h3>
            <span class="text-xs text-muted-foreground">Enter = حفظ وإضافة التالي</span>
        </div>

        <Form
            v-bind="AppointmentController.store.form()"
            class="grid gap-3 md:grid-cols-5 md:items-end"
            v-slot="{ errors, processing }"
            reset-on-success
            @success="emit('success')"
            @error="emit('error')"
        >
            <div class="grid gap-1">
                <Label for="quick_patient" class="text-xs">المريض *</Label>
                <select
                    id="quick_patient"
                    name="patient_id"
                    required
                    class="pattern-field-clay h-9 px-2 py-1 text-sm"
                >
                    <option value="">اختر مريضاً</option>
                    <option v-for="p in patients" :key="p.id" :value="p.id">
                        {{ p.full_name ?? p.name }}
                    </option>
                </select>
                <p v-if="errors.patient_id" class="text-xs text-destructive">{{ errors.patient_id }}</p>
            </div>

            <div class="grid gap-1">
                <Label for="quick_doctor" class="text-xs">الطبيب</Label>
                <select
                    id="quick_doctor"
                    name="doctor_id"
                    class="pattern-field-clay h-9 px-2 py-1 text-sm"
                >
                    <option value="">اختر طبيباً</option>
                    <option v-for="d in doctors" :key="d.id" :value="d.id">
                        {{ d.name }}
                    </option>
                </select>
                <p v-if="errors.doctor_id" class="text-xs text-destructive">{{ errors.doctor_id }}</p>
            </div>

            <div class="grid gap-1">
                <AppointmentWorkingHoursInput
                    :working-hours="clinicWorkingHours"
                    label="التاريخ والوقت *"
                />
                <p v-if="errors.scheduled_for" class="text-xs text-destructive">{{ errors.scheduled_for }}</p>
            </div>

            <div class="grid gap-1">
                <Label for="quick_duration" class="text-xs">المدة (دقيقة) *</Label>
                <Input
                    id="quick_duration"
                    name="duration_minutes"
                    type="number"
                    min="5"
                    max="480"
                    value="15"
                    required
                    class="pattern-field-clay h-9 text-sm"
                />
                <p v-if="errors.duration_minutes" class="text-xs text-destructive">{{ errors.duration_minutes }}</p>
            </div>

            <div class="flex gap-2">
                <Button
                    type="submit"
                    variant="default"
                    size="sm"
                    class="h-9 px-4 text-xs"
                    :disabled="processing"
                >
                    {{ processing ? 'جاري الحفظ...' : 'حفظ' }}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-9 px-3 text-xs"
                    @click="emit('reset')"
                >
                    مسح
                </Button>
            </div>
        </Form>
    </section>
</template>
