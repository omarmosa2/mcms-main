<script setup lang="ts">
type HeroMetric = {
    label: string;
    value: string;
    hint?: string;
};

withDefaults(
    defineProps<{
        kicker: string;
        title: string;
        description: string;
        metrics: HeroMetric[];
    }>(),
    {
        metrics: () => [],
    },
);
</script>

<template>
    <section
        class="glass-panel-lux animate-rise-fade relative overflow-hidden p-6 md:p-7"
    >
        <div class="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
            <div>
                <span class="hero-kicker">{{ kicker }}</span>
                <h2
                    class="mt-3 text-2xl leading-tight font-semibold tracking-normal sm:text-3xl"
                >
                    {{ title }}
                </h2>
                <p
                    class="mt-3 max-w-2xl text-sm leading-7 text-muted-foreground sm:text-base"
                >
                    {{ description }}
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                <article
                    v-for="metric in metrics"
                    :key="metric.label"
                    class="metric-tile lift-hover"
                >
                    <p
                        class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                    >
                        {{ metric.label }}
                    </p>
                    <p class="mt-2 text-2xl font-semibold tracking-normal">
                        {{ metric.value }}
                    </p>
                    <p
                        v-if="metric.hint"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ metric.hint }}
                    </p>
                </article>
            </div>
        </div>
    </section>
</template>
