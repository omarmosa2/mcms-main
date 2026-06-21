<script setup lang="ts">
import {
    Building2,
    CalendarOff,
    CheckCircle2,
    Stethoscope,
    XCircle,
} from 'lucide-vue-next';
import type { DoctorProfileStats } from './types';

defineProps<{
    stats: DoctorProfileStats;
}>();
</script>

<template>
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    إجمالي الأطباء
                </p>
                <Stethoscope class="size-5 text-primary" />
            </div>
            <p class="mt-4 text-3xl font-black text-foreground tabular-nums">
                {{ stats.total_doctors }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    أطباء نشطون
                </p>
                <CheckCircle2 class="size-5 text-primary" />
            </div>
            <p class="mt-4 text-3xl font-black text-primary tabular-nums">
                {{ stats.active_doctors }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">في إجازة</p>
                <CalendarOff class="size-5 text-amber-600" />
            </div>
            <p class="mt-4 text-3xl font-black text-amber-700 tabular-nums">
                {{ stats.on_leave_doctors }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">غير نشطين</p>
                <XCircle class="size-5 text-destructive" />
            </div>
            <p class="mt-4 text-3xl font-black text-destructive tabular-nums">
                {{ stats.inactive_doctors }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card sm:col-span-2 xl:col-span-1"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    عيادات لديها أطباء
                </p>
                <Building2 class="size-5 text-sky-600" />
            </div>
            <p class="mt-4 text-3xl font-black text-sky-700 tabular-nums">
                {{ stats.clinics_with_doctors }}
            </p>
        </article>
    </section>
</template>
