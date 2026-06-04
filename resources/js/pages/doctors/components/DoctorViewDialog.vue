<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { DoctorProfile } from './types';

defineProps<{
    profile: DoctorProfile | null;
}>();

const emit = defineEmits<{ close: [] }>();

const days: Record<number, string> = {
    6: 'السبت',
    0: 'الأحد',
    1: 'الإثنين',
    2: 'الثلاثاء',
    3: 'الأربعاء',
    4: 'الخميس',
    5: 'الجمعة',
};

const genderLabel = (profile: DoctorProfile): string => {
    if (profile.gender === 'female') {
        return 'أنثى';
    }

    if (profile.gender === 'male') {
        return 'ذكر';
    }

    return '-';
};

const compensationTypeLabel = (profile: DoctorProfile): string => {
    if (profile.compensation_type === 'weekly') {
        return 'أجر أسبوعي';
    }

    if (profile.compensation_type === 'monthly') {
        return 'أجر شهري';
    }

    return profile.compensation_type === 'percentage' ? 'نسبة مئوية' : '-';
};
</script>

<template>
    <Dialog
        :open="profile !== null"
        @update:open="(open) => !open && emit('close')"
    >
        <DialogContent class="max-w-3xl rounded-xl bg-white" dir="rtl">
            <DialogHeader class="text-right">
                <DialogTitle class="text-2xl font-bold text-slate-900">
                    {{ profile?.user?.name ?? 'تفاصيل الطبيب' }}
                </DialogTitle>
            </DialogHeader>

            <div v-if="profile" class="space-y-4">
                <div class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 md:grid-cols-3">
                    <div>
                        <p class="text-xs text-slate-500">الجنس</p>
                        <p class="font-semibold">{{ genderLabel(profile) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">الاختصاص</p>
                        <p class="font-semibold">{{ profile.specialty }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">العيادة</p>
                        <p class="font-semibold">{{ profile.department?.name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">رقم الهاتف</p>
                        <p class="font-semibold">{{ profile.phone ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">اسم المستخدم</p>
                        <p class="font-semibold">{{ profile.user?.email ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">حالة الحساب</p>
                        <p class="font-semibold">{{ profile.user?.is_active ? 'نشط' : 'غير نشط' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">نوع الأجر</p>
                        <p class="font-semibold">{{ compensationTypeLabel(profile) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">قيمة الأجر</p>
                        <p class="font-semibold">{{ profile.compensation_value ?? '-' }}</p>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">دوام الطبيب</h3>
                    <div class="grid gap-2 md:grid-cols-2">
                        <div
                            v-for="day in profile.working_hours"
                            :key="day.day_of_week"
                            class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2"
                        >
                            <span class="font-semibold">{{ days[day.day_of_week] }}</span>
                            <span class="text-sm text-slate-600">
                                {{ day.is_active ? `${day.start_time?.slice(0, 5)} - ${day.end_time?.slice(0, 5)}` : 'لا يوجد دوام' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" @click="emit('close')">إغلاق</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
