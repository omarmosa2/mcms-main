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
    <div class="flex flex-col gap-1">
        <template v-for="section in visibleSections" :key="section.key">
            <SidebarGroup class="px-0.5 py-0.5">
                <SidebarGroupLabel
                    class="mb-1 px-3 pt-2 pb-0.5 text-[10px] font-bold uppercase tracking-normal text-[#6C7F95] group-data-[collapsible=icon]:hidden"
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
                                class="h-11 rounded-[1.1rem] px-3 text-[14px] font-medium text-[#264762] transition-all duration-200 hover:bg-white/65 hover:text-[#075985] data-[active=true]:bg-[#BDE9FB] data-[active=true]:font-bold data-[active=true]:text-[#075985] data-[active=true]:shadow-[0_12px_22px_-18px_rgb(14_165_233_/_0.9)]"
                            >
                                <Link :href="item.href">
                                    <component :is="item.icon" class="size-8 rounded-full bg-[#D7F1FE] p-1.5 text-[#0EA5E9] group-data-[state=collapsed]:mx-auto" />
                                    <span>{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </template>

        <div v-if="visibleSections.length === 0" class="px-3 py-4">
            <div class="rounded-2xl bg-white/65 p-3 text-center text-xs text-sidebar-foreground/70">
                لا توجد وحدات متاحة
            </div>
        </div>
    </div>
</template>
