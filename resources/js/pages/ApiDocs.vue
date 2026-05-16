<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

type Endpoint = {
    method: string;
    path: string;
    summary: string;
};

type EndpointMap = {
    [key: string]: Endpoint[];
};

const props = defineProps<{
    specUrl?: string;
}>();

const loading = ref(true);
const spec = ref<Record<string, unknown> | null>(null);
const error = ref<string | null>(null);

onMounted(async () => {
    try {
        if (!props.specUrl) {
            throw new Error('No spec URL provided');
        }

        const response = await fetch(props.specUrl);

        if (!response.ok) {
            throw new Error('Failed to load API specification');
        }

        const yamlText = await response.text();
        spec.value = parseYaml(yamlText);
    } catch (e: unknown) {
        error.value = e instanceof Error ? e.message : 'Unknown error';
    } finally {
        loading.value = false;
    }
});

function parseYaml(yaml: string): Record<string, unknown> {
    const lines = yaml.split('\n');
    const result: Record<string, unknown> = {};
    const currentPath: string[] = [];
    const indentStack = [-1];

    for (const line of lines) {
        const trimmed = line.trimStart();

        if (!trimmed || trimmed.startsWith('#')) {
            continue;
        }

        const indent = line.length - trimmed.length;

        while (indent <= indentStack[indentStack.length - 1] && indentStack.length > 1) {
            indentStack.pop();
            currentPath.pop();
        }

        if (trimmed.startsWith('- ')) {
            continue;
        }

        const colonIndex = trimmed.indexOf(':');

        if (colonIndex === -1) {
            continue;
        }

        const key = trimmed.substring(0, colonIndex).trim().replace(/^['"]|['"]$/g, '');
        const value = trimmed.substring(colonIndex + 1).trim();

        indentStack.push(indent);
        currentPath.push(key);

        if (value && !value.startsWith('{') && !value.startsWith('[')) {
            let obj: Record<string, unknown> = result;

            for (let i = 0; i < currentPath.length - 1; i++) {
                const pathKey = currentPath[i];

                if (!obj[pathKey]) {
                    obj[pathKey] = {};
                }

                obj = obj[pathKey] as Record<string, unknown>;
            }

            obj[currentPath[currentPath.length - 1]] = value.replace(/^['"]|['"]$/g, '');
        }
    }

    return result;
}

const endpoints = [
    { tag: 'Patients', icon: '👤' },
    { tag: 'Appointments', icon: '📅' },
    { tag: 'Queue', icon: '🔢' },
    { tag: 'Visits', icon: '🏥' },
    { tag: 'Billing', icon: '💰' },
    { tag: 'Financial', icon: '📊' },
    { tag: 'Inventory', icon: '📦' },
    { tag: 'Diagnostics', icon: '🔬' },
    { tag: 'Departments', icon: '🏢' },
    { tag: 'Doctors', icon: '👨‍⚕️' },
    { tag: 'Users', icon: '👥' },
    { tag: 'Roles', icon: '🔑' },
    { tag: 'Expenses', icon: '💸' },
    { tag: 'Cashbox', icon: '🏧' },
    { tag: 'Salaries', icon: '💵' },
    { tag: 'Reports', icon: '📈' },
    { tag: 'Monitoring', icon: '🖥️' },
    { tag: 'Settings', icon: '⚙️' },
];

const methods: Record<string, { label: string; color: string }> = {
    get: { label: 'GET', color: 'bg-info-100 text-info-800 dark:bg-info-900 dark:text-info-200' },
    post: { label: 'POST', color: 'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200' },
    put: { label: 'PUT', color: 'bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200' },
    patch: { label: 'PATCH', color: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' },
    delete: { label: 'DELETE', color: 'bg-destructive/10 text-destructive dark:bg-destructive/20 dark:text-destructive-foreground' },
};

function getEndpointsForTag(tag: string): Endpoint[] {
    const endpointMap: EndpointMap = {
        Patients: [
            { method: 'get', path: '/patients', summary: 'List patients' },
            { method: 'post', path: '/patients', summary: 'Create patient' },
            { method: 'get', path: '/patients/{id}', summary: 'Show patient' },
            { method: 'put', path: '/patients/{id}', summary: 'Update patient' },
            { method: 'delete', path: '/patients/{id}', summary: 'Delete patient' },
            { method: 'delete', path: '/patients/bulk', summary: 'Bulk delete patients' },
        ],
        Appointments: [
            { method: 'get', path: '/appointments', summary: 'List appointments' },
            { method: 'post', path: '/appointments', summary: 'Create appointment' },
            { method: 'get', path: '/appointments/{id}', summary: 'Show appointment' },
            { method: 'put', path: '/appointments/{id}', summary: 'Update appointment' },
            { method: 'patch', path: '/appointments/{id}/status', summary: 'Transition status' },
            { method: 'delete', path: '/appointments/{id}', summary: 'Delete appointment' },
            { method: 'delete', path: '/appointments/bulk', summary: 'Bulk delete' },
        ],
        Queue: [
            { method: 'get', path: '/queue', summary: 'List queue entries' },
            { method: 'post', path: '/queue', summary: 'Enqueue patient' },
            { method: 'post', path: '/queue/call-next', summary: 'Call next patient' },
            { method: 'get', path: '/queue/{id}', summary: 'Show queue entry' },
            { method: 'patch', path: '/queue/{id}/status', summary: 'Update status' },
            { method: 'delete', path: '/queue/{id}', summary: 'Delete queue entry' },
            { method: 'delete', path: '/queue/bulk', summary: 'Bulk delete' },
        ],
        Visits: [
            { method: 'get', path: '/visits', summary: 'List visits' },
            { method: 'post', path: '/visits', summary: 'Start visit' },
            { method: 'get', path: '/visits/{id}', summary: 'Show visit' },
            { method: 'put', path: '/visits/{id}', summary: 'Update visit' },
            { method: 'patch', path: '/visits/{id}/status', summary: 'Transition status' },
            { method: 'post', path: '/visits/{id}/diagnoses', summary: 'Add diagnosis' },
            { method: 'post', path: '/visits/{id}/vitals', summary: 'Add vitals' },
            { method: 'delete', path: '/visits/{id}', summary: 'Delete visit' },
            { method: 'delete', path: '/visits/bulk', summary: 'Bulk delete' },
        ],
        Billing: [
            { method: 'get', path: '/billing/invoices', summary: 'List invoices' },
            { method: 'post', path: '/billing/invoices', summary: 'Create invoice' },
            { method: 'get', path: '/billing/invoices/{id}', summary: 'Show invoice' },
            { method: 'put', path: '/billing/invoices/{id}', summary: 'Update invoice' },
            { method: 'patch', path: '/billing/invoices/{id}/issue', summary: 'Issue invoice' },
            { method: 'delete', path: '/billing/invoices/{id}', summary: 'Delete invoice' },
            { method: 'delete', path: '/billing/invoices/bulk', summary: 'Bulk delete' },
            { method: 'post', path: '/billing/invoices/{id}/payments', summary: 'Record payment' },
            { method: 'patch', path: '/billing/payments/{id}/refund', summary: 'Refund payment' },
        ],
        Financial: [
            { method: 'get', path: '/payment-plans', summary: 'List payment plans' },
            { method: 'post', path: '/payment-plans', summary: 'Create payment plan' },
            { method: 'post', path: '/payment-plans/{id}/apply', summary: 'Apply payment plan' },
            { method: 'get', path: '/installments', summary: 'List installments' },
            { method: 'post', path: '/installments/{id}/pay', summary: 'Pay installment' },
        ],
        Inventory: [
            { method: 'post', path: '/inventory/adjust-stock', summary: 'Adjust stock' },
            { method: 'get', path: '/inventory/batches', summary: 'List batches' },
            { method: 'post', path: '/inventory/batches', summary: 'Create batch' },
            { method: 'post', path: '/inventory/batches/consume', summary: 'Consume batch' },
            { method: 'get', path: '/inventory/returns', summary: 'List returns' },
            { method: 'post', path: '/inventory/returns', summary: 'Return stock' },
            { method: 'get', path: '/inventory/adjustments', summary: 'List adjustments' },
        ],
        Diagnostics: [
            { method: 'get', path: '/diagnostics/lab-templates', summary: 'List lab templates' },
            { method: 'post', path: '/diagnostics/lab-templates', summary: 'Create lab template' },
            { method: 'get', path: '/diagnostics/radiology-study-types', summary: 'List radiology types' },
            { method: 'post', path: '/diagnostics/radiology-study-types', summary: 'Create radiology type' },
        ],
        Departments: [
            { method: 'get', path: '/departments', summary: 'List departments' },
            { method: 'post', path: '/departments', summary: 'Create department' },
            { method: 'get', path: '/departments/{id}', summary: 'Show department' },
            { method: 'put', path: '/departments/{id}', summary: 'Update department' },
            { method: 'delete', path: '/departments/{id}', summary: 'Delete department' },
            { method: 'delete', path: '/departments/bulk', summary: 'Bulk delete' },
        ],
        Doctors: [
            { method: 'get', path: '/doctors', summary: 'List doctors' },
            { method: 'post', path: '/doctors', summary: 'Create doctor' },
            { method: 'get', path: '/doctors/{id}', summary: 'Show doctor' },
            { method: 'put', path: '/doctors/{id}', summary: 'Update doctor' },
            { method: 'delete', path: '/doctors/{id}', summary: 'Delete doctor' },
            { method: 'delete', path: '/doctors/bulk', summary: 'Bulk delete' },
        ],
        Users: [
            { method: 'get', path: '/users', summary: 'List users' },
            { method: 'post', path: '/users', summary: 'Create user' },
            { method: 'put', path: '/users/{id}', summary: 'Update user' },
            { method: 'delete', path: '/users/{id}', summary: 'Delete user' },
            { method: 'delete', path: '/users/bulk', summary: 'Bulk delete' },
        ],
        Roles: [
            { method: 'get', path: '/roles', summary: 'List roles' },
            { method: 'post', path: '/roles', summary: 'Create role' },
            { method: 'put', path: '/roles/{id}', summary: 'Update role' },
            { method: 'delete', path: '/roles/{id}', summary: 'Delete role' },
        ],
        Expenses: [
            { method: 'get', path: '/expenses', summary: 'List expenses' },
            { method: 'post', path: '/expenses', summary: 'Create expense' },
            { method: 'put', path: '/expenses/{id}', summary: 'Update expense' },
            { method: 'post', path: '/expenses/{id}/approve', summary: 'Approve expense' },
            { method: 'post', path: '/expenses/{id}/reject', summary: 'Reject expense' },
            { method: 'delete', path: '/expenses/{id}', summary: 'Delete expense' },
            { method: 'delete', path: '/expenses/bulk', summary: 'Bulk delete' },
        ],
        Cashbox: [
            { method: 'get', path: '/cashbox', summary: 'View cashbox' },
            { method: 'post', path: '/cashbox', summary: 'Open cashbox' },
            { method: 'post', path: '/cashbox/{id}/close', summary: 'Close cashbox' },
        ],
        Salaries: [
            { method: 'get', path: '/salaries', summary: 'List salaries' },
            { method: 'post', path: '/salaries', summary: 'Create salary' },
            { method: 'put', path: '/salaries/{id}', summary: 'Update salary' },
            { method: 'post', path: '/salaries/{id}/approve', summary: 'Approve salary' },
            { method: 'post', path: '/salaries/{id}/pay', summary: 'Pay salary' },
            { method: 'delete', path: '/salaries/{id}', summary: 'Delete salary' },
        ],
        Reports: [
            { method: 'get', path: '/reports', summary: 'View reports' },
            { method: 'get', path: '/reports/export/excel', summary: 'Export Excel' },
            { method: 'get', path: '/reports/export/pdf', summary: 'Export PDF' },
            { method: 'get', path: '/reports/audit', summary: 'Audit report' },
            { method: 'get', path: '/reports/audit/export', summary: 'Export audit' },
        ],
        Monitoring: [
            { method: 'get', path: '/monitoring/health', summary: 'Health check' },
            { method: 'get', path: '/monitoring/metrics', summary: 'Prometheus metrics' },
        ],
        Settings: [
            { method: 'get', path: '/settings/profile', summary: 'Edit profile' },
            { method: 'patch', path: '/settings/profile', summary: 'Update profile' },
            { method: 'delete', path: '/settings/profile', summary: 'Delete account' },
            { method: 'get', path: '/settings/security', summary: 'Security settings' },
            { method: 'get', path: '/settings/security/policies', summary: 'View policies' },
            { method: 'put', path: '/settings/security/policies', summary: 'Update policies' },
            { method: 'put', path: '/settings/password', summary: 'Update password' },
            { method: 'get', path: '/settings/appearance', summary: 'Appearance settings' },
            { method: 'get', path: '/settings/compliance', summary: 'Compliance reports' },
        ],
    };

    return endpointMap[tag] || [];
}
</script>
