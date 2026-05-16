<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, Home, RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';
import { dashboard } from '@/routes';

const { status, message } = defineProps<{
    status?: number;
    message?: string;
}>();

const globalWindow = typeof window !== 'undefined' ? window : null;

const title = status === 503
    ? 'الخدمة غير متاحة'
    : status === 500
        ? 'خطأ في الخادم'
        : status === 404
            ? 'الصفحة غير موجودة'
            : status === 403
                ? 'محظور'
                : status === 401
                    ? 'غير مصرح'
                    : status === 429
                        ? 'طلبات كثيرة جداً'
                        : 'خطأ';

const description = status === 503
    ? 'الخدمة غير متاحة مؤقتاً. يرجى المحاولة مرة أخرى لاحقاً.'
    : status === 500
        ? 'حدث خطأ داخلي في الخادم. نحن نعمل على إصلاحه.'
        : status === 404
            ? 'الصفحة التي تبحث عنها غير موجودة.'
            : status === 403
                ? 'ليس لديك صلاحية للوصول إلى هذا المورد.'
                : status === 401
                    ? 'يجب تسجيل الدخول للوصول إلى هذه الصفحة.'
                    : status === 429
                        ? 'لقد أجريت طلبات كثيرة جداً. يرجى الإبطاء.'
                        : message ?? 'حدث خطأ غير متوقع.';
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-surface-secondary to-info-50 px-4 dark:from-slate-950 dark:to-slate-900" dir="rtl">
        <div class="glass-panel-lux max-w-md w-full p-8 text-center">
            <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-300">
                <AlertTriangle class="size-10" />
            </div>

            <h1 class="text-6xl font-bold tracking-tight text-foreground">
                {{ status ?? 'خطأ' }}
            </h1>
            <h2 class="mt-4 text-xl font-semibold">{{ title }}</h2>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                {{ description }}
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
                    @click="globalWindow?.location.reload()"
                >
                    <RefreshCw class="size-4" />
                    إعادة المحاولة
                </button>
            </div>
        </div>
    </div>
</template>
