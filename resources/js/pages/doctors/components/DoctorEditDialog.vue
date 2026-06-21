<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import DoctorProfileController from '@/actions/App/Http/Controllers/Doctors/DoctorProfileController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';

type DoctorProfileStatus = 'active' | 'on_leave' | 'inactive';

type DoctorOption = {
    id: number;
    name: string;
    email: string | null;
};

type ClinicOption = {
    id: number;
    name: string;
    code: string | null;
    is_active: boolean;
};

type DoctorProfile = {
    id: number;
    clinic_id: number;
    user_id: number;
    clinic_id: number | null;
    license_number: string | null;
    specialty: string;
    consultation_duration_minutes: number;
    status: DoctorProfileStatus;
    work_schedule: Record<string, unknown> | null;
    bio: string | null;
    user?: DoctorOption | null;
    clinic?: ClinicOption | null;
    created_at: string | null;
    updated_at: string | null;
};

const props = defineProps<{
    profile: DoctorProfile | null;
    doctors: DoctorOption[];
    clinics: ClinicOption[];
    statusOptions: DoctorProfileStatus[];
}>();

const emit = defineEmits<{ close: [] }>();

const { can } = usePermissions();

const formatStatus = (status: DoctorProfileStatus): string => {
    const labels: Record<DoctorProfileStatus, string> = {
        active: 'نشط',
        on_leave: 'في إجازة',
        inactive: 'غير نشط',
    };

    return labels[status] ?? status.replace('_', ' ');
};

const stringifyWorkSchedule = (workSchedule: Record<string, unknown> | null): string => {
    if (workSchedule === null) {
        return '';
    }

    return JSON.stringify(workSchedule, null, 2);
};
</script>

<template>
    <Dialog :open="profile !== null" @update:open="(open) => !open && emit('close')">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>تعديل ملف الطبيب</DialogTitle>
                <DialogDescription>
                    تحديث تعيين الطبيب، تفاصيل الملف، وجدول العمل.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-if="profile && can('doctor_profile.update')"
                v-bind="DoctorProfileController.update.form(profile.id)"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                @success="emit('close')"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="edit_doctor_user_id">الطبيب</Label>
                        <select
                            id="edit_doctor_user_id"
                            name="user_id"
                            class="pattern-field-clay h-9 px-3 py-1.5"
                            :value="profile.user_id"
                        >
                            <option
                                v-for="doctor in doctors"
                                :key="`edit-doctor-option-${doctor.id}`"
                                :value="doctor.id"
                            >
                                {{ doctor.name }}{{ doctor.email ? ` (${doctor.email})` : '' }}
                            </option>
                        </select>
                        <InputError :message="errors.user_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_doctor_clinic_id">القسم</Label>
                        <select
                            id="edit_doctor_clinic_id"
                            name="clinic_id"
                            class="pattern-field-clay h-9 px-3 py-1.5"
                            :value="profile.clinic_id ?? ''"
                        >
                            <option value="">
                                غير معين
                            </option>
                            <option
                                v-for="clinic in clinics"
                                :key="`edit-clinic-option-${clinic.id}`"
                                :value="clinic.id"
                            >
                                {{
                                    clinic.code !== null
                                        ? `${clinic.name} (${clinic.code})`
                                        : clinic.name
                                }}
                            </option>
                        </select>
                        <InputError :message="errors.clinic_id" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="edit_doctor_specialty">التخصص</Label>
                        <Input
                            id="edit_doctor_specialty"
                            name="specialty"
                            :value="profile.specialty"
                            required
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.specialty" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_doctor_license_number">رقم الترخيص</Label>
                        <Input
                            id="edit_doctor_license_number"
                            name="license_number"
                            :value="profile.license_number ?? ''"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.license_number" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="edit_doctor_consultation_duration">المدة (دقيقة)</Label>
                        <Input
                            id="edit_doctor_consultation_duration"
                            name="consultation_duration_minutes"
                            type="number"
                            min="5"
                            max="480"
                            :value="profile.consultation_duration_minutes"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.consultation_duration_minutes" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_doctor_status">الحالة</Label>
                        <select
                            id="edit_doctor_status"
                            name="status"
                            class="pattern-field-clay h-9 px-3 py-1.5"
                            :value="profile.status"
                        >
                            <option
                                v-for="statusOption in statusOptions"
                                :key="`edit-status-option-${statusOption}`"
                                :value="statusOption"
                            >
                                {{ formatStatus(statusOption) }}
                            </option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="edit_doctor_work_schedule">جدول العمل (JSON)</Label>
                    <textarea
                        id="edit_doctor_work_schedule"
                        name="work_schedule"
                        rows="4"
                        class="pattern-field-clay font-mono text-xs"
                        :value="stringifyWorkSchedule(profile.work_schedule)"
                    />
                    <InputError :message="errors.work_schedule" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit_doctor_bio">السيرة الذاتية</Label>
                    <textarea
                        id="edit_doctor_bio"
                        name="bio"
                        rows="3"
                        class="pattern-field-clay"
                        :value="profile.bio ?? ''"
                    />
                    <InputError :message="errors.bio" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="neumorphic"
                        :disabled="processing"
                        @click="emit('close')"
                    >
                        إلغاء
                    </Button>
                    <Button
                        type="submit"
                        variant="clay"
                        :disabled="processing"
                    >
                        حفظ التغييرات
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
