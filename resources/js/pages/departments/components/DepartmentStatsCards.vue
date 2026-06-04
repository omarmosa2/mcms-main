<script setup lang="ts">
import { Building2, CheckCircle2, Users, XCircle } from 'lucide-vue-next';

defineProps<{
    totalDepartments: number;
    activeCount: number;
    inactiveCount: number;
    totalDoctors: number;
}>();
</script>

<template>
    <section class="grid gap-3 md:grid-cols-4">
        <article class="rounded-xl border border-border/70 bg-card p-4">
            <div class="flex items-center justify-between">
                <p class="section-label">إجمالي الأقسام</p>
                <Building2 class="size-4 text-muted-foreground" />
            </div>
            <p class="card-value text-2xl">{{ totalDepartments }}</p>
        </article>
        <article class="rounded-xl border border-border/70 bg-card p-4">
            <div class="flex items-center justify-between">
                <p class="section-label">نشط</p>
                <CheckCircle2 class="size-4 text-success-500" />
            </div>
            <p class="card-value text-2xl text-success-600 dark:text-success-400">{{ activeCount }}</p>
        </article>
        <article class="rounded-xl border border-border/70 bg-card p-4">
            <div class="flex items-center justify-between">
                <p class="section-label">غير نشط</p>
                <XCircle class="size-4 text-destructive" />
            </div>
            <p class="card-value text-2xl text-destructive">{{ inactiveCount }}</p>
        </article>
        <article class="rounded-xl border border-border/70 bg-card p-4">
            <div class="flex items-center justify-between">
                <p class="section-label">الأطباء</p>
                <Users class="size-4 text-info-500" />
            </div>
            <p class="card-value text-2xl text-info-600 dark:text-info-400">{{ totalDoctors }}</p>
        </article>
    </section>
</template>