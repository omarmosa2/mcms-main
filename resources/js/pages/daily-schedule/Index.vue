<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { toPng } from 'html-to-image';
import {
    CalendarDays,
    Clock,
    Download,
    Monitor,
    RefreshCw,
    Stethoscope,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type DoctorSchedule = {
    doctor_id: number;
    doctor_name: string | null;
    specialty: string | null;
    start_time: string;
    end_time: string;
};

type ClinicData = {
    id: number;
    name: string;
    clinic_type: string | null;
    clinic_start_time: string | null;
    clinic_end_time: string | null;
    doctors: DoctorSchedule[];
};

type ScheduleData = {
    date: string;
    day_name: string;
    day_of_week: number;
    formatted_date: string;
    branding: {
        company_name: string | null;
        logo_path: string | null;
    };
    clinic_settings: {
        name: string | null;
        phone: string | null;
        address: string | null;
        logo_path: string | null;
    };
    clinics: ClinicData[];
};

const props = defineProps<{
    scheduleData: ScheduleData;
    departments: Array<{ id: number; name: string }>;
    doctors: Array<{ id: number; name: string }>;
    filters: {
        date: string;
        department_id: number | null;
        doctor_id: number | null;
    };
}>();

const page = usePage();
const isExporting = ref(false);
const exportRef = ref<HTMLElement | null>(null);

const branding = computed(() => page.props.branding as { company_name: string | null; logo_path: string | null });
const clinicName = computed(
    () => props.scheduleData.branding.company_name || branding.value.company_name || 'المجمع الطبي',
);
const logoPath = computed(
    () => props.scheduleData.branding.logo_path || props.scheduleData.clinic_settings.logo_path || branding.value.logo_path,
);

const filterDate = ref(props.filters.date);
const filterDepartment = ref<string>(props.filters.department_id?.toString() ?? '');
const filterDoctor = ref<string>(props.filters.doctor_id?.toString() ?? '');

function applyFilters() {
    const params: Record<string, string> = {};

    if (filterDate.value && filterDate.value !== props.scheduleData.date) {
        params.date = filterDate.value;
    }

    if (filterDepartment.value) {
        params.department_id = filterDepartment.value;
    }

    if (filterDoctor.value) {
        params.doctor_id = filterDoctor.value;
    }

    router.get('/daily-schedule', params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function resetFilters() {
    filterDate.value = '';
    filterDepartment.value = '';
    filterDoctor.value = '';
    router.get('/daily-schedule', {}, {
        preserveState: true,
        preserveScroll: true,
    });
}

function refreshData() {
    router.reload({ only: ['scheduleData'] });
}

function openDisplayMode() {
    window.open('/daily-schedule/display', '_blank', 'noopener,noreferrer');
}

async function exportAsPng() {
    if (!exportRef.value) {
        toast.error('لم يتم العثور على المحتوى للتصدير');

        return;
    }

    isExporting.value = true;

    try {
        const dataUrl = await toPng(exportRef.value, {
            backgroundColor: '#ffffff',
            pixelRatio: 2,
            cacheBust: true,
        });

        const link = document.createElement('a');
        link.download = `جدول-العيادات-${props.scheduleData.date}.png`;
        link.href = dataUrl;
        link.click();

        toast.success('تم تصدير الجدول بنجاح');
    } catch (error) {
        console.error('Export error:', error);

        toast.error('حدث خطأ أثناء التصدير');
    } finally {
        isExporting.value = false;
    }
}

function formatTimeShort(time: string): string {
    if (!time) {
        return '';
    }

    const [hours, minutes] = time.split(':');
    const h = parseInt(hours, 10);
    const m = minutes?.substring(0, 2) ?? '00';
    const period = h >= 12 ? 'م' : 'ص';
    const displayHours = h % 12 || 12;

    return `${displayHours}:${m} ${period}`;
}

const hasClinics = computed(() => props.scheduleData.clinics.length > 0);
</script>

<template>
    <Head title="العيادات المتوفرة اليوم" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
            <div>
                <h1 class="page-title">
                    {{ clinicName }} - العيادات المتوفرة اليوم
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ scheduleData.day_name }} - {{ scheduleData.formatted_date }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button variant="neumorphic" size="sm" @click="refreshData">
                    <RefreshCw class="size-4 ms-2" />
                    تحديث
                </Button>
                <Button variant="neumorphic" size="sm" :disabled="isExporting" @click="exportAsPng">
                    <Download v-if="!isExporting" class="size-4 ms-2" />
                    <RefreshCw v-else class="size-4 ms-2 animate-spin" />
                    {{ isExporting ? 'جاري التصدير...' : 'تصدير PNG' }}
                </Button>
                <Button variant="clay" size="sm" @click="openDisplayMode">
                    <Monitor class="size-4 ms-2" />
                    وضع الشاشة
                </Button>
            </div>
        </div>

        <Card class="print:hidden">
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center gap-2 text-base">
                    <CalendarDays class="size-4 text-[var(--accent-mint)]" />
                    الفلاتر
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap items-end gap-4">
                    <div class="w-[180px]">
                        <Label>التاريخ</Label>
                        <Input v-model="filterDate" type="date" class="mt-1 h-9" />
                    </div>

                    <div class="w-[200px]">
                        <Label>العيادة</Label>
                        <select v-model="filterDepartment" class="pattern-field-clay mt-1 h-9 w-full px-3 py-1.5">
                            <option value="">جميع العيادات</option>
                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                                {{ dept.name }}
                            </option>
                        </select>
                    </div>

                    <div class="w-[200px]">
                        <Label>الطبيب</Label>
                        <select v-model="filterDoctor" class="pattern-field-clay mt-1 h-9 w-full px-3 py-1.5">
                            <option value="">جميع الأطباء</option>
                            <option v-for="doc in doctors" :key="doc.id" :value="doc.id">
                                {{ doc.name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button variant="clay" size="sm" @click="applyFilters">
                            تطبيق
                        </Button>
                        <Button variant="ghost" size="sm" @click="resetFilters">
                            إعادة تعيين
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div ref="exportRef" class="bg-card p-8 rounded-2xl shadow-sm" dir="rtl">
            <div class="flex items-center justify-between border-b-4 border-primary pb-6 mb-8">
                <div class="flex items-center gap-5">
                    <div v-if="logoPath" class="flex size-20 items-center justify-center overflow-hidden rounded-2xl border-2 border-border bg-card">
                        <img :src="`/storage/${logoPath}`" :alt="clinicName" class="size-full object-contain" />
                    </div>
                    <div v-else class="flex size-20 items-center justify-center rounded-2xl bg-primary">
                        <Stethoscope class="size-10 text-primary-foreground" />
                    </div>
                    <div>
                        <h2 class="text-3xl font-bold text-accent-foreground">{{ clinicName }}</h2>
                        <p class="mt-1 text-xl font-semibold text-foreground">جدول العيادات المتوفرة</p>
                    </div>
                </div>
                <div class="text-left">
                    <div class="flex items-center gap-3 rounded-xl bg-primary px-6 py-4 text-primary-foreground">
                        <CalendarDays class="size-7" />
                        <div>
                            <p class="text-2xl font-bold">{{ scheduleData.day_name }}</p>
                            <p class="text-base">{{ scheduleData.formatted_date }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="hasClinics" class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="clinic in scheduleData.clinics"
                    :key="clinic.id"
                    class="overflow-hidden rounded-2xl border-2 border-border bg-card shadow-md"
                >
                    <div class="bg-primary p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex size-11 items-center justify-center rounded-xl bg-white/30">
                                <Stethoscope class="size-5 text-white" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-bold text-white truncate">{{ clinic.name }}</h3>
                                <span v-if="clinic.clinic_type" class="text-xs text-white/90">{{ clinic.clinic_type }}</span>
                            </div>
                        </div>
                        <div v-if="clinic.clinic_start_time" class="mt-2.5 flex items-center gap-2 rounded-lg bg-primary/80 px-3 py-1.5 text-sm text-white">
                            <Clock class="size-3.5" />
                            <span class="tabular-nums font-medium whitespace-nowrap">{{ formatTimeShort(clinic.clinic_start_time) }} - {{ formatTimeShort(clinic.clinic_end_time!) }}</span>
                        </div>
                    </div>

                    <div class="p-3">
                        <div class="space-y-2">
                            <div
                                v-for="doctor in clinic.doctors"
                                :key="doctor.doctor_id"
                                class="flex items-center justify-between rounded-xl border border-border bg-muted p-2.5"
                            >
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary">
                                        <span class="text-sm font-bold text-primary-foreground">{{ doctor.doctor_name?.charAt(0) }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-bold text-foreground truncate">
                                            {{ doctor.doctor_name }}
                                        </p>
                                        <p v-if="doctor.specialty" class="text-xs text-muted-foreground truncate">
                                            {{ doctor.specialty }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 rounded-lg bg-accent px-2.5 py-1.5 text-xs font-bold text-accent-foreground shrink-0">
                                    <Clock class="size-3" />
                                    <span class="tabular-nums whitespace-nowrap">
                                        {{ formatTimeShort(doctor.start_time) }} - {{ formatTimeShort(doctor.end_time) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-border bg-muted py-16 text-center">
                <div class="flex size-20 items-center justify-center rounded-full bg-accent">
                    <CalendarDays class="size-10 text-primary" />
                </div>
                <h3 class="mt-4 text-xl font-bold text-foreground">لا توجد عيادات متوفرة اليوم</h3>
                <p class="mt-2 text-base text-muted-foreground">
                    لا توجد عيادات أو أطباء مسجلين للدوام في هذا اليوم.
                </p>
            </div>

            <div v-if="scheduleData.clinic_settings.phone || scheduleData.clinic_settings.address" class="mt-8 border-t-2 border-border pt-4 text-center text-base text-muted-foreground">
                <p v-if="scheduleData.clinic_settings.phone" class="font-medium">
                    هاتف: {{ scheduleData.clinic_settings.phone }}
                </p>
                <p v-if="scheduleData.clinic_settings.address">
                    {{ scheduleData.clinic_settings.address }}
                </p>
            </div>
        </div>
    </div>
</template>
