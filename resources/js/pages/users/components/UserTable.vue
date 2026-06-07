<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
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
import UserController from '@/actions/App/Http/Controllers/Security/UserController';
import { Button } from '@/components/ui/button';
import { FilterBar, FilterSearch, FilterSelect } from '@/components/ui/filter';

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

type UserSortField = 'name' | 'email' | 'is_active' | 'created_at';
type SortDirection = 'asc' | 'desc';
type ActiveFilter = 'all' | 'active' | 'inactive';

const search = defineModel<string>('search', { default: '' });
const activeFilter = defineModel<ActiveFilter>('activeFilter', { default: 'all' });
const roleName = defineModel<string | null>('roleName', { default: null });
const rowsPerPage = defineModel<number>('rowsPerPage', { default: 15 });
const selectedIds = defineModel<number[]>('selectedIds', { default: () => [] });

const props = defineProps<{
    users: User[];
    page: number;
    totalPages: number;
    visibleFrom: number;
    visibleTo: number;
    total: number;
    sortBy: UserSortField;
    sortDirection: SortDirection;
    areAllSelected: boolean;
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
    'toggle-all-selection': [event: Event];
    'clear-selection': [];
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
        ? 'bg-[#DBEAFE] text-[#1D4ED8]'
        : 'bg-[#F4F7FA] text-[#6B7280]';
};

const sortMark = (field: UserSortField): string => {
    if (props.sortBy !== field) {
        return '↕';
    }

    return props.sortDirection === 'asc' ? '↑' : '↓';
};

const toggleUserSelection = (userId: number, checked: boolean) => {
    if (checked) {
        if (!selectedIds.value.includes(userId)) {
            selectedIds.value = [...selectedIds.value, userId];
        }
    } else {
        selectedIds.value = selectedIds.value.filter((id) => id !== userId);
    }
};
</script>

<template>
    <div class="space-y-8">
        <section class="rounded-[1.45rem] border border-[#E2ECF6] bg-white/95 p-6 shadow-card-float">
            <div class="grid gap-4 lg:grid-cols-[1fr_14rem_14rem_9rem]">
                <FilterSearch
                    v-model="search"
                    placeholder="البحث باسم المستخدم أو البريد..."
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
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl border border-[#E8EEF6] bg-white px-5 text-sm font-semibold text-[#1A2B3F] shadow-[0_10px_24px_-24px_rgb(15_42_71_/_0.35)] transition-all duration-200 hover:bg-[#F7FBFE] hover:text-[#075985]"
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

        <Form
            v-if="canDelete && selectedIds.length > 0"
            v-bind="UserController.bulkDestroy.form()"
            class="flex items-center gap-3 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] p-4 shadow-card"
            v-slot="{ processing }"
        >
            <input
                v-for="userId in selectedIds"
                :key="`selected-user-${userId}`"
                type="hidden"
                name="ids[]"
                :value="userId"
            />

            <p class="flex-1 text-sm font-semibold text-[#DC2626]">
                {{ selectedIds.length }} مستخدم محدد
            </p>
            <Button
                type="submit"
                variant="destructive"
                size="sm"
                class="h-9 rounded-xl px-4 text-xs"
                :disabled="processing"
            >
                حذف
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-9 rounded-xl px-4 text-xs text-[#6B7280] hover:bg-white"
                @click="emit('clear-selection')"
            >
                إلغاء
            </Button>
        </Form>

        <section class="overflow-hidden rounded-[1.35rem] border border-[#DDE8F3] bg-white/95 shadow-card-float">
            <div class="w-full overflow-hidden">
                <table class="w-full table-fixed border-separate border-spacing-0 [&_td]:align-middle [&_th]:align-middle">
                    <colgroup>
                        <col class="w-[5%]" />
                        <col class="w-[25%]" />
                        <col class="w-[22%]" />
                        <col class="w-[18%]" />
                        <col class="w-[12%]" />
                        <col class="w-[18%]" />
                    </colgroup>
                    <thead>
                        <tr class="h-16 bg-[#F3F8FC]">
                            <th v-if="canDelete" class="px-2 py-3 text-center text-sm font-bold text-[#111827]">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-border"
                                    :checked="areAllSelected"
                                    @change="emit('toggle-all-selection', $event)"
                                />
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-center text-sm font-bold text-[#111827] transition-colors select-none hover:text-[#0284C7]"
                                @click="emit('toggle-sort', 'name')"
                            >
                                الاسم
                                <span class="ms-1 text-[#A8B8C8]">{{ sortMark('name') }}</span>
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-center text-sm font-bold text-[#111827] transition-colors select-none hover:text-[#0284C7]"
                                @click="emit('toggle-sort', 'email')"
                            >
                                البريد الإلكتروني
                                <span class="ms-1 text-[#A8B8C8]">{{ sortMark('email') }}</span>
                            </th>
                            <th class="px-3 py-3 text-center text-sm font-bold text-[#111827]">
                                الأدوار
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-center text-sm font-bold text-[#111827] transition-colors select-none hover:text-[#0284C7]"
                                @click="emit('toggle-sort', 'is_active')"
                            >
                                الحالة
                                <span class="ms-1 text-[#A8B8C8]">{{ sortMark('is_active') }}</span>
                            </th>
                            <th class="px-3 py-3 text-center text-sm font-bold text-[#111827]">
                                الإجراءات
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(user, index) in users"
                            :key="user.id"
                            class="group h-20 border-b border-[#E8EEF6] transition-all duration-150 last:border-b-0 hover:bg-[#F8FCFF]"
                        >
                            <td v-if="canDelete" class="px-2 py-4 text-center">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-border"
                                    :checked="selectedIds.includes(user.id)"
                                    @change="toggleUserSelection(user.id, ($event.target as HTMLInputElement).checked)"
                                />
                            </td>
                            <td class="px-3 py-4 text-center">
                                <div class="flex items-center justify-center gap-3">
                                    <span
                                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#0EA5E9] text-sm font-bold text-white shadow-[0_12px_24px_-18px_rgb(14_165_233_/_0.95)]"
                                    >
                                        {{ user.name.charAt(0) }}
                                    </span>
                                    <div class="min-w-0 text-right">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <span class="truncate text-sm font-semibold text-[#111827]">{{ user.name }}</span>
                                            <Shield v-if="user.is_super_admin" class="size-3.5 text-amber-500" />
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-4 text-center text-sm text-[#6C7F95]">
                                {{ user.email }}
                            </td>
                            <td class="px-3 py-4 text-center">
                                <div class="flex flex-wrap justify-center gap-1">
                                    <span
                                        v-for="roleName in user.role_names"
                                        :key="roleName"
                                        class="inline-flex items-center rounded-full bg-[#F4F7FA] px-2.5 py-1 text-xs font-bold text-[#111827]"
                                    >
                                        {{ roleName }}
                                    </span>
                                    <span v-if="user.role_names.length === 0" class="text-xs text-[#9CA3AF]">—</span>
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
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-[#0EA5E9] transition-all duration-150 hover:bg-[#EAF7FE] active:scale-[0.95]"
                                        title="عرض"
                                        @click="emit('view', user)"
                                    >
                                        <Eye class="size-4" />
                                    </button>
                                    <button
                                        v-if="canUpdate"
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-[#2563EB] transition-all duration-150 hover:bg-[#EFF6FF] active:scale-[0.95]"
                                        title="تعديل"
                                        @click="emit('edit', user)"
                                    >
                                        <Pencil class="size-4" />
                                    </button>
                                    <button
                                        v-if="canDelete"
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-[#FF3B30] transition-all duration-150 hover:bg-[#FEF2F2] active:scale-[0.95]"
                                        title="حذف"
                                        @click="emit('delete', user.id)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="users.length === 0">
                            <td :colspan="canDelete ? 6 : 5" class="px-5">
                                <div class="py-20 text-center">
                                    <h3 class="mb-2 text-base font-bold text-[#111827]">
                                        لا يوجد مستخدمين
                                    </h3>
                                    <p class="text-sm text-[#6C7F95]">
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
                        class="inline-flex size-10 items-center justify-center rounded-full border border-[#E8EEF6] bg-white text-[#93A4B7] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] transition hover:text-[#075985] disabled:opacity-40"
                        :disabled="page === 1"
                        @click="emit('previous-page')"
                    >
                        <ChevronsRight class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-[#E8EEF6] bg-white text-[#93A4B7] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] transition hover:text-[#075985] disabled:opacity-40"
                        :disabled="page === 1"
                        @click="emit('previous-page')"
                    >
                        <ChevronRight class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-[#E8EEF6] bg-white text-[#93A4B7] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] transition hover:text-[#075985] disabled:opacity-40"
                        :disabled="page >= totalPages"
                        @click="emit('next-page')"
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-[#E8EEF6] bg-white text-[#93A4B7] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] transition hover:text-[#075985] disabled:opacity-40"
                        :disabled="page >= totalPages"
                        @click="emit('next-page')"
                    >
                        <ChevronsLeft class="size-4" />
                    </button>
                </div>

                <span class="text-sm font-semibold text-[#111827]">صفحة {{ page }} من {{ totalPages }}</span>

                <div class="flex items-center gap-3">
                    <select
                        v-model.number="rowsPerPage"
                        class="h-11 rounded-2xl border border-[#DDE9F3] bg-white px-4 text-sm font-semibold text-[#1A2B3F] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] focus:border-[#0EA5E9] focus:ring-2 focus:ring-[#0EA5E9]/10 focus:outline-none"
                    >
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                    <span class="text-sm font-semibold text-[#111827]">عدد الصفوف لكل صفحة</span>
                </div>
            </div>

            <p class="text-sm font-medium text-[#6C7F95]">
                عرض {{ visibleFrom }} إلى {{ visibleTo }} من {{ total }} مستخدم
            </p>
        </div>
    </div>
</template>
