<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, Plus, Shield } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Security/UserController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    FilterBar,
    FilterSearch,
    FilterSelect,
} from '@/components/ui/filter';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';

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
    if (filters.is_active === true) {
        return 'active';
    }

    if (filters.is_active === false) {
        return 'inactive';
    }

    return 'all';
};

const localIsActive = ref<ActiveFilter>(resolveInitialActiveFilter());
const localRoleName = ref<string | null>(filters.role_name ?? null);

const allowedSortFields: UserSortField[] = ['name', 'email', 'is_active', 'created_at'];

const resolveInitialSortBy = (): UserSortField => {
    const sortBy = filters.sort_by;

    if (sortBy !== null && allowedSortFields.includes(sortBy as UserSortField)) {
        return sortBy;
    }

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
        if (filtersDebounceTimeout !== null) {
            clearTimeout(filtersDebounceTimeout);
        }

        filtersDebounceTimeout = setTimeout(executeReload, 300);

        return;
    }

    executeReload();
};

const sortIconFor = (field: UserSortField) => {
    if (localSortBy.value !== field) {
        return ArrowUpDown;
    }

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

const goToPreviousPage = () => {
    if (localPage.value <= 1) {
        return;
    }

    localPage.value -= 1;
    reloadUsers({ page: localPage.value });
};

const goToNextPage = () => {
    if (localPage.value >= totalLocalPages.value) {
        return;
    }

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
    reloadUsers({
        page: 1,
        sort_by: localSortBy.value,
        sort_direction: localSortDirection.value,
    });
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
    if (filtersDebounceTimeout !== null) {
        clearTimeout(filtersDebounceTimeout);
    }
});

const selectableUserIds = computed<number[]>(() =>
    visibleUsers.value.map((user) => user.id),
);

const areAllUsersSelected = computed<boolean>(() => {
    if (selectableUserIds.value.length === 0) {
        return false;
    }

    return selectableUserIds.value.every((id) =>
        selectedUserIds.value.includes(id),
    );
});

watch(selectableUserIds, (ids) => {
    selectedUserIds.value = selectedUserIds.value.filter((id) => ids.includes(id));
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
            onSuccess: () => {
                toast.success('تم حذف المستخدم بنجاح');
            },
            onError: () => {
                toast.error('فشل حذف المستخدم');
            },
        });
    }
};
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

        <div class="glass-panel-soft p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                <h3 class="pattern-typographic-title text-[0.76rem]">قائمة المستخدمين</h3>
                <span class="text-xs text-muted-foreground">الإجمالي: {{ users.meta.total }}</span>
            </div>

            <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
                <div class="grid gap-3 md:grid-cols-[repeat(4,minmax(0,1fr))] md:items-end">
                    <div class="grid gap-2">
                        <Label for="users_search">بحث</Label>
                        <FilterSearch
                            id="users_search"
                            v-model="localSearch"
                            placeholder="الاسم أو البريد..."
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="users_role">الدور</Label>
                        <FilterSelect
                            id="users_role"
                            v-model="localRoleName"
                            :options="roleOptions"
                            placeholder="كل الأدوار"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="users_status">الحالة</Label>
                        <FilterSelect
                            id="users_status"
                            v-model="localIsActive"
                            :options="statusOptions"
                            placeholder="الكل"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="users_per_page">صفوف</Label>
                        <select
                            id="users_per_page"
                            v-model.number="localRowsPerPage"
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
                    @remove="handleRemoveFilter"
                    @clear-all="resetLocalFilters"
                />
            </div>

            <Form
                v-if="can('users.delete') && selectedUserIds.length > 0"
                v-bind="UserController.bulkDestroy.form()"
                class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3"
                v-slot="{ processing }"
            >
                <input
                    v-for="userId in selectedUserIds"
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
                    حذف المحدد ({{ selectedUserIds.length }})
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="clearSelectedUsers"
                >
                    إلغاء التحديد
                </Button>
            </Form>

            <div class="ui-table-shell">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th v-if="can('users.delete')" class="px-3 py-2">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-border"
                                    :checked="areAllUsersSelected"
                                    @change="toggleAllUsersSelection"
                                />
                            </th>
                            <th class="px-3 py-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="toggleSort('name')"
                                >
                                    الاسم
                                    <component :is="sortIconFor('name')" class="size-3.5" />
                                </button>
                            </th>
                            <th class="px-3 py-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="toggleSort('email')"
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
                                    @click="toggleSort('is_active')"
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
                            v-for="user in visibleUsers"
                            :key="user.id"
                            class="ui-table-row"
                        >
                            <td v-if="can('users.delete')" class="px-3 py-2" data-label="تحديد">
                                <input
                                    v-model="selectedUserIds"
                                    type="checkbox"
                                    class="size-4 rounded border-border"
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
                                        v-if="can('users.view')"
                                        type="button"
                                        variant="neumorphic"
                                        size="sm"
                                        class="h-8 px-3 text-xs"
                                        @click="openViewUser(user)"
                                    >
                                        عرض
                                    </Button>
                                    <Button
                                        v-if="can('users.update')"
                                        type="button"
                                        variant="neumorphic"
                                        size="sm"
                                        class="h-8 px-3 text-xs"
                                        @click="openEditUser(user)"
                                    >
                                        تعديل
                                    </Button>
                                    <Button
                                        v-if="can('users.delete')"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 px-3 text-xs text-destructive"
                                        @click="deleteUser(user.id)"
                                    >
                                        حذف
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="visibleUsers.length === 0" class="table-empty-state">
                            <td :colspan="can('users.delete') ? 6 : 5" class="px-3 py-10 text-center text-muted-foreground">
                                لا يوجد مستخدمين.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2">
                <p class="text-xs text-muted-foreground">
                    عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من {{ users.meta.total }}
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
        </div>

        <Sheet :open="isCreateSheetOpen" @update:open="isCreateSheetOpen = $event">
            <SheetContent side="left" class="w-full sm:max-w-lg">
                <SheetHeader class="text-right">
                    <SheetTitle>إنشاء مستخدم</SheetTitle>
                    <SheetDescription>إضافة مستخدم جديد إلى النظام.</SheetDescription>
                </SheetHeader>

                <Form
                    v-bind="UserController.store.form()"
                    class="mt-6 space-y-4"
                    reset-on-success
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="name">الاسم الكامل</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            placeholder="الاسم الكامل"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">البريد الإلكتروني</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            required
                            placeholder="example@domain.com"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="role_name">الدور</Label>
                        <select
                            id="role_name"
                            name="role_name"
                            required
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="">اختر دوراً</option>
                            <option
                                v-for="role in roles"
                                :key="role.id"
                                :value="role.name"
                            >
                                {{ role.name }}
                            </option>
                        </select>
                        <InputError :message="errors.role_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">كلمة المرور</Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            placeholder="اتركه فارغاً للتوليد التلقائي"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.password" />
                        <p class="text-xs text-muted-foreground">
                            8 أحرف على الأقل، أو اتركه فارغاً للتوليد التلقائي.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="is_active"
                            name="is_active"
                            type="checkbox"
                            value="1"
                            checked
                            class="size-4 rounded border-border"
                        />
                        <Label for="is_active" class="text-sm font-normal">
                            حساب نشط
                        </Label>
                    </div>

                    <Button :disabled="processing" variant="clay" class="w-full">
                        <Plus class="ms-2 size-4" />
                        إنشاء مستخدم
                    </Button>
                </Form>
            </SheetContent>
        </Sheet>

        <Dialog :open="viewingUser !== null" @update:open="(open) => !open && closeViewUser()">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>تفاصيل المستخدم</DialogTitle>
                    <DialogDescription>{{ viewingUser?.name }} - {{ viewingUser?.email }}</DialogDescription>
                </DialogHeader>

                <div v-if="viewingUser" class="grid gap-4">
                    <dl class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4 sm:grid-cols-2">
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">الاسم الكامل</dt>
                            <dd class="text-sm font-semibold">{{ viewingUser.name }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">البريد الإلكتروني</dt>
                            <dd class="text-sm">{{ viewingUser.email }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">الحالة</dt>
                            <dd>
                                <span
                                    :class="[
                                        viewingUser.is_active
                                            ? 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/35 dark:bg-success-500/15 dark:text-success-100'
                                            : 'border-destructive/70 bg-destructive/10 text-destructive dark:border-destructive/35 dark:bg-destructive/15 dark:text-destructive-foreground',
                                    ]"
                                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                                >
                                    <span
                                        class="w-1.5 h-1.5 rounded-full"
                                        :class="viewingUser.is_active ? 'bg-success-500' : 'bg-destructive'"
                                    ></span>
                                    {{ viewingUser.is_active ? 'نشط' : 'غير نشط' }}
                                </span>
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">مدير النظام</dt>
                            <dd class="text-sm">{{ viewingUser.is_super_admin ? 'نعم' : 'لا' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">المصادقة الثنائية</dt>
                            <dd class="text-sm">{{ viewingUser.two_factor_enabled ? 'مفعّلة' : 'غير مفعّلة' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">تاريخ الإنشاء</dt>
                            <dd class="text-sm">{{ viewingUser.created_at ?? '-' }}</dd>
                        </div>
                        <div class="space-y-1 sm:col-span-2">
                            <dt class="text-[0.65rem] font-semibold tracking-[0.1em] text-muted-foreground uppercase">الأدوار</dt>
                            <dd>
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="roleName in viewingUser.role_names"
                                        :key="roleName"
                                        class="rounded-full border border-border/70 bg-background/80 px-2 py-0.5 text-xs"
                                    >
                                        {{ roleName }}
                                    </span>
                                    <span v-if="viewingUser.role_names.length === 0" class="text-sm text-muted-foreground">لا توجد أدوار</span>
                                </div>
                            </dd>
                        </div>
                    </dl>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="closeViewUser()">إغلاق</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="editingUser !== null" @update:open="(open) => !open && closeEditUser()">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>تعديل المستخدم</DialogTitle>
                    <DialogDescription>تعديل بيانات المستخدم والأدوار.</DialogDescription>
                </DialogHeader>

                <Form
                    v-if="editingUser"
                    v-bind="UserController.update.form(editingUser.id)"
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="edit_name">الاسم الكامل</Label>
                        <Input
                            id="edit_name"
                            name="name"
                            :default-value="editingUser.name"
                            required
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_email">البريد الإلكتروني</Label>
                        <Input
                            id="edit_email"
                            name="email"
                            type="email"
                            :default-value="editingUser.email"
                            required
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_role_name">الدور</Label>
                        <select
                            id="edit_role_name"
                            name="role_name"
                            required
                            class="pattern-field-clay h-9 px-3 py-1.5"
                        >
                            <option value="">اختر دوراً</option>
                            <option
                                v-for="role in roles"
                                :key="role.id"
                                :value="role.name"
                                :selected="editingUser.role_names.includes(role.name)"
                            >
                                {{ role.name }}
                            </option>
                        </select>
                        <InputError :message="errors.role_name" />
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="edit_is_active"
                            name="is_active"
                            type="checkbox"
                            value="1"
                            :checked="editingUser.is_active"
                            class="size-4 rounded border-border"
                        />
                        <Label for="edit_is_active" class="text-sm font-normal">
                            حساب نشط
                        </Label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" @click="closeEditUser()">إلغاء</Button>
                        <Button type="submit" variant="clay" :disabled="processing">حفظ التغييرات</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <ConfirmationDialog
            :open="isConfirmOpen"
            :options="confirmOptions"
            @confirm="handleConfirmDelete"
            @cancel="handleConfirmCancel"
            @update:open="handleConfirmCancel"
        />
    </div>
</template>
