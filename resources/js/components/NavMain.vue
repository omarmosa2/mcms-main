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
return [{ key: 'main', label: 'Navigation', description: '', items: props.items }];
}

    return [];
});

const visibleSections = computed<NavSection[]>(() => {
    return navSections.value.filter((section) => section.items.length > 0);
});
</script>

<template>
    <div class="flex flex-col">
        <template v-for="section in visibleSections" :key="section.key">
            <SidebarGroup class="px-0.5">
                <SidebarGroupLabel
                    class="text-[10px] font-semibold uppercase tracking-[0.1em] text-slate-400 mb-1 px-2.5 pt-2 pb-0.5 group-data-[collapsible=icon]:hidden"
                >
                    {{ section.label }}
                </SidebarGroupLabel>

                <SidebarGroupContent>
                    <SidebarMenu class="gap-[2px]">
                        <SidebarMenuItem v-for="item in section.items" :key="item.title">
                            <SidebarMenuButton
                                as-child
                                :is-active="isCurrentUrl(item.href, undefined, true)"
                                :tooltip="item.title"
                                class="h-9 rounded-lg px-2.5 text-[13px] font-normal transition-all duration-200 hover:bg-slate-50 hover:text-slate-800 data-[active=true]:bg-[#E7F7F2] data-[active=true]:text-[#0F6E56] data-[active=true]:font-medium data-[active=true]:shadow-[inset_3px_0_0_0_#0F9D7A]"
                            >
                                <Link :href="item.href">
                                    <component :is="item.icon" class="size-[16px] text-current group-data-[state=collapsed]:mx-auto" />
                                    <span>{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </template>

        <div v-if="visibleSections.length === 0" class="px-3 py-4">
            <div class="rounded-lg bg-sidebar-accent/40 p-3 text-xs text-sidebar-foreground/60 text-center">
                لا توجد وحدات متاحة
            </div>
        </div>
    </div>
</template>
