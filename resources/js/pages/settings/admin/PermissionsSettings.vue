<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { updatePermissions, permissions as permissionsUrl } from '@/actions/App/Http/Controllers/Admin/AdminSettingsController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';

const props = defineProps<{
    roles: Array<{
        id: number;
        name: string;
        description: string | null;
        is_system: boolean;
        permissions: string[];
    }>;
    allPermissions: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'إعدادات الصلاحيات',
                href: permissionsUrl(),
            },
        ],
    },
});

const isDirty = ref(false);

const form = useForm({
    roles: props.roles.map((role) => ({
        id: role.id,
        permissions: [...role.permissions],
    })),
});

const permissionGroups: Record<string, string[]> = {};

props.allPermissions.forEach((perm) => {
    const group = perm.split('.')[0];

    if (!permissionGroups[group]) {
        permissionGroups[group] = [];
    }

    permissionGroups[group].push(perm);
});

const groupLabels: Record<string, string> = {
    patient: 'المرضى',
    department: 'العيادات',
    doctor_profile: 'الأطباء',
    appointment: 'المواعيد',
    billing: 'الفواتير',
    payment: 'المدفوعات',
    expense: 'المصروفات',
    user: 'المستخدمون',
    employee: 'الموظفون',
    salary: 'الرواتب',
    cashbox: 'الصندوق',
    account: 'الحسابات',
    reports: 'التقارير',
    settings: 'الإعدادات',
};

watch(form, () => {
    isDirty.value = form.isDirty;
}, { deep: true });

function hasPermission(roleIndex: number, permission: string): boolean {
    return form.roles[roleIndex].permissions.includes(permission);
}

function togglePermission(roleIndex: number, permission: string) {
    const perms = form.roles[roleIndex].permissions;
    const index = perms.indexOf(permission);

    if (index > -1) {
        perms.splice(index, 1);
    } else {
        perms.push(permission);
    }
}

function toggleGroupAll(roleIndex: number, group: string) {
    const groupPerms = permissionGroups[group] ?? [];
    const allSelected = groupPerms.every((p) => hasPermission(roleIndex, p));

    if (allSelected) {
        form.roles[roleIndex].permissions = form.roles[roleIndex].permissions.filter(
            (p) => !groupPerms.includes(p),
        );
    } else {
        const newPerms = [...form.roles[roleIndex].permissions];

        groupPerms.forEach((p) => {
            if (!newPerms.includes(p)) {
                newPerms.push(p);
            }
        });

        form.roles[roleIndex].permissions = newPerms;
    }
}

function isGroupFullySelected(roleIndex: number, group: string): boolean {
    const groupPerms = permissionGroups[group] ?? [];

    return groupPerms.length > 0 && groupPerms.every((p) => hasPermission(roleIndex, p));
}

function submit() {
    form.put(updatePermissions.url(), {
        onSuccess: () => {
            isDirty.value = false;
        },
    });
}
</script>

<template>
    <Head title="إعدادات الصلاحيات" />

    <div class="space-y-6">
        <div class="glass-panel-soft p-5">
            <Heading
                tone="typographic"
                variant="small"
                title="الصلاحيات والأدوار"
                description="إدارة صلاحيات الوصول لكل دور في النظام."
            />
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div
                v-for="(role, roleIndex) in form.roles"
                :key="role.id"
                class="glass-panel-soft space-y-4 p-5"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">
                            {{ props.roles[roleIndex].name }}
                        </h3>
                        <p v-if="props.roles[roleIndex].description" class="text-xs text-muted-foreground">
                            {{ props.roles[roleIndex].description }}
                        </p>
                    </div>
                    <span v-if="props.roles[roleIndex].is_system" class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">نظامي</span>
                </div>

                <div class="overflow-x-auto">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="(perms, group) in permissionGroups"
                            :key="group"
                            class="rounded-xl border border-border/50 bg-muted/20 p-3"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs font-semibold text-foreground">
                                    {{ groupLabels[group] ?? group }}
                                </span>
                                <button
                                    type="button"
                                    class="text-xs text-primary hover:underline"
                                    @click="toggleGroupAll(roleIndex, group as string)"
                                >
                                    {{ isGroupFullySelected(roleIndex, group as string) ? 'إلغاء تحديد الكل' : 'تحديد الكل' }}
                                </button>
                            </div>
                            <div class="space-y-1.5">
                                <div
                                    v-for="perm in perms"
                                    :key="perm"
                                    class="flex items-center gap-2"
                                >
                                    <Checkbox
                                        :id="`${role.id}-${perm}`"
                                        :checked="hasPermission(roleIndex, perm)"
                                        @update:checked="togglePermission(roleIndex, perm)"
                                    />
                                    <Label :for="`${role.id}-${perm}`" class="cursor-pointer text-xs">
                                        {{ perm.split('.')[1] }}
                                    </Label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <span v-if="isDirty" class="text-sm text-amber-600">لديك تغييرات غير محفوظة</span>
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'جاري الحفظ...' : 'حفظ الصلاحيات' }}
                </Button>
            </div>
        </form>
    </div>
</template>
