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
        <article class="rounded-[1.2rem] border border-[#E2ECF6] bg-white/95 p-5 shadow-card">
            <div class="flex items-center justify-between">
                <p class="text-sm font-bold text-[#6C7F95]">إجمالي العيادات</p>
                <Building2 class="size-5 text-[#0EA5E9]" />
            </div>
            <p class="mt-4 text-3xl font-black text-[#111827]">{{ totalDepartments }}</p>
        </article>

        <article class="rounded-[1.2rem] border border-[#E2ECF6] bg-white/95 p-5 shadow-card">
            <div class="flex items-center justify-between">
                <p class="text-sm font-bold text-[#6C7F95]">عيادات نشطة</p>
                <CheckCircle2 class="size-5 text-[#1D4ED8]" />
            </div>
            <p class="mt-4 text-3xl font-black text-[#1D4ED8]">{{ activeCount }}</p>
        </article>

        <article class="rounded-[1.2rem] border border-[#E2ECF6] bg-white/95 p-5 shadow-card">
            <div class="flex items-center justify-between">
                <p class="text-sm font-bold text-[#6C7F95]">غير نشطة</p>
                <XCircle class="size-5 text-[#EF4444]" />
            </div>
            <p class="mt-4 text-3xl font-black text-[#EF4444]">{{ inactiveCount }}</p>
        </article>

        <article class="rounded-[1.2rem] border border-[#E2ECF6] bg-white/95 p-5 shadow-card">
            <div class="flex items-center justify-between">
                <p class="text-sm font-bold text-[#6C7F95]">الأطباء</p>
                <Users class="size-5 text-[#0EA5E9]" />
            </div>
            <p class="mt-4 text-3xl font-black text-[#0284C7]">{{ totalDoctors }}</p>
        </article>
    </section>
</template>
