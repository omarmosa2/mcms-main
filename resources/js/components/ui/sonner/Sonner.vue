<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { useDirection } from '@/composables/useDirection';
import { Toaster as SonnerPrimitive } from 'vue-sonner';
import 'vue-sonner/style.css';

const { appearance } = useAppearance();
const { direction } = useDirection();
</script>

<template>
    <SonnerPrimitive
        :theme="appearance"
        class="toaster group"
        position="bottom-right"
        :dir="direction"
        :style="{
            '--normal-bg': 'var(--popover)',
            '--normal-text': 'var(--popover-foreground)',
            '--normal-border': 'var(--border)',
        }"
    />
</template>
