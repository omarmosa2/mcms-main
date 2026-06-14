<script setup lang="ts">
import {
    CalendarCheck,
    CheckCircle2,
    Clock,
    UserCheck,
    XCircle,
} from 'lucide-vue-next';

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
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <article
            class="group flex items-center justify-between gap-3 rounded-2xl border border-primary/15 bg-primary/5 px-4 py-3.5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
        >
            <div class="min-w-0">
                <p class="text-[0.72rem] font-medium text-muted-foreground">
                    اليوم
                </p>
                <p
                    class="mt-0.5 text-2xl font-bold text-foreground tabular-nums"
                >
                    {{ todaySummary.total }}
                </p>
            </div>
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-transform duration-200 group-hover:scale-105"
            >
                <CalendarCheck class="size-5" />
            </div>
        </article>

        <article
            class="group flex items-center justify-between gap-3 rounded-2xl border border-info/15 bg-info/5 px-4 py-3.5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
        >
            <div class="min-w-0">
                <p class="text-[0.72rem] font-medium text-muted-foreground">
                    مجدول
                </p>
                <p
                    class="mt-0.5 text-2xl font-bold text-foreground tabular-nums"
                >
                    {{ todaySummary.scheduled }}
                </p>
            </div>
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-info/10 text-info transition-transform duration-200 group-hover:scale-105"
            >
                <Clock class="size-5" />
            </div>
        </article>

        <article
            class="group flex items-center justify-between gap-3 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3.5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-sky-800/40 dark:bg-sky-950/30"
        >
            <div class="min-w-0">
                <p class="text-[0.72rem] font-medium text-muted-foreground">
                    حاضر
                </p>
                <p
                    class="mt-0.5 text-2xl font-bold text-foreground tabular-nums"
                >
                    {{ todaySummary.arrived }}
                </p>
            </div>
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-600 dark:bg-sky-900/40 dark:text-sky-400 transition-transform duration-200 group-hover:scale-105"
            >
                <UserCheck class="size-5" />
            </div>
        </article>

        <article
            class="group flex items-center justify-between gap-3 rounded-2xl border border-success/15 bg-success/5 px-4 py-3.5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
        >
            <div class="min-w-0">
                <p class="text-[0.72rem] font-medium text-muted-foreground">
                    مكتمل
                </p>
                <p
                    class="mt-0.5 text-2xl font-bold text-foreground tabular-nums"
                >
                    {{ todaySummary.completed }}
                </p>
            </div>
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success transition-transform duration-200 group-hover:scale-105"
            >
                <CheckCircle2 class="size-5" />
            </div>
        </article>

        <article
            class="group flex items-center justify-between gap-3 rounded-2xl border border-destructive/15 bg-destructive/5 px-4 py-3.5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
            :class="{ 'opacity-50': todaySummary.canceled === 0 }"
        >
            <div class="min-w-0">
                <p class="text-[0.72rem] font-medium text-muted-foreground">
                    ملغي
                </p>
                <p
                    class="mt-0.5 text-2xl font-bold text-foreground tabular-nums"
                >
                    {{ todaySummary.canceled }}
                </p>
            </div>
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-destructive/10 text-destructive transition-transform duration-200 group-hover:scale-105"
            >
                <XCircle class="size-5" />
            </div>
        </article>
    </section>
</template>
