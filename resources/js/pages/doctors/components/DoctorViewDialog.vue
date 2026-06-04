<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

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

type DoctorProfile = {
    id: number;
    clinic_id: number;
    user_id: number;
    department_id: number | null;
    license_number: string | null;
    specialty: string;
    consultation_duration_minutes: number;
    status: DoctorProfileStatus;
    work_schedule: Record<string, unknown> | null;
    bio: string | null;
    user?: DoctorOption | null;
    department?: DepartmentOption | null;
    created_at: string | null;
    updated_at: string | null;
};

const props = defineProps<{
    profile: DoctorProfile | null;
}>();

const emit = defineEmits<{ close: [] }>();

const statusLabels: Record<DoctorProfileStatus, string> = {
    active: 'نشط',
    on_leave: 'في إجازة',
    inactive: 'غير نشط',
};

const formatStatus = (status: DoctorProfileStatus): string => {
    return statusLabels[status] ?? status.replace('_', ' ');
};

const doctorLabel = (profile: DoctorProfile): string => {
    return profile.user?.name ?? `Doctor #${profile.user_id}`;
};

const departmentLabel = (profile: DoctorProfile): string => {
    if (profile.department === null || profile.department === undefined) {
        return 'غير معين';
    }

    return profile.department.code !== null
        ? `${profile.department.name} (${profile.department.code})`
        : profile.department.name;
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
                <DialogTitle>
                    {{ profile ? doctorLabel(profile) : 'تفاصيل ملف الطبيب' }}
                </DialogTitle>
                <DialogDescription>
                    تفاصيل الملف، رابط القسم، وجدول العمل.
                </DialogDescription>
            </DialogHeader>

            <dl
                v-if="profile"
                class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4 sm:grid-cols-2"
            >
                <div class="space-y-1">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">
                        التخصص
                    </dt>
                    <dd class="text-sm">
                        {{ profile.specialty }}
                    </dd>
                </div>

                <div class="space-y-1">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">
                        الحالة
                    </dt>
                    <dd class="text-sm capitalize">
                        {{ formatStatus(profile.status) }}
                    </dd>
                </div>

                <div class="space-y-1">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">
                        القسم
                    </dt>
                    <dd class="text-sm">
                        {{ departmentLabel(profile) }}
                    </dd>
                </div>

                <div class="space-y-1">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">
                        مدة الاستشارة
                    </dt>
                    <dd class="text-sm">
                        {{ profile.consultation_duration_minutes }} دقيقة
                    </dd>
                </div>

                <div class="space-y-1 sm:col-span-2">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">
                        رقم الترخيص
                    </dt>
                    <dd class="text-sm">
                        {{ profile.license_number ?? '-' }}
                    </dd>
                </div>

                <div class="space-y-1 sm:col-span-2">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">
                        جدول العمل
                    </dt>
                    <dd class="rounded-lg border border-border/60 bg-background/60 p-3">
                        <pre class="overflow-x-auto text-xs text-muted-foreground">{{ stringifyWorkSchedule(profile.work_schedule) || '-' }}</pre>
                    </dd>
                </div>

                <div class="space-y-1 sm:col-span-2">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">
                        السيرة الذاتية
                    </dt>
                    <dd class="text-sm leading-6 text-muted-foreground">
                        {{ profile.bio ?? 'لا توجد سيرة ذاتية' }}
                    </dd>
                </div>
            </dl>

            <DialogFooter>
                <Button
                    type="button"
                    variant="ghost"
                    @click="emit('close')"
                >
                    إغلاق
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
