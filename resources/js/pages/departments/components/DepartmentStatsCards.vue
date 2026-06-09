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
    <section class="grid gap-4 md:grid-cols-4">
        <article class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card">
            <div class="flex items-center justify-between">
                <p class="text-sm font-bold text-muted-foreground">إجمالي العيادات</p>
                <Building2 class="size-5 text-primary" />
            </div>
            <p class="mt-4 text-3xl font-black text-foreground">{{ totalDepartments }}</p>
        </article>

        <article class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card">
            <div class="flex items-center justify-between">
                <p class="text-sm font-bold text-muted-foreground">عيادات نشطة</p>
                <CheckCircle2 class="size-5 text-primary" />
            </div>
            <p class="mt-4 text-3xl font-black text-primary">{{ activeCount }}</p>
        </article>

        <article class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card">
            <div class="flex items-center justify-between">
                <p class="text-sm font-bold text-muted-foreground">غير نشطة</p>
                <XCircle class="size-5 text-destructive" />
            </div>
            <p class="mt-4 text-3xl font-black text-destructive">{{ inactiveCount }}</p>
        </article>

        <article class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card">
            <div class="flex items-center justify-between">
                <p class="text-sm font-bold text-muted-foreground">الأطباء</p>
                <Users class="size-5 text-primary" />
            </div>
            <p class="mt-4 text-3xl font-black text-primary">{{ totalDoctors }}</p>
        </article>
    </section>
</template>
