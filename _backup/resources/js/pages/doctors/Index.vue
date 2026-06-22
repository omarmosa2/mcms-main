<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Search, Stethoscope } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    destroy,
    index,
    show,
} from '@/actions/App/Http/Controllers/Doctors/DoctorProfileController';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import { Input } from '@/components/ui/input';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';
import DoctorFormModal from './components/DoctorFormModal.vue';
import DoctorStatsCards from './components/DoctorStatsCards.vue';
import DoctorTable from './components/DoctorTable.vue';
import DoctorViewDialog from './components/DoctorViewDialog.vue';
import type {
    ClinicOption,
    ClinicSelectOption,
    DoctorProfile,
    DoctorProfileStats,
    DoctorProfileStatus,
    PaginatedResponse,
} from './components/types';

const props = defineProps<{
    doctor_profiles: PaginatedResponse<DoctorProfile>;
    doctors: PaginatedResponse<DoctorProfile>;
    stats: DoctorProfileStats;
    clinic: ClinicOption;
    clinics: ClinicSelectOption[];
    filters: {
        status: DoctorProfileStatus | null;
        clinic_id: number | null;
        search: string | null;
        per_page: number;
        sort_by: string | null;
        sort_direction: string | null;
    };
    all_clinics: boolean;
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
const {
    isOpen: isConfirmOpen,
    options: confirmOptions,
    confirm,
    close: closeConfirm,
    handleConfirm: handleConfirmDelete,
    handleCancel: handleConfirmCancel,
} = useConfirm();

const search = ref(props.filters.search ?? '');
const status = ref<DoctorProfileStatus | 'all'>(props.filters.status ?? 'all');
const clinicId = ref<number | 'all'>(props.filters.clinic_id ?? 'all');
const formOpen = ref(false);
const editingProfile = ref<DoctorProfile | null>(null);
const viewingProfile = ref<DoctorProfile | null>(null);

const totalLabel = computed(() => {
    return `عرض ${props.doctors.meta.from ?? 0} إلى ${props.doctors.meta.to ?? 0} من ${props.doctors.meta.total} طبيب`;
});

let searchTimer: ReturnType<typeof setTimeout> | null = null;

const reload = (): void => {
    router.get(
        index.url(),
        {
            search: search.value,
            status: status.value === 'all' ? '' : status.value,
            clinic_id:
                clinicId.value === 'all' ? '' : clinicId.value,
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

watch([status, clinicId], reload);

const openCreate = (): void => {
    editingProfile.value = null;
    formOpen.value = true;
};

const openEdit = async (profile: DoctorProfile): Promise<void> => {
    const doctorProfile = await loadDoctorProfile(profile);

    if (doctorProfile === null) {
        return;
    }

    editingProfile.value = doctorProfile;
    formOpen.value = true;
};

const openView = async (profile: DoctorProfile): Promise<void> => {
    const doctorProfile = await loadDoctorProfile(profile);

    if (doctorProfile === null) {
        return;
    }

    viewingProfile.value = doctorProfile;
};

const loadDoctorProfile = async (
    profile: DoctorProfile,
): Promise<DoctorProfile | null> => {
    try {
        const response = await window.fetch(show.url(profile.id), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Unable to load doctor profile.');
        }

        const payload = await response.json() as { data: DoctorProfile };

        return payload.data;
    } catch {
        toast.error('تعذر تحميل بيانات الطبيب الكاملة.');

        return null;
    }
};

const deleteProfile = async (profile: DoctorProfile): Promise<void> => {
    const accepted = await confirm({
        title: 'حذف الطبيب',
        description:
            'إذا كان للطبيب زيارات أو سجلات مالية سيتم تعطيل حسابه وأرشفته بدلاً من الحذف.',
        confirmText: 'تأكيد الحذف',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (!accepted) {
        return;
    }

    router.delete(destroy.url(profile.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeConfirm();
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
        <section
            class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
        >
            <div class="space-y-2 text-right">
                <div
                    class="inline-flex items-center gap-2 rounded-full bg-accent px-3 py-1 text-xs font-semibold text-accent-foreground"
                >
                    <Stethoscope class="size-4" />
                    إدارة الأطباء
                </div>
                <h1 class="text-4xl font-extrabold text-foreground">
                    صفحة الأطباء
                </h1>
                <p class="text-lg text-muted-foreground">
                    إدارة بيانات الأطباء وحساباتهم ودوامهم ونظام الأجر.
                </p>
            </div>

            <Button
                v-if="can('doctor_profile.create')"
                type="button"
                class="h-12 rounded-lg bg-primary px-6 text-base font-bold text-primary-foreground shadow-lg shadow-primary/20 hover:bg-primary/90"
                @click="openCreate"
            >
                <Plus class="size-5" />
                إضافة طبيب جديد
            </Button>
        </section>

        <DoctorStatsCards :stats="stats" />

        <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
            <div class="grid gap-4 md:grid-cols-[1fr_220px_220px]">
                <div class="relative">
                    <Search
                        class="absolute top-1/2 right-4 size-5 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        class="h-12 rounded-lg pr-12"
                        placeholder="البحث باسم الطبيب أو الاختصاص أو العيادة..."
                    />
                </div>

                <select
                    v-model="status"
                    class="h-12 rounded-lg border border-input bg-muted px-4 text-sm"
                >
                    <option value="all">كل الحالات</option>
                    <option value="active">نشط</option>
                    <option value="on_leave">في إجازة</option>
                    <option value="inactive">غير نشط</option>
                </select>

                <select
                    v-model="clinicId"
                    class="h-12 rounded-lg border border-input bg-muted px-4 text-sm"
                >
                    <option value="all">كل العيادات</option>
                    <option
                        v-for="clinic in clinics"
                        :key="clinic.id"
                        :value="clinic.id"
                    >
                        {{ clinic.name }}
                    </option>
                </select>
            </div>
        </section>

        <DoctorTable
            :doctor-profiles="doctors"
            @view="openView($event)"
            @edit="openEdit($event)"
            @delete="deleteProfile($event)"
        />

        <div
            class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
        >
            <p>{{ totalLabel }}</p>
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="rounded-lg"
                    :disabled="doctors.links.prev === null"
                    @click="goTo(doctors.links.prev)"
                >
                    السابق
                </Button>
                <span class="font-semibold text-foreground">
                    صفحة {{ doctors.meta.current_page }} من
                    {{ doctors.meta.last_page }}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    class="rounded-lg"
                    :disabled="doctors.links.next === null"
                    @click="goTo(doctors.links.next)"
                >
                    التالي
                </Button>
            </div>
        </div>

        <DoctorFormModal
            v-model:open="formOpen"
            :profile="editingProfile"
            :clinic="clinic"
            :clinics="clinics"
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
