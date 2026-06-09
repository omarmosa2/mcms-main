<script setup lang="ts">
import { CalendarDays, Clock, Eye, Pencil, Plus } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    appointmentStatusClass,
    appointmentStatusDotClass,
    appointmentStatusLabel,
    formatTime,
} from './appointmentHelpers';
import type { Appointment } from './types';

defineProps<{
    groupedByHour: { hour: string; appointments: Appointment[] }[];
    todaySummary: {
        total: number;
        scheduled: number;
        arrived: number;
        completed: number;
        canceled: number;
    };
    canEditAppointment: boolean;
    canCreateAppointment: boolean;
}>();

defineEmits<{
    view: [appointment: Appointment];
    edit: [appointment: Appointment];
    create: [];
}>();
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="groupedByHour.length === 0"
            class="flex flex-col items-center justify-center rounded-2xl border border-border/60 bg-card py-16"
        >
            <div
                class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted/60"
            >
                <CalendarDays class="size-8 text-muted-foreground/50" />
            </div>
            <p class="text-sm font-medium text-muted-foreground">
                لا توجد مواعيد اليوم
            </p>
            <p class="mt-1 text-xs text-muted-foreground/70">
                ابدأ بإضافة موعد جديد لجدولة يومك
            </p>
            <Button
                v-if="canCreateAppointment"
                variant="default"
                size="sm"
                class="mt-5 gap-1.5"
                @click="$emit('create')"
            >
                <Plus class="size-3.5" />
                إضافة موعد
            </Button>
        </div>

        <div v-else class="space-y-5">
            <div v-for="group in groupedByHour" :key="group.hour">
                <div class="mb-3 flex items-center gap-2">
                    <div
                        class="flex items-center gap-1.5 rounded-lg bg-muted/60 px-2.5 py-1"
                    >
                        <Clock class="size-3 text-muted-foreground" />
                        <span
                            class="text-xs font-bold tabular-nums text-foreground"
                        >
                            {{ group.hour }}
                        </span>
                    </div>
                    <span class="text-xs text-muted-foreground">
                        {{ group.appointments.length }}
                        {{ group.appointments.length === 1 ? 'موعد' : 'مواعيد' }}
                    </span>
                    <div
                        class="h-px flex-1 bg-border/50"
                        aria-hidden="true"
                    ></div>
                </div>

                <div class="space-y-2">
                    <div
                        v-for="apt in group.appointments"
                        :key="apt.id"
                        class="group flex flex-col gap-3 rounded-xl border border-border/60 bg-card p-3.5 transition-all hover:border-primary/20 hover:shadow-sm sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary/5 text-xs font-bold tabular-nums text-primary"
                            >
                                {{ formatTime(apt.scheduled_for).split(' ')[0] }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-foreground">
                                    {{ apt.patient?.full_name ?? '-' }}
                                </p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ apt.doctor?.name ?? 'بدون طبيب' }}
                                    <span
                                        v-if="apt.doctor?.specialty"
                                        class="text-muted-foreground/60"
                                    >
                                        · {{ apt.doctor.specialty }}
                                    </span>
                                </p>
                                <p class="mt-0.5 text-[0.65rem] text-muted-foreground/60">
                                    {{ apt.duration_minutes }} دقيقة
                                    <span
                                        v-if="apt.appointment_type"
                                    >
                                        ·
                                        {{
                                            apt.appointment_type === 'first_visit'
                                                ? 'كشفية أولى'
                                                : 'مراجعة'
                                        }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 sm:gap-3">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium"
                                :class="appointmentStatusClass(apt.status)"
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :class="appointmentStatusDotClass(apt.status)"
                                ></span>
                                {{ appointmentStatusLabel(apt.status) }}
                            </span>

                            <div class="flex items-center gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                    @click="$emit('view', apt)"
                                    aria-label="عرض الموعد"
                                >
                                    <Eye class="size-3.5" />
                                </Button>
                                <Button
                                    v-if="canEditAppointment"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                    @click="$emit('edit', apt)"
                                    aria-label="تعديل الموعد"
                                >
                                    <Pencil class="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
