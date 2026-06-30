<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Eye,
    FileSpreadsheet,
    Pencil,
    Plus,
    Search,
    Stethoscope,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    destroy,
    exportMethod as exportDoctors,
    index,
    show,
} from '@/actions/App/Http/Controllers/DoctorController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useToast } from '@/composables/useToast';
import DoctorDeleteDialog from './components/DoctorDeleteDialog.vue';
import DoctorFormModal from './components/DoctorFormModal.vue';
import DoctorStatsCards from './components/DoctorStatsCards.vue';
import DoctorViewDialog from './components/DoctorViewDialog.vue';
import type {
    Clinic,
    Doctor,
    DoctorFilters,
    DoctorSchedule,
    PaginatedResponse,
} from './types';

const props = defineProps<{
    doctors: PaginatedResponse<Doctor>;
    clinics: Clinic[];
    filters: DoctorFilters;
    stats: {
        total: number;
        active: number;
        inactive: number;
        with_accounts: number;
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

const toast = useToast();

const search = ref<string>(props.filters.search ?? '');
const clinicId = ref<number | 'all'>(props.filters.clinic_id ?? 'all');
const isActive = ref<'all' | 'active' | 'inactive'>(
    props.filters.is_active === true
        ? 'active'
        : props.filters.is_active === false
          ? 'inactive'
          : 'all',
);

const formOpen = ref(false);
const editingDoctor = ref<Doctor | null>(null);
const viewingDoctor = ref<Doctor | null>(null);
const deletingDoctor = ref<Doctor | null>(null);

const doctorsList = computed<Doctor[]>(() => props.doctors.data);

const totalLabel = computed(() => {
    return `عرض ${props.doctors.meta.from ?? 0} إلى ${props.doctors.meta.to ?? 0} من ${props.doctors.meta.total} طبيب`;
});

const compensationLabel = (doctor: Doctor): string => {
    switch (doctor.compensation_type) {
        case 'percentage':
            return `${doctor.percentage_value ?? doctor.compensation_value ?? 0}% نسبة`;
        case 'fixed_weekly':
            return `${doctor.fixed_weekly_amount ?? doctor.compensation_value ?? 0} أسبوعي`;
        case 'fixed_monthly':
            return `${doctor.fixed_monthly_amount ?? doctor.compensation_value ?? 0} شهري`;
        default:
            return '—';
    }
};

const scheduleSummary = (schedules: DoctorSchedule[]): string => {
    const active = schedules.filter((s) => s.is_available);

    if (active.length === 0) {
        return 'لا يوجد دوام';
    }

    return `${active.length} أيام`;
};

const clinicName = (doctor: Doctor): string => doctor.clinic?.name ?? '—';

let searchTimer: ReturnType<typeof setTimeout> | null = null;

const reload = (): void => {
    router.get(
        index.url(),
        {
            search: search.value.trim() || undefined,
            clinic_id: clinicId.value === 'all' ? undefined : clinicId.value,
            is_active:
                isActive.value === 'all'
                    ? undefined
                    : isActive.value === 'active'
                      ? '1'
                      : '0',
            per_page: props.filters.per_page,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const exportExcel = (): void => {
    window.open(
        exportDoctors.url({
            query: {
                search: search.value.trim() || undefined,
                clinic_id: clinicId.value === 'all' ? undefined : clinicId.value,
                is_active:
                    isActive.value === 'all'
                        ? undefined
                        : isActive.value === 'active'
                          ? '1'
                          : '0',
            },
        }),
        '_blank',
    );
};

watch(search, () => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(reload, 350);
});

watch([clinicId, isActive], reload);

const openCreate = (): void => {
    editingDoctor.value = null;
    formOpen.value = true;
};

const openEdit = async (doctor: Doctor): Promise<void> => {
    const full = await loadDoctor(doctor.id);

    if (full !== null) {
        editingDoctor.value = full;
        formOpen.value = true;
    }
};

const openView = async (doctor: Doctor): Promise<void> => {
    const full = await loadDoctor(doctor.id);

    if (full !== null) {
        viewingDoctor.value = full;
    }
};

const openDelete = (doctor: Doctor): void => {
    deletingDoctor.value = doctor;
};

const loadDoctor = async (id: number): Promise<Doctor | null> => {
    try {
        const response = await window.fetch(show.url(id), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Unable to load doctor.');
        }

        const payload = (await response.json()) as { data: Doctor };

        return payload.data;
    } catch {
        toast.error('تعذر تحميل بيانات الطبيب.');

        return null;
    }
};

const confirmDelete = (): void => {
    if (deletingDoctor.value === null) {
        return;
    }

    router.delete(destroy.url(deletingDoctor.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('تم حذف الطبيب بنجاح.');
            deletingDoctor.value = null;
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

    router.visit(url, { preserveScroll: true, preserveState: true });
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

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="h-12 rounded-lg px-5 text-base font-bold"
                    @click="exportExcel"
                >
                    <FileSpreadsheet class="size-5" />
                    تصدير Excel
                </Button>

                <Button
                    type="button"
                    class="h-12 rounded-lg bg-primary px-6 text-base font-bold text-primary-foreground shadow-lg shadow-primary/20 hover:bg-primary/90"
                    @click="openCreate"
                >
                    <Plus class="size-5" />
                    إضافة طبيب جديد
                </Button>
            </div>
        </section>

        <DoctorStatsCards :stats="props.stats" />

        <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
            <div class="grid gap-4 md:grid-cols-[1fr_220px_220px]">
                <div class="relative">
                    <Search
                        class="absolute top-1/2 right-4 size-5 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        class="h-12 rounded-lg pr-12"
                        placeholder="البحث بالاسم أو الاختصاص أو الهاتف أو اسم المستخدم..."
                    />
                </div>

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

                <select
                    v-model="isActive"
                    class="h-12 rounded-lg border border-input bg-muted px-4 text-sm"
                >
                    <option value="all">كل الحالات</option>
                    <option value="active">نشط</option>
                    <option value="inactive">غير نشط</option>
                </select>
            </div>
        </section>

        <section class="rounded-xl border border-border bg-card shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="border-b border-border bg-muted/40 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-3 font-bold text-foreground">الاسم</th>
                            <th class="px-4 py-3 font-bold text-foreground">العيادة</th>
                            <th class="px-4 py-3 font-bold text-foreground">الاختصاص</th>
                            <th class="px-4 py-3 font-bold text-foreground">الهاتف</th>
                            <th class="px-4 py-3 font-bold text-foreground">نوع الأجر</th>
                            <th class="px-4 py-3 font-bold text-foreground">الحالة</th>
                            <th class="px-4 py-3 font-bold text-foreground">الدوام</th>
                            <th class="px-4 py-3 font-bold text-foreground">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="doctor in doctorsList"
                            :key="doctor.id"
                            class="hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-semibold text-foreground">
                                {{ doctor.full_name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ clinicName(doctor) }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ doctor.specialty }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ doctor.phone ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ compensationLabel(doctor) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                        doctor.is_active
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-rose-100 text-rose-700',
                                    ]"
                                >
                                    {{ doctor.is_active ? 'نشط' : 'غير نشط' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ scheduleSummary(doctor.schedules) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-8"
                                        title="عرض"
                                        @click="openView(doctor)"
                                    >
                                        <Eye class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-8"
                                        title="تعديل"
                                        @click="openEdit(doctor)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 text-rose-600 hover:text-rose-700"
                                        title="حذف"
                                        @click="openDelete(doctor)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="doctorsList.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-10 text-center text-muted-foreground"
                            >
                                لا يوجد أطباء مطابقون.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

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
            :doctor="editingDoctor"
            :clinics="clinics"
            @saved="editingDoctor = null"
        />

        <DoctorViewDialog
            :doctor="viewingDoctor"
            @close="viewingDoctor = null"
        />

        <DoctorDeleteDialog
            :doctor="deletingDoctor"
            @close="deletingDoctor = null"
            @confirm="confirmDelete"
        />
    </div>
</template>
