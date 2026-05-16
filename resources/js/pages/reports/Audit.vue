<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Search, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AuditReportController from '@/actions/App/Http/Controllers/Reports/AuditReportController';
import InternalPageHero from '@/components/InternalPageHero.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type AuditLog = {
    id: number;
    user_id: number | null;
    user_name: string | null;
    action: string;
    resource_type: string | null;
    resource_id: number | null;
    description: string | null;
    ip_address: string | null;
    user_agent: string | null;
    occurred_at: string;
};

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
    links: PaginationLink[];
};

type PaginationNavigation = {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
};

type PaginatedResponse<T> = {
    data: T[];
    links: PaginationNavigation;
    meta: PaginationMeta;
};

type AuditFilters = {
    from: string | null;
    to: string | null;
    action: string | null;
    user_id: number | null;
    per_page: number;
};

const { audit_logs, filters } = defineProps<{
    audit_logs: PaginatedResponse<AuditLog>;
    filters: AuditFilters;
}>();

const defaultRowsPerPage = 15;

const localSearch = ref<string>('');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(audit_logs.meta.current_page);
const localFrom = ref<string>(filters.from ?? '');
const localTo = ref<string>(filters.to ?? '');
const localAction = ref<string>(filters.action ?? '');
const localUserId = ref<number | null>(filters.user_id);

const totalLocalPages = computed(() => Math.max(1, audit_logs.meta.last_page));
const localVisibleFrom = computed(() => audit_logs.meta.from ?? 0);
const localVisibleTo = computed(() => audit_logs.meta.to ?? 0);

const buildIndexQuery = (overrides: Record<string, unknown> = {}) => ({
    search: localSearch.value.trim() || undefined,
    per_page: localRowsPerPage.value,
    page: localPage.value,
    from: localFrom.value || undefined,
    to: localTo.value || undefined,
    action: localAction.value || undefined,
    user_id: localUserId.value || undefined,
    ...overrides,
});

const reloadAuditLogs = (overrides: Record<string, unknown> = {}) => {
    router.get(AuditReportController.index.url(), buildIndexQuery(overrides), {
        only: ['audit_logs', 'filters'],
        preserveState: true,
        preserveScroll: true,
    });
};

const goToPreviousPage = () => {
    if (localPage.value <= 1) {
return;
}

    localPage.value -= 1;
    reloadAuditLogs({ page: localPage.value });
};

const goToNextPage = () => {
    if (localPage.value >= totalLocalPages.value) {
return;
}

    localPage.value += 1;
    reloadAuditLogs({ page: localPage.value });
};

const applyFilters = () => {
    localPage.value = 1;
    reloadAuditLogs({ page: 1 });
};

const resetFilters = () => {
    localSearch.value = '';
    localPage.value = 1;
    localFrom.value = '';
    localTo.value = '';
    localAction.value = '';
    localUserId.value = null;
    localRowsPerPage.value = defaultRowsPerPage;
    reloadAuditLogs({
        search: '',
        page: 1,
        from: '',
        to: '',
        action: '',
        user_id: null,
        per_page: defaultRowsPerPage,
    });
};

const auditExportUrl = computed(() => {
    return AuditReportController.export.url({
        query: {
            from: localFrom.value || undefined,
            to: localTo.value || undefined,
            action: localAction.value || undefined,
            user_id: localUserId.value || undefined,
        },
    });
});

const formatDateTime = (value: string) => {
    return new Date(value).toLocaleString();
};

const auditHeroMetrics = computed(() => [
    {
        label: 'Total logs',
        value: String(audit_logs.meta.total),
        hint: 'All audit entries',
    },
    {
        label: 'Current page',
        value: String(audit_logs.data.length),
        hint: 'Visible entries',
    },
    {
        label: 'Current page',
        value: `${localPage.value}/${totalLocalPages.value}`,
        hint: 'Pagination',
    },
]);

const getActionClass = (action: string) => {
    const lowerAction = action.toLowerCase();

    if (lowerAction.includes('create')) {
        return 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/40 dark:bg-success-500/15 dark:text-success-100';
    }

    if (lowerAction.includes('update') || lowerAction.includes('edit')) {
        return 'border-info-300/70 bg-info-50 text-info-800 dark:border-info-500/40 dark:bg-info-500/15 dark:text-info-100';
    }

    if (lowerAction.includes('delete') || lowerAction.includes('destroy')) {
        return 'border-destructive/70 bg-destructive/10 text-destructive dark:border-destructive/40 dark:bg-destructive/15 dark:text-destructive-foreground';
    }

    return 'border-border/70 bg-background/80 text-muted-foreground';
};
</script>

<template>
    <Head title="Audit Logs" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6">
        <InternalPageHero
            kicker="System audit"
            title="Audit Logs"
            description="Track all system activities and changes for security and compliance."
            :metrics="auditHeroMetrics"
        />

        <section class="glass-panel-soft p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                <h3 class="pattern-typographic-title text-[0.76rem]">
                    Filters
                </h3>
            </div>

            <div class="grid gap-3 rounded-2xl border border-border/70 bg-background/60 p-4 md:grid-cols-6 md:items-end">
                <div class="grid gap-2 md:col-span-1">
                    <Label for="audit_from">From</Label>
                    <Input
                        id="audit_from"
                        v-model="localFrom"
                        type="date"
                        class="pattern-field-clay"
                    />
                </div>

                <div class="grid gap-2 md:col-span-1">
                    <Label for="audit_to">To</Label>
                    <Input
                        id="audit_to"
                        v-model="localTo"
                        type="date"
                        class="pattern-field-clay"
                    />
                </div>

                <div class="grid gap-2 md:col-span-1">
                    <Label for="audit_action">Action</Label>
                    <Input
                        id="audit_action"
                        v-model="localAction"
                        placeholder="e.g., user.create"
                        class="pattern-field-clay"
                    />
                </div>

                <div class="grid gap-2 md:col-span-1">
                    <Label for="audit_per_page">Rows</Label>
                    <select
                        id="audit_per_page"
                        v-model.number="localRowsPerPage"
                        class="pattern-field-clay h-9 px-3 py-1.5"
                    >
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                </div>

                <div class="md:col-span-2 md:justify-self-end">
                    <div class="flex flex-wrap items-center gap-2">
                        <Button type="button" variant="neumorphic" size="sm" @click="applyFilters">
                            <Search class="mr-1 size-4" />
                            Apply
                        </Button>
                        <Button type="button" variant="ghost" size="sm" @click="resetFilters">
                            Reset
                        </Button>
                        <a
                            :href="auditExportUrl"
                            class="inline-flex h-9 items-center justify-center rounded-xl border border-border/70 bg-background/80 px-3 text-xs font-semibold"
                        >
                            <Download class="mr-1 size-4" />
                            CSV
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="glass-panel-soft p-5">
            <div class="ui-table-shell">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th class="px-3 py-2">Timestamp</th>
                            <th class="px-3 py-2">User</th>
                            <th class="px-3 py-2">Action</th>
                            <th class="px-3 py-2">Resource</th>
                            <th class="px-3 py-2">Description</th>
                            <th class="px-3 py-2">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in audit_logs.data" :key="log.id" class="ui-table-row">
                            <td class="px-3 py-2" data-label="Timestamp">
                                <span class="text-xs font-mono">
                                    {{ formatDateTime(log.occurred_at) }}
                                </span>
                            </td>
                            <td class="px-3 py-2" data-label="User">
                                <div class="flex items-center gap-2">
                                    <User class="size-4 text-muted-foreground" />
                                    {{ log.user_name ?? 'System' }}
                                </div>
                            </td>
                            <td class="px-3 py-2" data-label="Action">
                                <span
                                    class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                                    :class="getActionClass(log.action)"
                                >
                                    {{ log.action }}
                                </span>
                            </td>
                            <td class="px-3 py-2" data-label="Resource">
                                <div v-if="log.resource_type">
                                    <span class="text-sm">{{ log.resource_type }}</span>
                                    <span v-if="log.resource_id" class="text-xs text-muted-foreground">
                                        #{{ log.resource_id }}
                                    </span>
                                </div>
                                <span v-else class="text-muted-foreground">-</span>
                            </td>
                            <td class="px-3 py-2" data-label="Description">
                                <span class="text-sm">
                                    {{ log.description ?? '-' }}
                                </span>
                            </td>
                            <td class="px-3 py-2" data-label="IP">
                                <span class="text-xs font-mono">
                                    {{ log.ip_address ?? '-' }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="audit_logs.data.length === 0" class="table-empty-state">
                            <td :colspan="6" class="px-3 py-10 text-center text-muted-foreground">
                                لا توجد سجلات تدقيق.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2">
                <p class="text-xs text-muted-foreground">
                    عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من {{ audit_logs.meta.total }} سجل
                </p>
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="neumorphic"
                        size="sm"
                        class="h-8 px-3 text-xs"
                        :disabled="localPage === 1"
                        @click="goToPreviousPage"
                    >
                        السابق
                    </Button>
                    <span class="text-xs font-semibold text-foreground/85">
                        صفحة {{ localPage }} / {{ totalLocalPages }}
                    </span>
                    <Button
                        type="button"
                        variant="neumorphic"
                        size="sm"
                        class="h-8 px-3 text-xs"
                        :disabled="localPage >= totalLocalPages"
                        @click="goToNextPage"
                    >
                        التالي
                    </Button>
                </div>
            </div>
        </section>
    </div>
</template>