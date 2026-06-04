<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

type Props = {
    user?: User;
    showEmail?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const { getInitials } = useInitials();

const showAvatar = computed(
    () => props.user?.avatar && props.user.avatar !== '',
);
</script>

<template>
    <Avatar class="h-9 w-9 overflow-hidden rounded-full border border-white/80 shadow-[0_8px_18px_-14px_rgb(14_165_233_/_0.8)]">
        <AvatarImage v-if="showAvatar && user && user.avatar" :src="user.avatar" :alt="user.name" />
        <AvatarFallback class="rounded-full bg-[#D7F1FE] text-[#075985] dark:text-white">
            {{ user ? getInitials(user.name) : '?' }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-start text-sm leading-tight">
        <span class="truncate font-bold text-[#0F172A]">{{ user?.name ?? 'Guest' }}</span>
        <span v-if="showEmail && user" class="truncate text-xs text-muted-foreground">{{
            user.email
        }}</span>
    </div>
</template>
