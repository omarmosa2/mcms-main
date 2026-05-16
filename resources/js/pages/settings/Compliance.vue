<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ComplianceController from '@/actions/App/Http/Controllers/Settings/ComplianceController';
import InternalPageHero from '@/components/InternalPageHero.vue';

type SecurityPolicy = {
    password_min_length: number;
    require_mixed_case: boolean;
    require_numbers: boolean;
    require_symbols: boolean;
    session_lifetime_minutes: number;
    idle_timeout_minutes: number;
    force_two_factor: boolean;
    confirm_password_for_security_actions: boolean;
    audit_retention_days: number;
    sensitive_access_retention_days: number;
};

type ComplianceRun = {
    id: number;
    run_type: string;
    status: string;
    summary: Record<string, unknown> | null;
    ran_at: string | null;
};

defineProps<{
    kpis: {
        audit_events_today: number;
        sensitive_access_today: number;
        pending_invitations: number;
        policy_configured: boolean;
    };
    security_policy: SecurityPolicy | null;
    recent_audit_events: Array<{
        id: number;
        action: string;
        user: string | null;
        occurred_at: string | null;
    }>;
    recent_sensitive_access: Array<{
        id: number;
        resource_type: string;
        resource_id: number | null;
        user: string | null;
        reason: string | null;
        accessed_at: string | null;
    }>;
    recent_runs: ComplianceRun[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Compliance',
                href: ComplianceController.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Compliance" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6">
        <InternalPageHero
            kicker="Governance & controls"
            title="Compliance Cockpit"
            description="Track security posture, sensitive access, and compliance automation from one operational view."
            :metrics="[
                {
                    label: 'Audit events today',
                    value: String(kpis.audit_events_today),
                    hint: 'Audit trail activity',
                },
                {
                    label: 'Sensitive access today',
                    value: String(kpis.sensitive_access_today),
                    hint: 'PII access logs',
                },
                {
                    label: 'Pending invitations',
                    value: String(kpis.pending_invitations),
                    hint: 'Unaccepted invite links',
                },
            ]"
        />

        <section class="grid gap-4 md:grid-cols-3">
            <article class="glass-panel-soft p-4">
                <p class="text-xs text-muted-foreground">Policy status</p>
                <p class="mt-1 text-lg font-semibold">
                    {{ kpis.policy_configured ? 'Configured' : 'Missing' }}
                </p>
            </article>
            <article class="glass-panel-soft p-4">
                <p class="text-xs text-muted-foreground">Audit retention</p>
                <p class="mt-1 text-lg font-semibold">
                    {{ security_policy?.audit_retention_days ?? '-' }} days
                </p>
            </article>
            <article class="glass-panel-soft p-4">
                <p class="text-xs text-muted-foreground">Sensitive retention</p>
                <p class="mt-1 text-lg font-semibold">
                    {{ security_policy?.sensitive_access_retention_days ?? '-' }} days
                </p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <div class="glass-panel-soft p-4">
                <h3 class="pattern-typographic-title mb-3 text-[0.72rem]">
                    Recent audit events
                </h3>
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="eventItem in recent_audit_events"
                        :key="`audit-${eventItem.id}`"
                        class="rounded-xl border border-border/70 bg-background/70 px-3 py-2"
                    >
                        <p class="font-semibold">{{ eventItem.action }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ eventItem.user ?? 'System' }} |
                            {{ eventItem.occurred_at ?? '-' }}
                        </p>
                    </li>
                </ul>
            </div>

            <div class="glass-panel-soft p-4">
                <h3 class="pattern-typographic-title mb-3 text-[0.72rem]">
                    Recent sensitive access
                </h3>
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="entry in recent_sensitive_access"
                        :key="`sensitive-${entry.id}`"
                        class="rounded-xl border border-border/70 bg-background/70 px-3 py-2"
                    >
                        <p class="font-semibold">
                            {{ entry.resource_type }} #{{ entry.resource_id ?? '-' }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ entry.user ?? 'System' }} | {{ entry.reason ?? 'No reason' }}
                        </p>
                    </li>
                </ul>
            </div>
        </section>

        <section class="glass-panel-soft p-4">
            <h3 class="pattern-typographic-title mb-3 text-[0.72rem]">
                عمليات الامتثال التلقائية
            </h3>
            <div class="ui-table-shell">
                <table class="ui-table md:min-w-[720px]">
                    <thead>
                        <tr>
                            <th class="px-3 py-2">نوع العملية</th>
                            <th class="px-3 py-2">الحالة</th>
                            <th class="px-3 py-2">تاريخ التنفيذ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="run in recent_runs"
                            :key="`run-${run.id}`"
                            class="ui-table-row"
                        >
                            <td class="px-3 py-2">{{ run.run_type }}</td>
                            <td class="px-3 py-2">{{ run.status }}</td>
                            <td class="px-3 py-2">{{ run.ran_at ?? '-' }}</td>
                        </tr>
                        <tr v-if="recent_runs.length === 0" class="table-empty-state">
                            <td colspan="3" class="px-3 py-8 text-center text-muted-foreground">
                                لم يتم تسجيل عمليات امتثال بعد.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
