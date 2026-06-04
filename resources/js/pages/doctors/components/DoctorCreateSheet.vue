<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import DoctorProfileController from '@/actions/App/Http/Controllers/Doctors/DoctorProfileController';
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

type DoctorProfileStatus = 'active' | 'on_leave' | 'inactive';

type DoctorOption = {
    id: number;
    name: string;
    email: string | null;
};

type DepartmentOption = {
    id: number;
    name: string;
    code: string | null;
    is_active: boolean;
};

const props = defineProps<{
    open: boolean;
    doctors: DoctorOption[];
    departments: DepartmentOption[];
    statusOptions: DoctorProfileStatus[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const statusLabels: Record<DoctorProfileStatus, string> = {
    active: 'نشط',
    on_leave: 'في إجازة',
    inactive: 'غير نشط',
};

const formatStatus = (status: DoctorProfileStatus): string => {
    return statusLabels[status] ?? status.replace('_', ' ');
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-[520px] p-0 overflow-hidden">
            <DialogHeader class="p-6 pb-4 border-b border-border/60">
                <DialogTitle>إنشاء ملف طبيب</DialogTitle>
                <DialogDescription>إضافة ملف طبيب جديد.</DialogDescription>
            </DialogHeader>

            <Form
                id="doctor-create-form"
                v-bind="DoctorProfileController.store.form()"
                class="px-6 py-4 space-y-4 max-h-[60vh] overflow-y-auto"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="doctor_user_id">الطبيب</Label>
                    <select
                        id="doctor_user_id"
                        name="user_id"
                        required
                        class="pattern-field-clay h-9 px-3 py-1.5"
                    >
                        <option value="" disabled selected>
                            اختر طبيب
                        </option>
                        <option
                            v-for="doctor in doctors"
                            :key="`doctor-option-${doctor.id}`"
                            :value="doctor.id"
                        >
                            {{ doctor.name }}{{ doctor.email ? ` (${doctor.email})` : '' }}
                        </option>
                    </select>
                    <InputError :message="errors.user_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="doctor_department_id">القسم</Label>
                    <select
                        id="doctor_department_id"
                        name="department_id"
                        class="pattern-field-clay h-9 px-3 py-1.5"
                    >
                        <option value="">
                            غير معين
                        </option>
                        <option
                            v-for="department in departments"
                            :key="`department-option-${department.id}`"
                            :value="department.id"
                        >
                            {{
                                department.code !== null
                                    ? `${department.name} (${department.code})`
                                    : department.name
                            }}
                        </option>
                    </select>
                    <InputError :message="errors.department_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="doctor_specialty">التخصص</Label>
                    <Input
                        id="doctor_specialty"
                        name="specialty"
                        required
                        placeholder="الطب الباطني"
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.specialty" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="doctor_license_number">رقم الترخيص</Label>
                        <Input
                            id="doctor_license_number"
                            name="license_number"
                            placeholder="LIC-20260012"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.license_number" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="doctor_consultation_duration">المدة (دقيقة)</Label>
                        <Input
                            id="doctor_consultation_duration"
                            name="consultation_duration_minutes"
                            type="number"
                            min="5"
                            max="480"
                            value="30"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.consultation_duration_minutes" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="doctor_status">الحالة</Label>
                    <select
                        id="doctor_status"
                        name="status"
                        class="pattern-field-clay h-9 px-3 py-1.5"
                    >
                        <option
                            v-for="statusOption in statusOptions"
                            :key="`status-option-${statusOption}`"
                            :value="statusOption"
                            :selected="statusOption === 'active'"
                        >
                            {{ formatStatus(statusOption) }}
                        </option>
                    </select>
                    <InputError :message="errors.status" />
                </div>

                <div class="grid gap-2">
                    <Label for="doctor_work_schedule">جدول العمل (JSON)</Label>
                    <textarea
                        id="doctor_work_schedule"
                        name="work_schedule"
                        rows="4"
                        class="pattern-field-clay font-mono text-xs"
                        placeholder='{"sunday":["09:00-13:00"],"monday":["09:00-13:00","17:00-20:00"]}'
                    />
                    <InputError :message="errors.work_schedule" />
                </div>

                <div class="grid gap-2">
                    <Label for="doctor_bio">السيرة الذاتية</Label>
                    <textarea
                        id="doctor_bio"
                        name="bio"
                        rows="3"
                        class="pattern-field-clay"
                        placeholder="نبذة تعريفية قصيرة"
                    />
                    <InputError :message="errors.bio" />
                </div>

                <p
                    v-if="doctors.length === 0"
                    class="rounded-xl border border-amber-300/60 bg-amber-100/65 px-3 py-2 text-xs text-amber-900 dark:border-amber-500/35 dark:bg-amber-500/12 dark:text-amber-100"
                >
                    لا يوجد أطباء في هذه العيادة بعد.
                </p>

                <Button
                    :disabled="processing || doctors.length === 0"
                    variant="default"
                    class="w-full"
                >
                    إنشاء ملف طبيب
                </Button>
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
                    form="doctor-create-form"
                    type="submit"
                    variant="default"
                >
                    إنشاء ملف طبيب
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>