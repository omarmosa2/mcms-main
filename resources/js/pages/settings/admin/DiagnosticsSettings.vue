<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Activity, CheckCircle2, Database, Monitor, XCircle } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { diagnostics as diagnosticsUrl } from '@/routes/admin-settings';

defineProps<{
    diagnostics: {
        database: {
            status: string;
            name: string;
            size: string;
            table_count: number;
        };
        application: {
            version: string;
            php_version: string;
            laravel_version: string;
            user_count: number;
            doctor_count: number;
            patient_count: number;
            employee_count: number;
        };
        performance: {
            memory_usage: string;
            memory_peak: string;
        };
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'تشخيص النظام',
                href: diagnosticsUrl(),
            },
        ],
    },
});
</script>

<template>
    <Head title="تشخيص النظام" />

    <div class="space-y-6">
        <div class="glass-panel-soft p-5">
            <Heading
                tone="typographic"
                variant="small"
                title="تشخيص النظام"
                description="معلومات مفصلة عن حالة النظام وقاعدة البيانات والأداء."
            />
        </div>

        <div class="glass-panel-soft space-y-5 p-5">
            <div class="flex items-center gap-2">
                <Database class="h-5 w-5 text-primary" />
                <h3 class="text-sm font-semibold text-foreground">معلومات قاعدة البيانات</h3>
                <span
                    :class="[
                        'mr-auto flex items-center gap-1 rounded-full px-2 py-0.5 text-xs',
                        diagnostics.database.status === 'connected'
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                            : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                    ]"
                >
                    <CheckCircle2 v-if="diagnostics.database.status === 'connected'" class="h-3 w-3" />
                    <XCircle v-else class="h-3 w-3" />
                    {{ diagnostics.database.status === 'connected' ? 'متصل' : 'خطأ' }}
                </span>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-border/50 bg-muted/20 p-3">
                    <p class="text-xs text-muted-foreground">اسم قاعدة البيانات</p>
                    <p class="mt-1 text-sm font-semibold">{{ diagnostics.database.name }}</p>
                </div>
                <div class="rounded-xl border border-border/50 bg-muted/20 p-3">
                    <p class="text-xs text-muted-foreground">حجم قاعدة البيانات</p>
                    <p class="mt-1 text-sm font-semibold">{{ diagnostics.database.size }}</p>
                </div>
                <div class="rounded-xl border border-border/50 bg-muted/20 p-3">
                    <p class="text-xs text-muted-foreground">عدد الجداول</p>
                    <p class="mt-1 text-sm font-semibold">{{ diagnostics.database.table_count }}</p>
                </div>
                <div class="rounded-xl border border-border/50 bg-muted/20 p-3">
                    <p class="text-xs text-muted-foreground">حالة الاتصال</p>
                    <p class="mt-1 text-sm font-semibold text-emerald-600">نشط</p>
                </div>
            </div>
        </div>

        <div class="glass-panel-soft space-y-5 p-5">
            <div class="flex items-center gap-2">
                <Monitor class="h-5 w-5 text-primary" />
                <h3 class="text-sm font-semibold text-foreground">معلومات التطبيق</h3>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-border/50 bg-muted/20 p-3">
                    <p class="text-xs text-muted-foreground">إصدار التطبيق</p>
                    <p class="mt-1 text-sm font-semibold">{{ diagnostics.application.version }}</p>
                </div>
                <div class="rounded-xl border border-border/50 bg-muted/20 p-3">
                    <p class="text-xs text-muted-foreground">إصدار PHP</p>
                    <p class="mt-1 text-sm font-semibold">{{ diagnostics.application.php_version }}</p>
                </div>
                <div class="rounded-xl border border-border/50 bg-muted/20 p-3">
                    <p class="text-xs text-muted-foreground">إصدار Laravel</p>
                    <p class="mt-1 text-sm font-semibold">{{ diagnostics.application.laravel_version }}</p>
                </div>
                <div class="rounded-xl border border-border/50 bg-muted/20 p-3">
                    <p class="text-xs text-muted-foreground">عدد المستخدمين</p>
                    <p class="mt-1 text-sm font-semibold">{{ diagnostics.application.user_count }}</p>
                </div>
                <div class="rounded-xl border border-border/50 bg-muted/20 p-3">
                    <p class="text-xs text-muted-foreground">عدد الأطباء</p>
                    <p class="mt-1 text-sm font-semibold">{{ diagnostics.application.doctor_count }}</p>
                </div>
                <div class="rounded-xl border border-border/50 bg-muted/20 p-3">
                    <p class="text-xs text-muted-foreground">عدد المرضى</p>
                    <p class="mt-1 text-sm font-semibold">{{ diagnostics.application.patient_count }}</p>
                </div>
                <div class="rounded-xl border border-border/50 bg-muted/20 p-3">
                    <p class="text-xs text-muted-foreground">عدد الموظفين</p>
                    <p class="mt-1 text-sm font-semibold">{{ diagnostics.application.employee_count }}</p>
                </div>
            </div>
        </div>

        <div class="glass-panel-soft space-y-5 p-5">
            <div class="flex items-center gap-2">
                <Activity class="h-5 w-5 text-primary" />
                <h3 class="text-sm font-semibold text-foreground">معلومات الأداء</h3>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-border/50 bg-muted/20 p-3">
                    <p class="text-xs text-muted-foreground">استخدام الذاكرة</p>
                    <p class="mt-1 text-sm font-semibold">{{ diagnostics.performance.memory_usage }}</p>
                </div>
                <div class="rounded-xl border border-border/50 bg-muted/20 p-3">
                    <p class="text-xs text-muted-foreground">ذروة الذاكرة</p>
                    <p class="mt-1 text-sm font-semibold">{{ diagnostics.performance.memory_peak }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
