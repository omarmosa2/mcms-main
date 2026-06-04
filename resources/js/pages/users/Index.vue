<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, Plus, Shield } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Security/UserController';
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

const activeFilters = computed(() => {
    const filters: { key: string; label: string; value: string | null }[] = [];
    if (localSearch.value.trim()) {
        filters.push({ key: 'search', label: 'بحث', value: localSearch.value.trim() });
    }
    if (localRoleName.value) {
        const role = roles.find(r => r.name === localRoleName.value);
        filters.push({ key: 'role_name', label: 'الدور', value: role?.name || localRoleName.value });
    }
    if (localIsActive.value !== 'all') {
        filters.push({ key: 'is_active', label: 'الحالة', value: localIsActive.value === 'active' ? 'نشط' : 'غير نشط' });
    }
    return filters;
});

const roleOptions = computed(() =>
    roles.map(role => ({ label: role.name, value: role.name }))
);

const statusOptions = [
    { label: 'الكل', value: 'all' },
    { label: 'نشط', value: 'active' },
    { label: 'غير نشط', value: 'inactive' },
];

const handleRemoveFilter = (key: string) => {
    if (key === 'search') {
        localSearch.value = '';
    } else if (key === 'role_name') {
        localRoleName.value = null;
    } else if (key === 'is_active') {
        localIsActive.value = 'all';
    }
};

const sortIconFor = (field: UserSortField) => {
    if (localSortBy.value !== field) return ArrowUpDown;
    return localSortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

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

const closeEditUser = () => {
    editingUser.value = null;
};

const openViewUser = (user: User) => {
    viewingUser.value = user;
};

const closeViewUser = () => {
    viewingUser.value = null;
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

const bulkDeleteForm = UserController.bulkDestroy.form();

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

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="page-title">المستخدمين</h1>
                    <p class="mt-1 text-sm text-muted-foreground">إدارة حسابات المستخدمين والأدوار والصلاحيات.</p>
                </div>
                <span class="inline-flex items-center rounded-full border border-border/60 bg-muted/40 px-2.5 py-0.5 text-[0.7rem] font-medium text-muted-foreground">
                    {{ activeRoleLabel }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="can('users.create')"
                    variant="clay"
                    size="sm"
                    class="h-8 rounded-lg px-3 text-xs"
                    @click="isCreateSheetOpen = true"
                >
                    <Plus class="size-3.5" />
                    إنشاء مستخدم
                </Button>
            </div>
        </div>

        <UserTable
            :users="visibleUsers"
            :local-page="localPage"
            :total-pages="totalLocalPages"
            :local-search="localSearch"
            :local-is-active="localIsActive"
            :local-role-name="localRoleName"
            :local-rows-per-page="localRowsPerPage"
            :selected-ids="selectedUserIds"
            :are-all-selected="areAllUsersSelected"
            :can-view="can('users.view')"
            :can-update="can('users.update')"
            :can-delete="can('users.delete')"
            :active-filters="activeFilters"
            :role-options="roleOptions"
            :status-options="statusOptions"
            :sort-by="localSortBy"
            :sort-direction="localSortDirection"
            :local-visible-from="localVisibleFrom"
            :local-visible-to="localVisibleTo"
            :total="users.meta.total"
            :is-confirm-open="isConfirmOpen"
            :confirm-options="confirmOptions"
            @toggle-sort="toggleSort"
            @toggle-all-selection="toggleAllUsersSelection"
            @change-page="(page) => { localPage.value = page; reloadUsers({ page }); }"
            @change-rows-per-page="(v) => { localRowsPerPage.value = v; }"
            @open-view="openViewUser"
            @open-edit="openEditUser"
            @delete-user="deleteUser"
            @remove-filter="handleRemoveFilter"
            @clear-filters="resetLocalFilters"
            @confirm-delete="handleConfirmDelete"
            @cancel-delete="handleConfirmCancel"
            @update-confirm-open="(v) => { if (!v) handleConfirmCancel(); }"
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
