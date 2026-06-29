<script setup lang="ts">
import {
    ArrowDownRight,
    ArrowUpRight,
    Banknote,
    Building2,
    CircleDollarSign,
    HandCoins,
    Receipt,
    Stethoscope,
    TrendingDown,
    TrendingUp,
    UsersRound,
    Wallet,
} from 'lucide-vue-next';
import { useMoneyFormatter } from '@/lib/money';

defineProps<{
    summaries: {
        total_income: number;
        total_collected: number;
        total_remaining: number;
        doctor_due: number;
        doctor_paid: number;
        employee_salaries: number;
        total_expenses: number;
        net_profit: number;
        net_liquidity: number;
        total_outflow: number;
        paid_count: number;
        unpaid_count: number;
        partially_paid_count: number;
    };
}>();

const { formatMoney } = useMoneyFormatter();
</script>

<template>
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3">
        <article
            class="rounded-[1.2rem] border border-emerald-500/20 bg-emerald-500/5 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    إجمالي الدخل
                </p>
                <TrendingUp class="size-5 text-emerald-600" />
            </div>
            <p class="mt-4 text-2xl font-black text-emerald-700 tabular-nums">
                {{ formatMoney(summaries.total_income) }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-primary/20 bg-primary/5 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    إجمالي المقبوض
                </p>
                <CircleDollarSign class="size-5 text-primary" />
            </div>
            <p class="mt-4 text-2xl font-black text-primary tabular-nums">
                {{ formatMoney(summaries.total_collected) }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-amber-500/20 bg-amber-500/5 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    إجمالي المتبقي
                </p>
                <TrendingDown class="size-5 text-amber-600" />
            </div>
            <p class="mt-4 text-2xl font-black text-amber-700 tabular-nums">
                {{ formatMoney(summaries.total_remaining) }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-violet-500/20 bg-violet-500/5 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    مستحقات الأطباء
                </p>
                <Stethoscope class="size-5 text-violet-600" />
            </div>
            <p class="mt-4 text-2xl font-black text-violet-700 tabular-nums">
                {{ formatMoney(summaries.doctor_due) }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                مدفوع: {{ formatMoney(summaries.doctor_paid) }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-sky-500/20 bg-sky-500/5 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    رواتب الموظفين
                </p>
                <UsersRound class="size-5 text-sky-600" />
            </div>
            <p class="mt-4 text-2xl font-black text-sky-700 tabular-nums">
                {{ formatMoney(summaries.employee_salaries) }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-rose-500/20 bg-rose-500/5 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    إجمالي المصروفات
                </p>
                <Receipt class="size-5 text-rose-600" />
            </div>
            <p class="mt-4 text-2xl font-black text-rose-700 tabular-nums">
                {{ formatMoney(summaries.total_expenses) }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    إجمالي الخرج
                </p>
                <ArrowDownRight class="size-5 text-rose-500" />
            </div>
            <p class="mt-4 text-2xl font-black text-rose-600 tabular-nums">
                {{ formatMoney(summaries.total_outflow) }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    صافي الربح
                </p>
                <Banknote class="size-5 text-foreground" />
            </div>
            <p
                class="mt-4 text-2xl font-black tabular-nums"
                :class="
                    summaries.net_profit >= 0
                        ? 'text-emerald-700'
                        : 'text-rose-700'
                "
            >
                {{ formatMoney(summaries.net_profit) }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    صافي السيولة
                </p>
                <Wallet class="size-5 text-foreground" />
            </div>
            <p
                class="mt-4 text-2xl font-black tabular-nums"
                :class="
                    summaries.net_liquidity >= 0
                        ? 'text-emerald-700'
                        : 'text-rose-700'
                "
            >
                {{ formatMoney(summaries.net_liquidity) }}
            </p>
        </article>
    </section>
</template>
