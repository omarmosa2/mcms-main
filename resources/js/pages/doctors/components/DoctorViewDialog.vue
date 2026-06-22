<script setup lang="ts">
import { computed } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import type { Doctor, DoctorSchedule } from '../types';
import { DAY_NAMES } from '../types';

const props = defineProps<{
    doctor: Doctor | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const open = computed({
    get: () => props.doctor !== null,
    set: (value: boolean) => {
        if (!value) {
            emit('close');
        }
    },
});

const compensationLabel = computed(() => {
    if (props.doctor === null) {
        return '—';
    }
    switch (props.doctor.compensation_type) {
        case 'percentage':
            return `${props.doctor.compensation_value ?? 0}% نسبة مئوية`;
        case 'weekly_fixed':
            return `${props.doctor.compensation_value ?? 0} أجر أسبوعي ثابت`;
        case 'monthly_fixed':
            return `${props.doctor.compensation_value ?? 0} أجر شهري ثابت`;
        default:
            return '—';
    }
});

const sortedSchedules = computed<DoctorSchedule[]>(() => {
    if (props.doctor === null) {
        return [];
    }
    return [...props.doctor.schedules].sort(
        (a, b) => Number(a.day_of_week) - Number(b.day_of_week),
    );
});

const dayName = (day: number): string => DAY_NAMES[day] ?? '';
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-w-2xl rounded-xl bg-card p-0"
            dir="rtl"
        >
            <DialogHeader class="border-b border-border px-5 py-4 text-right">
                <DialogTitle class="text-xl font-bold text-foreground">
                    بيانات الطبيب
                </DialogTitle>
                <DialogDescription class="text-muted-foreground">
                    عرض تفاصيل الطبيب وجدول الدوام.
                </DialogDescription>
            </DialogHeader>

            <div v-if="doctor" class="space-y-5 px-5 py-5">
                <!-- بطاقة علوية -->
                <div class="flex items-center justify-between gap-4 rounded-lg border border-border bg-muted/40 p-4">
                    <div>
                        <h2 class="text-lg font-bold text-foreground">
                            {{ doctor.full_name }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ doctor.specialty }}
                        </p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <span
                            :class="[
                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                doctor.is_active
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : 'bg-rose-100 text-rose-700',
                            ]"
                        >
                            {{ doctor.is_active ? 'نشط' : 'غير نشط' }}
                        </span>
                        <span class="text-sm font-semibold text-muted-foreground">
                            {{ doctor.clinic?.name ?? '—' }}
                        </span>
                    </div>
                </div>

                <!-- البيانات الأساسية -->
                <section class="space-y-2">
                    <h3 class="text-sm font-bold text-foreground">البيانات الأساسية</h3>
                    <dl class="grid gap-2 text-sm sm:grid-cols-2">
                        <div class="flex justify-between gap-2 rounded bg-muted/30 px-3 py-2">
                            <dt class="text-muted-foreground">الجنس</dt>
                            <dd class="font-semibold text-foreground">
                                {{ doctor.gender === 'male' ? 'ذكر' : doctor.gender === 'female' ? 'أنثى' : '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2 rounded bg-muted/30 px-3 py-2">
                            <dt class="text-muted-foreground">الهاتف</dt>
                            <dd class="font-semibold text-foreground">{{ doctor.phone ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2 rounded bg-muted/30 px-3 py-2">
                            <dt class="text-muted-foreground">البريد الإلكتروني</dt>
                            <dd class="font-semibold text-foreground">{{ doctor.email ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2 rounded bg-muted/30 px-3 py-2">
                            <dt class="text-muted-foreground">اسم المستخدم</dt>
                            <dd class="font-semibold text-foreground">{{ doctor.username ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2 rounded bg-muted/30 px-3 py-2">
                            <dt class="text-muted-foreground">تاريخ المباشرة</dt>
                            <dd class="font-semibold text-foreground">
                                {{ doctor.employment_start_date ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <!-- الأجر -->
                <section class="space-y-2">
                    <h3 class="text-sm font-bold text-foreground">نظام الأجر</h3>
                    <div class="rounded bg-muted/30 px-3 py-2 text-sm font-semibold text-foreground">
                        {{ compensationLabel }}
                    </div>
                </section>

                <!-- الدوام -->
                <section class="space-y-2">
                    <h3 class="text-sm font-bold text-foreground">جدول الدوام</h3>
                    <div v-if="sortedSchedules.length === 0" class="rounded-lg border border-dashed border-border px-3 py-4 text-center text-sm text-muted-foreground">
                        لا يوجد دوام
                    </div>
                    <div v-else class="grid gap-2 sm:grid-cols-2">
                        <div
                            v-for="schedule in sortedSchedules"
                            :key="schedule.id ?? schedule.day_of_week"
                            class="flex items-center justify-between gap-2 rounded-lg border border-border/70 bg-muted/30 px-3 py-2 text-sm"
                        >
                            <span class="font-semibold text-foreground">
                                {{ dayName(Number(schedule.day_of_week)) }}
                            </span>
                            <span class="text-muted-foreground">
                                {{ schedule.start_time ?? '—' }} - {{ schedule.end_time ?? '—' }}
                            </span>
                        </div>
                    </div>
                </section>

                <section v-if="doctor.notes" class="space-y-2">
                    <h3 class="text-sm font-bold text-foreground">ملاحظات</h3>
                    <p class="rounded-lg bg-muted/30 px-3 py-2 text-sm text-foreground">
                        {{ doctor.notes }}
                    </p>
                </section>
            </div>

            <div class="flex justify-end border-t border-border px-5 py-3">
                <Button variant="outline" class="rounded-lg" @click="emit('close')">
                    إغلاق
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
