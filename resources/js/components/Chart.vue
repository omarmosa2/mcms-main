<script setup lang="ts">
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';
import { computed, ref, watch } from 'vue';
import { Bar, Line } from 'vue-chartjs';

ChartJS.register(CategoryScale, LinearScale, BarElement, LineElement, PointElement, Title, Tooltip, Legend, Filler);

type ChartType = 'bar' | 'line';

type ChartDataset = {
    label: string;
    data: number[];
    backgroundColor?: string | string[];
    borderColor?: string | string[];
    borderWidth?: number;
};

const props = withDefaults(defineProps<{
    type?: ChartType;
    labels: string[];
    datasets: ChartDataset[];
    title?: string;
}>(), {
    type: 'bar',
});

const resolvedData = ref<{ labels: string[]; datasets: ChartDataset[] }>({ labels: [], datasets: [] });

function resolveCssVariable(value: string): string {
    if (!value?.startsWith('var(')) {
        return value;
    }

    if (typeof document === 'undefined') {
        return value;
    }

    const match = value.match(/var\(--([^)]+)\)/);

    if (!match) {
        return value;
    }

    const computed = getComputedStyle(document.documentElement).getPropertyValue(`--${match[1]}`).trim();

    return computed || value;
}

function resolveColors(color: string | string[] | undefined): string | string[] | undefined {
    if (!color) {
        return color;
    }

    if (Array.isArray(color)) {
        return color.map(resolveCssVariable);
    }

    return resolveCssVariable(color);
}

function updateResolvedData() {
    resolvedData.value = {
        labels: props.labels,
        datasets: props.datasets.map((ds) => ({
            ...ds,
            backgroundColor: resolveColors(ds.backgroundColor ?? 'var(--chart-1-bg)'),
            borderColor: resolveColors(ds.borderColor ?? 'var(--chart-1)'),
            borderWidth: ds.borderWidth ?? 1,
        })),
    };
}

watch(
    () => [props.labels, props.datasets],
    updateResolvedData,
    { deep: true, immediate: true },
);

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom' as const,
        },
        title: {
            display: !!props.title,
            text: props.title,
        },
    },
}));

const chartComponent = computed(() => (props.type === 'line' ? Line : Bar));
</script>

<template>
    <div class="h-72 w-full">
        <component :is="chartComponent" :data="resolvedData" :options="chartOptions" />
    </div>
</template>
