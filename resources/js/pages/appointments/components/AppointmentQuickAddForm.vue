<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    Building2,
    Calculator,
    Clock,
    DollarSign,
    Stethoscope,
    User,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

const handleDepartmentChange = (value: unknown) => {
    const strValue = String(value ?? '');
    selectedDepartmentId.value = strValue === '__all__' ? '' : strValue;
};

const handleSubmit = (event: SubmitEvent) => {
    const form = event.target as HTMLFormElement;
    const doctorSelect = form.querySelector(
        'select[name="doctor_id"]',
    ) as HTMLSelectElement | null;

    if (doctorSelect && doctorSelect.value === '__none__') {
        doctorSelect.disabled = true;
        setTimeout(() => {
            doctorSelect.disabled = false;
        }, 0);
    }
};
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm"
    >
        <div
            class="flex items-center justify-between border-b border-border/50 bg-muted/30 px-5 py-3"
        >
            <div class="flex items-center gap-2.5">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <Zap class="size-4" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-foreground">
                        إضافة سريعة - موعد جديد
                    </h3>
                    <p class="text-[0.7rem] text-muted-foreground">
                        املأ الحقول واضغط Enter للحفظ والإضافة التالية
                    </p>
                </div>
            </div>
            <span
                class="hidden items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-[0.65rem] font-medium text-muted-foreground sm:inline-flex"
            >
                <kbd
                    class="rounded border border-border/70 bg-background px-1.5 py-0.5 font-mono text-[0.6rem]"
                    >Enter</kbd
                >
                حفظ سريع
            </span>
        </div>

        <Form
            v-bind="AppointmentController.store.form()"
            class="p-5"
            v-slot="{ errors, processing }"
            reset-on-success
            @submit="handleSubmit"
            @success="emit('success')"
            @error="emit('error')"
        >
            <div
                class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7"
            >
                <div class="grid gap-1.5 xl:col-span-2">
                    <Label
                        for="quick_patient"
                        class="flex items-center gap-1.5 text-xs font-medium"
                    >
                        <User class="size-3.5 text-muted-foreground" />
                        المريض
                        <span class="text-destructive">*</span>
                    </Label>
                    <Select name="patient_id" required>
                        <SelectTrigger
                            id="quick_patient"
                            class="w-full"
                            :class="{
                                'border-destructive': errors.patient_id,
                            }"
                        >
                            <SelectValue placeholder="اختر مريضاً" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="p in props.patients"
                                :key="p.id"
                                :value="String(p.id)"
                            >
                                {{
                                    p.file_number
                                        ? `${p.full_name ?? p.name} - ${p.file_number}`
                                        : (p.full_name ?? p.name)
                                }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.patient_id" />
                </div>

                <div class="grid gap-1.5">
                    <Label
                        for="quick_department"
                        class="flex items-center gap-1.5 text-xs font-medium"
                    >
                        <Building2 class="size-3.5 text-muted-foreground" />
                        العيادة
                    </Label>
                    <Select
                        :model-value="selectedDepartmentId"
                        @update:model-value="handleDepartmentChange"
                    >
                        <SelectTrigger id="quick_department" class="w-full">
                            <SelectValue placeholder="كل العيادات" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all__">كل العيادات</SelectItem>
                            <SelectItem
                                v-for="department in props.departments"
                                :key="department.id"
                                :value="String(department.id)"
                            >
                                {{ department.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-1.5">
                    <Label
                        for="quick_doctor"
                        class="flex items-center gap-1.5 text-xs font-medium"
                    >
                        <Stethoscope class="size-3.5 text-muted-foreground" />
                        الطبيب
                    </Label>
                    <Select name="doctor_id">
                        <SelectTrigger
                            id="quick_doctor"
                            class="w-full"
                            :class="{
                                'border-destructive': errors.doctor_id,
                            }"
                        >
                            <SelectValue placeholder="اختر طبيباً" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__none__">يُحدد لاحقاً</SelectItem>
                            <SelectItem
                                v-for="d in filteredDoctors"
                                :key="d.id"
                                :value="String(d.id)"
                            >
                                {{
                                    d.department?.name
                                        ? `${d.name} - ${d.department.name}`
                                        : d.name
                                }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.doctor_id" />
                </div>

                <div class="grid gap-1.5 xl:col-span-2">
                    <Label
                        class="flex items-center gap-1.5 text-xs font-medium"
                    >
                        <Clock class="size-3.5 text-muted-foreground" />
                        التاريخ والوقت
                        <span class="text-destructive">*</span>
                    </Label>
                    <AppointmentWorkingHoursInput
                        :working-hours="props.clinicWorkingHours"
                        label=""
                    />
                    <InputError :message="errors.scheduled_for" />
                </div>

                <div class="grid gap-1.5">
                    <Label
                        for="quick_duration"
                        class="flex items-center gap-1.5 text-xs font-medium"
                    >
                        <Clock class="size-3.5 text-muted-foreground" />
                        المدة
                        <span class="text-destructive">*</span>
                    </Label>
                    <Select name="duration_minutes" required>
                        <SelectTrigger
                            id="quick_duration"
                            class="w-full"
                            :class="{
                                'border-destructive': errors.duration_minutes,
                            }"
                        >
                            <SelectValue placeholder="15 دقيقة" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="15">15 دقيقة</SelectItem>
                            <SelectItem value="30">30 دقيقة</SelectItem>
                            <SelectItem value="45">45 دقيقة</SelectItem>
                            <SelectItem value="60">60 دقيقة</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.duration_minutes" />
                </div>

                <div class="grid gap-1.5">
                    <Label
                        for="quick_type"
                        class="flex items-center gap-1.5 text-xs font-medium"
                    >
                        <Calculator class="size-3.5 text-muted-foreground" />
                        النوع
                        <span class="text-destructive">*</span>
                    </Label>
                    <Select name="appointment_type" required>
                        <SelectTrigger
                            id="quick_type"
                            class="w-full"
                            :class="{
                                'border-destructive': errors.appointment_type,
                            }"
                        >
                            <SelectValue placeholder="كشفية أولى" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="first_visit"
                                >كشفية أولى</SelectItem
                            >
                            <SelectItem value="review">مراجعة</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.appointment_type" />
                </div>

                <div class="grid gap-1.5">
                    <Label
                        for="quick_cost"
                        class="flex items-center gap-1.5 text-xs font-medium"
                    >
                        <DollarSign class="size-3.5 text-muted-foreground" />
                        التكلفة
                        <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="quick_cost"
                        name="cost"
                        type="number"
                        min="0"
                        step="0.01"
                        required
                        placeholder="0"
                        :class="{ 'border-destructive': errors.cost }"
                    />
                    <InputError :message="errors.cost" />
                </div>

                <div class="flex items-end gap-2 xl:col-span-1">
                    <Button
                        type="submit"
                        variant="default"
                        size="sm"
                        class="h-9 flex-1 gap-1.5 text-xs"
                        :disabled="processing"
                    >
                        <Zap v-if="!processing" class="size-3.5" />
                        <span
                            v-if="processing"
                            class="inline-block size-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"
                        ></span>
                        {{ processing ? 'جاري...' : 'حفظ' }}
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
            </div>
        </Form>
    </section>
</template>
