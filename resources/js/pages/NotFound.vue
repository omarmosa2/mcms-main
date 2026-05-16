<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { Home, Search, ArrowLeft } from 'lucide-vue-next';
import { ref } from 'vue';
import { dashboard } from '@/routes';

const { message } = defineProps<{
    message?: string;
}>();

const globalHistory = typeof window !== 'undefined' ? window.history : null;
</script>

<template>
    <Head title="الصفحة غير موجودة" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-surface-secondary to-info-50 px-4 dark:from-slate-950 dark:to-slate-900" dir="rtl">
        <div class="glass-panel-lux max-w-md w-full p-8 text-center">
            <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-info-500/10 text-info-600 dark:text-info-300">
                <Search class="size-10" />
            </div>

            <h1 class="text-6xl font-bold tracking-tight text-foreground">404</h1>
            <h2 class="mt-4 text-xl font-semibold">الصفحة غير موجودة</h2>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                {{ message ?? "الصفحة التي تبحث عنها غير موجودة أو تم نقلها." }}
            </p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <Link
                    :href="dashboard()"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90"
                >
                    <Home class="size-4" />
                    الانتقال إلى لوحة التحكم
                </Link>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-border/80 bg-background/80 px-4 py-2.5 text-sm font-semibold text-foreground transition hover:bg-background"
                    @click="globalHistory?.back()"
                >
                    <ArrowLeft class="size-4" />
                    العودة
                </button>
            </div>
        </div>
    </div>
</template>
