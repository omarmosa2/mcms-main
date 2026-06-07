<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppointmentWorkingHoursInput from './AppointmentWorkingHoursInput.vue';
import type { ClinicWorkingHour, DepartmentOption, Option } from './types';

const props = defineProps<{
    patients: Option[];
    doctors: Option[];
    departments: DepartmentOption[];
    clinicWorkingHours: ClinicWorkingHour[];
}>();

const emit = defineEmits<{
    success: [];
    error: [];
    reset: [];
}>();

const selectedDepartmentId = ref('');

const filteredDoctors = computed(() => {
    if (!selectedDepartmentId.value) {
        return props.doctors;
    }

    const departmentId = Number(selectedDepartmentId.value);

    return props.doctors.filter(
        (doctor) => doctor.department_id === departmentId,
    );
});
</script>

<template>
    <section
        class="rounded-xl border-2 border-dashed border-primary/30 bg-primary/5 p-4"
    >
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-primary">
                إضافة سريعة - موعد جديد
            </h3>
            <span class="text-xs text-muted-foreground"
                >Enter = حفظ وإضافة التالي</span
            >
        </div>

        <Form
            v-bind="AppointmentController.store.form()"
            class="grid gap-3 md:grid-cols-4 lg:grid-cols-8 md:items-end"
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
                    <option
                        v-for="p in props.patients"
                        :key="p.id"
                        :value="p.id"
                    >
                        {{
                            p.file_number
                                ? `${p.full_name ?? p.name} - ${p.file_number}`
                                : (p.full_name ?? p.name)
                        }}
                    </option>
                </select>
                <p v-if="errors.patient_id" class="text-xs text-destructive">
                    {{ errors.patient_id }}
                </p>
            </div>

            <div class="grid gap-1">
                <Label for="quick_department" class="text-xs">العيادة</Label>
                <select
                    id="quick_department"
                    v-model="selectedDepartmentId"
                    class="pattern-field-clay h-9 px-2 py-1 text-sm"
                >
                    <option value="">كل العيادات</option>
                    <option
                        v-for="department in props.departments"
                        :key="department.id"
                        :value="String(department.id)"
                    >
                        {{ department.name }}
                    </option>
                </select>
            </div>

            <div class="grid gap-1">
                <Label for="quick_doctor" class="text-xs">الطبيب</Label>
                <select
                    id="quick_doctor"
                    name="doctor_id"
                    class="pattern-field-clay h-9 px-2 py-1 text-sm"
                >
                    <option value="">اختر طبيباً</option>
                    <option
                        v-for="d in filteredDoctors"
                        :key="d.id"
                        :value="d.id"
                    >
                        {{
                            d.department?.name
                                ? `${d.name} - ${d.department.name}`
                                : d.name
                        }}
                    </option>
                </select>
                <p v-if="errors.doctor_id" class="text-xs text-destructive">
                    {{ errors.doctor_id }}
                </p>
            </div>

            <div class="grid gap-1">
                <AppointmentWorkingHoursInput
                    :working-hours="props.clinicWorkingHours"
                    label="التاريخ والوقت *"
                />
                <p v-if="errors.scheduled_for" class="text-xs text-destructive">
                    {{ errors.scheduled_for }}
                </p>
            </div>

            <div class="grid gap-1">
                <Label for="quick_duration" class="text-xs"
                    >المدة (دقيقة) *</Label
                >
                <select
                    id="quick_duration"
                    name="duration_minutes"
                    required
                    class="pattern-field-clay h-9 px-2 py-1 text-sm"
                >
                    <option value="15">15 دقيقة</option>
                    <option value="30">30 دقيقة</option>
                    <option value="45">45 دقيقة</option>
                    <option value="60">60 دقيقة</option>
                </select>
                <p
                    v-if="errors.duration_minutes"
                    class="text-xs text-destructive"
                >
                    {{ errors.duration_minutes }}
                </p>
            </div>

            <div class="grid gap-1">
                <Label for="quick_type" class="text-xs">نوع الموعد *</Label>
                <select
                    id="quick_type"
                    name="appointment_type"
                    required
                    class="pattern-field-clay h-9 px-2 py-1 text-sm"
                >
                    <option value="first_visit">كشفية أولى</option>
                    <option value="review">مراجعة</option>
                </select>
                <p v-if="errors.appointment_type" class="text-xs text-destructive">
                    {{ errors.appointment_type }}
                </p>
            </div>

            <div class="grid gap-1">
                <Label for="quick_cost" class="text-xs">التكلفة *</Label>
                <input
                    id="quick_cost"
                    name="cost"
                    type="number"
                    min="0"
                    step="0.01"
                    required
                    placeholder="0"
                    class="pattern-field-clay h-9 px-2 py-1 text-sm"
                />
                <p v-if="errors.cost" class="text-xs text-destructive">
                    {{ errors.cost }}
                </p>
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
