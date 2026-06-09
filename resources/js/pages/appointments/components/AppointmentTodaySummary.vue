<script setup lang="ts">
import { Calendar, CheckCircle2, Clock, UserCheck, XCircle } from 'lucide-vue-next';

defineProps<{
    todaySummary: {
        total: number;
        scheduled: number;
        arrived: number;
        completed: number;
        canceled: number;
    };
    canCreateAppointment: boolean;
}>();
</script>

<template>
    <div
        class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
    >
        <div
            class="flex items-center gap-3 rounded-xl border border-border/60 bg-card px-4 py-3"
        >
            <div
                class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
            >
                <Calendar class="size-4" />
            </div>
            <div>
                <p class="text-[0.7rem] text-muted-foreground">اليوم</p>
                <p class="text-lg font-bold tabular-nums text-foreground">
                    {{ todaySummary.total }}
                </p>
            </div>
        </div>

        <div
            class="flex items-center gap-3 rounded-xl border border-[var(--accent-teal-soft)]/60 bg-[var(--accent-teal-soft)]/30 px-4 py-3"
        >
            <div
                class="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--accent-teal)]/15 text-[var(--accent-teal-strong)]"
            >
                <Clock class="size-4" />
            </div>
            <div>
                <p class="text-[0.7rem] text-[var(--accent-teal-strong)]/70">
                    مجدول
                </p>
                <p
                    class="text-lg font-bold tabular-nums text-[var(--accent-teal-strong)]"
                >
                    {{ todaySummary.scheduled }}
                </p>
            </div>
        </div>

        <div
            class="flex items-center gap-3 rounded-xl border border-[var(--accent-mint-soft)]/60 bg-[var(--accent-mint-soft)]/30 px-4 py-3"
        >
            <div
                class="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--accent-mint)]/15 text-[var(--accent-mint-strong)]"
            >
                <UserCheck class="size-4" />
            </div>
            <div>
                <p class="text-[0.7rem] text-[var(--accent-mint-strong)]/70">
                    حاضر
                </p>
                <p
                    class="text-lg font-bold tabular-nums text-[var(--accent-mint-strong)]"
                >
                    {{ todaySummary.arrived }}
                </p>
            </div>
        </div>

        <div
            class="flex items-center gap-3 rounded-xl border border-[var(--accent-mint-soft)]/60 bg-[var(--accent-mint-soft)]/30 px-4 py-3"
        >
            <div
                class="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--accent-mint)]/15 text-[var(--accent-mint-strong)]"
            >
                <CheckCircle2 class="size-4" />
            </div>
            <div>
                <p class="text-[0.7rem] text-[var(--accent-mint-strong)]/70">
                    مكتمل
                </p>
                <p
                    class="text-lg font-bold tabular-nums text-[var(--accent-mint-strong)]"
                >
                    {{ todaySummary.completed }}
                </p>
            </div>
        </div>

        <div
            v-if="todaySummary.canceled > 0"
            class="flex items-center gap-3 rounded-xl border border-[var(--accent-coral-soft)]/60 bg-[var(--accent-coral-soft)]/30 px-4 py-3"
        >
            <div
                class="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--accent-coral)]/15 text-[var(--accent-coral-strong)]"
            >
                <XCircle class="size-4" />
            </div>
            <div>
                <p class="text-[0.7rem] text-[var(--accent-coral-strong)]/70">
                    ملغي
                </p>
                <p
                    class="text-lg font-bold tabular-nums text-[var(--accent-coral-strong)]"
                >
                    {{ todaySummary.canceled }}
                </p>
            </div>
        </div>
    </div>
</template>
