<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Shield, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/RBAC/RoleController';
import InputError from '@/components/InputError.vue';
import InternalPageHero from '@/components/InternalPageHero.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Roles',
                href: RoleController.index.url(),
            },
        ],
    },
});

type RolePermission = {
    id: number;
    name: string;
    description: string;
};

type Role = {
    id: number;
    name: string;
    description: string | null;
    is_system: boolean;
    permissions: RolePermission[];
    permissions_count: number;
    users_count: number;
    created_at: string | null;
};

type PermissionGroup = {
    [group: string]: RolePermission[];
};

const { roles, permissions } = defineProps<{
    roles: Role[];
    permissions: PermissionGroup;
    filters: {
        search: string | null;
    };
}>();

const { can } = usePermissions();
const toast = useToast();

const editingRole = ref<Role | null>(null);
const viewingRole = ref<Role | null>(null);

const roleHeroMetrics = computed(() => [
    { label: 'Total roles', value: String(roles.length), hint: 'All roles in this clinic' },
    { label: 'Custom roles', value: String(roles.filter((r) => !r.is_system).length), hint: 'Non-system roles' },
    { label: 'System roles', value: String(roles.filter((r) => r.is_system).length), hint: 'Built-in roles' },
]);

const openEditDialog = (role: Role) => {
    editingRole.value = role;
    editingPermissions.value = role.permissions.map((p) => p.id);
};

const closeEditDialog = () => {
    editingRole.value = null;
};

const openViewDialog = (role: Role) => {
    viewingRole.value = role;
};

const closeViewDialog = () => {
    viewingRole.value = null;
};

const newPermissions = ref<number[]>([]);
const editingPermissions = ref<number[]>([]);

const deleteRole = (roleId: number) => {
    router.delete(RoleController.destroy(roleId), {
        onSuccess: () => {
            toast.success('Role deleted successfully');
        },
        onError: () => {
            toast.error('Failed to delete role');
        },
    });
};

const submitCreateForm = (event: Event) => {
    event.preventDefault();
    const form = event.target as HTMLFormElement;
    const formData = new FormData(form);
    newPermissions.value.forEach((id) => formData.append('permissions[]', id.toString()));
    router.post(RoleController.index.url(), formData, {
        onSuccess: () => {
            toast.success('Role created successfully');
            newPermissions.value = [];
            form.reset();
        },
        onError: () => {
            toast.error('Failed to create role');
        },
    });
};

// Removed legacy checked state helper
</script>

<template>
    <Head title="Roles" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6">
        <InternalPageHero
            kicker="Access control"
            title="Roles Management"
            description="Manage roles and their permissions for clinic access control."
            :metrics="roleHeroMetrics"
        />

        <div class="grid gap-5 xl:grid-cols-3">
            <section v-if="can('roles.create')" class="glass-panel-soft p-5 xl:col-span-1">
                <h3 class="pattern-typographic-title mb-4 text-[0.76rem]">
                    Create New Role
                </h3>

                <Form
                    :action="RoleController.index.url()"
                    method="post"
                    class="space-y-4"
                    reset-on-success
                    @submit="submitCreateForm"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="role_name">Role Name</Label>
                        <Input
                            id="role_name"
                            name="name"
                            required
                            placeholder="e.g., nurse, receptionist"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="role_description">Description</Label>
                        <textarea
                            id="role_description"
                            name="description"
                            rows="2"
                            class="pattern-field-clay"
                            placeholder="Role description..."
                        />
                    </div>

                    <div class="grid gap-2 rounded-xl border border-border/60 bg-background/40 p-3">
                        <Label class="text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                            Permissions
                        </Label>
                        <div class="max-h-48 space-y-2 overflow-y-auto">
                            <div v-for="(perms, group) in permissions" :key="group" class="mb-3">
                                <p class="mb-1 text-xs font-semibold capitalize text-foreground/70">
                                    {{ group }}
                                </p>
                                <div v-for="perm in perms" :key="perm.id" class="ms-3 flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        v-model="newPermissions"
                                        :value="perm.id"
                                        :id="`create_perm_${perm.id}`"
                                        class="size-4 rounded border-border"
                                    />
                                    <Label :for="`create_perm_${perm.id}`" class="text-xs font-normal">
                                        {{ perm.name }}
                                    </Label>
                                </div>
                            </div>
                        </div>
                        <InputError :message="errors.permissions" />
                        <!-- Hidden fields to submit permissions as permissions[] -->
                        <div v-for="id in newPermissions" :key="`hidden-${id}`" style="display:none">
                            <input type="hidden" name="permissions[]" :value="id" />
                        </div>
                    </div>

                    <Button
                        :disabled="processing"
                        variant="clay"
                        class="w-full"
                    >
                        Create Role
                    </Button>
                </Form>
            </section>

            <section :class="['glass-panel-soft p-5', can('roles.create') ? 'xl:col-span-2' : 'xl:col-span-3']">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                    <h3 class="pattern-typographic-title text-[0.76rem]">
                        Roles List
                    </h3>
                    <span class="text-xs text-muted-foreground">
                        Total: {{ roles.length }}
                    </span>
                </div>

                <div class="ui-table-shell">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th class="px-3 py-2">اسم الدور</th>
                                <th class="px-3 py-2">الوصف</th>
                                <th class="px-3 py-2">الحالة</th>
                                <th class="px-3 py-2">الصلاحيات</th>
                                <th class="px-3 py-2">المستخدمون</th>
                                <th class="px-3 py-2 text-right">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="role in roles" :key="role.id" class="ui-table-row">
                                <td class="px-3 py-2 font-medium" data-label="Name">
                                    <div class="flex items-center gap-2">
                                        <Shield class="size-4 text-muted-foreground" />
                                        <span class="font-semibold">{{ role.name }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2" data-label="Description">
                                    <span class="text-sm text-muted-foreground">
                                        {{ role.description ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2" data-label="Status">
                                    <span
                                        :class="[
                                            role.is_system
                                                ? 'border-info-300/70 bg-info-50 text-info-800 dark:border-info-500/40 dark:bg-info-500/15 dark:text-info-100'
                                                : 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/40 dark:bg-success-500/15 dark:text-success-100',
                                        ]"
                                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                                    >
                                        <span
                                            class="w-1.5 h-1.5 rounded-full"
                                            :class="role.is_system ? 'bg-info-500' : 'bg-success-500'"
                                        ></span>
                                        {{ role.is_system ? 'System' : 'Custom' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2" data-label="Permissions">
                                    <span class="text-sm font-medium">
                                        {{ role.permissions_count }}
                                    </span>
                                </td>
                                <td class="px-3 py-2" data-label="Users">
                                    <span class="text-sm font-medium">
                                        {{ role.users_count }}
                                    </span>
                                </td>
                                <td class="table-cell-actions px-3 py-2 md:text-right" data-label="Actions">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="neumorphic"
                                            size="sm"
                                            class="h-8 px-3 text-xs"
                                            @click="openViewDialog(role)"
                                        >
                                            View
                                        </Button>
                                        <Button
                                            v-if="can('roles.update') && !role.is_system"
                                            type="button"
                                            variant="clay"
                                            size="sm"
                                            class="h-8 px-3 text-xs"
                                            @click="openEditDialog(role)"
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            v-if="can('roles.delete') && !role.is_system && role.users_count === 0"
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 px-2 text-xs text-destructive"
                                            @click="deleteRole(role.id)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="roles.length === 0" class="table-empty-state">
                                <td :colspan="6" class="px-3 py-10 text-center text-muted-foreground">
                                    No roles found for this clinic.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <Dialog :open="viewingRole !== null" @update:open="(open) => !open && closeViewDialog()">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ viewingRole?.name ?? 'Role Details' }}
                    </DialogTitle>
                    <DialogDescription>
                        Role details and permissions.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="viewingRole" class="grid gap-4">
                    <div class="rounded-xl border border-border/70 bg-background/55 p-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">
                                    Role Name
                                </dt>
                                <dd class="text-sm font-semibold">
                                    {{ viewingRole.name }}
                                </dd>
                            </div>
                            <div class="space-y-1">
                                <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">
                                    Type
                                </dt>
                                <dd class="text-sm">
                                    <span
                                        :class="[
                                            viewingRole.is_system
                                                ? 'border-info-300/70 bg-info-50 text-info-800 dark:border-info-500/40 dark:bg-info-500/15 dark:text-info-100'
                                                : 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/40 dark:bg-success-500/15 dark:text-success-100',
                                        ]"
                                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                                    >
                                        <span
                                            class="w-1.5 h-1.5 rounded-full"
                                            :class="viewingRole.is_system ? 'bg-info-500' : 'bg-success-500'"
                                        ></span>
                                        {{ viewingRole.is_system ? 'System' : 'Custom' }}
                                    </span>
                                </dd>
                            </div>
                            <div class="space-y-1 sm:col-span-2">
                                <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">
                                    Description
                                </dt>
                                <dd class="text-sm text-muted-foreground">
                                    {{ viewingRole.description ?? 'No description' }}
                                </dd>
                            </div>
                            <div class="space-y-1">
                                <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">
                                    Permissions Count
                                </dt>
                                <dd class="text-sm font-medium">
                                    {{ viewingRole.permissions_count }}
                                </dd>
                            </div>
                            <div class="space-y-1">
                                <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">
                                    Users Assigned
                                </dt>
                                <dd class="text-sm font-medium">
                                    {{ viewingRole.users_count }}
                                </dd>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="closeViewDialog()">
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="editingRole !== null" @update:open="(open) => !open && closeEditDialog()">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Edit Role</DialogTitle>
                    <DialogDescription>
                        Update role details and permissions.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-if="editingRole"
                    v-bind="RoleController.update.form(editingRole.id)"
                    class="space-y-4"
                    @success="closeEditDialog"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="edit_role_name">Role Name</Label>
                        <Input
                            id="edit_role_name"
                            name="name"
                            :default-value="editingRole.name"
                            required
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_role_description">Description</Label>
                        <textarea
                            id="edit_role_description"
                            name="description"
                            rows="2"
                            class="pattern-field-clay"
                            :default-value="editingRole.description ?? ''"
                        />
                    </div>

                    <div class="grid gap-2 rounded-xl border border-border/60 bg-background/40 p-3">
                        <Label class="text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                            Permissions
                        </Label>
                        <div class="max-h-48 space-y-2 overflow-y-auto">
                            <div v-for="(perms, group) in permissions" :key="group" class="mb-3">
                                <p class="mb-1 text-xs font-semibold capitalize text-foreground/70">
                                    {{ group }}
                                </p>
                                <div v-for="perm in perms" :key="perm.id" class="ms-3 flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        v-model="editingPermissions"
                                        :value="perm.id"
                                        :id="`edit_perm_${perm.id}`"
                                        class="size-4 rounded border-border"
                                    />
                                    <Label :for="`edit_perm_${perm.id}`" class="text-xs font-normal">
                                        {{ perm.name }}
                                    </Label>
                                </div>
                            </div>
                        </div>
                        <InputError :message="errors.permissions" />
                        <!-- Hidden fields to submit permissions as permissions[] for edit -->
                        <div v-for="id in editingPermissions" :key="`hidden-edit-${id}`" style="display:none">
                            <input type="hidden" name="permissions[]" :value="id" />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" @click="closeEditDialog()">
                            Cancel
                        </Button>
                        <Button type="submit" variant="clay" :disabled="processing">
                            Save Changes
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
