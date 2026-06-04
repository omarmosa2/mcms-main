<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const { isMobile, state } = useSidebar();
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        v-if="user"
                        size="lg"
                        class="h-12 rounded-2xl border border-[#CFE8F7]/80 bg-white/65 text-[#1A2B3F] transition-all duration-200 data-[state=open]:bg-[#BDE9FB] data-[state=open]:text-[#075985]"
                        data-test="sidebar-menu-button"
                    >
                        <UserInfo :user="user" />
                        <ChevronsUpDown class="ms-auto size-3.5 opacity-50" />
                    </SidebarMenuButton>
                    <SidebarMenuButton
                        v-else
                        size="lg"
                        class="h-12 rounded-2xl border border-[#CFE8F7]/80 bg-white/65 text-[#1A2B3F]"
                    >
                        <UserInfo />
                        <ChevronsUpDown class="ms-auto size-3.5 opacity-50" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-2xl border-[#DDE9F3] bg-white p-1.5 shadow-dropdown"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'left'
                              : 'bottom'
                    "
                    align="end"
                    :side-offset="4"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
