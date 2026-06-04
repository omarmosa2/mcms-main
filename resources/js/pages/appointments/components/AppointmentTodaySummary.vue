<script setup lang="ts">
import { Calendar } from 'lucide-vue-next';

const props = defineProps<{
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
    <div class="rounded-xl border border-border/70 bg-card px-4 py-3">
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
    </div>
</template>
