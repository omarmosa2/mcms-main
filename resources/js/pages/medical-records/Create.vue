<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Plus,
    Save,
    Stethoscope,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';

type Clinic = {
    id: number;
    name: string;
    code: string | null;
};

type PatientOption = {
    id: number;
    first_name: string;
    last_name: string;
    file_number: number;
};

type AppointmentData = {
    appointment_id: number;
    patient_id: number;
    doctor_id: number | null;
    clinic_id: number | null;
    scheduled_for: string;
    appointment_type: string | null;
} | null;

const { clinics, clinicTypes, patients, appointment_data } = defineProps<{
    clinics: Clinic[];
    clinicTypes: string[];
    patients: PatientOption[];
    appointment_data: AppointmentData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'السجلات الطبية',
                href: '/medical-records',
            },
            {
                title: 'سجل طبي جديد',
                href: '/medical-records/create',
            },
        ],
    },
});

const { can } = usePermissions();

const form = useForm({
    patient_id: null as number | null,
    clinic_id: null as number | null,
    clinic_type: '' as string,
    appointment_id: null as number | null,
    visit_date: new Date().toISOString().split('T')[0],
    chief_complaint: '',
    examination: '',
    primary_diagnosis: '',
    secondary_diagnosis: '',
    clinical_notes: '',
    status: 'draft',
    form_data: {} as Record<string, string>,
    treatment_plans: [] as Array<{
        title: string;
        description: string;
        start_date: string;
        end_date: string;
        status: string;
    }>,
    follow_ups: [] as Array<{
        follow_up_date: string;
        notes: string;
        recommended_action: string;
    }>,
});

onMounted(() => {
    if (appointment_data) {
        form.patient_id = appointment_data.patient_id;
        form.clinic_id = appointment_data.clinic_id;
        form.appointment_id = appointment_data.appointment_id;
    }
});

function addTreatmentPlan() {
    form.treatment_plans.push({
        title: '',
        description: '',
        start_date: new Date().toISOString().split('T')[0],
        end_date: '',
        status: 'new',
    });
}

function removeTreatmentPlan(index: number) {
    form.treatment_plans.splice(index, 1);
}

function addFollowUp() {
    form.follow_ups.push({
        follow_up_date: '',
        notes: '',
        recommended_action: '',
    });
}

function removeFollowUp(index: number) {
    form.follow_ups.splice(index, 1);
}

function submit() {
    form.post('/medical-records');
}

const clinicTypeLabel = (type: string | null): string => {
    if (!type) {
        return 'غير محدد';
    }
    const labels: Record<string, string> = {
        internal_medicine: 'باطنية',
        pediatrics: 'أطفال',
        gynecology: 'نسائية وتوليد',
        orthopedics: 'عظام',
        dermatology: 'جلدية',
        ophthalmology: 'عيون',
        ent: 'أنف وأذن وحنجرة',
        cardiology: 'قلب',
        neurology: 'أعصاب',
        psychiatry: 'نفسية',
        general_surgery: 'جراحة عامة',
        urology: 'مسالك بولية',
        dental: 'أسنان',
        other: 'أخرى',
    };

    return labels[type] ?? type;
};

const clinicFormFields = computed(() => {
    const type = form.clinic_type;

    const fieldMap: Record<string, Array<{ key: string; label: string; type: 'text' | 'textarea' }>> = {
        internal_medicine: [
            { key: 'history_of_present_illness', label: 'تاريخ المرض الحالي', type: 'textarea' },
            { key: 'chronic_diseases', label: 'الأمراض المزمنة', type: 'textarea' },
            { key: 'current_medications', label: 'الأدوية الحالية', type: 'textarea' },
            { key: 'allergies', label: 'الحساسية', type: 'textarea' },
            { key: 'physical_examination', label: 'الفحص السريري', type: 'textarea' },
        ],
        pediatrics: [
            { key: 'weight', label: 'الوزن (كغ)', type: 'text' },
            { key: 'height', label: 'الطول (سم)', type: 'text' },
            { key: 'temperature', label: 'الحرارة', type: 'text' },
            { key: 'vaccination_status', label: 'حالة التطعيم', type: 'textarea' },
            { key: 'physical_examination', label: 'الفحص السريري', type: 'textarea' },
        ],
        gynecology: [
            { key: 'medical_history', label: 'التاريخ الطبي', type: 'textarea' },
            { key: 'pregnancy_status', label: 'حالة الحمل', type: 'text' },
            { key: 'obstetric_history', label: 'التاريخ التوليدي', type: 'textarea' },
            { key: 'examination', label: 'الفحص', type: 'textarea' },
        ],
        orthopedics: [
            { key: 'injury_location', label: 'موقع الإصابة', type: 'text' },
            { key: 'pain_severity', label: 'شدة الألم', type: 'text' },
            { key: 'mobility_assessment', label: 'تقييم الحركة', type: 'textarea' },
            { key: 'physical_examination', label: 'الفحص السريري', type: 'textarea' },
        ],
        dermatology: [
            { key: 'affected_area', label: 'المنطقة المصابة', type: 'text' },
            { key: 'skin_condition_description', label: 'وصف الحالة الجلدية', type: 'textarea' },
        ],
        ophthalmology: [
            { key: 'visual_acuity', label: 'حدة البصر', type: 'text' },
            { key: 'eye_examination', label: 'فحص العين', type: 'textarea' },
        ],
        ent: [
            { key: 'symptoms', label: 'الأعراض', type: 'textarea' },
            { key: 'examination_details', label: 'تفاصيل الفحص', type: 'textarea' },
        ],
        cardiology: [
            { key: 'blood_pressure', label: 'ضغط الدم', type: 'text' },
            { key: 'pulse', label: 'النبض', type: 'text' },
            { key: 'symptoms', label: 'الأعراض', type: 'textarea' },
            { key: 'examination_details', label: 'تفاصيل الفحص', type: 'textarea' },
        ],
        neurology: [
            { key: 'neurological_examination', label: 'الفحص العصبي', type: 'textarea' },
            { key: 'symptoms', label: 'الأعراض', type: 'textarea' },
        ],
        psychiatry: [
            { key: 'mental_status_examination', label: 'فحص الحالة النفسية', type: 'textarea' },
            { key: 'symptoms', label: 'الأعراض', type: 'textarea' },
        ],
        general_surgery: [
            { key: 'surgical_history', label: 'التاريخ الجراحي', type: 'textarea' },
            { key: 'physical_examination', label: 'الفحص السريري', type: 'textarea' },
        ],
        urology: [
            { key: 'symptoms', label: 'الأعراض', type: 'textarea' },
            { key: 'examination_details', label: 'تفاصيل الفحص', type: 'textarea' },
        ],
        dental: [
            { key: 'dental_complaint', label: 'الشكوى السنية', type: 'textarea' },
            { key: 'oral_examination', label: 'فحص الفم', type: 'textarea' },
        ],
    };

    return fieldMap[type ?? ''] ?? [];
});

function setFormDataField(key: string, value: string) {
    form.form_data = { ...form.form_data, [key]: value };
}

function getFormDataField(key: string): string {
    return form.form_data?.[key] ?? '';
}
</script>

<template>
    <Head title="سجل طبي جديد" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Link href="/medical-records" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" />
                    العودة
                </Link>
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10">
                        <Stethoscope class="size-5 text-primary" />
                    </div>
                    <div>
                        <h1 class="page-title">سجل طبي جديد</h1>
                        <p class="mt-0.5 text-sm text-muted-foreground">إنشاء سجل طبي للمريض</p>
                    </div>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div v-if="appointment_data" class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <div class="flex items-center gap-2 text-emerald-700">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm font-medium">تم تعبئة البيانات تلقائياً من الموعد</span>
                </div>
                <p class="mt-1 text-xs text-emerald-600">
                    الموعد: {{ new Date(appointment_data.scheduled_for).toLocaleString('ar-SA') }}
                    <span v-if="appointment_data.appointment_type" class="ms-2">
                        ({{ appointment_data.appointment_type === 'first_visit' ? 'زيارة أولى' : 'متابعة' }})
                    </span>
                </p>
            </div>

            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold border-b border-border/50 pb-3">بيانات الزيارة</h3>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="flex flex-col gap-1.5">
                        <Label>المريض *</Label>
                        <select
                            v-model="form.patient_id"
                            class="flex h-10 w-full rounded-lg border border-border bg-background px-3 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                        >
                            <option :value="null">اختر المريض</option>
                            <option v-for="patient in patients" :key="patient.id" :value="patient.id">
                                {{ patient.first_name }} {{ patient.last_name }} (#{{ patient.file_number }})
                            </option>
                        </select>
                        <InputError :message="form.errors.patient_id" />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label>العيادة / القسم</Label>
                        <select
                            v-model="form.clinic_id"
                            class="flex h-10 w-full rounded-lg border border-border bg-background px-3 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                        >
                            <option :value="null">اختر العيادة</option>
                            <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
                                {{ clinic.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.clinic_id" />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label>نوع العيادة</Label>
                        <select
                            v-model="form.clinic_type"
                            class="flex h-10 w-full rounded-lg border border-border bg-background px-3 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                        >
                            <option value="">اختر نوع العيادة</option>
                            <option v-for="type in clinicTypes" :key="type" :value="type">
                                {{ clinicTypeLabel(type) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.clinic_type" />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label>تاريخ الزيارة</Label>
                        <Input v-model="form.visit_date" type="date" />
                        <InputError :message="form.errors.visit_date" />
                    </div>
                </div>

                <div v-if="form.clinic_type" class="flex items-center gap-2">
                    <Badge variant="outline" class="border-primary/30 text-primary bg-primary/5">
                        <Stethoscope class="me-1 size-3" />
                        نوع العيادة: {{ clinicTypeLabel(form.clinic_type) }}
                    </Badge>
                </div>
            </div>

            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold border-b border-border/50 pb-3">الفحص والتشخيص</h3>

                <div class="flex flex-col gap-1.5">
                    <Label>الشكوى الرئيسية</Label>
                    <textarea
                        v-model="form.chief_complaint"
                        rows="3"
                        class="flex w-full rounded-lg border border-border bg-background px-3 py-2 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                        placeholder="اكتب الشكوى الرئيسية..."
                    />
                    <InputError :message="form.errors.chief_complaint" />
                </div>

                <div v-if="clinicFormFields.length > 0" class="space-y-4 rounded-lg border border-primary/20 bg-primary/5 p-4">
                    <h4 class="text-xs font-semibold text-primary uppercase">
                        حقول خاصة بعيادة {{ clinicTypeLabel(form.clinic_type) }}
                    </h4>
                    <div
                        v-for="field in clinicFormFields"
                        :key="field.key"
                        class="flex flex-col gap-1.5"
                    >
                        <Label>{{ field.label }}</Label>
                        <textarea
                            v-if="field.type === 'textarea'"
                            :value="getFormDataField(field.key)"
                            rows="2"
                            class="flex w-full rounded-lg border border-border bg-background px-3 py-2 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                            @input="setFormDataField(field.key, ($event.target as HTMLTextAreaElement).value)"
                        />
                        <Input
                            v-else
                            :value="getFormDataField(field.key)"
                            @input="setFormDataField(field.key, ($event.target as HTMLInputElement).value)"
                        />
                    </div>
                </div>

                <div v-else class="flex flex-col gap-1.5">
                    <Label>الفحص السريري</Label>
                    <textarea
                        v-model="form.examination"
                        rows="3"
                        class="flex w-full rounded-lg border border-border bg-background px-3 py-2 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                        placeholder="اكتب نتائج الفحص السريري..."
                    />
                    <InputError :message="form.errors.examination" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <Label>التشخيص الرئيسي</Label>
                        <textarea
                            v-model="form.primary_diagnosis"
                            rows="2"
                            class="flex w-full rounded-lg border border-border bg-background px-3 py-2 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                            placeholder="التشخيص الرئيسي..."
                        />
                        <InputError :message="form.errors.primary_diagnosis" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>التشخيص الثانوي</Label>
                        <textarea
                            v-model="form.secondary_diagnosis"
                            rows="2"
                            class="flex w-full rounded-lg border border-border bg-background px-3 py-2 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                            placeholder="التشخيص الثانوي (اختياري)..."
                        />
                        <InputError :message="form.errors.secondary_diagnosis" />
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label>ملاحظات سريرية</Label>
                    <textarea
                        v-model="form.clinical_notes"
                        rows="3"
                        class="flex w-full rounded-lg border border-border bg-background px-3 py-2 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                        placeholder="ملاحظات إضافية..."
                    />
                    <InputError :message="form.errors.clinical_notes" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label>الحالة</Label>
                    <select
                        v-model="form.status"
                        class="flex h-10 w-full max-w-xs rounded-lg border border-border bg-background px-3 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                    >
                        <option value="draft">مسودة</option>
                        <option value="active">نشط</option>
                        <option value="completed">مكتمل</option>
                    </select>
                </div>
            </div>

            <div class="glass-panel-soft space-y-5 p-5">
                <div class="flex items-center justify-between border-b border-border/50 pb-3">
                    <h3 class="text-sm font-semibold">خطط العلاج</h3>
                    <Button type="button" variant="outline" size="sm" @click="addTreatmentPlan">
                        <Plus class="me-1 size-3.5" />
                        إضافة خطة علاج
                    </Button>
                </div>

                <div v-if="form.treatment_plans.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                    لا توجد خطط علاج مضافة
                </div>

                <div
                    v-for="(plan, index) in form.treatment_plans"
                    :key="index"
                    class="space-y-3 rounded-lg border border-border/50 p-4"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-muted-foreground">خطة علاج #{{ index + 1 }}</span>
                        <button type="button" class="text-red-500 hover:text-red-700" @click="removeTreatmentPlan(index)">
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <Label>العنوان *</Label>
                            <Input v-model="plan.title" placeholder="عنوان خطة العلاج" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label>الحالة</Label>
                            <select v-model="plan.status" class="flex h-10 w-full rounded-lg border border-border bg-background px-3 text-sm">
                                <option value="new">جديد</option>
                                <option value="in_progress">قيد التنفيذ</option>
                                <option value="completed">مكتمل</option>
                                <option value="cancelled">ملغي</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>الوصف</Label>
                        <textarea v-model="plan.description" rows="2" class="flex w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <Label>تاريخ البداية</Label>
                            <Input v-model="plan.start_date" type="date" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label>تاريخ النهاية</Label>
                            <Input v-model="plan.end_date" type="date" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="glass-panel-soft space-y-5 p-5">
                <div class="flex items-center justify-between border-b border-border/50 pb-3">
                    <h3 class="text-sm font-semibold">المتابعات</h3>
                    <Button type="button" variant="outline" size="sm" @click="addFollowUp">
                        <Plus class="me-1 size-3.5" />
                        إضافة متابعة
                    </Button>
                </div>

                <div v-if="form.follow_ups.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                    لا توجد متابعات مضافة
                </div>

                <div
                    v-for="(followUp, index) in form.follow_ups"
                    :key="index"
                    class="space-y-3 rounded-lg border border-border/50 p-4"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-muted-foreground">متابعة #{{ index + 1 }}</span>
                        <button type="button" class="text-red-500 hover:text-red-700" @click="removeFollowUp(index)">
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <Label>تاريخ المتابعة *</Label>
                            <Input v-model="followUp.follow_up_date" type="date" />
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>ملاحظات</Label>
                        <textarea v-model="followUp.notes" rows="2" class="flex w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>الإجراء الموصى به</Label>
                        <Input v-model="followUp.recommended_action" placeholder="الإجراء الموصى به..." />
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <Link href="/medical-records" class="inline-flex items-center gap-2 rounded-xl border border-border/60 px-4 py-2.5 text-sm font-medium transition-colors hover:bg-muted/50">
                    إلغاء
                </Link>
                <Button type="submit" :disabled="form.processing">
                    <Save class="me-2 size-4" />
                    {{ form.processing ? 'جارٍ الحفظ...' : 'حفظ السجل الطبي' }}
                </Button>
            </div>
        </form>
    </div>
</template>
