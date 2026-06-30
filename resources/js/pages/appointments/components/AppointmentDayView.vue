<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Clock, Eye, IdCard, Pencil, Plus, Trash2 } from 'lucide-vue-next';
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
    canDeleteAppointment: boolean;
}>();

defineEmits<{
    view: [appointment: Appointment];
    edit: [appointment: Appointment];
    delete: [appointment: Appointment];
    create: [];
}>();
</script>

<template>
    <section class="glass-panel-soft overflow-hidden">
        <div
            class="flex flex-col gap-3 border-b border-border/70 bg-secondary/20 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-2xl border border-primary/15 bg-primary/10 text-primary"
                >
                    <CalendarDays class="size-4.5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-foreground">
                        جدول اليوم
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        ترتيب المواعيد حسب ساعة الحضور
                    </p>
                </div>
            </div>
            <span
                class="inline-flex w-fit items-center gap-1.5 rounded-full border border-border/80 bg-background px-3 py-1.5 text-xs font-semibold text-muted-foreground shadow-sm"
            >
                <CalendarDays class="size-3.5 text-primary" />
                {{ todaySummary.total }} موعد
            </span>
        </div>

        <div class="p-5">
            <div
                v-if="groupedByHour.length === 0"
                class="flex min-h-[280px] flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-secondary/20 px-4 py-12 text-center"
            >
                <div
                    class="mb-3 flex size-14 items-center justify-center rounded-2xl bg-background text-muted-foreground shadow-sm"
                >
                    <CalendarDays class="size-7" />
                </div>
                <p class="text-sm font-bold text-foreground">
                    لا توجد مواعيد اليوم
                </p>
                <p class="mt-1 max-w-sm text-xs text-muted-foreground">
                    عند إضافة أول موعد سيظهر هنا ضمن الساعة المناسبة
                </p>
                <Button
                    v-if="canCreateAppointment"
                    variant="default"
                    size="sm"
                    class="mt-4 gap-1.5 rounded-xl"
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
                            class="inline-flex items-center gap-1.5 rounded-full border border-border bg-background px-3 py-1.5 shadow-sm"
                        >
                            <Clock class="size-3.5 text-primary" />
                            <span
                                class="text-xs font-bold text-foreground tabular-nums"
                                dir="ltr"
                            >
                                {{ group.hour }}
                            </span>
                        </div>
                        <span class="text-[0.72rem] text-muted-foreground">
                            {{ group.appointments.length }}
                            {{
                                group.appointments.length === 1
                                    ? 'موعد'
                                    : 'مواعيد'
                            }}
                        </span>
                        <div class="h-px flex-1 bg-border/60" aria-hidden="true" />
                    </div>

                    <div class="grid gap-2.5">
                        <article
                            v-for="appointment in group.appointments"
                            :key="appointment.id"
                            class="group flex flex-col gap-3 rounded-2xl border border-border/80 bg-card px-4 py-3 shadow-sm transition-all duration-200 hover:border-primary/30 hover:bg-primary/[0.03] hover:shadow-card lg:flex-row lg:items-center lg:justify-between"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <div
                                    class="flex size-12 shrink-0 items-center justify-center rounded-2xl border border-primary/15 bg-primary/10 text-sm font-black text-primary tabular-nums"
                                    dir="ltr"
                                >
                                    {{
                                        formatTime(
                                            appointment.scheduled_for,
                                        ).split(' ')[0]
                                    }}
                                </div>

                                <div class="min-w-0">
                                    <p class="truncate text-base font-bold text-foreground">
                                        {{
                                            appointment.patient?.full_name ??
                                            '-'
                                        }}
                                    </p>
                                    <p class="mt-1 truncate text-xs text-muted-foreground">
                                        {{
                                            appointment.doctor?.name ??
                                            'بدون طبيب'
                                        }}
                                        <span
                                            v-if="appointment.doctor?.specialty"
                                        >
                                            · {{ appointment.doctor.specialty }}
                                        </span>
                                    </p>
                                    <p class="mt-0.5 text-[0.72rem] text-muted-foreground">
                                        {{ appointment.duration_minutes }} دقيقة
                                        <span
                                            v-if="appointment.appointment_type"
                                        >
                                            ·
                                            {{
                                                appointment.appointment_type ===
                                                'first_visit'
                                                    ? 'كشفية أولى'
                                                    : 'مراجعة'
                                            }}
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <div
                                class="flex flex-wrap items-center justify-between gap-2 lg:justify-end"
                            >
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[0.72rem] font-medium"
                                    :class="
                                        appointmentStatusClass(
                                            appointment.status,
                                        )
                                    "
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="
                                            appointmentStatusDotClass(
                                                appointment.status,
                                            )
                                        "
                                    />
                                    {{
                                        appointmentStatusLabel(
                                            appointment.status,
                                        )
                                    }}
                                </span>

                                <div class="flex items-center gap-1 rounded-xl border border-border/60 bg-secondary/20 p-1">
                                    <Link
                                        v-if="appointment.patient?.id"
                                        :href="`/patients/${appointment.patient.id}/card`"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-background hover:text-foreground"
                                        title="بطاقة المريض"
                                    >
                                        <IdCard class="size-3.5" />
                                    </Link>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 rounded-lg text-muted-foreground hover:text-foreground"
                                        aria-label="عرض الموعد"
                                        @click="$emit('view', appointment)"
                                    >
                                        <Eye class="size-3.5" />
                                    </Button>
                                    <Button
                                        v-if="canEditAppointment"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 rounded-lg text-muted-foreground hover:text-foreground"
                                        aria-label="تعديل الموعد"
                                        @click="$emit('edit', appointment)"
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                    <Button
                                        v-if="canDeleteAppointment && appointment.status === 'scheduled'"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 rounded-lg text-destructive hover:bg-destructive/10"
                                        aria-label="حذف الموعد"
                                        @click="$emit('delete', appointment)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
