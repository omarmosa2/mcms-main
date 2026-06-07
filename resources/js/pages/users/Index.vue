<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Security/UserController';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';
import UserViewDialog from './components/UserViewDialog.vue';
import UserEditDialog from './components/UserEditDialog.vue';
import UserCreateSheet from './components/UserCreateSheet.vue';
import UserTable from './components/UserTable.vue';

type Role = {
    id: number;
    name: string;
    description: string | null;
    is_system: boolean;
};

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

type UserSortField = 'name' | 'email' | 'is_active' | 'created_at';
type SortDirection = 'asc' | 'desc';
type ActiveFilter = 'all' | 'active' | 'inactive';

const { users, roles, filters } = defineProps<{
    users: PaginatedResponse<User>;
    roles: Role[];
    filters: {
        search: string | null;
        role_name: string | null;
        is_active: boolean | null;
        per_page: number;
        sort_by: UserSortField | null;
        sort_direction: SortDirection | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'المستخدمين',
                href: UserController.index(),
            },
        ],
    },
});

const { can } = usePermissions();
const { isOpen: isConfirmOpen, options: confirmOptions, confirm, handleConfirm: handleConfirmDelete, handleCancel: handleConfirmCancel } = useConfirm();
const toast = useToast();
const page = usePage();

const roleNames = computed<string[]>(() => {
    return (
        ((page.props.auth as { roles?: string[] } | undefined)?.roles ?? [])
            .filter((value): value is string => typeof value === 'string')
    );
});

const primaryRole = computed<string>(() => {
    const rolePriority = [
        'super_admin',
        'admin',
        'clinic_admin',
        'doctor',
        'receptionist',
        'accountant',
    ];
    return rolePriority.find((role) => roleNames.value.includes(role)) ?? 'staff';
});

const roleLabels: Record<string, string> = {
    super_admin: 'مدير النظام',
    admin: 'مدير',
    clinic_admin: 'مدير العيادة',
    doctor: 'طبيب',
    receptionist: 'استقبال',
    accountant: 'محاسب',
    staff: 'موظف',
};

const activeRoleLabel = computed<string>(() => roleLabels[primaryRole.value] ?? roleLabels.staff);

const selectedUserIds = ref<number[]>([]);
const viewingUser = ref<User | null>(null);
const editingUser = ref<User | null>(null);
const isCreateSheetOpen = ref(false);

const localSearch = ref<string>(filters.search ?? '');
const localRowsPerPage = ref<number>(filters.per_page);
const localPage = ref<number>(users.meta.current_page);

const resolveInitialActiveFilter = (): ActiveFilter => {
    if (filters.is_active === true) return 'active';
    if (filters.is_active === false) return 'inactive';
    return 'all';
};

const localIsActive = ref<ActiveFilter>(resolveInitialActiveFilter());
const localRoleName = ref<string | null>(filters.role_name ?? null);

const allowedSortFields: UserSortField[] = ['name', 'email', 'is_active', 'created_at'];

const resolveInitialSortBy = (): UserSortField => {
    const sortBy = filters.sort_by;
    if (sortBy !== null && allowedSortFields.includes(sortBy as UserSortField)) return sortBy;
    return 'name';
};

const localSortBy = ref<UserSortField>(resolveInitialSortBy());
const localSortDirection = ref<SortDirection>(
    filters.sort_direction === 'desc' ? 'desc' : 'asc',
);

const visibleUsers = computed<User[]>(() => users.data);
const totalLocalPages = computed<number>(() => Math.max(1, users.meta.last_page));
const localVisibleFrom = computed<number>(() => users.meta.from ?? 0);
const localVisibleTo = computed<number>(() => users.meta.to ?? 0);

const defaultRowsPerPage = 15;
let filtersDebounceTimeout: ReturnType<typeof setTimeout> | null = null;

const buildIndexQuery = (
    overrides: Partial<{
        search: string;
        role_name: string | null;
        is_active: ActiveFilter;
        per_page: number;
        page: number;
        sort_by: UserSortField;
        sort_direction: SortDirection;
    }> = {},
) => {
    const activeQuery = (overrides.is_active ?? localIsActive.value) === 'all'
        ? undefined
        : (overrides.is_active ?? localIsActive.value) === 'active';
    return {
        search: (overrides.search ?? localSearch.value).trim(),
        role_name: overrides.role_name ?? localRoleName.value ?? undefined,
        is_active: activeQuery,
        per_page: overrides.per_page ?? localRowsPerPage.value,
        page: overrides.page ?? localPage.value,
        sort_by: overrides.sort_by ?? localSortBy.value,
        sort_direction: overrides.sort_direction ?? localSortDirection.value,
    };
};

const reloadUsers = (
    overrides: Partial<{
        search: string;
        role_name: string | null;
        is_active: ActiveFilter;
        per_page: number;
        page: number;
        sort_by: UserSortField;
        sort_direction: SortDirection;
    }> = {},
    debounce = false,
) => {
    const executeReload = () => {
        router.cancelAll();
        router.get(UserController.index.url(), buildIndexQuery(overrides), {
            only: ['users', 'roles', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };
    if (debounce) {
        if (filtersDebounceTimeout !== null) clearTimeout(filtersDebounceTimeout);
        filtersDebounceTimeout = setTimeout(executeReload, 300);
        return;
    }
    executeReload();
};

const selectableUserIds = computed<number[]>(() =>
    visibleUsers.value.map((user) => user.id),
);

const areAllUsersSelected = computed<boolean>(() => {
    if (selectableUserIds.value.length === 0) return false;
    return selectableUserIds.value.every((id) =>
        selectedUserIds.value.includes(id),
    );
});

const toggleAllUsersSelection = (event: Event) => {
    const target = event.target as HTMLInputElement;
    selectedUserIds.value = target.checked ? [...selectableUserIds.value] : [];
};

const clearSelectedUsers = () => {
    selectedUserIds.value = [];
};

const roleOptions = computed(() =>
    roles.map(role => ({ label: role.name, value: role.name }))
);

const toggleSort = (field: UserSortField) => {
    if (localSortBy.value === field) {
        localSortDirection.value = localSortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        localSortBy.value = field;
        localSortDirection.value = 'asc';
    }
};

const goToPreviousPage = () => {
    if (localPage.value <= 1) return;
    localPage.value -= 1;
    reloadUsers({ page: localPage.value });
};

const goToNextPage = () => {
    if (localPage.value >= totalLocalPages.value) return;
    localPage.value += 1;
    reloadUsers({ page: localPage.value });
};

watch(
    () => [filters.search, filters.per_page, users.meta.current_page],
    () => {
        localSearch.value = filters.search ?? '';
        localRowsPerPage.value = filters.per_page;
        localPage.value = users.meta.current_page;
    },
    { immediate: true },
);

watch(localSearch, () => {
    localPage.value = 1;
    reloadUsers({ page: 1, search: localSearch.value.trim() }, true);
});

watch(localRowsPerPage, () => {
    localPage.value = 1;
    reloadUsers({ page: 1, per_page: localRowsPerPage.value });
});

watch([localSortBy, localSortDirection], () => {
    localPage.value = 1;
    reloadUsers({ page: 1, sort_by: localSortBy.value, sort_direction: localSortDirection.value });
});

watch(localIsActive, () => {
    localPage.value = 1;
    reloadUsers({ page: 1, is_active: localIsActive.value });
});

watch(localRoleName, () => {
    localPage.value = 1;
    reloadUsers({ page: 1, role_name: localRoleName.value });
});

onBeforeUnmount(() => {
    if (filtersDebounceTimeout !== null) clearTimeout(filtersDebounceTimeout);
});

const openEditUser = (user: User) => {
    editingUser.value = user;
};

const openViewUser = (user: User) => {
    viewingUser.value = user;
};

const deleteUser = async (userId: number) => {
    const confirmed = await confirm({
        title: 'حذف المستخدم',
        description: 'هل أنت متأكد من حذف هذا المستخدم؟ لا يمكن التراجع عن هذا الإجراء.',
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });
    if (confirmed) {
        router.delete(UserController.destroy(userId), {
            onSuccess: () => { toast.success('تم حذف المستخدم بنجاح'); },
            onError: () => { toast.error('فشل حذف المستخدم'); },
        });
    }
};

const resetLocalFilters = () => {
    localSearch.value = '';
    localIsActive.value = 'all';
    localRoleName.value = null;
    localRowsPerPage.value = defaultRowsPerPage;
    localSortBy.value = 'name';
    localSortDirection.value = 'asc';
    localPage.value = 1;
    reloadUsers({
        search: '',
        role_name: null,
        is_active: 'all',
        per_page: defaultRowsPerPage,
        page: 1,
        sort_by: 'name',
        sort_direction: 'asc',
    });
};

watch(selectableUserIds, (ids) => {
    selectedUserIds.value = selectedUserIds.value.filter((id) => ids.includes(id));
});
</script>

<template>
    <Head title="المستخدمين" />

    <div class="container-modern space-y-8 py-5" dir="rtl">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex items-center gap-4">
                <div>
                    <h1 class="page-title text-[2.35rem]">إدارة المستخدمين</h1>
                    <p class="page-subtitle mt-2 text-base">إدارة حسابات المستخدمين والأدوار والصلاحيات</p>
                </div>
                <span class="inline-flex items-center rounded-full border border-[#DDE9F3] bg-white px-3 py-1 text-xs font-semibold text-[#6C7F95]">
                    {{ activeRoleLabel }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="can('users.create')"
                    variant="default"
                    size="lg"
                    class="h-12 rounded-2xl bg-[#0EA5E9] px-6 text-sm font-bold text-white shadow-[0_18px_34px_-22px_rgb(14_165_233_/_0.9)] hover:bg-[#0284C7]"
                    @click="isCreateSheetOpen = true"
                >
                    <Plus class="size-4" />
                    إنشاء مستخدم
                </Button>
            </div>
        </div>

        <UserTable
            v-model:search="localSearch"
            v-model:active-filter="localIsActive"
            v-model:role-name="localRoleName"
            v-model:rows-per-page="localRowsPerPage"
            v-model:selected-ids="selectedUserIds"
            :users="visibleUsers"
            :page="localPage"
            :total-pages="totalLocalPages"
            :visible-from="localVisibleFrom"
            :visible-to="localVisibleTo"
            :total="users.meta.total"
            :sort-by="localSortBy"
            :sort-direction="localSortDirection"
            :are-all-selected="areAllUsersSelected"
            :can-view="can('users.view')"
            :can-update="can('users.update')"
            :can-delete="can('users.delete')"
            :role-options="roleOptions"
            @toggle-sort="toggleSort"
            @previous-page="goToPreviousPage"
            @next-page="goToNextPage"
            @reset-filters="resetLocalFilters"
            @toggle-all-selection="toggleAllUsersSelection"
            @clear-selection="clearSelectedUsers"
            @view="openViewUser"
            @edit="openEditUser"
            @delete="deleteUser"
        />

        <UserCreateSheet
            :open="isCreateSheetOpen"
            :roles="roles"
            @update:open="isCreateSheetOpen = $event"
        />

        <UserViewDialog
            :user="viewingUser"
            @close="viewingUser = null"
        />

        <UserEditDialog
            :user="editingUser"
            :roles="roles"
            @close="editingUser = null"
        />

        <ConfirmationDialog
            :open="isConfirmOpen"
            :options="confirmOptions"
            @confirm="handleConfirmDelete"
            @cancel="handleConfirmCancel"
            @update:open="handleConfirmCancel"
        />
    </div>
</template>
