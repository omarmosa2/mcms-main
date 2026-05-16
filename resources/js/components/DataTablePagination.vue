<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginationMeta = {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links?: PaginationLink[];
};

const props = defineProps<{
    meta: PaginationMeta;
}>();

const paginationLinks = computed<PaginationLink[]>(() => {
    return Array.isArray(props.meta.links) ? props.meta.links : [];
});

const formatLabel = (label: string): string => {
    return label
        .replaceAll('&laquo;', '<<')
        .replaceAll('&raquo;', '>>')
        .replace(/<[^>]*>/g, '')
        .trim();
};
</script>

<template>
    <div
        class="mt-4 rounded-2xl border border-border/70 bg-background/65 px-3 py-3 sm:px-4"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-xs font-medium text-muted-foreground">
                Showing {{ meta.from ?? 0 }}-{{ meta.to ?? 0 }} of
                {{ meta.total }} (page {{ meta.current_page }} of
                {{ meta.last_page }})
            </p>

            <div class="flex flex-wrap items-center gap-1.5">
                <template
                    v-for="(link, index) in paginationLinks"
                    :key="`${index}-${link.label}`"
                >
                    <span
                        v-if="link.url === null"
                        class="inline-flex h-9 items-center rounded-full border border-border/70 bg-muted/40 px-3.5 text-xs font-semibold text-muted-foreground/70"
                    >
                        {{ formatLabel(link.label) }}
                    </span>
                    <Link
                        v-else
                        :href="link.url"
                        class="inline-flex h-9 items-center rounded-full border px-3.5 text-xs font-semibold transition"
                        :class="
                            link.active
                                ? 'border-info-400/70 bg-info-100/80 text-info-800 shadow-[0_0_0_1px_hsl(215_85%_48%_/_0.25)] dark:border-info-500/60 dark:bg-info-500/15 dark:text-info-100'
                                : 'border-border/70 bg-background/75 text-foreground/80 hover:border-info-400/50 hover:text-foreground'
                        "
                        preserve-scroll
                    >
                        {{ formatLabel(link.label) }}
                    </Link>
                </template>
            </div>
        </div>
    </div>
</template>
