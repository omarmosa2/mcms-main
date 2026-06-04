<script setup lang="ts">
import { Edit, Eye, Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import type { DoctorProfile, PaginatedResponse } from './types';

defineProps<{
    doctorProfiles: PaginatedResponse<DoctorProfile>;
}>();

const emit = defineEmits<{
    view: [profile: DoctorProfile];
    edit: [profile: DoctorProfile];
    delete: [profile: DoctorProfile];
}>();

const { can } = usePermissions();

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

const compensationValueLabel = (profile: DoctorProfile): string => {
    if (profile.compensation_value === null || profile.compensation_value === undefined) {
        return '-';
    }

    const value = Number(profile.compensation_value);

    if (profile.compensation_type === 'percentage') {
        return `${value}%`;
    }

    return value.toLocaleString('ar-SY', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
};
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full table-fixed text-right text-sm" dir="rtl">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-slate-900">
                        <th class="w-[19%] px-5 py-4 font-bold">الاسم</th>
                        <th class="w-[9%] px-4 py-4 font-bold">الجنس</th>
                        <th class="w-[15%] px-4 py-4 font-bold">الاختصاص</th>
                        <th class="w-[14%] px-4 py-4 font-bold">العيادة</th>
                        <th class="w-[12%] px-4 py-4 font-bold">نوع الأجر</th>
                        <th class="w-[11%] px-4 py-4 font-bold">قيمة الأجر</th>
                        <th class="w-[11%] px-4 py-4 font-bold">حالة الحساب</th>
                        <th class="w-[9%] px-4 py-4 font-bold">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="profile in doctorProfiles.data"
                        :key="profile.id"
                        class="border-b border-slate-100 text-slate-900 last:border-b-0 hover:bg-sky-50/35"
                    >
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-sky-500 text-sm font-bold text-white">
                                    {{ (profile.user?.name ?? 'ط').slice(0, 1) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold">{{ profile.user?.name ?? '-' }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ profile.user?.email ?? '-' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-800">
                                {{ genderLabel(profile) }}
                            </span>
                        </td>
                        <td class="px-4 py-4">{{ profile.specialty }}</td>
                        <td class="px-4 py-4 text-slate-700">{{ profile.department?.name ?? '-' }}</td>
                        <td class="px-4 py-4">{{ compensationTypeLabel(profile) }}</td>
                        <td class="px-4 py-4">{{ compensationValueLabel(profile) }}</td>
                        <td class="px-4 py-4">
                            <span
                                class="inline-flex rounded-full px-3 py-1 text-xs font-bold"
                                :class="profile.user?.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                            >
                                {{ profile.user?.is_active ? 'نشط' : 'غير نشط' }}
                            </span>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center justify-end gap-1.5">
                                <Button type="button" variant="ghost" size="icon" class="size-8 text-sky-500" title="عرض" @click="emit('view', profile)">
                                    <Eye class="size-4" />
                                </Button>
                                <Button v-if="can('doctor_profile.update')" type="button" variant="ghost" size="icon" class="size-8 text-blue-600" title="تعديل" @click="emit('edit', profile)">
                                    <Edit class="size-4" />
                                </Button>
                                <Button v-if="can('doctor_profile.delete')" type="button" variant="ghost" size="icon" class="size-8 text-red-500" title="حذف" @click="emit('delete', profile)">
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="doctorProfiles.data.length === 0">
                        <td colspan="8" class="px-5 py-12 text-center text-slate-500">
                            لا يوجد أطباء مسجلون حالياً.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
