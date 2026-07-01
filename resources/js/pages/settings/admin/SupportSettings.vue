<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Info, Mail, MessageCircle, Phone, Users } from 'lucide-vue-next';
import { support as supportUrl } from '@/routes/admin-settings';
import Heading from '@/components/Heading.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'فريق الدعم',
                href: supportUrl(),
            },
        ],
    },
});
</script>

<template>
    <Head title="فريق الدعم الفني" />

    <div class="space-y-6">
        <div class="glass-panel-soft p-5">
            <span class="hero-kicker">معلومات فريق التطوير</span>
            <Heading
                tone="typographic"
                title="معلومات فريق التطوير"
                description="تواصل مع فريق التطوير للدعم الفني والاستفسارات"
            />
        </div>

        <div class="space-y-5">
            <div class="glass-panel-soft flex items-center gap-4 p-5">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/15">
                    <Users class="h-6 w-6 text-primary" />
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-foreground">اسم الفريق</p>
                    <p class="mt-1 text-lg font-bold text-foreground">AgorraCode</p>
                    <p class="text-sm text-muted-foreground">فريق تطوير تطبيقات إدارة العيادات</p>
                </div>
            </div>

            <div class="glass-panel-soft flex items-center gap-4 p-5">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/15">
                    <Phone class="h-6 w-6 text-primary" />
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-foreground">رقم التواصل</p>
                    <p class="mt-1 text-lg font-bold text-foreground" dir="ltr">00963940046997</p>
                    <p class="text-sm text-muted-foreground">متاح للدعم الفني من 9 صباحاً إلى 6 مساءً</p>
                </div>
                <a
                    href="https://wa.me/00963940046997"
                    target="_blank"
                    rel="noopener"
                >
                    <button class="rounded-full bg-[#25D366] px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#20BD5A]">
                        تواصل عبر واتساب
                    </button>
                </a>
            </div>

            <div class="glass-panel-soft flex items-center gap-4 p-5">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/15">
                    <Mail class="h-6 w-6 text-primary" />
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-foreground">البريد الإلكتروني</p>
                    <p class="mt-1 text-lg font-bold text-foreground">AgorraCode@gmail.com</p>
                    <p class="text-sm text-muted-foreground">للاستفسارات والدعم الفني</p>
                </div>
                <a href="mailto:AgorraCode@gmail.com">
                    <button class="rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90">
                        إرسال إيميل
                    </button>
                </a>
            </div>

            <div class="rounded-2xl border border-primary/20 bg-accent p-5">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/15">
                        <Info class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-foreground">معلومات إضافية</p>
                        <ul class="mt-3 space-y-2 text-sm text-muted-foreground">
                            <li>• نقدم دعماً فنياً شاملاً لجميع مستخدمي التطبيق</li>
                            <li>• نستقبل اقتراحاتكم لتطوير وتحسين التطبيق</li>
                            <li>• نوفر تدريباً مجانياً على استخدام التطبيق</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
