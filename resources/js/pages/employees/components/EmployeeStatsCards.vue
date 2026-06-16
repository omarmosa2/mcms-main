<script setup lang="ts">
import { UserCheck, UserX, UsersRound, WalletCards } from 'lucide-vue-next';

defineProps<{
    stats: {
        total: number;
        active: number;
        inactive: number;
        monthly_salaries: number;
    };
}>();

const formatMoney = (value: number): string =>
    new Intl.NumberFormat('ar-SY', { maximumFractionDigits: 0 }).format(value);
</script>

<template>
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    إجمالي الموظفين
                </p>
                <UsersRound class="size-5 text-primary" />
            </div>
            <p class="mt-4 text-3xl font-black text-foreground tabular-nums">
                {{ stats.total }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">النشطون</p>
                <UserCheck class="size-5 text-primary" />
            </div>
            <p class="mt-4 text-3xl font-black text-primary tabular-nums">
                {{ stats.active }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    غير النشطين
                </p>
                <UserX class="size-5 text-destructive" />
            </div>
            <p class="mt-4 text-3xl font-black text-destructive tabular-nums">
                {{ stats.inactive }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    رواتب شهرية أساسية
                </p>
                <WalletCards class="size-5 text-sky-600" />
            </div>
            <p class="mt-4 text-2xl font-black text-sky-700 tabular-nums">
                {{ formatMoney(stats.monthly_salaries) }}
            </p>
        </article>
    </section>
</template>
