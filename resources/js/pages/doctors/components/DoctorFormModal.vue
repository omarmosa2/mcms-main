<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { KeyRound, Save, UserPlus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Doctors/DoctorProfileController';
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
import DoctorWorkingHoursSelector from './DoctorWorkingHoursSelector.vue';
import type {
    ClinicOption,
    ClinicWorkingHour,
    CompensationType,
    DepartmentOption,
    DoctorGender,
    DoctorProfile,
    WorkingHour,
} from './types';

const props = defineProps<{
    open: boolean;
    profile: DoctorProfile | null;
    clinic: ClinicOption;
    departments: DepartmentOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const clinicDayToDoctorDay: Record<ClinicWorkingHour['day_of_week'], number> = {
    sunday: 0,
    monday: 1,
    tuesday: 2,
    wednesday: 3,
    thursday: 4,
    friday: 5,
    saturday: 6,
};

type DoctorForm = {
    name: string;
    gender: DoctorGender;
    specialty: string;
    phone: string;
    work_start_date: string;
    department_id: number | '';
    username: string;
    password: string;
    status: 'active' | 'on_leave' | 'inactive';
    is_active: boolean;
    compensation_type: CompensationType;
    compensation_value: string;
    consultation_duration_minutes: number;
    working_hours: WorkingHour[];
};

const normalizeTime = (value: string | null): string | null => {
    return value === null ? null : value.slice(0, 5);
};

const departmentForId = (
    departmentId: number | '' | null | undefined,
): DepartmentOption | null => {
    if (
        departmentId === null ||
        departmentId === undefined ||
        departmentId === ''
    ) {
        return null;
    }

    return (
        props.departments.find(
            (department) => department.id === Number(departmentId),
        ) ?? null
    );
};

const isWithinClinicHours = (
    doctorStartTime: string | null,
    doctorEndTime: string | null,
    clinicStartTime: string | null,
    clinicEndTime: string | null,
): boolean => {
    if (
        doctorStartTime === null ||
        doctorEndTime === null ||
        clinicStartTime === null ||
        clinicEndTime === null
    ) {
        return false;
    }

    return (
        doctorStartTime >= clinicStartTime &&
        doctorEndTime <= clinicEndTime &&
        doctorEndTime > doctorStartTime
    );
};

const workingHoursForDepartment = (
    department: DepartmentOption | null,
    profile: DoctorProfile | null = null,
): WorkingHour[] => {
    if (department === null) {
        return [];
    }

    return department.working_hours
        .filter(
            (day) =>
                day.is_active &&
                day.start_time !== null &&
                day.end_time !== null,
        )
        .map((clinicDay) => {
            const dayOfWeek = clinicDayToDoctorDay[clinicDay.day_of_week];
            const current = profile?.working_hours.find(
                (item) => item.day_of_week === dayOfWeek,
            );
            const currentStartTime = normalizeTime(current?.start_time ?? null);
            const currentEndTime = normalizeTime(current?.end_time ?? null);
            const canUseCurrentHours =
                current?.is_active === true &&
                isWithinClinicHours(
                    currentStartTime,
                    currentEndTime,
                    normalizeTime(clinicDay.start_time),
                    normalizeTime(clinicDay.end_time),
                );

            return {
                day_of_week: dayOfWeek,
                is_active: canUseCurrentHours,
                start_time: canUseCurrentHours ? currentStartTime : null,
                end_time: canUseCurrentHours ? currentEndTime : null,
            };
        });
};

const defaultsFor = (profile: DoctorProfile | null): DoctorForm => ({
    name: profile?.user?.name ?? '',
    gender: profile?.gender ?? 'male',
    specialty: profile?.specialty ?? '',
    phone: profile?.phone ?? '',
    work_start_date: profile?.work_start_date ?? '',
    department_id: profile?.department_id ?? '',
    username: profile?.user?.email ?? '',
    password: '',
    status: profile?.status ?? 'active',
    is_active: profile?.user?.is_active ?? true,
    compensation_type: profile?.compensation_type ?? 'percentage',
    compensation_value:
        profile?.compensation_value !== null &&
        profile?.compensation_value !== undefined
            ? String(Number(profile.compensation_value))
            : '',
    consultation_duration_minutes: profile?.consultation_duration_minutes ?? 30,
    working_hours: workingHoursForDepartment(
        departmentForId(profile?.department_id),
        profile,
    ),
});

const form = useForm<DoctorForm>(defaultsFor(props.profile));
const isHydratingForm = ref(false);

const isEditing = computed(() => props.profile !== null);
const selectedDepartment = computed<DepartmentOption | null>(() =>
    departmentForId(form.department_id),
);
const compensationLabel = computed(() => {
    if (form.compensation_type === 'percentage') {
        return 'نسبة الطبيب (%)';
    }

    if (form.compensation_type === 'weekly') {
        return 'قيمة الأجر الأسبوعي';
    }

    return 'قيمة الأجر الشهري';
});

watch(
    () => [props.open, props.profile?.id],
    () => {
        if (!props.open) {
            return;
        }

        isHydratingForm.value = true;
        form.defaults(defaultsFor(props.profile));
        form.reset();
        form.clearErrors();
        isHydratingForm.value = false;
    },
);

watch(
    () => form.department_id,
    () => {
        if (!props.open || isHydratingForm.value) {
            return;
        }

        form.working_hours = workingHoursForDepartment(
            selectedDepartment.value,
        );
        form.clearErrors();
    },
);

watch(
    () => form.compensation_type,
    () => {
        form.compensation_value = '';
    },
);

const close = (): void => {
    emit('update:open', false);
};

const submit = (): void => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            close();
        },
    };

    if (props.profile !== null) {
        form.put(update.url(props.profile.id), options);
        return;
    }

    form.post(store.url(), options);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="max-h-[92vh] max-w-4xl overflow-hidden rounded-xl bg-card p-0"
            dir="rtl"
        >
            <DialogHeader class="border-b border-border px-6 py-5 text-right">
                <DialogTitle class="text-2xl font-bold text-foreground">
                    {{ isEditing ? 'تعديل بيانات الطبيب' : 'إضافة طبيب جديد' }}
                </DialogTitle>
                <DialogDescription class="text-muted-foreground">
                    إدارة بيانات الطبيب والحساب والدوام ونظام الأجر من نموذج
                    واحد.
                </DialogDescription>
            </DialogHeader>

            <form
                class="max-h-[68vh] space-y-5 overflow-y-auto px-6 py-5"
                @submit.prevent="submit"
            >
                <section class="space-y-3">
                    <h3 class="text-sm font-bold text-foreground">
                        البيانات الأساسية
                    </h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_name">الاسم الكامل</Label>
                            <Input
                                id="doctor_name"
                                v-model="form.name"
                                class="h-11 rounded-lg"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_gender">الجنس</Label>
                            <select
                                id="doctor_gender"
                                v-model="form.gender"
                                class="h-11 rounded-lg border border-input bg-muted px-3 text-sm"
                            >
                                <option value="male">ذكر</option>
                                <option value="female">أنثى</option>
                            </select>
                            <InputError :message="form.errors.gender" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_specialty">الاختصاص</Label>
                            <Input
                                id="doctor_specialty"
                                v-model="form.specialty"
                                class="h-11 rounded-lg"
                            />
                            <InputError :message="form.errors.specialty" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_phone"
                                >رقم الهاتف (اختياري)</Label
                            >
                            <Input
                                id="doctor_phone"
                                v-model="form.phone"
                                class="h-11 rounded-lg"
                            />
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_work_start_date"
                                >تاريخ مباشرة العمل</Label
                            >
                            <Input
                                id="doctor_work_start_date"
                                v-model="form.work_start_date"
                                type="date"
                                class="h-11 rounded-lg"
                            />
                            <InputError
                                :message="form.errors.work_start_date"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_department"
                                >العيادة التابعة للطبيب</Label
                            >
                            <select
                                id="doctor_department"
                                v-model="form.department_id"
                                class="h-11 rounded-lg border border-input bg-muted px-3 text-sm"
                            >
                                <option value="">يرجى اختيار العيادة</option>
                                <option
                                    v-for="department in departments"
                                    :key="department.id"
                                    :value="department.id"
                                >
                                    {{ department.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.department_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_clinic">العيادة الرئيسية</Label>
                            <Input
                                id="doctor_clinic"
                                :model-value="clinic.name ?? '-'"
                                readonly
                                class="h-11 rounded-lg bg-muted"
                            />
                        </div>
                    </div>
                </section>

                <DoctorWorkingHoursSelector
                    v-model="form.working_hours"
                    :errors="form.errors"
                    :clinic-working-hours="
                        selectedDepartment?.working_hours ?? []
                    "
                    :has-selected-department="selectedDepartment !== null"
                />

                <section
                    class="space-y-3 rounded-lg border border-border bg-card p-4"
                >
                    <div class="flex items-center gap-2">
                        <KeyRound class="size-4 text-primary" />
                        <h3 class="text-sm font-bold text-foreground">
                            بيانات الحساب
                        </h3>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_username">اسم المستخدم</Label>
                            <Input
                                id="doctor_username"
                                v-model="form.username"
                                type="email"
                                class="h-11 rounded-lg"
                                placeholder="doctor@example.com"
                            />
                            <InputError :message="form.errors.username" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_password">كلمة المرور</Label>
                            <Input
                                id="doctor_password"
                                v-model="form.password"
                                type="password"
                                class="h-11 rounded-lg"
                                :placeholder="
                                    isEditing ? 'اتركها فارغة بدون تغيير' : ''
                                "
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <label
                            v-if="isEditing"
                            class="flex items-center justify-between gap-3 rounded-lg border border-border bg-muted px-3 py-2 md:col-span-2"
                        >
                            <span>
                                <span
                                    class="block text-sm font-semibold text-foreground"
                                    >حالة الحساب</span
                                >
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{
                                        form.is_active ? 'نشط' : 'غير نشط'
                                    }}</span
                                >
                            </span>
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="size-5 accent-primary"
                            />
                        </label>
                    </div>
                </section>

                <section
                    class="space-y-3 rounded-lg border border-border bg-card p-4"
                >
                    <h3 class="text-sm font-bold text-foreground">
                        نظام أجر الطبيب
                    </h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_compensation_type"
                                >نوع أجر الطبيب</Label
                            >
                            <select
                                id="doctor_compensation_type"
                                v-model="form.compensation_type"
                                class="h-11 rounded-lg border border-input bg-muted px-3 text-sm"
                            >
                                <option value="percentage">نسبة مئوية</option>
                                <option value="weekly">أجر أسبوعي</option>
                                <option value="monthly">أجر شهري</option>
                            </select>
                            <InputError
                                :message="form.errors.compensation_type"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_compensation_value">{{
                                compensationLabel
                            }}</Label>
                            <Input
                                id="doctor_compensation_value"
                                v-model="form.compensation_value"
                                type="number"
                                min="0"
                                step="0.01"
                                class="h-11 rounded-lg"
                            />
                            <InputError
                                :message="form.errors.compensation_value"
                            />
                        </div>
                    </div>
                </section>
            </form>

            <DialogFooter class="border-t border-border px-6 py-4">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    @click="close"
                >
                    <X class="size-4" />
                    إلغاء
                </Button>
                <Button
                    type="button"
                    class="bg-primary text-primary-foreground hover:bg-primary/90"
                    :disabled="form.processing"
                    @click="submit"
                >
                    <component
                        :is="isEditing ? Save : UserPlus"
                        class="size-4"
                    />
                    {{ isEditing ? 'حفظ التغييرات' : 'إضافة طبيب جديد' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
