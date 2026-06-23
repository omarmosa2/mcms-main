<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { KeyRound, Save, Stethoscope, UserPlus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { store, update } from '@/actions/App/Http/Controllers/DoctorController';
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
import DoctorScheduleSection from './DoctorScheduleSection.vue';
import type {
    Clinic,
    CompensationType,
    Doctor,
    DoctorFormData,
} from '../types';

const props = defineProps<{
    open: boolean;
    doctor: Doctor | null;
    clinics: Clinic[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const selectedClinic = computed<Clinic | null>(() => {
    if (form.clinic_id === '' || form.clinic_id === null) {
        return null;
    }
    return props.clinics.find((c) => c.id === Number(form.clinic_id)) ?? null;
});

const defaultsFor = (doctor: Doctor | null): DoctorFormData => ({
    clinic_id: doctor?.clinic_id ?? '',
    user_id: doctor?.user_id ?? null,
    full_name: doctor?.full_name ?? '',
    gender: doctor?.gender ?? 'male',
    specialty: doctor?.specialty ?? '',
    phone: doctor?.phone ?? '',
    email: doctor?.email ?? '',
    username: doctor?.username ?? '',
    employment_start_date: doctor?.employment_start_date ?? '',
    compensation_type: doctor?.compensation_type ?? 'percentage',
    compensation_value:
        doctor?.compensation_value !== null && doctor?.compensation_value !== undefined
            ? String(doctor.compensation_value)
            : '',
    is_active: doctor?.is_active ?? true,
    notes: doctor?.notes ?? '',
    schedules: doctor?.schedules ?? [],
});

const form = useForm<DoctorFormData>(defaultsFor(props.doctor));
const isHydrating = ref(false);

const isEditing = computed(() => props.doctor !== null);

const compensationLabel = computed(() => {
    switch (form.compensation_type) {
        case 'percentage':
            return 'نسبة الطبيب (%)';
        case 'weekly_fixed':
            return 'قيمة الأجر الأسبوعي';
        case 'monthly_fixed':
            return 'قيمة الأجر الشهري';
        default:
            return 'قيمة الأجر';
    }
});

watch(
    () => [props.open, props.doctor?.id],
    () => {
        if (!props.open) {
            return;
        }
        isHydrating.value = true;
        const next = defaultsFor(props.doctor);
        form.defaults(next);
        form.reset();
        Object.assign(form, next);
        form.clearErrors();
        isHydrating.value = false;
    },
);

const close = (): void => emit('update:open', false);

const submit = (): void => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            close();
        },
    };

    if (props.doctor !== null) {
        form.put(update.url(props.doctor.id), options);
        return;
    }

    form.post(store.url(), options);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="flex max-h-[90vh] max-w-4xl flex-col overflow-hidden rounded-xl bg-card p-0"
            dir="rtl"
        >
            <DialogHeader
                class="shrink-0 border-b border-border px-4 py-4 text-right sm:px-6 sm:py-5"
            >
                <DialogTitle class="text-2xl font-bold text-foreground">
                    {{ isEditing ? 'تعديل بيانات الطبيب' : 'إضافة طبيب جديد' }}
                </DialogTitle>
                <DialogDescription class="text-muted-foreground">
                    إدارة بيانات الطبيب والحساب والدوام ونظام الأجر من نموذج واحد.
                </DialogDescription>
            </DialogHeader>

            <form
                class="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-5 pb-28 sm:px-6"
                @submit.prevent="submit"
            >
                <!-- 1. البيانات الأساسية -->
                <section class="space-y-3">
                    <h3 class="text-sm font-bold text-foreground">البيانات الأساسية</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_full_name">الاسم الكامل</Label>
                            <Input
                                id="doctor_full_name"
                                v-model="form.full_name"
                                class="h-11 rounded-lg"
                            />
                            <InputError :message="form.errors.full_name" />
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
                            <Label for="doctor_phone">رقم الهاتف (اختياري)</Label>
                            <Input
                                id="doctor_phone"
                                v-model="form.phone"
                                class="h-11 rounded-lg"
                            />
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_email">البريد الإلكتروني (اختياري)</Label>
                            <Input
                                id="doctor_email"
                                v-model="form.email"
                                type="email"
                                class="h-11 rounded-lg"
                            />
                            <InputError :message="form.errors.email" />
                        </div>
                    </div>
                </section>

                <!-- 2. بيانات العيادة والعمل -->
                <section
                    class="space-y-3 rounded-lg border border-border bg-card p-4"
                >
                    <h3 class="text-sm font-bold text-foreground">بيانات العيادة والعمل</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_clinic">العيادة التابعة للطبيب</Label>
                            <select
                                id="doctor_clinic"
                                v-model="form.clinic_id"
                                class="h-11 rounded-lg border border-input bg-muted px-3 text-sm"
                            >
                                <option value="">يرجى اختيار العيادة</option>
                                <option
                                    v-for="clinic in clinics"
                                    :key="clinic.id"
                                    :value="clinic.id"
                                >
                                    {{ clinic.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.clinic_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_employment_start_date">تاريخ مباشرة العمل</Label>
                            <Input
                                id="doctor_employment_start_date"
                                v-model="form.employment_start_date"
                                type="date"
                                class="h-11 rounded-lg"
                            />
                            <InputError :message="form.errors.employment_start_date" />
                        </div>
                    </div>
                </section>

                <!-- 3. جدول الدوام -->
                <DoctorScheduleSection
                    v-if="selectedClinic !== null"
                    :model-value="form"
                    :selected-clinic="selectedClinic"
                    :errors="form.errors"
                    @update:model-value="Object.assign(form, $event)"
                />

                <section
                    v-else-if="form.clinic_id === ''"
                    class="rounded-lg border border-dashed border-border bg-muted/30 p-6 text-center text-sm text-muted-foreground"
                >
                    اختر عيادة أولاً لتحديد جدول الدوام.
                </section>

                <!-- 4. بيانات الحساب -->
                <section
                    class="space-y-3 rounded-lg border border-border bg-card p-4"
                >
                    <div class="flex items-center gap-2">
                        <KeyRound class="size-4 text-primary" />
                        <h3 class="text-sm font-bold text-foreground">بيانات الحساب</h3>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_username">اسم المستخدم (اختياري)</Label>
                            <Input
                                id="doctor_username"
                                v-model="form.username"
                                class="h-11 rounded-lg"
                                placeholder="اسم مستخدم فريد للطبيب"
                            />
                            <InputError :message="form.errors.username" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_is_active">حالة الطبيب</Label>
                            <label class="flex items-center gap-3 rounded-lg border border-border bg-muted px-3 py-2.5">
                                <Switch v-model="form.is_active" />
                                <span class="text-sm font-semibold text-foreground">
                                    {{ form.is_active ? 'نشط' : 'غير نشط' }}
                                </span>
                            </label>
                            <InputError :message="form.errors.is_active" />
                        </div>
                    </div>
                </section>

                <!-- 5. نظام الأجر -->
                <section
                    class="space-y-3 rounded-lg border border-border bg-card p-4"
                >
                    <div class="flex items-center gap-2">
                        <Stethoscope class="size-4 text-primary" />
                        <h3 class="text-sm font-bold text-foreground">نظام أجر الطبيب</h3>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="doctor_compensation_type">نوع أجر الطبيب</Label>
                            <select
                                id="doctor_compensation_type"
                                v-model="form.compensation_type"
                                class="h-11 rounded-lg border border-input bg-muted px-3 text-sm"
                            >
                                <option value="percentage">نسبة مئوية</option>
                                <option value="weekly_fixed">أجر أسبوعي ثابت</option>
                                <option value="monthly_fixed">أجر شهري ثابت</option>
                            </select>
                            <InputError :message="form.errors.compensation_type" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="doctor_compensation_value">{{ compensationLabel }}</Label>
                            <Input
                                id="doctor_compensation_value"
                                v-model="form.compensation_value"
                                type="number"
                                min="0"
                                step="0.01"
                                class="h-11 rounded-lg"
                            />
                            <InputError :message="form.errors.compensation_value" />
                        </div>
                    </div>
                </section>

                <!-- 6. الملاحظات -->
                <section class="space-y-3">
                    <h3 class="text-sm font-bold text-foreground">الملاحظات</h3>
                    <div class="grid gap-2">
                        <textarea
                            id="doctor_notes"
                            v-model="form.notes"
                            rows="3"
                            class="rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/10 focus:outline-none"
                            placeholder="ملاحظات إدارية حول الطبيب (اختياري)"
                        ></textarea>
                        <InputError :message="form.errors.notes" />
                    </div>
                </section>
            </form>

            <!-- 7. أزرار -->
            <DialogFooter
                class="sticky bottom-0 z-10 shrink-0 border-t border-border bg-card px-4 py-3 shadow-[0_-12px_24px_rgba(15,23,42,0.06)] sm:px-6 sm:py-4"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="h-10 w-full sm:w-auto"
                    :disabled="form.processing"
                    @click="close"
                >
                    <X class="size-4" />
                    إلغاء
                </Button>
                <Button
                    type="button"
                    class="h-10 w-full bg-primary text-primary-foreground hover:bg-primary/90 sm:w-auto"
                    :disabled="form.processing"
                    @click="submit"
                >
                    <component :is="isEditing ? Save : UserPlus" class="size-4" />
                    {{ isEditing ? 'حفظ التغييرات' : 'إضافة طبيب جديد' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
