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
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <article
            class="rounded-2xl border border-border bg-card/95 p-4 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    مواعيد اليوم
                </p>
                <span class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <CalendarCheck class="size-4.5" />
                </span>
            </div>
            <p class="mt-3 text-2xl font-black text-foreground tabular-nums">
                {{ todaySummary.total }}
            </p>
        </article>

        <article
            class="rounded-2xl border border-info/20 bg-info/5 p-4 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">مجدولة</p>
                <span class="flex size-9 items-center justify-center rounded-xl bg-info/10 text-info">
                    <Clock class="size-4.5" />
                </span>
            </div>
            <p class="mt-3 text-2xl font-black text-info tabular-nums">
                {{ todaySummary.scheduled }}
            </p>
        </article>

        <article
            class="rounded-2xl border border-primary/20 bg-primary/5 p-4 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">حضروا</p>
                <span class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <UserCheck class="size-4.5" />
                </span>
            </div>
            <p class="mt-3 text-2xl font-black text-primary tabular-nums">
                {{ todaySummary.arrived }}
            </p>
        </article>

        <article
            class="rounded-2xl border border-success/20 bg-success/5 p-4 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">مكتملة</p>
                <span class="flex size-9 items-center justify-center rounded-xl bg-success/10 text-success">
                    <CheckCircle2 class="size-4.5" />
                </span>
            </div>
            <p class="mt-3 text-2xl font-black text-success tabular-nums">
                {{ todaySummary.completed }}
            </p>
        </article>

        <article
            class="rounded-2xl border border-destructive/20 bg-destructive/5 p-4 shadow-card"
            :class="{ 'opacity-50': todaySummary.canceled === 0 }"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">ملغية</p>
                <span class="flex size-9 items-center justify-center rounded-xl bg-destructive/10 text-destructive">
                    <XCircle class="size-4.5" />
                </span>
            </div>
            <p class="mt-3 text-2xl font-black text-destructive tabular-nums">
                {{ todaySummary.canceled }}
            </p>
        </article>
    </section>
</template>
