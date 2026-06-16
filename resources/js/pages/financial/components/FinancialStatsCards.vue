<script setup lang="ts">
import {
    CalendarCheck,
    CircleDollarSign,
    FileText,
    TrendingDown,
    TrendingUp,
} from 'lucide-vue-next';

defineProps<{
    summaries: {
        total_cost: number;
        total_paid: number;
        total_remaining: number;
        paid_count: number;
        unpaid_count: number;
        partially_paid_count: number;
    };
}>();

const formatMoney = (value: number): string =>
    new Intl.NumberFormat('ar-SY', { maximumFractionDigits: 0 }).format(value);
</script>

<template>
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    إجمالي التكاليف
                </p>
                <TrendingUp class="size-5 text-foreground" />
            </div>
            <p class="mt-4 text-2xl font-black text-foreground tabular-nums">
                {{ formatMoney(summaries.total_cost) }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    المبالغ المدفوعة
                </p>
                <CircleDollarSign class="size-5 text-primary" />
            </div>
            <p class="mt-4 text-2xl font-black text-primary tabular-nums">
                {{ formatMoney(summaries.total_paid) }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    المبالغ المتبقية
                </p>
                <TrendingDown class="size-5 text-amber-600" />
            </div>
            <p class="mt-4 text-2xl font-black text-amber-700 tabular-nums">
                {{ formatMoney(summaries.total_remaining) }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    مواعيد مدفوعة
                </p>
                <CalendarCheck class="size-5 text-primary" />
            </div>
            <p class="mt-4 text-3xl font-black text-primary tabular-nums">
                {{ summaries.paid_count }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    غير مدفوعة
                </p>
                <FileText class="size-5 text-muted-foreground" />
            </div>
            <p class="mt-4 text-3xl font-black text-foreground tabular-nums">
                {{ summaries.unpaid_count }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    مدفوعة جزئيا
                </p>
                <FileText class="size-5 text-amber-600" />
            </div>
            <p class="mt-4 text-3xl font-black text-amber-700 tabular-nums">
                {{ summaries.partially_paid_count }}
            </p>
        </article>
    </section>
</template>
