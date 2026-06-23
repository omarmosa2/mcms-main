<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Eye,
    Filter,
    Pencil,
    Shield,
    Trash2,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { FilterBar, FilterSearch, FilterSelect } from '@/components/ui/filter';

type User = {
    id: number;
    name: string;
    email: string;
    username: string | null;
    clinic: { id: number; name: string } | null;
    is_active: boolean;
    is_super_admin: boolean;
    roles: string[];
    role_names: string[];
    two_factor_enabled: boolean;
    created_at: string | null;
};

type UserSortField = 'name' | 'email' | 'is_active' | 'created_at';
type SortDirection = 'asc' | 'desc';
type ActiveFilter = 'all' | 'active' | 'inactive';

const search = defineModel<string>('search', { default: '' });
const activeFilter = defineModel<ActiveFilter>('activeFilter', { default: 'all' });
const roleName = defineModel<string | null>('roleName', { default: null });
const rowsPerPage = defineModel<number>('rowsPerPage', { default: 15 });

const props = defineProps<{
    users: User[];
    page: number;
    totalPages: number;
    visibleFrom: number;
    visibleTo: number;
    total: number;
    sortBy: UserSortField;
    sortDirection: SortDirection;
    canView: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    roleOptions: { label: string; value: string }[];
}>();

const emit = defineEmits<{
    'toggle-sort': [field: UserSortField];
    'previous-page': [];
    'next-page': [];
    'reset-filters': [];
    'view': [user: User];
    'edit': [user: User];
    'delete': [userId: number];
}>();

const statusOptions = computed(() => [
    { label: 'الكل', value: 'all' },
    { label: 'نشط', value: 'active' },
    { label: 'غير نشط', value: 'inactive' },
]);

const activeFilters = computed(() => {
    const filters: { key: string; label: string; value: string | null }[] = [];

    if (search.value.trim()) {
        filters.push({ key: 'search', label: 'بحث', value: search.value.trim() });
    }

    if (roleName.value) {
        filters.push({ key: 'role_name', label: 'الدور', value: roleName.value });
    }

    if (activeFilter.value !== 'all') {
        filters.push({
            key: 'is_active',
            label: 'الحالة',
            value: activeFilter.value === 'active' ? 'نشط' : 'غير نشط',
        });
    }

    return filters;
});

const handleRemoveFilter = (key: string) => {
    if (key === 'search') {
        search.value = '';
    }

    if (key === 'role_name') {
        roleName.value = null;
    }

    if (key === 'is_active') {
        activeFilter.value = 'all';
    }
};

const statusClass = (isActive: boolean): string => {
    return isActive
        ? 'bg-accent text-primary'
        : 'bg-muted text-muted-foreground';
};

const sortMark = (field: UserSortField): string => {
    if (props.sortBy !== field) {
        return '↕';
    }

    return props.sortDirection === 'asc' ? '↑' : '↓';
};

const serialNumber = (index: number): number => {
    return props.visibleFrom + index;
};
</script>

<template>
    <div class="space-y-8">
        <section class="rounded-[1.45rem] border border-border bg-card/95 p-6 shadow-card-float">
            <div class="grid gap-4 lg:grid-cols-[1fr_14rem_14rem_9rem]">
                <FilterSearch
                    v-model="search"
                    placeholder="البحث بالاسم أو البريد..."
                />

                <FilterSelect
                    v-model="roleName"
                    :options="roleOptions"
                    placeholder="كل الأدوار"
                />

                <FilterSelect
                    v-model="activeFilter"
                    :options="statusOptions"
                    placeholder="الحالة"
                />

                <button
                    type="button"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl border border-border bg-card px-5 text-sm font-semibold text-foreground shadow-sm transition-all duration-200 hover:bg-muted hover:text-accent-foreground"
                    @click="emit('reset-filters')"
                >
                    <Filter class="size-4" />
                    تصفية
                </button>
            </div>

            <FilterBar
                v-if="activeFilters.length > 0"
                class="mt-4"
                :active-filters="activeFilters"
                @remove="handleRemoveFilter"
                @clear-all="emit('reset-filters')"
            />
        </section>

        <section class="overflow-hidden rounded-[1.35rem] border border-border bg-card/95 shadow-card-float">
            <div class="w-full overflow-hidden">
                <table class="w-full table-fixed border-separate border-spacing-0 [&_td]:align-middle [&_th]:align-middle">
                    <colgroup>
                        <col class="w-[4%]" />
                        <col class="w-[22%]" />
                        <col class="w-[20%]" />
                        <col class="w-[16%]" />
                        <col class="w-[16%]" />
                        <col class="w-[10%]" />
                        <col class="w-[12%]" />
                    </colgroup>
                    <thead>
                        <tr class="h-16 bg-muted">
                            <th class="px-2 py-3 text-center text-sm font-bold text-foreground">
                                #
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-center text-sm font-bold text-foreground transition-colors select-none hover:text-primary"
                                @click="emit('toggle-sort', 'name')"
                            >
                                الاسم
                                <span class="ms-1 text-muted-foreground/50">{{ sortMark('name') }}</span>
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-center text-sm font-bold text-foreground transition-colors select-none hover:text-primary"
                                @click="emit('toggle-sort', 'email')"
                            >
                                بيانات الدخول
                                <span class="ms-1 text-muted-foreground/50">{{ sortMark('email') }}</span>
                            </th>
                            <th class="px-3 py-3 text-center text-sm font-bold text-foreground">
                                العيادة
                            </th>
                            <th class="px-3 py-3 text-center text-sm font-bold text-foreground">
                                الأدوار
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-center text-sm font-bold text-foreground transition-colors select-none hover:text-primary"
                                @click="emit('toggle-sort', 'is_active')"
                            >
                                الحالة
                                <span class="ms-1 text-muted-foreground/50">{{ sortMark('is_active') }}</span>
                            </th>
                            <th class="px-3 py-3 text-center text-sm font-bold text-foreground">
                                الإجراءات
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(user, index) in users"
                            :key="user.id"
                            class="group border-b border-border transition-all duration-150 last:border-b-0 hover:bg-muted/50"
                        >
                            <td class="px-2 py-4 text-center text-sm font-semibold text-muted-foreground">
                                {{ serialNumber(index) }}
                            </td>
                            <td class="px-3 py-4 text-center">
                                <div class="flex items-center justify-center gap-3">
                                    <span
                                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground shadow-primary/20"
                                    >
                                        {{ user.name.charAt(0) }}
                                    </span>
                                    <div class="min-w-0 text-right">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <span class="truncate text-sm font-semibold text-foreground">{{ user.name }}</span>
                                            <Shield v-if="user.is_super_admin" class="size-3.5 text-warning" />
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-4 text-center">
                                <p class="truncate text-sm font-medium text-foreground">
                                    {{ user.username ?? user.email }}
                                </p>
                                <p v-if="user.username" class="truncate text-xs text-muted-foreground">
                                    {{ user.email }}
                                </p>
                            </td>
                            <td class="px-3 py-4 text-center text-sm text-muted-foreground">
                                {{ user.clinic?.name ?? '—' }}
                            </td>
                            <td class="px-3 py-4 text-center">
                                <div class="flex flex-wrap justify-center gap-1">
                                    <span
                                        v-for="roleName in user.role_names"
                                        :key="roleName"
                                        class="inline-flex items-center rounded-full bg-muted px-2.5 py-1 text-xs font-bold text-foreground"
                                    >
                                        {{ roleName }}
                                    </span>
                                    <span v-if="user.role_names.length === 0" class="text-xs text-muted-foreground/50">—</span>
                                </div>
                            </td>
                            <td class="px-3 py-4 text-center">
                                <span
                                    class="inline-flex min-w-20 items-center justify-center rounded-full px-3 py-1.5 text-xs font-bold"
                                    :class="statusClass(user.is_active)"
                                >
                                    {{ user.is_active ? 'نشط' : 'غير نشط' }}
                                </span>
                            </td>
                            <td class="px-3 py-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button
                                        v-if="canView"
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-primary transition-all duration-150 hover:bg-accent active:scale-[0.95]"
                                        title="عرض"
                                        @click="emit('view', user)"
                                    >
                                        <Eye class="size-4" />
                                    </button>
                                    <button
                                        v-if="canUpdate"
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-primary transition-all duration-150 hover:bg-accent active:scale-[0.95]"
                                        title="تعديل"
                                        @click="emit('edit', user)"
                                    >
                                        <Pencil class="size-4" />
                                    </button>
                                    <button
                                        v-if="canDelete"
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-destructive transition-all duration-150 hover:bg-destructive/10 active:scale-[0.95]"
                                        title="حذف"
                                        @click="emit('delete', user.id)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="users.length === 0">
                            <td colspan="7" class="px-5">
                                <div class="py-20 text-center">
                                    <h3 class="mb-2 text-base font-bold text-foreground">
                                        لا يوجد مستخدمين
                                    </h3>
                                    <p class="text-sm text-muted-foreground">
                                        غيّر التصفية أو أضف مستخدماً جديداً للبدء.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="flex flex-col gap-4 px-2 md:flex-row md:items-center md:justify-between">
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-border bg-card text-muted-foreground shadow-sm transition hover:text-accent-foreground disabled:opacity-40"
                        :disabled="page === 1"
                        @click="emit('previous-page')"
                    >
                        <ChevronsRight class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-border bg-card text-muted-foreground shadow-sm transition hover:text-accent-foreground disabled:opacity-40"
                        :disabled="page === 1"
                        @click="emit('previous-page')"
                    >
                        <ChevronRight class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-border bg-card text-muted-foreground shadow-sm transition hover:text-accent-foreground disabled:opacity-40"
                        :disabled="page >= totalPages"
                        @click="emit('next-page')"
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-border bg-card text-muted-foreground shadow-sm transition hover:text-accent-foreground disabled:opacity-40"
                        :disabled="page >= totalPages"
                        @click="emit('next-page')"
                    >
                        <ChevronsLeft class="size-4" />
                    </button>
                </div>

                <span class="text-sm font-semibold text-foreground">صفحة {{ page }} من {{ totalPages }}</span>

                <div class="flex items-center gap-3">
                    <select
                        v-model.number="rowsPerPage"
                        class="h-11 rounded-2xl border border-input bg-card px-4 text-sm font-semibold text-foreground shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/10 focus:outline-none"
                    >
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                    <span class="text-sm font-semibold text-foreground">عدد الصفوف لكل صفحة</span>
                </div>
            </div>

            <p class="text-sm font-medium text-muted-foreground">
                عرض {{ visibleFrom }} إلى {{ visibleTo }} من {{ total }} مستخدم
            </p>
        </div>
    </div>
</template>
