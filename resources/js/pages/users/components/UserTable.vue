<script setup lang="ts">
import UserController from '@/actions/App/Http/Controllers/Security/UserController';
import { ArrowDown, ArrowUp, ArrowUpDown, Shield } from 'lucide-vue-next';
import { computed } from 'vue';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import { Button } from '@/components/ui/button';
import {
    FilterBar,
    FilterSearch,
    FilterSelect,
} from '@/components/ui/filter';
import { Label } from '@/components/ui/label';
import { Form } from '@inertiajs/vue3';

type User = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    is_super_admin: boolean;
    roles: string[];
    role_names: string[];
    two_factor_enabled: boolean;
    created_at: string | null;
};

type Role = {
    id: number;
    name: string;
    description: string | null;
    is_system: boolean;
};

type UserSortField = 'name' | 'email' | 'is_active' | 'created_at';
type SortDirection = 'asc' | 'desc';
type ActiveFilter = 'all' | 'active' | 'inactive';

const props = defineProps<{
    users: User[]
    localPage: number
    totalPages: number
    localSearch: string
    localIsActive: ActiveFilter
    localRoleName: string | null
    localRowsPerPage: number
    selectedIds: number[]
    areAllSelected: boolean
    canView: boolean
    canUpdate: boolean
    canDelete: boolean
    activeFilters: { key: string; label: string; value: string | null }[]
    roleOptions: { label: string; value: string }[]
    statusOptions: { label: string; value: string }[]
    sortBy: UserSortField
    sortDirection: SortDirection
    localVisibleFrom: number
    localVisibleTo: number
    total: number
    isConfirmOpen: boolean
    confirmOptions: any
}>();

const emit = defineEmits<{
    'toggle-sort': [field: UserSortField]
    'toggle-all-selection': [event: Event]
    'change-page': [page: number]
    'change-rows-per-page': [value: number]
    'open-view': [user: User]
    'open-edit': [user: User]
    'delete-user': [userId: number]
    'remove-filter': [key: string]
    'clear-filters': []
    'confirm-delete': []
    'cancel-delete': []
    'update-confirm-open': [value: boolean]
}>();

const sortIconFor = (field: UserSortField) => {
    if (props.sortBy !== field) {
        return ArrowUpDown;
    }
    return props.sortDirection === 'asc' ? ArrowUp : ArrowDown;
};
</script>

<template>
    <div class="glass-panel-soft p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
            <h3 class="pattern-typographic-title text-[0.76rem]">قائمة المستخدمين</h3>
            <span class="text-xs text-muted-foreground">الإجمالي: {{ total }}</span>
        </div>

        <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
            <div class="grid gap-3 md:grid-cols-[repeat(4,minmax(0,1fr))] md:items-end">
                <div class="grid gap-2">
                    <Label for="users_search">بحث</Label>
                    <FilterSearch
                        id="users_search"
                        :model-value="localSearch"
                        placeholder="الاسم أو البريد..."
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="users_role">الدور</Label>
                    <FilterSelect
                        id="users_role"
                        :model-value="localRoleName"
                        :options="roleOptions"
                        placeholder="كل الأدوار"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="users_status">الحالة</Label>
                    <FilterSelect
                        id="users_status"
                        :model-value="localIsActive"
                        :options="statusOptions"
                        placeholder="الكل"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="users_per_page">صفوف</Label>
                    <select
                        id="users_per_page"
                        :model-value="localRowsPerPage"
                        @change="emit('change-rows-per-page', Number(($event.target as HTMLSelectElement).value))"
                        class="pattern-field-clay h-9 px-3 py-1.5"
                    >
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                </div>
            </div>

            <FilterBar
                v-if="activeFilters.length > 0"
                :active-filters="activeFilters"
                @remove="(key) => emit('remove-filter', key)"
                @clear-all="emit('clear-filters')"
            />
        </div>

        <Form
            v-if="canDelete && selectedIds.length > 0"
            v-bind="UserController.bulkDestroy.form()"
            class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3"
            v-slot="{ processing }"
        >
            <input
                v-for="userId in selectedIds"
                :key="`selected-user-${userId}`"
                type="hidden"
                name="ids[]"
                :value="userId"
            />
            <Button
                type="submit"
                variant="destructive"
                size="sm"
                :disabled="processing"
            >
                حذف المحدد ({{ selectedIds.length }})
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="emit('toggle-all-selection', new Event('change'))"
            >
                إلغاء التحديد
            </Button>
        </Form>

        <div class="ui-table-shell">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th v-if="canDelete" class="px-3 py-2">
                            <input
                                type="checkbox"
                                class="size-4 rounded border-border"
                                :checked="areAllSelected"
                                @change="emit('toggle-all-selection', $event)"
                            />
                        </th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'name')"
                            >
                                الاسم
                                <component :is="sortIconFor('name')" class="size-3.5" />
                            </button>
                        </th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'email')"
                            >
                                البريد الإلكتروني
                                <component :is="sortIconFor('email')" class="size-3.5" />
                            </button>
                        </th>
                        <th class="px-3 py-2">الأدوار</th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'is_active')"
                            >
                                الحالة
                                <component :is="sortIconFor('is_active')" class="size-3.5" />
                            </button>
                        </th>
                        <th class="px-3 py-2 text-start">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users"
                        :key="user.id"
                        class="ui-table-row"
                    >
                        <td v-if="canDelete" class="px-3 py-2" data-label="تحديد">
                            <input
                                :model-value="selectedIds"
                                type="checkbox"
                                class="size-4 rounded border-border"
                                :checked="selectedIds.includes(user.id)"
                                @change="$event.target.checked ? emit('toggle-all-selection', $event) : null"
                                :value="user.id"
                            />
                        </td>
                        <td class="px-3 py-2 font-medium" data-label="الاسم">
                            <div class="flex items-center gap-2">
                                {{ user.name }}
                                <Shield v-if="user.is_super_admin" class="size-4 text-amber-500" />
                            </div>
                        </td>
                        <td class="px-3 py-2" data-label="البريد الإلكتروني">
                            {{ user.email }}
                        </td>
                        <td class="px-3 py-2" data-label="الأدوار">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="roleName in user.role_names"
                                    :key="roleName"
                                    class="rounded-full border border-border/70 bg-background/80 px-2 py-0.5 text-xs"
                                >
                                    {{ roleName }}
                                </span>
                            </div>
                        </td>
                        <td class="px-3 py-2" data-label="الحالة">
                            <span
                                :class="[
                                    user.is_active
                                        ? 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/35 dark:bg-success-500/15 dark:text-success-100'
                                        : 'border-destructive/70 bg-destructive/10 text-destructive dark:border-destructive/35 dark:bg-destructive/15 dark:text-destructive-foreground',
                                ]"
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                            >
                                <span
                                    class="w-1.5 h-1.5 rounded-full"
                                    :class="user.is_active ? 'bg-success-500' : 'bg-destructive'"
                                ></span>
                                {{ user.is_active ? 'نشط' : 'غير نشط' }}
                            </span>
                        </td>
                        <td class="table-cell-actions px-3 py-2 md:text-start" data-label="الإجراءات">
                            <div class="flex flex-wrap justify-end gap-2">
                                <Button
                                    v-if="canView"
                                    type="button"
                                    variant="neumorphic"
                                    size="sm"
                                    class="h-8 px-3 text-xs"
                                    @click="emit('open-view', user)"
                                >
                                    عرض
                                </Button>
                                <Button
                                    v-if="canUpdate"
                                    type="button"
                                    variant="neumorphic"
                                    size="sm"
                                    class="h-8 px-3 text-xs"
                                    @click="emit('open-edit', user)"
                                >
                                    تعديل
                                </Button>
                                <Button
                                    v-if="canDelete"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 px-3 text-xs text-destructive"
                                    @click="emit('delete-user', user.id)"
                                >
                                    حذف
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="users.length === 0" class="table-empty-state">
                        <td :colspan="canDelete ? 6 : 5" class="px-3 py-10 text-center text-muted-foreground">
                            لا يوجد مستخدمين.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2">
            <p class="text-xs text-muted-foreground">
                عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من {{ total }}
            </p>
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="neumorphic"
                    size="sm"
                    class="h-8 px-3 text-xs"
                    :disabled="localPage === 1"
                    @click="emit('change-page', localPage - 1)"
                >
                    السابق
                </Button>
                <span class="text-xs font-semibold text-foreground/85">
                    صفحة {{ localPage }} / {{ totalPages }}
                </span>
                <Button
                    type="button"
                    variant="neumorphic"
                    size="sm"
                    class="h-8 px-3 text-xs"
                    :disabled="localPage >= totalPages"
                    @click="emit('change-page', localPage + 1)"
                >
                    التالي
                </Button>
            </div>
        </div>
    </div>
</template>
