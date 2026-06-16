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
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    مواعيد اليوم
                </p>
                <CalendarCheck class="size-5 text-primary" />
            </div>
            <p class="mt-4 text-3xl font-black text-foreground tabular-nums">
                {{ todaySummary.total }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">مجدولة</p>
                <Clock class="size-5 text-sky-600" />
            </div>
            <p class="mt-4 text-3xl font-black text-sky-700 tabular-nums">
                {{ todaySummary.scheduled }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">حضروا</p>
                <UserCheck class="size-5 text-primary" />
            </div>
            <p class="mt-4 text-3xl font-black text-primary tabular-nums">
                {{ todaySummary.arrived }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">مكتملة</p>
                <CheckCircle2 class="size-5 text-primary" />
            </div>
            <p class="mt-4 text-3xl font-black text-primary tabular-nums">
                {{ todaySummary.completed }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
            :class="{ 'opacity-50': todaySummary.canceled === 0 }"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">ملغية</p>
                <XCircle class="size-5 text-destructive" />
            </div>
            <p class="mt-4 text-3xl font-black text-destructive tabular-nums">
                {{ todaySummary.canceled }}
            </p>
        </article>
    </section>
</template>
