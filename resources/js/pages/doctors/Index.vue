<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Search, Stethoscope } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { destroy, index } from '@/actions/App/Http/Controllers/Doctors/DoctorProfileController';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import { Input } from '@/components/ui/input';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';
import DoctorFormModal from './components/DoctorFormModal.vue';
import DoctorTable from './components/DoctorTable.vue';
import DoctorViewDialog from './components/DoctorViewDialog.vue';
import type { ClinicOption, DepartmentOption, DoctorProfile, DoctorProfileStatus, PaginatedResponse } from './components/types';

const props = defineProps<{
    doctor_profiles: PaginatedResponse<DoctorProfile>;
    clinic: ClinicOption;
    departments: DepartmentOption[];
    filters: {
        status: DoctorProfileStatus | null;
        department_id: number | null;
        search: string | null;
        per_page: number;
        sort_by: string | null;
        sort_direction: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'الأطباء',
                href: index(),
            },
        ],
    },
});

const { can } = usePermissions();
const toast = useToast();
const { isOpen: isConfirmOpen, options: confirmOptions, confirm, handleConfirm: handleConfirmDelete, handleCancel: handleConfirmCancel } = useConfirm();

const search = ref(props.filters.search ?? '');
const status = ref<DoctorProfileStatus | 'all'>(props.filters.status ?? 'all');
const departmentId = ref<number | 'all'>(props.filters.department_id ?? 'all');
const formOpen = ref(false);
const editingProfile = ref<DoctorProfile | null>(null);
const viewingProfile = ref<DoctorProfile | null>(null);

const totalLabel = computed(() => {
    return `عرض ${props.doctor_profiles.meta.from ?? 0} إلى ${props.doctor_profiles.meta.to ?? 0} من ${props.doctor_profiles.meta.total} طبيب`;
});

let searchTimer: ReturnType<typeof setTimeout> | null = null;

const reload = (): void => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            department_id: departmentId.value === 'all' ? undefined : departmentId.value,
            per_page: props.filters.per_page,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

watch(search, () => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(reload, 350);
});

watch([status, departmentId], reload);

const openCreate = (): void => {
    editingProfile.value = null;
    formOpen.value = true;
};

const openEdit = (profile: DoctorProfile): void => {
    editingProfile.value = profile;
    formOpen.value = true;
};

const deleteProfile = async (profile: DoctorProfile): Promise<void> => {
    const accepted = await confirm({
        title: 'حذف الطبيب',
        description: 'إذا كان للطبيب زيارات أو سجلات مالية سيتم تعطيل حسابه وأرشفته بدلاً من الحذف.',
        confirmText: 'تأكيد الحذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (! accepted) {
        return;
    }

    router.delete(destroy.url(profile.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('تم تنفيذ العملية بنجاح.');
        },
        onError: () => {
            toast.error('تعذر حذف الطبيب.');
        },
    });
};

const goTo = (url: string | null): void => {
    if (url === null) {
        return;
    }

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head title="الأطباء" />

    <div class="mx-auto w-full max-w-[1680px] space-y-7 p-4 md:p-6" dir="rtl">
        <section class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div class="space-y-2 text-right">
                <div class="inline-flex items-center gap-2 rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">
                    <Stethoscope class="size-4" />
                    إدارة الأطباء
                </div>
                <h1 class="text-4xl font-extrabold text-slate-900">صفحة الأطباء</h1>
                <p class="text-lg text-slate-500">إدارة بيانات الأطباء وحساباتهم ودوامهم ونظام الأجر.</p>
            </div>

            <Button
                v-if="can('doctor_profile.create')"
                type="button"
                class="h-12 rounded-lg bg-sky-500 px-6 text-base font-bold text-white shadow-lg shadow-sky-200 hover:bg-sky-600"
                @click="openCreate"
            >
                <Plus class="size-5" />
                إضافة طبيب جديد
            </Button>
        </section>

        <section class="rounded-xl border border-slate-100 bg-white p-5 shadow-sm">
            <div class="grid gap-4 md:grid-cols-[1fr_220px_220px]">
                <div class="relative">
                    <Search class="absolute right-4 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
                    <Input
                        v-model="search"
                        class="h-12 rounded-lg pr-12"
                        placeholder="البحث باسم الطبيب أو الاختصاص أو العيادة..."
                    />
                </div>

                <select v-model="status" class="h-12 rounded-lg border border-slate-200 bg-slate-50 px-4 text-sm">
                    <option value="all">كل الحالات</option>
                    <option value="active">نشط</option>
                    <option value="on_leave">في إجازة</option>
                    <option value="inactive">غير نشط</option>
                </select>

                <select v-model="departmentId" class="h-12 rounded-lg border border-slate-200 bg-slate-50 px-4 text-sm">
                    <option value="all">كل العيادات</option>
                    <option
                        v-for="department in departments"
                        :key="department.id"
                        :value="department.id"
                    >
                        {{ department.name }}
                    </option>
                </select>
            </div>
        </section>

        <DoctorTable
            :doctor-profiles="doctor_profiles"
            @view="viewingProfile = $event"
            @edit="openEdit($event)"
            @delete="deleteProfile($event)"
        />

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
            <p>{{ totalLabel }}</p>
            <div class="flex items-center gap-2">
                <Button type="button" variant="outline" class="rounded-lg" :disabled="doctor_profiles.links.prev === null" @click="goTo(doctor_profiles.links.prev)">
                    السابق
                </Button>
                <span class="font-semibold text-slate-900">
                    صفحة {{ doctor_profiles.meta.current_page }} من {{ doctor_profiles.meta.last_page }}
                </span>
                <Button type="button" variant="outline" class="rounded-lg" :disabled="doctor_profiles.links.next === null" @click="goTo(doctor_profiles.links.next)">
                    التالي
                </Button>
            </div>
        </div>

        <DoctorFormModal
            v-model:open="formOpen"
            :profile="editingProfile"
            :clinic="clinic"
            :departments="departments"
            @saved="editingProfile = null"
        />

        <DoctorViewDialog
            :profile="viewingProfile"
            @close="viewingProfile = null"
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
