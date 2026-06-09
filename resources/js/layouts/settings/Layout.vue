<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    Building2,
    CalendarClock,
    Headset,
    MonitorSmartphone,
    Palette,
    Shield,
    ShieldCheck,
    UserRound,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermissions } from '@/composables/usePermissions';
import { toUrl } from '@/lib/utils';
import {
    clinic as clinicUrl,
    appointments as appointmentsUrl,
    financial as financialUrl,
    permissions as permissionsUrl,
    appearance as adminAppearanceUrl,
    security as adminSecurityUrl,
    diagnostics as diagnosticsUrl,
    support as supportUrl,
} from '@/routes/admin-settings';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const { can } = usePermissions();

type SettingsNavItem = NavItem & { adminOnly?: boolean };

const allSidebarNavItems: SettingsNavItem[] = [
    {
        title: 'الملف الشخصي',
        href: editProfile(),
        icon: UserRound,
    },
    {
        title: 'الأمان',
        href: editSecurity(),
        icon: Shield,
    },
    {
        title: 'المظهر',
        href: editAppearance(),
        icon: Palette,
    },
    {
        title: 'المجمع الطبي',
        href: clinicUrl(),
        icon: Building2,
        adminOnly: true,
    },
    
    {
        title: 'الأمان والنسخ',
        href: adminSecurityUrl(),
        icon: Activity,
        adminOnly: true,
    },
    {
        title: 'تشخيص النظام',
        href: diagnosticsUrl(),
        icon: Activity,
        adminOnly: true,
    },
    {
        title: 'فريق الدعم',
        href: supportUrl(),
        icon: Headset,
        adminOnly: true,
    },
];

const sidebarNavItems = computed<SettingsNavItem[]>(() =>
    allSidebarNavItems.filter((item) => {
        if (item.adminOnly && !can('settings.view')) {
            return false;
        }

        return true;
    }),
);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="space-y-5 px-4 py-6 md:px-6" dir="rtl">
        <section class="glass-panel-lux p-6 md:p-7">
            <span class="hero-kicker">إعدادات النظام</span>
            <Heading
                tone="typographic"
                title="مساحة الإعدادات"
                description="التحكم في جميع إعدادات النظام من مكان واحد."
            />
        </section>

        <div class="grid gap-5 lg:grid-cols-[260px_1fr]">
            <aside class="w-full">
                <nav
                    class="glass-panel-soft flex flex-col gap-1.5 p-3"
                    aria-label="الإعدادات"
                >
                    <template
                        v-for="(item, index) in sidebarNavItems"
                        :key="toUrl(item.href)"
                    >
                        <Separator
                            v-if="item.adminOnly && !sidebarNavItems[index - 1]?.adminOnly"
                            class="my-1"
                        />
                        <Button
                            variant="neumorphic"
                            :class="[
                                'h-10 w-full justify-start rounded-xl',
                                {
                                    'border-[var(--accent-teal)] bg-[var(--accent-teal-soft)] text-[var(--accent-teal-strong)] dark:bg-[var(--accent-teal-soft)]/30':
                                        isCurrentOrParentUrl(item.href),
                                },
                            ]"
                            as-child
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" class="h-4 w-4" />
                                {{ item.title }}
                            </Link>
                        </Button>
                    </template>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1">
                <section class="max-w-3xl space-y-5">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
