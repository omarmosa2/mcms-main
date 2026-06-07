<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Database, Download, KeyRound, LogIn, ShieldCheck, Trash2, Upload } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { security as securityUrl } from '@/routes/admin-settings';

defineProps<{
    activityLogs: Array<{
        id: number;
        user_name: string;
        action: string;
        description: string;
        created_at: string;
    }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'الأمان والنسخ الاحتياطي',
                href: securityUrl(),
            },
        ],
    },
});

const actionIcons: Record<string, typeof LogIn> = {
    login: LogIn,
    update: KeyRound,
    delete: Trash2,
    backup: Database,
};

function getActionIcon(action: string) {
    return actionIcons[action] ?? ShieldCheck;
}
</script>

<template>
    <Head title="الأمان والنسخ الاحتياطي" />

    <div class="space-y-6">
        <div class="glass-panel-soft p-5">
            <Heading
                tone="typographic"
                variant="small"
                title="الأمان والنسخ الاحتياطي"
                description="إدارة إعدادات الأمان والنسخ الاحتياطي وسجل النشاطات."
            />
        </div>

        <div class="glass-panel-soft space-y-5 p-5">
            <h3 class="text-sm font-semibold text-foreground">إعدادات الأمان</h3>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label>مهلة الجلسة (دقيقة)</Label>
                    <Input type="number" value="30" min="5" max="480" />
                </div>
                <div class="space-y-2">
                    <Label>الحد الأقصى لمحاولات تسجيل الدخول الفاشلة</Label>
                    <Input type="number" value="5" min="3" max="20" />
                </div>
            </div>

            <div class="space-y-2">
                <Label>تغيير كلمة مرور المدير</Label>
                <div class="flex gap-2">
                    <Input type="password" placeholder="كلمة المرور الحالية" />
                    <Input type="password" placeholder="كلمة المرور الجديدة" />
                    <Button variant="outline">تغيير</Button>
                </div>
            </div>
        </div>

        <div class="glass-panel-soft space-y-5 p-5">
            <h3 class="text-sm font-semibold text-foreground">النسخ الاحتياطي</h3>

            <div class="grid gap-3 sm:grid-cols-3">
                <Button variant="outline" class="flex items-center gap-2">
                    <Database class="h-4 w-4" />
                    إنشاء نسخة احتياطية
                </Button>
                <Button variant="outline" class="flex items-center gap-2">
                    <Download class="h-4 w-4" />
                    تحميل النسخة
                </Button>
                <Button variant="outline" class="flex items-center gap-2">
                    <Upload class="h-4 w-4" />
                    استعادة نسخة
                </Button>
            </div>
        </div>

        <div class="glass-panel-soft space-y-5 p-5">
            <h3 class="text-sm font-semibold text-foreground">سجل النشاطات</h3>

            <div v-if="activityLogs.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                لا توجد نشاطات مسجلة حالياً.
            </div>

            <div v-else class="space-y-2">
                <div
                    v-for="log in activityLogs"
                    :key="log.id"
                    class="flex items-center gap-3 rounded-xl border border-border/50 bg-muted/20 p-3"
                >
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10">
                        <component :is="getActionIcon(log.action)" class="h-4 w-4 text-primary" />
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium">{{ log.description }}</p>
                        <p class="text-xs text-muted-foreground">{{ log.user_name }} - {{ log.created_at }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
