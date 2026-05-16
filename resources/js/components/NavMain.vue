<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
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

const visibleSections = computed<NavSection[]>(() => {
    return navSections.value.filter((section) => section.items.length > 0);
});
</script>

<template>
    <div class="flex flex-col gap-5">
        <template v-for="section in visibleSections" :key="section.key">
            <SidebarGroup class="px-1">
                <SidebarGroupLabel
                    class="text-xs font-bold uppercase tracking-[0.14em] text-sidebar-foreground/40 mb-2 px-2 group-data-[collapsible=icon]:hidden"
                >
                    {{ section.label }}
                </SidebarGroupLabel>

                <SidebarGroupContent>
                    <SidebarMenu class="gap-1">
                        <SidebarMenuItem v-for="item in section.items" :key="item.title">
                            <SidebarMenuButton
                                as-child
                                :is-active="isCurrentUrl(item.href, undefined, true)"
                                :tooltip="item.title"
                                class="h-10 rounded-xl px-3 text-sm font-semibold transition-all duration-200 border-s-[3px] border-transparent hover:bg-sidebar-accent hover:text-sidebar-foreground data-[active=true]:border-s-[3px] data-[active=true]:border-sidebar-primary data-[active=true]:bg-sidebar-primary/10 data-[active=true]:text-sidebar-primary data-[active=true]:shadow-none"
                            >
                                <Link :href="item.href">
                                    <component :is="item.icon" class="size-5 text-sidebar-foreground/70 group-data-[state=collapsed]:mx-auto" />
                                    <span>{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </template>

        <div
            v-if="visibleSections.length === 0"
            class="px-3 py-4"
        >
            <div class="rounded-2xl border border-sidebar-border/70 bg-sidebar-accent/40 p-4 text-xs leading-5 text-sidebar-foreground/70 text-center">
                لا توجد وحدات متاحة حالياً لدورك.
            </div>
        </div>
    </div>
</template>