<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { computed } from 'vue';
import type { DoctorProfile } from './types';

const { profile } = defineProps<{
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

const activeSchedules = computed(() => {
    if (profile === null) {
        return [];
    }

    return profile.schedules
        .filter((schedule) => schedule.is_available)
        .sort((first, second) => first.day_of_week - second.day_of_week);
});

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
    if (
        profile.compensation_value === null ||
        profile.compensation_value === undefined
    ) {
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

const formatDate = (value: string | null): string => {
    if (value === null) {
        return '-';
    }

    return new Intl.DateTimeFormat('ar-SY').format(new Date(value));
};

const scheduleTimeLabel = (
    startTime: string | null,
    endTime: string | null,
): string => {
    if (startTime === null || endTime === null) {
        return '-';
    }

    return `${startTime.slice(0, 5)} - ${endTime.slice(0, 5)}`;
};
</script>

<template>
    <Dialog
        :open="profile !== null"
        @update:open="(open) => !open && emit('close')"
    >
        <DialogContent size="2xl" class="max-h-[90vh] bg-card p-0" dir="rtl">
            <DialogHeader class="border-b border-border px-6 py-5 text-right">
                <div class="flex items-start justify-between gap-4 pl-10">
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-lg font-black text-primary"
                        >
                            {{ (profile?.name ?? 'ط').slice(0, 1) }}
                        </span>
                        <div class="min-w-0">
                            <DialogTitle
                                class="truncate text-2xl font-black text-foreground"
                            >
                                {{ profile?.name ?? 'تفاصيل الطبيب' }}
                            </DialogTitle>
                            <p
                                v-if="profile"
                                class="mt-1 truncate text-sm text-muted-foreground"
                            >
                                {{ profile.specialty }} ·
                                {{ profile.clinic?.name ?? 'غير معين' }}
                            </p>
                        </div>
                    </div>

                    <span
                        v-if="profile"
                        class="mt-1 inline-flex shrink-0 items-center gap-2 rounded-full border px-3 py-1 text-xs font-bold"
                        :class="
                            profile.is_active
                                ? 'border-success/20 bg-success/10 text-success'
                                : 'border-destructive/20 bg-destructive/10 text-destructive'
                        "
                    >
                        <span class="size-2 rounded-full bg-current"></span>
                        {{ profile.is_active ? 'نشط' : 'غير نشط' }}
                    </span>
                </div>
            </DialogHeader>

            <div
                v-if="profile"
                class="max-h-[66vh] space-y-5 overflow-y-auto p-6"
            >
                <section
                    class="rounded-xl border border-border bg-muted/40 p-4"
                >
                    <h3 class="mb-4 text-sm font-black text-foreground">
                        البيانات الأساسية
                    </h3>

                    <dl class="grid gap-3 md:grid-cols-3">
                        <div
                            class="rounded-lg border border-border/70 bg-card px-4 py-3"
                        >
                            <dt
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                الجنس
                            </dt>
                            <dd class="mt-1 font-bold text-foreground">
                                {{ genderLabel(profile) }}
                            </dd>
                        </div>
                        <div
                            class="rounded-lg border border-border/70 bg-card px-4 py-3"
                        >
                            <dt
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                الاختصاص
                            </dt>
                            <dd class="mt-1 font-bold text-foreground">
                                {{ profile.specialty }}
                            </dd>
                        </div>
                        <div
                            class="rounded-lg border border-border/70 bg-card px-4 py-3"
                        >
                            <dt
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                العيادة
                            </dt>
                            <dd class="mt-1 font-bold text-foreground">
                                {{ profile.clinic?.name ?? '-' }}
                            </dd>
                        </div>
                        <div
                            class="rounded-lg border border-border/70 bg-card px-4 py-3"
                        >
                            <dt
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                رقم الهاتف
                            </dt>
                            <dd class="mt-1 font-bold text-foreground">
                                {{ profile.phone ?? '-' }}
                            </dd>
                        </div>
                        <div
                            class="rounded-lg border border-border/70 bg-card px-4 py-3"
                        >
                            <dt
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                تاريخ مباشرة العمل
                            </dt>
                            <dd class="mt-1 font-bold text-foreground">
                                {{ formatDate(profile.employment_start_date) }}
                            </dd>
                        </div>
                        <div
                            class="rounded-lg border border-border/70 bg-card px-4 py-3"
                        >
                            <dt
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                اسم المستخدم
                            </dt>
                            <dd
                                class="mt-1 truncate font-bold text-foreground"
                                dir="ltr"
                            >
                                {{ profile.username ?? '-' }}
                            </dd>
                        </div>
                        <div
                            class="rounded-lg border border-border/70 bg-card px-4 py-3"
                        >
                            <dt
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                نوع الأجر
                            </dt>
                            <dd class="mt-1 font-bold text-foreground">
                                {{ compensationTypeLabel(profile) }}
                            </dd>
                        </div>
                        <div
                            class="rounded-lg border border-border/70 bg-card px-4 py-3"
                        >
                            <dt
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                قيمة الأجر
                            </dt>
                            <dd
                                class="mt-1 font-bold text-foreground tabular-nums"
                            >
                                {{ compensationValueLabel(profile) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-xl border border-border bg-card p-4">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h3 class="text-sm font-black text-foreground">
                            دوام الطبيب
                        </h3>
                        <span
                            class="text-xs font-semibold text-muted-foreground"
                        >
                            الأيام الفعالة تعرض وقت البداية والنهاية
                        </span>
                    </div>

                    <div class="grid gap-2 md:grid-cols-2">
                        <div
                            v-for="schedule in activeSchedules"
                            :key="schedule.day_of_week"
                            class="flex min-h-12 items-center justify-between gap-3 rounded-lg border border-border/70 bg-muted/40 px-4 py-3"
                        >
                            <span class="font-bold text-foreground">
                                {{ days[schedule.day_of_week] }}
                            </span>
                            <span
                                class="rounded-full bg-primary/10 px-3 py-1 text-sm font-bold text-primary tabular-nums"
                                dir="ltr"
                            >
                                {{
                                    scheduleTimeLabel(
                                        schedule.start_time,
                                        schedule.end_time,
                                    )
                                }}
                            </span>
                        </div>
                        <p
                            v-if="activeSchedules.length === 0"
                            class="rounded-lg border border-dashed border-border px-4 py-5 text-center text-sm font-medium text-muted-foreground md:col-span-2"
                        >
                            لا يوجد جدول دوام محدد لهذا الطبيب
                        </p>
                    </div>
                </section>
            </div>

            <DialogFooter class="border-t border-border px-6 py-4">
                <Button type="button" variant="outline" @click="emit('close')"
                    >إغلاق</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
