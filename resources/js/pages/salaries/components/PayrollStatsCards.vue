<script setup lang="ts">
import {
    Banknote,
    CircleDollarSign,
    Stethoscope,
    UsersRound,
    WalletCards,
} from 'lucide-vue-next';

defineProps<{
    summaries: {
        employee_due: number;
        employee_paid: number;
        employee_remaining: number;
        employee_count: number;
        employee_paid_count: number;
        employee_unpaid_count: number;
        doctor_due: number;
        doctor_paid: number;
        doctor_remaining: number;
        doctor_count: number;
        doctor_paid_count: number;
        doctor_unpaid_count: number;
        total_due: number;
        total_paid: number;
        total_remaining: number;
        total_count: number;
    };
}>();

const formatMoney = (value: number): string =>
    new Intl.NumberFormat('en-US-u-nu-latn', {
        maximumFractionDigits: 0,
    }).format(value);
</script>

<template>
    <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    إجمالي الرواتب
                </p>
                <WalletCards class="size-5 text-foreground" />
            </div>
            <p class="mt-4 text-2xl font-black text-foreground tabular-nums">
                {{ formatMoney(summaries.total_due) }}
            </p>
            <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                <span
                    class="rounded-lg bg-primary/10 px-2 py-1 font-bold text-primary"
                >
                    مدفوع {{ formatMoney(summaries.total_paid) }}
                </span>
                <span
                    class="rounded-lg bg-amber-500/10 px-2 py-1 font-bold text-amber-700"
                >
                    متبقي {{ formatMoney(summaries.total_remaining) }}
                </span>
            </div>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    رواتب الموظفين
                </p>
                <UsersRound class="size-5 text-primary" />
            </div>
            <p class="mt-4 text-2xl font-black text-foreground tabular-nums">
                {{ formatMoney(summaries.employee_due) }}
            </p>
            <div class="mt-4 flex flex-wrap gap-2 text-xs font-bold">
                <span
                    class="rounded-lg bg-muted px-2 py-1 text-muted-foreground"
                >
                    {{ summaries.employee_count }} سجل
                </span>
                <span class="rounded-lg bg-primary/10 px-2 py-1 text-primary">
                    {{ summaries.employee_paid_count }} مدفوع
                </span>
                <span
                    class="rounded-lg bg-amber-500/10 px-2 py-1 text-amber-700"
                >
                    {{ summaries.employee_unpaid_count }} بانتظار الدفع
                </span>
            </div>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    مستحقات الأطباء
                </p>
                <Stethoscope class="size-5 text-sky-600" />
            </div>
            <p class="mt-4 text-2xl font-black text-foreground tabular-nums">
                {{ formatMoney(summaries.doctor_due) }}
            </p>
            <div class="mt-4 flex flex-wrap gap-2 text-xs font-bold">
                <span
                    class="rounded-lg bg-muted px-2 py-1 text-muted-foreground"
                >
                    {{ summaries.doctor_count }} سجل
                </span>
                <span class="rounded-lg bg-primary/10 px-2 py-1 text-primary">
                    {{ summaries.doctor_paid_count }} مدفوع
                </span>
                <span
                    class="rounded-lg bg-amber-500/10 px-2 py-1 text-amber-700"
                >
                    {{ summaries.doctor_unpaid_count }} بانتظار الدفع
                </span>
            </div>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    المتبقي للصرف
                </p>
                <CircleDollarSign class="size-5 text-amber-600" />
            </div>
            <p class="mt-4 text-2xl font-black text-amber-700 tabular-nums">
                {{ formatMoney(summaries.total_remaining) }}
            </p>
            <div
                class="mt-4 flex items-center gap-2 text-xs font-bold text-muted-foreground"
            >
                <Banknote class="size-4" />
                <span>{{ summaries.total_count }} سجل رواتب لهذا الشهر</span>
            </div>
        </article>
    </section>
</template>
