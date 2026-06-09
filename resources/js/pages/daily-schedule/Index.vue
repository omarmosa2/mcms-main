<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import html2canvas from 'html2canvas';
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
        const canvas = await html2canvas(exportRef.value, {
            backgroundColor: '#ffffff',
            scale: 2,
            useCORS: true,
            allowTaint: true,
            logging: false,
            windowWidth: 1200,
        });

        const link = document.createElement('a');
        link.download = `جدول-العيادات-${props.scheduleData.date}.png`;
        link.href = canvas.toDataURL('image/png');
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
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="page-title">العيادات المتوفرة اليوم</h1>
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

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <Card v-for="clinic in scheduleData.clinics" :key="clinic.id" class="overflow-hidden">
                <CardHeader class="bg-gradient-to-l from-[#EAF7FE] to-white pb-3">
                    <CardTitle class="flex items-center gap-3 text-base">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-[#BDE9FB]">
                            <Stethoscope class="size-5 text-[#075985]" />
                        </div>
                        <div>
                            <span class="block text-[#075985]">{{ clinic.name }}</span>
                            <span v-if="clinic.clinic_type" class="block text-xs font-normal text-muted-foreground">
                                {{ clinic.clinic_type }}
                            </span>
                        </div>
                    </CardTitle>
                    <div v-if="clinic.clinic_start_time" class="mt-2 flex items-center gap-1 text-xs text-muted-foreground">
                        <Clock class="size-3" />
                        <span>دوام العيادة: {{ formatTimeShort(clinic.clinic_start_time) }} - {{ formatTimeShort(clinic.clinic_end_time!) }}</span>
                    </div>
                </CardHeader>
                <CardContent class="pt-4">
                    <div class="space-y-3">
                        <div
                            v-for="doctor in clinic.doctors"
                            :key="doctor.doctor_id"
                            class="flex items-center justify-between rounded-xl border border-[#E2EEF8] bg-[#FAFCFE] p-3 transition-colors hover:bg-[#F0F8FF]"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex size-9 items-center justify-center rounded-full bg-[#0EA5E9]">
                                    <span class="text-sm font-bold text-white">{{ doctor.doctor_name?.charAt(0) }}</span>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-[#264762]">
                                        {{ doctor.doctor_name }}
                                    </p>
                                    <p v-if="doctor.specialty" class="text-xs text-muted-foreground">
                                        {{ doctor.specialty }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs font-medium text-[#0EA5E9]">
                                <Clock class="size-3.5" />
                                <span class="tabular-nums">
                                    {{ formatTimeShort(doctor.start_time) }} - {{ formatTimeShort(doctor.end_time) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-if="!hasClinics" class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-[#CFE8F7] py-16 text-center">
            <div class="flex size-16 items-center justify-center rounded-full bg-[#EAF7FE]">
                <CalendarDays class="size-8 text-[#0EA5E9]" />
            </div>
            <h3 class="mt-4 text-lg font-semibold text-[#264762]">لا توجد عيادات متوفرة اليوم</h3>
            <p class="mt-1 text-sm text-muted-foreground">
                لا توجد عيادات أو أطباء مسجلين للدوام في هذا اليوم.
            </p>
        </div>

        <div ref="exportRef" dir="rtl" style="position: fixed; left: -9999px; top: 0; width: 1200px; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background: #ffffff; padding: 40px;">
            <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 4px solid #0EA5E9; padding-bottom: 24px; margin-bottom: 32px;">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <div v-if="logoPath" style="width: 80px; height: 80px; border-radius: 16px; overflow: hidden; border: 2px solid #E2EEF8;">
                        <img :src="`/storage/${logoPath}`" :alt="clinicName" style="width: 100%; height: 100%; object-fit: contain;" crossorigin="anonymous" />
                    </div>
                    <div v-else style="width: 80px; height: 80px; border-radius: 16px; background: #0EA5E9; display: flex; align-items: center; justify-content: center;">
                        <span style="color: white; font-size: 36px;">&#9877;</span>
                    </div>
                    <div>
                        <h2 style="font-size: 28px; font-weight: bold; color: #075985; margin: 0;">{{ clinicName }}</h2>
                        <p style="font-size: 18px; color: #264762; margin: 4px 0 0;">جدول العيادات المتوفرة</p>
                    </div>
                </div>
                <div style="background: #0EA5E9; color: white; border-radius: 12px; padding: 16px 24px; text-align: center;">
                    <p style="font-size: 24px; font-weight: bold; margin: 0;">{{ scheduleData.day_name }}</p>
                    <p style="font-size: 14px; margin: 4px 0 0; opacity: 0.9;">{{ scheduleData.formatted_date }}</p>
                </div>
            </div>

            <div v-if="hasClinics" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <div
                    v-for="clinic in scheduleData.clinics"
                    :key="clinic.id"
                    style="border-radius: 16px; border: 2px solid #E2EEF8; overflow: hidden; background: #ffffff;"
                >
                    <div style="background: #0EA5E9; padding: 16px 20px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(255,255,255,0.3); display: flex; align-items: center; justify-content: center;">
                                <span style="color: white; font-size: 22px;">&#9877;</span>
                            </div>
                            <div style="flex: 1;">
                                <h3 style="font-size: 18px; font-weight: bold; color: #ffffff; margin: 0;">{{ clinic.name }}</h3>
                                <span v-if="clinic.clinic_type" style="font-size: 13px; color: rgba(255,255,255,0.9);">{{ clinic.clinic_type }}</span>
                            </div>
                        </div>
                        <div v-if="clinic.clinic_start_time" style="margin-top: 10px; background: #0284C7; border-radius: 8px; padding: 8px 12px; display: inline-flex; align-items: center; gap: 8px; color: white; font-size: 14px;">
                            <span>&#128339;</span>
                            <span>{{ formatTimeShort(clinic.clinic_start_time) }} - {{ formatTimeShort(clinic.clinic_end_time!) }}</span>
                        </div>
                    </div>

                    <div style="padding: 16px;">
                        <div v-for="doctor in clinic.doctors" :key="doctor.doctor_id" style="display: flex; align-items: center; justify-content: space-between; border: 1px solid #E2EEF8; border-radius: 12px; background: #F8FCFF; padding: 12px; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: #0EA5E9; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <span style="font-size: 16px; font-weight: bold; color: white;">{{ doctor.doctor_name?.charAt(0) }}</span>
                                </div>
                                <div>
                                    <p style="font-size: 15px; font-weight: bold; color: #264762; margin: 0;">{{ doctor.doctor_name }}</p>
                                    <p v-if="doctor.specialty" style="font-size: 12px; color: #6B7280; margin: 2px 0 0;">{{ doctor.specialty }}</p>
                                </div>
                            </div>
                            <div style="background: #E0F2FE; border-radius: 8px; padding: 6px 12px; font-size: 13px; font-weight: bold; color: #075985; white-space: nowrap;">
                                &#128339; {{ formatTimeShort(doctor.start_time) }} - {{ formatTimeShort(doctor.end_time) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else style="text-align: center; padding: 60px 20px; border: 2px dashed #CFE8F7; border-radius: 16px; background: #F8FCFF;">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: #E0F2FE; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                    <span style="font-size: 36px; color: #0EA5E9;">&#128197;</span>
                </div>
                <h3 style="font-size: 20px; font-weight: bold; color: #264762; margin: 16px 0 0;">لا توجد عيادات متوفرة اليوم</h3>
                <p style="font-size: 14px; color: #6B7280; margin: 8px 0 0;">لا توجد عيادات أو أطباء مسجلين للدوام في هذا اليوم.</p>
            </div>

            <div v-if="scheduleData.clinic_settings.phone || scheduleData.clinic_settings.address" style="margin-top: 32px; border-top: 2px solid #E2EEF8; padding-top: 16px; text-align: center; color: #6B7280; font-size: 14px;">
                <p v-if="scheduleData.clinic_settings.phone" style="font-weight: 500; margin: 0;">هاتف: {{ scheduleData.clinic_settings.phone }}</p>
                <p v-if="scheduleData.clinic_settings.address" style="margin: 4px 0 0;">{{ scheduleData.clinic_settings.address }}</p>
            </div>
        </div>
    </div>
</template>
