<script setup lang="ts">
import {
    Calendar,
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
            class="flex items-center justify-between gap-3 rounded-2xl border border-border bg-card px-4 py-3 shadow-sm"
        >
            <div>
                <p class="text-xs text-muted-foreground">اليوم</p>
                <p class="text-xl font-bold text-foreground tabular-nums">
                    {{ todaySummary.total }}
                </p>
            </div>
            <div
                class="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary"
            >
                <Calendar class="size-5" />
            </div>
        </article>

        <article
            class="flex items-center justify-between gap-3 rounded-2xl border border-info/20 bg-info/5 px-4 py-3 shadow-sm"
        >
            <div>
                <p class="text-xs text-muted-foreground">مجدول</p>
                <p class="text-xl font-bold text-foreground tabular-nums">
                    {{ todaySummary.scheduled }}
                </p>
            </div>
            <div
                class="flex size-11 items-center justify-center rounded-xl bg-info/10 text-info"
            >
                <Clock class="size-5" />
            </div>
        </article>

        <article
            class="flex items-center justify-between gap-3 rounded-2xl border border-success/20 bg-success/5 px-4 py-3 shadow-sm"
        >
            <div>
                <p class="text-xs text-muted-foreground">حاضر</p>
                <p class="text-xl font-bold text-foreground tabular-nums">
                    {{ todaySummary.arrived }}
                </p>
            </div>
            <div
                class="flex size-11 items-center justify-center rounded-xl bg-success/10 text-success"
            >
                <UserCheck class="size-5" />
            </div>
        </article>

        <article
            class="flex items-center justify-between gap-3 rounded-2xl border border-success/20 bg-success/5 px-4 py-3 shadow-sm"
        >
            <div>
                <p class="text-xs text-muted-foreground">مكتمل</p>
                <p class="text-xl font-bold text-foreground tabular-nums">
                    {{ todaySummary.completed }}
                </p>
            </div>
            <div
                class="flex size-11 items-center justify-center rounded-xl bg-success/10 text-success"
            >
                <CheckCircle2 class="size-5" />
            </div>
        </article>

        <article
            class="flex items-center justify-between gap-3 rounded-2xl border border-destructive/20 bg-destructive/5 px-4 py-3 shadow-sm"
            :class="{ 'opacity-60': todaySummary.canceled === 0 }"
        >
            <div>
                <p class="text-xs text-muted-foreground">ملغي</p>
                <p class="text-xl font-bold text-foreground tabular-nums">
                    {{ todaySummary.canceled }}
                </p>
            </div>
            <div
                class="flex size-11 items-center justify-center rounded-xl bg-destructive/10 text-destructive"
            >
                <XCircle class="size-5" />
            </div>
        </article>
    </section>
</template>
