<script setup lang="ts">
import { Bell } from 'lucide-vue-next';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';

type Notification = {
    id: number;
    title: string;
    message: string;
    read: boolean;
    created_at: string;
};

const notifications = ref<Notification[]>([
]);

const unreadCount = computed(() => notifications.value.filter(n => !n.read).length);
const isOpen = ref(false);

const markAsRead = (id: number) => {
    const notification = notifications.value.find(n => n.id === id);

    if (notification) {
        notification.read = true;
    }
};

const markAllAsRead = () => {
    notifications.value.forEach(n => n.read = true);
};

const closeDropdown = () => {
    isOpen.value = false;
};

const handleEscape = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && isOpen.value) {
        closeDropdown();
    }
};

const handleClickOutside = (e: MouseEvent) => {
    const target = e.target as HTMLElement;

    if (isOpen.value && !target.closest('[data-notification-dropdown]')) {
        closeDropdown();
    }
};

onMounted(() => {
    document.addEventListener('keydown', handleEscape);
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleEscape);
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="relative" data-notification-dropdown>
        <button
            type="button"
            class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg border border-border/60 bg-background/80 text-muted-foreground transition hover:bg-background hover:text-foreground min-h-[44px]"
            @click.stop="isOpen = !isOpen"
            aria-label="الإشعارات"
        >
            <Bell class="size-4" />
            <span
                v-if="unreadCount > 0"
                class="absolute -top-1 -inset-inline-end-1 flex h-4 w-4 items-center justify-center rounded-full bg-[var(--accent-coral)] text-[10px] font-bold text-white"
            >
                {{ unreadCount }}
            </span>
        </button>

        <div
            v-if="isOpen"
            class="absolute inset-inline-start-0 top-full z-50 mt-2 w-80 rounded-xl border border-border/70 bg-background/95 p-3 shadow-lg backdrop-blur-md"
        >
            <div class="mb-2 flex items-center justify-between border-b border-border/50 pb-2">
                <h3 class="text-sm font-semibold">الإشعارات</h3>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="text-xs text-[var(--accent-mint-strong)] hover:underline"
                    @click="markAllAsRead"
                >
                    تحديد الكل كمقروء
                </button>
            </div>

            <div v-if="notifications.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                لا توجد إشعارات بعد
            </div>

            <div v-else class="max-h-64 space-y-2 overflow-y-auto">
                <button
                    v-for="notification in notifications"
                    :key="notification.id"
                    type="button"
                    class="w-full rounded-lg p-2 text-start transition hover:bg-muted/50"
                    :class="!notification.read ? 'bg-info-500/5' : ''"
                    @click="markAsRead(notification.id)"
                >
                    <p class="text-xs font-semibold">{{ notification.title }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ notification.message }}</p>
                </button>
            </div>
        </div>
    </div>
</template>
