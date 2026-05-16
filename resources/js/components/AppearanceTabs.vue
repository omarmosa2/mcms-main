<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'فاتح' },
    { value: 'dark', Icon: Moon, label: 'داكن' },
    { value: 'system', Icon: Monitor, label: 'النظام' },
] as const;
</script>

<template>
    <div class="glass-panel-soft inline-flex gap-1.5 p-1.5">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            @click="updateAppearance(value)"
            :class="[
                'pattern-control-neumorphic flex items-center rounded-xl px-3.5 py-2 transition-colors min-h-[44px]',
                appearance === value
                    ? 'border-[var(--accent-teal)] bg-[var(--accent-teal-soft)] text-[var(--accent-teal-strong)] dark:bg-[var(--accent-teal-soft)]/30'
                    : 'text-muted-foreground hover:text-foreground',
            ]"
        >
            <component :is="Icon" class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
