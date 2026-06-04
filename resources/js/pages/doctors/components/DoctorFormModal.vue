<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { KeyRound, Save, UserPlus, X } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { store, update } from '@/actions/App/Http/Controllers/Doctors/DoctorProfileController';
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
import type { ClinicOption, CompensationType, DepartmentOption, DoctorGender, DoctorProfile, WorkingHour } from './types';

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

type DoctorForm = {
    name: string;
    gender: DoctorGender;
    specialty: string;
    phone: string;
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

const defaultWorkingHours = (): WorkingHour[] => [
    { day_of_week: 6, is_active: false, start_time: null, end_time: null },
    { day_of_week: 0, is_active: false, start_time: null, end_time: null },
    { day_of_week: 1, is_active: false, start_time: null, end_time: null },
    { day_of_week: 2, is_active: false, start_time: null, end_time: null },
    { day_of_week: 3, is_active: false, start_time: null, end_time: null },
    { day_of_week: 4, is_active: false, start_time: null, end_time: null },
    { day_of_week: 5, is_active: false, start_time: null, end_time: null },
];

const normalizeTime = (value: string | null): string | null => {
    return value === null ? null : value.slice(0, 5);
};

const workingHoursForProfile = (profile: DoctorProfile | null): WorkingHour[] => {
    const defaults = defaultWorkingHours();

    if (profile === null || profile.working_hours.length === 0) {
        return defaults;
    }

    return defaults.map((day) => {
        const current = profile.working_hours.find((item) => item.day_of_week === day.day_of_week);

        if (current === undefined) {
            return day;
        }

        return {
            day_of_week: day.day_of_week,
            is_active: current.is_active,
            start_time: normalizeTime(current.start_time),
            end_time: normalizeTime(current.end_time),
        };
    });
};

const defaultsFor = (profile: DoctorProfile | null): DoctorForm => ({
    name: profile?.user?.name ?? '',
    gender: profile?.gender ?? 'male',
    specialty: profile?.specialty ?? '',
    phone: profile?.phone ?? '',
    department_id: profile?.department_id ?? '',
    username: profile?.user?.email ?? '',
    password: '',
    status: profile?.status ?? 'active',
    is_active: profile?.user?.is_active ?? true,
    compensation_type: profile?.compensation_type ?? 'percentage',
    compensation_value: profile?.compensation_value !== null && profile?.compensation_value !== undefined
        ? String(Number(profile.compensation_value))
        : '',
    consultation_duration_minutes: profile?.consultation_duration_minutes ?? 30,
    working_hours: workingHoursForProfile(profile),
});

const form = useForm<DoctorForm>(defaultsFor(props.profile));

const isEditing = computed(() => props.profile !== null);
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
        if (! props.open) {
            return;
        }

        form.defaults(defaultsFor(props.profile));
        form.reset();
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
    <Dialog
        :open="open"
        @update:open="emit('update:open', $event)"
    >
        <DialogContent class="max-h-[92vh] max-w-4xl overflow-hidden rounded-xl bg-white p-0" dir="rtl">
            <DialogHeader class="border-b border-slate-100 px-6 py-5 text-right">
                <DialogTitle class="text-2xl font-bold text-slate-900">
                    {{ isEditing ? 'تعديل بيانات الطبيب' : 'إضافة طبيب جديد' }}
                </DialogTitle>
                <DialogDescription class="text-slate-500">
                    إدارة بيانات الطبيب والحساب والدوام ونظام الأجر من نموذج واحد.
                </DialogDescription>
            </DialogHeader>

            <form
                class="max-h-[68vh] space-y-5 overflow-y-auto px-6 py-5"
                @submit.prevent="submit"
            >
                <section class="space-y-3">
                    <h3 class="text-sm font-bold text-slate-900">البيانات الأساسية</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_name">الاسم الكامل</Label>
                            <Input id="doctor_name" v-model="form.name" class="h-11 rounded-lg" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_gender">الجنس</Label>
                            <select id="doctor_gender" v-model="form.gender" class="h-11 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
                                <option value="male">ذكر</option>
                                <option value="female">أنثى</option>
                            </select>
                            <InputError :message="form.errors.gender" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_specialty">الاختصاص</Label>
                            <Input id="doctor_specialty" v-model="form.specialty" class="h-11 rounded-lg" />
                            <InputError :message="form.errors.specialty" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_phone">رقم الهاتف (اختياري)</Label>
                            <Input id="doctor_phone" v-model="form.phone" class="h-11 rounded-lg" />
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_department">العيادة التابعة للطبيب</Label>
                            <select id="doctor_department" v-model="form.department_id" class="h-11 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
                                <option value="">بدون عيادة داخلية</option>
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
                            <Input id="doctor_clinic" :model-value="clinic.name ?? '-'" readonly class="h-11 rounded-lg bg-slate-100" />
                        </div>
                    </div>
                </section>

                <DoctorWorkingHoursSelector
                    v-model="form.working_hours"
                    :errors="form.errors"
                />

                <section class="space-y-3 rounded-lg border border-slate-200 bg-white p-4">
                    <div class="flex items-center gap-2">
                        <KeyRound class="size-4 text-sky-500" />
                        <h3 class="text-sm font-bold text-slate-900">بيانات الحساب</h3>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_username">اسم المستخدم</Label>
                            <Input id="doctor_username" v-model="form.username" type="email" class="h-11 rounded-lg" placeholder="doctor@example.com" />
                            <InputError :message="form.errors.username" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_password">كلمة المرور</Label>
                            <Input
                                id="doctor_password"
                                v-model="form.password"
                                type="password"
                                class="h-11 rounded-lg"
                                :placeholder="isEditing ? 'اتركها فارغة بدون تغيير' : ''"
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <label
                            v-if="isEditing"
                            class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 md:col-span-2"
                        >
                            <span>
                                <span class="block text-sm font-semibold text-slate-900">حالة الحساب</span>
                                <span class="block text-xs text-slate-500">{{ form.is_active ? 'نشط' : 'غير نشط' }}</span>
                            </span>
                            <input v-model="form.is_active" type="checkbox" class="size-5 accent-sky-500" />
                        </label>
                    </div>
                </section>

                <section class="space-y-3 rounded-lg border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-bold text-slate-900">نظام أجر الطبيب</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_compensation_type">نوع أجر الطبيب</Label>
                            <select id="doctor_compensation_type" v-model="form.compensation_type" class="h-11 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
                                <option value="percentage">نسبة مئوية</option>
                                <option value="weekly">أجر أسبوعي</option>
                                <option value="monthly">أجر شهري</option>
                            </select>
                            <InputError :message="form.errors.compensation_type" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_compensation_value">{{ compensationLabel }}</Label>
                            <Input id="doctor_compensation_value" v-model="form.compensation_value" type="number" min="0" step="0.01" class="h-11 rounded-lg" />
                            <InputError :message="form.errors.compensation_value" />
                        </div>
                    </div>
                </section>
            </form>

            <DialogFooter class="border-t border-slate-100 px-6 py-4">
                <Button type="button" variant="outline" :disabled="form.processing" @click="close">
                    <X class="size-4" />
                    إلغاء
                </Button>
                <Button type="button" class="bg-sky-500 text-white hover:bg-sky-600" :disabled="form.processing" @click="submit">
                    <component :is="isEditing ? Save : UserPlus" class="size-4" />
                    {{ isEditing ? 'حفظ التغييرات' : 'إضافة طبيب جديد' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
