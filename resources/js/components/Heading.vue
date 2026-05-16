<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
    tone?: 'default' | 'typographic';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
    tone: 'default',
});
</script>

<template>
    <header :class="variant === 'small' ? 'space-y-1' : 'mb-6 space-y-1.5'">
        <h2
            :class="
                tone === 'typographic'
                    ? variant === 'small'
                        ? 'text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase'
                        : 'text-2xl font-semibold tracking-tight text-foreground sm:text-3xl'
                    : variant === 'small'
                      ? 'mb-0.5 text-base font-medium'
                      : 'text-xl font-semibold tracking-tight'
            "
        >
            {{ title }}
        </h2>
        <p
            v-if="description"
            :class="
                tone === 'typographic'
                    ? 'max-w-3xl text-sm text-muted-foreground sm:text-base'
                    : 'text-sm text-muted-foreground'
            "
        >
            {{ description }}
        </p>
    </header>
</template>
