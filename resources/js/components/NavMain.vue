<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem, NavSection } from '@/types';

const props = withDefaults(
    defineProps<{
        items?: NavItem[];
        sections?: NavSection[];
    }>(),
    {
        items: () => [],
        sections: () => [],
    },
);

const { isMobile, state } = useSidebar();
const { isCurrentUrl } = useCurrentUrl();

const navSections = computed<NavSection[]>(() => {
    if (props.sections.length > 0) {
        return props.sections;
    }

    if (props.items.length > 0) {
        return [
            {
                key: 'main',
                label: 'Navigation',
                description: '',
                items: props.items,
            },
        ];
    }

    return [];
});

const visibleSections = computed<NavSection[]>(() =>
    navSections.value.filter((section) => section.items.length > 0),
);

const isActive = (href: NonNullable<NavItem['href']>): boolean =>
    isCurrentUrl(href, undefined, true);

const iconClasses = (href: NonNullable<NavItem['href']>): string[] => {
    const active = isActive(href);

    return [
        'nav-icon size-5 shrink-0 transition-colors duration-200 ease-linear',
        active
            ? 'text-primary group-data-[collapsible=icon]:text-primary-foreground'
            : 'text-sidebar-foreground/70 group-data-[collapsible=icon]:text-sidebar-foreground/90',
    ];
};
</script>

<template>
    <div class="flex flex-1 flex-col gap-2">
        <template v-for="section in visibleSections" :key="section.key">
            <SidebarGroup class="px-0 py-0.5">
                <SidebarGroupLabel
                    class="mb-1 px-3 pt-1.5 pb-1 text-[11px] font-bold tracking-normal text-sidebar-foreground/55 transition-[opacity,height,margin] duration-200 ease-linear group-data-[collapsible=icon]:m-0 group-data-[collapsible=icon]:h-0 group-data-[collapsible=icon]:overflow-hidden group-data-[collapsible=icon]:opacity-0"
                >
                    {{ section.label }}
                </SidebarGroupLabel>

                <SidebarGroupContent>
                    <SidebarMenu class="gap-1">
                        <SidebarMenuItem
                            v-for="item in section.items"
                            :key="item.title"
                        >
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Link
                                        :href="item.href"
                                        :data-active="isActive(item.href)"
                                        class="nav-link group/link relative flex h-11 w-full items-center gap-3 rounded-xl px-3 text-[13px] font-medium text-sidebar-foreground/80 transition-all duration-200 ease-linear outline-none group-data-[collapsible=icon]:mx-auto group-data-[collapsible=icon]:h-11 group-data-[collapsible=icon]:w-11 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:gap-0 group-data-[collapsible=icon]:rounded-2xl group-data-[collapsible=icon]:p-0 hover:bg-sidebar-accent/70 hover:text-sidebar-accent-foreground group-data-[collapsible=icon]:hover:bg-sidebar-accent focus-visible:ring-2 focus-visible:ring-sidebar-ring data-[active=true]:bg-sidebar-accent data-[active=true]:font-bold data-[active=true]:text-sidebar-accent-foreground data-[active=true]:shadow-[0_10px_24px_-18px_rgb(14_165_233_/_0.45)] group-data-[collapsible=icon]:data-[active=true]:rounded-full group-data-[collapsible=icon]:data-[active=true]:bg-primary group-data-[collapsible=icon]:data-[active=true]:text-primary-foreground group-data-[collapsible=icon]:data-[active=true]:shadow-[0_10px_24px_-14px_rgb(14_165_233_/_0.6)]"
                                    >
                                        <component
                                            :is="item.icon"
                                            :class="iconClasses(item.href)"
                                        />
                                        <span
                                            class="nav-text truncate transition-opacity duration-200 ease-linear group-data-[collapsible=icon]:pointer-events-none group-data-[collapsible=icon]:absolute group-data-[collapsible=icon]:opacity-0"
                                            >{{ item.title }}</span
                                        >
                                    </Link>
                                </TooltipTrigger>
                                <TooltipContent
                                    side="left"
                                    align="center"
                                    :side-offset="8"
                                    :hidden="state !== 'collapsed' || isMobile"
                                >
                                    {{ item.title }}
                                </TooltipContent>
                            </Tooltip>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </template>

        <div v-if="visibleSections.length === 0" class="px-3 py-4">
            <div
                class="rounded-2xl bg-sidebar-accent/30 p-3 text-center text-xs text-sidebar-foreground/70"
            >
                لا توجد وحدات متاحة
            </div>
        </div>
    </div>
</template>
