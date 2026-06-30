<script setup lang="ts">
import { Stethoscope, UserCheck, UserPlus, UserX } from 'lucide-vue-next';

defineProps<{
    stats: {
        total: number;
        active: number;
        inactive: number;
        with_accounts: number;
    };
}>();
</script>

<template>
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
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
                {{ stats.total }}
            </p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-muted-foreground">
                    الأطباء النشطين
                </p>
                <UserCheck class="size-5 text-emerald-600" />
            </div>
            <p class="mt-4 text-3xl font-black text-emerald-600 tabular-nums">
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
                    لديهم حسابات
                </p>
                <UserPlus class="size-5 text-sky-600" />
            </div>
            <p class="mt-4 text-3xl font-black text-sky-600 tabular-nums">
                {{ stats.with_accounts }}
            </p>
        </article>
    </section>
</template>
