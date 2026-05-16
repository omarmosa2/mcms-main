<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Bell, Mail, MessageSquare } from 'lucide-vue-next';
import { ref } from 'vue';
import NotificationSettingsController from '@/actions/App/Http/Controllers/Settings/NotificationSettingsController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useToast } from '@/composables/useToast';
import { edit } from '@/routes/profile';

type NotificationType = {
    email: boolean;
    sms: boolean;
};

type Preferences = Record<string, NotificationType>;

type Props = {
    preferences: Preferences;
    defaultPreferences: Preferences;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'إعدادات الملف الشخصي', href: edit() },
            { title: 'الإشعارات', href: NotificationSettingsController.edit() },
        ],
    },
});

const toast = useToast();

const localPreferences = ref<Preferences>(JSON.parse(JSON.stringify(props.preferences)));

const notificationLabels: Record<string, { title: string; description: string }> = {
    appointment_reminder: {
        title: 'تذكيرات المواعيد',
        description: 'احصل على إشعارات قبل مواعيدك المجدولة.',
    },
    invoice_issued: {
        title: 'إصدار فاتورة',
        description: 'احصل على إشعار عند إنشاء فاتورة جديدة.',
    },
    prescription_ready: {
        title: 'وصفة جاهزة',
        description: 'احصل على إشعار عندما تكون وصفتك جاهزة للاستلام.',
    },
};

const savePreferences = (): void => {
    router.put(NotificationSettingsController.update.url(), {
        preferences: localPreferences.value,
    }, {
        onSuccess: () => {
            toast.success('تم تحديث تفضيلات الإشعارات بنجاح.');
        },
        onError: () => {
            toast.error('فشل في تحديث تفضيلات الإشعارات.');
        },
    });
};
</script>

<template>
    <Head title="إعدادات الإشعارات" />

    <div class="glass-panel-soft flex flex-col space-y-6 p-5">
        <Heading
            tone="typographic"
            variant="small"
            title="تفضيلات الإشعارات"
            description="اختر كيف ومتى تريد أن يتم إشعارك."
        />

        <div class="space-y-6">
            <div
                v-for="(pref, key) in localPreferences"
                :key="key"
                class="rounded-xl border border-border/60 bg-background/40 p-4"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <h4 class="text-sm font-semibold">
                            {{ notificationLabels[key]?.title ?? key }}
                        </h4>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ notificationLabels[key]?.description ?? '' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <Mail class="size-4 text-muted-foreground" />
                            <Checkbox
                                :id="`${key}-email`"
                                :checked="pref.email"
                                @update:checked="(value: boolean) => localPreferences[key].email = value"
                            />
                            <Label :for="`${key}-email`" class="text-xs">Email</Label>
                        </div>
                        <div class="flex items-center gap-2">
                            <MessageSquare class="size-4 text-muted-foreground" />
                            <Checkbox
                                :id="`${key}-sms`"
                                :checked="pref.sms"
                                @update:checked="(value: boolean) => localPreferences[key].sms = value"
                            />
                            <Label :for="`${key}-sms`" class="text-xs">SMS</Label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <Button variant="clay" @click="savePreferences">
                <Bell class="ms-2 size-4" />
                حفظ التفضيلات
            </Button>
        </div>
    </div>
</template>
