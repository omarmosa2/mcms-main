<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FileLock2, Palette, Shield, UserRound } from 'lucide-vue-next';
import { computed } from 'vue';
import ComplianceController from '@/actions/App/Http/Controllers/Settings/ComplianceController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermissions } from '@/composables/usePermissions';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const { can } = usePermissions();

const allSidebarNavItems: Array<NavItem & { requiresReportsPermission?: boolean }> = [
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
        title: 'الامتثال',
        href: ComplianceController.index(),
        icon: FileLock2,
        requiresReportsPermission: true,
    },
];

const sidebarNavItems = computed<NavItem[]>(() =>
    allSidebarNavItems.filter((item) =>
        !item.requiresReportsPermission || can('reports.view') || can('reports.financial'),
    ),
);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="space-y-5 px-4 py-6 md:px-6" dir="rtl">
        <section class="glass-panel-lux p-6 md:p-7">
            <span class="hero-kicker">إعدادات الحساب</span>
            <Heading
                tone="typographic"
                title="مساحة الإعدادات"
                description="التحكم في الملف الشخصي والأمان والمظهر بواجهة إدارة أكثر نظاماً."
            />
        </section>

        <div class="grid gap-5 lg:grid-cols-[260px_1fr]">
            <aside class="w-full">
                <nav
                    class="glass-panel-soft flex flex-col gap-1.5 p-3"
                    aria-label="الإعدادات"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
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
