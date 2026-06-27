<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { toPng } from 'html-to-image';
import {
    CalendarDays,
    Download,
    Monitor,
    Printer,
    RefreshCw,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DailySchedulePoster from './components/DailySchedulePoster.vue';

type DoctorSchedule = {
    doctor_id: number;
    doctor_name: string | null;
    specialty: string | null;
    start_time: string;
    end_time: string;
    available_periods: Array<{ start_time: string; end_time: string }>;
    unavailable_periods: Array<{
        start_time: string;
        end_time: string;
        reason: string | null;
    }>;
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
    clinics: Array<{ id: number; name: string }>;
    doctors: Array<{ id: number; name: string }>;
    filters: {
        date: string;
        clinic_id: number | null;
        doctor_id: number | null;
    };
}>();

const page = usePage();
const isExporting = ref(false);
const exportRef = ref<HTMLElement | null>(null);

const branding = computed(
    () =>
        page.props.branding as {
            company_name: string | null;
            logo_path: string | null;
        },
);
const clinicName = computed(
    () =>
        props.scheduleData.branding.company_name ||
        branding.value.company_name ||
        'المجمع الطبي',
);
const logoPath = computed(
    () =>
        props.scheduleData.branding.logo_path ||
        props.scheduleData.clinic_settings.logo_path ||
        branding.value.logo_path,
);

const filterDate = ref(props.filters.date);
const filterClinic = ref<string>(props.filters.clinic_id?.toString() ?? '');
const filterDoctor = ref<string>(props.filters.doctor_id?.toString() ?? '');

function applyFilters() {
    const params: Record<string, string> = {
        date: filterDate.value || props.scheduleData.date,
    };

    if (filterClinic.value) {
        params.clinic_id = filterClinic.value;
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
    filterDate.value = new Date().toISOString().split('T')[0];
    filterClinic.value = '';
    filterDoctor.value = '';
    router.get(
        '/daily-schedule',
        { date: filterDate.value },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

watch(filterDate, (newDate) => {
    if (newDate && newDate !== props.scheduleData.date) {
        const params: Record<string, string> = {
            date: newDate,
        };

        if (filterClinic.value) {
            params.clinic_id = filterClinic.value;
        }

        if (filterDoctor.value) {
            params.doctor_id = filterDoctor.value;
        }

        router.get('/daily-schedule', params, {
            preserveState: true,
            preserveScroll: true,
        });
    }
});

function refreshData() {
    router.reload({ only: ['scheduleData'] });
}

function openDisplayMode() {
    window.open('/daily-schedule/display', '_blank', 'noopener,noreferrer');
}

function exportAsPdf() {
    window.print();
}

async function exportAsPng() {
    if (!exportRef.value) {
        toast.error('لم يتم العثور على المحتوى للتصدير');

        return;
    }

    isExporting.value = true;

    try {
        const dataUrl = await toPng(exportRef.value, {
            backgroundColor: '#fbfdff',
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
</script>

<template>
    <Head title="العيادات المناوبة اليوم" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 rounded-3xl border border-border/80 bg-card p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between print:hidden"
        >
            <div>
                <h1 class="page-title">
                    {{ clinicName }} - العيادات المناوبة اليوم
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ scheduleData.day_name }} -
                    {{ scheduleData.formatted_date }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button variant="neumorphic" size="sm" @click="refreshData">
                    <RefreshCw class="ms-2 size-4" />
                    تحديث
                </Button>
                <Button variant="neumorphic" size="sm" @click="exportAsPdf">
                    <Printer class="ms-2 size-4" />
                    تصدير PDF
                </Button>
                <Button
                    variant="neumorphic"
                    size="sm"
                    :disabled="isExporting"
                    @click="exportAsPng"
                >
                    <Download v-if="!isExporting" class="ms-2 size-4" />
                    <RefreshCw v-else class="ms-2 size-4 animate-spin" />
                    {{ isExporting ? 'جاري التصدير...' : 'تصدير PNG' }}
                </Button>
                <Button variant="clay" size="sm" @click="openDisplayMode">
                    <Monitor class="ms-2 size-4" />
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
                        <Input
                            v-model="filterDate"
                            type="date"
                            class="mt-1 h-9"
                        />
                    </div>

                    <div class="w-[200px]">
                        <Label>العيادة</Label>
                        <select
                            v-model="filterClinic"
                            class="pattern-field-clay mt-1 h-9 w-full px-3 py-1.5"
                        >
                            <option value="">جميع العيادات</option>
                            <option
                                v-for="clinic in clinics"
                                :key="clinic.id"
                                :value="clinic.id"
                            >
                                {{ clinic.name }}
                            </option>
                        </select>
                    </div>

                    <div class="w-[200px]">
                        <Label>الطبيب</Label>
                        <select
                            v-model="filterDoctor"
                            class="pattern-field-clay mt-1 h-9 w-full px-3 py-1.5"
                        >
                            <option value="">جميع الأطباء</option>
                            <option
                                v-for="doc in doctors"
                                :key="doc.id"
                                :value="doc.id"
                            >
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

        <div ref="exportRef" class="print:-m-6">
            <DailySchedulePoster
                :schedule-data="scheduleData"
                :clinic-name="clinicName"
                :logo-path="logoPath"
            />
        </div>
    </div>
</template>
