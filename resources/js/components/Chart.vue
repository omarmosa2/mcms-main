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
import { computed, onMounted, ref } from 'vue';
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

const {
    type = 'bar',
    labels,
    datasets,
    title,
} = defineProps<{
    type?: ChartType;
    labels: string[];
    datasets: ChartDataset[];
    title?: string;
}>();

const chartRef = ref<HTMLElement | null>(null);
const resolvedData = ref<{ labels: string[]; datasets: ChartDataset[] }>({ labels: [], datasets: [] });

function resolveCssVariable(value: string): string {
    if (!value?.startsWith('var(')) {
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
        labels,
        datasets: datasets.map((ds) => ({
            ...ds,
            backgroundColor: resolveColors(ds.backgroundColor ?? 'var(--chart-1-bg)'),
            borderColor: resolveColors(ds.borderColor ?? 'var(--chart-1)'),
            borderWidth: ds.borderWidth ?? 1,
        })),
    };
}

onMounted(() => {
    updateResolvedData();
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom' as const,
        },
        title: {
            display: !!title,
            text: title,
        },
    },
}));

const chartComponent = type === 'line' ? Line : Bar;
</script>

<template>
    <div class="h-64 w-full">
        <component :is="chartComponent" :data="resolvedData" :options="chartOptions" />
    </div>
</template>
