<script setup lang="ts">
import { Calendar, CalendarDays, Plus, Eye, Pencil } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { appointmentStatusClass, appointmentStatusDotClass, appointmentStatusLabel, formatTime } from './appointmentHelpers';
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
    <section class="rounded-xl border border-border/70 bg-card px-4 py-3">
        <div class="flex flex-wrap items-center gap-4 md:gap-6">
            <div class="flex items-center gap-2">
                <Calendar class="size-4 text-muted-foreground" />
                <span class="text-sm text-muted-foreground">اليوم</span>
                <span class="text-lg font-bold tabular-nums text-foreground">{{ todaySummary.total }}</span>
            </div>
            <div class="hidden h-5 w-px bg-border/60 md:block" aria-hidden="true"></div>
            <div class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-[var(--accent-teal)]" aria-hidden="true"></span>
                <span class="text-sm text-muted-foreground">مجدول</span>
                <span class="text-lg font-bold tabular-nums text-[var(--accent-teal-strong)]">{{ todaySummary.scheduled }}</span>
            </div>
            <div class="hidden h-5 w-px bg-border/60 md:block" aria-hidden="true"></div>
            <div class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-[var(--accent-mint)]" aria-hidden="true"></span>
                <span class="text-sm text-muted-foreground">حاضر</span>
                <span class="text-lg font-bold tabular-nums text-[var(--accent-mint-strong)]">{{ todaySummary.arrived }}</span>
            </div>
            <div class="hidden h-5 w-px bg-border/60 md:block" aria-hidden="true"></div>
            <div class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-[var(--accent-mint)]" aria-hidden="true"></span>
                <span class="text-sm text-muted-foreground">مكتمل</span>
                <span class="text-lg font-bold tabular-nums text-[var(--accent-mint-strong)]">{{ todaySummary.completed }}</span>
            </div>
            <div v-if="todaySummary.canceled > 0" class="hidden h-5 w-px bg-border/60 md:block" aria-hidden="true"></div>
            <div v-if="todaySummary.canceled > 0" class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-[var(--accent-coral)]" aria-hidden="true"></span>
                <span class="text-sm text-muted-foreground">ملغي</span>
                <span class="text-lg font-bold tabular-nums text-[var(--accent-coral-strong)]">{{ todaySummary.canceled }}</span>
            </div>
        </div>
    </section>

    <div class="glass-panel-soft p-5">
        <div v-if="groupedByHour.length === 0" class="py-16 text-center">
            <CalendarDays class="mx-auto mb-4 size-12 text-muted-foreground/40" />
            <p class="text-sm font-medium text-muted-foreground">لا توجد مواعيد اليوم</p>
            <Button
                v-if="canCreateAppointment"
                variant="default"
                size="sm"
                class="mt-4 min-h-[44px]"
                @click="$emit('create')"
            >
                <Plus class="size-3.5" />
                إضافة موعد
            </Button>
        </div>

        <div v-else class="space-y-6">
            <div
                v-for="group in groupedByHour"
                :key="group.hour"
            >
                <div class="mb-2 flex items-center gap-2">
                    <span class="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-bold tabular-nums text-muted-foreground">
                        {{ group.hour }}
                    </span>
                    <span class="text-xs text-muted-foreground">
                        {{ group.appointments.length }} {{ group.appointments.length === 1 ? 'موعد' : 'مواعيد' }}
                    </span>
                </div>

                <div class="space-y-2">
                    <div
                        v-for="apt in group.appointments"
                        :key="apt.id"
                        class="flex flex-col gap-3 rounded-xl border border-border/60 bg-background/50 p-3 transition-colors hover:border-[var(--accent-mint-soft)] hover:bg-[var(--accent-mint-soft)]/30 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-bold tabular-nums text-muted-foreground">
                                {{ formatTime(apt.scheduled_for).split(' ')[0] }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold">{{ apt.patient?.full_name ?? '-' }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ apt.doctor?.name ?? 'بدون طبيب' }}
                                    <span class="mx-1">·</span>
                                    {{ apt.duration_minutes }} دقيقة
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium capitalize"
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
                                    variant="outline"
                                    size="icon-sm"
                                    class="h-9 w-9"
                                    @click="$emit('view', apt)"
                                    aria-label="عرض الموعد"
                                >
                                    <Eye class="size-3.5" />
                                </Button>
                                <Button
                                    v-if="canEditAppointment"
                                    type="button"
                                    variant="default"
                                    size="icon-sm"
                                    class="h-9 w-9"
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