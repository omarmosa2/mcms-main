<script setup lang="ts">
import { Users, User, UserRound } from 'lucide-vue-next';

defineProps<{
    total: number;
    male: number;
    female: number;
}>();
</script>

<template>
    <section class="grid gap-4 md:grid-cols-3">
        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between">
                <p class="text-sm font-bold text-muted-foreground">
                    إجمالي المرضى
                </p>
                <Users class="size-5 text-primary" />
            </div>
            <p class="mt-4 text-3xl font-black text-foreground">{{ total }}</p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between">
                <p class="text-sm font-bold text-muted-foreground">
                    الذكور
                </p>
                <User class="size-5 text-[#0EA5E9]" />
            </div>
            <p class="mt-4 text-3xl font-black text-[#0EA5E9]">{{ male }}</p>
        </article>

        <article
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div class="flex items-center justify-between">
                <p class="text-sm font-bold text-muted-foreground">
                    الإناث
                </p>
                <UserRound class="size-5 text-[#EC4899]" />
            </div>
            <p class="mt-4 text-3xl font-black text-[#EC4899]">{{ female }}</p>
        </article>
    </section>
</template>
