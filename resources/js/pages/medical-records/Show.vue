<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarClock,
    ClipboardList,
    FileText,
    Plus,
    Stethoscope,
    Trash2,
    User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';

type Patient = {
    id: number;
    full_name: string;
    file_number: number;
    phone: string | null;
    date_of_birth: string | null;
    gender: string | null;
};

type Department = {
    id: number;
    name: string;
    clinic_type: string | null;
};

type Doctor = {
    id: number;
    name: string;
};

type TreatmentPlan = {
    id: number;
    medical_record_id: number;
    patient_id: number;
    doctor_id: number | null;
    doctor: Doctor | null;
    title: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    status: string;
    created_at: string | null;
    updated_at: string | null;
};

type FollowUp = {
    id: number;
    medical_record_id: number | null;
    patient_id: number;
    doctor_id: number | null;
    doctor: Doctor | null;
    follow_up_date: string | null;
    notes: string | null;
    recommended_action: string | null;
    status: string;
    created_at: string | null;
    updated_at: string | null;
};

type MedicalRecord = {
    id: number;
    clinic_id: number;
    patient_id: number;
    patient: Patient;
    department_id: number | null;
    department: Department | null;
    appointment_id: number | null;
    doctor_id: number | null;
    doctor: Doctor | null;
    record_number: string;
    clinic_type: string | null;
    form_data: Record<string, string> | null;
    chief_complaint: string | null;
    primary_diagnosis: string | null;
    secondary_diagnosis: string | null;
    clinical_notes: string | null;
    examination: string | null;
    status: string;
    visit_date: string | null;
    creator: Doctor | null;
    treatment_plans: TreatmentPlan[];
    follow_ups: FollowUp[];
    created_at: string | null;
    updated_at: string | null;
};

const { record } = defineProps<{
    record: MedicalRecord;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'السجلات الطبية',
                href: '/medical-records',
            },
            {
                title: `سجل #${record.record_number}`,
                href: `/medical-records/${record.id}`,
            },
        ],
    },
});

const { can } = usePermissions();
const { success: toastSuccess } = useToast();

const showTreatmentPlanDialog = ref(false);
const showFollowUpDialog = ref(false);

const treatmentPlanForm = useForm({
    medical_record_id: record.id,
    patient_id: record.patient_id,
    title: '',
    description: '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
    status: 'new',
});

const followUpForm = useForm({
    medical_record_id: record.id,
    patient_id: record.patient_id,
    follow_up_date: '',
    notes: '',
    recommended_action: '',
    status: 'scheduled',
});

function submitTreatmentPlan() {
    treatmentPlanForm.post('/medical-records/treatment-plans', {
        preserveScroll: true,
        onSuccess: () => {
            showTreatmentPlanDialog.value = false;
            treatmentPlanForm.reset();
            toastSuccess('تم إضافة خطة العلاج بنجاح.');
            router.reload();
        },
    });
}

function submitFollowUp() {
    followUpForm.post('/medical-records/follow-ups', {
        preserveScroll: true,
        onSuccess: () => {
            showFollowUpDialog.value = false;
            followUpForm.reset();
            toastSuccess('تم إضافة المتابعة بنجاح.');
            router.reload();
        },
    });
}

function deleteTreatmentPlan(planId: number) {
    router.delete(`/medical-records/treatment-plans/${planId}`, {
        preserveScroll: true,
        onSuccess: () => {
            toastSuccess('تم حذف خطة العلاج.');
            router.reload();
        },
    });
}

function deleteFollowUp(followUpId: number) {
    router.delete(`/medical-records/follow-ups/${followUpId}`, {
        preserveScroll: true,
        onSuccess: () => {
            toastSuccess('تم حذف المتابعة.');
            router.reload();
        },
    });
}

function updateTreatmentPlanStatus(planId: number, status: string) {
    router.put(`/medical-records/treatment-plans/${planId}`, { status }, {
        preserveScroll: true,
        onSuccess: () => {
            toastSuccess('تم تحديث حالة خطة العلاج.');
            router.reload();
        },
    });
}

function updateFollowUpStatus(followUpId: number, status: string) {
    router.put(`/medical-records/follow-ups/${followUpId}`, { status }, {
        preserveScroll: true,
        onSuccess: () => {
            toastSuccess('تم تحديث حالة المتابعة.');
            router.reload();
        },
    });
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

const statusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        draft: 'مسودة',
        active: 'نشط',
        completed: 'مكتمل',
        cancelled: 'ملغي',
        new: 'جديد',
        in_progress: 'قيد التنفيذ',
        scheduled: 'مجدول',
        missed: 'فائت',
    };

    return labels[status] ?? status;
};

const statusClass = (status: string): string => {
    const classes: Record<string, string> = {
        draft: 'bg-gray-500/10 text-gray-600 border-gray-500/20',
        active: 'bg-blue-500/10 text-blue-600 border-blue-500/20',
        completed: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
        cancelled: 'bg-red-500/10 text-red-600 border-red-500/20',
        new: 'bg-blue-500/10 text-blue-600 border-blue-500/20',
        in_progress: 'bg-yellow-500/10 text-yellow-600 border-yellow-500/20',
        scheduled: 'bg-blue-500/10 text-blue-600 border-blue-500/20',
        missed: 'bg-red-500/10 text-red-600 border-red-500/20',
    };

    return classes[status] ?? 'bg-muted/50 text-muted-foreground border-border/40';
};

const formatDate = (date: string | null): string => {
    if (!date) {
        return '—';
    }

    return new Date(date).toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatDateTime = (date: string | null): string => {
    if (!date) {
        return '—';
    }

    return new Date(date).toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formDataEntries = computed(() => {
    if (!record.form_data) {
        return [];
    }

    return Object.entries(record.form_data).filter(
        ([, value]) => value !== null && value !== '',
    );
});
</script>

<template>
    <Head :title="`سجل طبي - ${record.record_number}`" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Link href="/medical-records" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" />
                    العودة
                </Link>
                <div>
                    <h1 class="page-title">سجل طبي #{{ record.record_number }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ record.patient?.full_name }} - {{ record.department?.name ?? 'بدون عيادة' }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Badge :class="statusClass(record.status)" class="text-sm">
                    {{ statusLabel(record.status) }}
                </Badge>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-border/70 bg-card p-4">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <User class="size-4" />
                    <span class="text-xs font-semibold uppercase">المريض</span>
                </div>
                <p class="mt-2 text-sm font-semibold">{{ record.patient?.full_name ?? '—' }}</p>
                <p class="text-xs text-muted-foreground">ملف: {{ record.patient?.file_number ?? '—' }}</p>
            </div>
            <div class="rounded-xl border border-border/70 bg-card p-4">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <Stethoscope class="size-4" />
                    <span class="text-xs font-semibold uppercase">الطبيب</span>
                </div>
                <p class="mt-2 text-sm font-semibold">{{ record.doctor?.name ?? '—' }}</p>
            </div>
            <div class="rounded-xl border border-border/70 bg-card p-4">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <CalendarClock class="size-4" />
                    <span class="text-xs font-semibold uppercase">تاريخ الزيارة</span>
                </div>
                <p class="mt-2 text-sm font-semibold">{{ formatDate(record.visit_date) }}</p>
            </div>
            <div class="rounded-xl border border-border/70 bg-card p-4">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <ClipboardList class="size-4" />
                    <span class="text-xs font-semibold uppercase">نوع العيادة</span>
                </div>
                <p class="mt-2 text-sm font-semibold">{{ clinicTypeLabel(record.clinic_type) }}</p>
            </div>
        </div>

        <div class="glass-panel-soft space-y-5 p-5">
            <h3 class="text-sm font-semibold border-b border-border/50 pb-3">الفحص والتشخيص</h3>

            <div v-if="record.chief_complaint" class="space-y-1">
                <span class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الشكوى الرئيسية</span>
                <p class="text-sm whitespace-pre-wrap">{{ record.chief_complaint }}</p>
            </div>

            <div v-if="record.examination" class="space-y-1">
                <span class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الفحص السريري</span>
                <p class="text-sm whitespace-pre-wrap">{{ record.examination }}</p>
            </div>

            <div v-if="formDataEntries.length > 0" class="grid gap-3 sm:grid-cols-2">
                <div v-for="[key, value] in formDataEntries" :key="key" class="space-y-1 rounded-lg border border-border/40 bg-background/50 p-3">
                    <span class="text-[0.6rem] font-semibold tracking-normal text-muted-foreground uppercase">{{ key.replace(/_/g, ' ') }}</span>
                    <p class="text-sm whitespace-pre-wrap">{{ value }}</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div v-if="record.primary_diagnosis" class="space-y-1 rounded-lg border border-red-500/20 bg-red-500/5 p-3">
                    <span class="text-[0.65rem] font-semibold tracking-normal text-red-600 uppercase">التشخيص الرئيسي</span>
                    <p class="text-sm whitespace-pre-wrap">{{ record.primary_diagnosis }}</p>
                </div>
                <div v-if="record.secondary_diagnosis" class="space-y-1 rounded-lg border border-amber-500/20 bg-amber-500/5 p-3">
                    <span class="text-[0.65rem] font-semibold tracking-normal text-amber-600 uppercase">التشخيص الثانوي</span>
                    <p class="text-sm whitespace-pre-wrap">{{ record.secondary_diagnosis }}</p>
                </div>
            </div>

            <div v-if="record.clinical_notes" class="space-y-1">
                <span class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">ملاحظات سريرية</span>
                <p class="text-sm whitespace-pre-wrap">{{ record.clinical_notes }}</p>
            </div>
        </div>

        <div class="glass-panel-soft space-y-5 p-5">
            <div class="flex items-center justify-between border-b border-border/50 pb-3">
                <h3 class="text-sm font-semibold">خطط العلاج ({{ record.treatment_plans?.length ?? 0 }})</h3>
                <Button
                    v-if="can('medical_record.update')"
                    variant="outline"
                    size="sm"
                    @click="showTreatmentPlanDialog = true"
                >
                    <Plus class="me-1 size-3.5" />
                    إضافة خطة
                </Button>
            </div>

            <div v-if="record.treatment_plans?.length > 0" class="space-y-3">
                <div
                    v-for="plan in record.treatment_plans"
                    :key="plan.id"
                    class="rounded-lg border border-border/50 p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-medium text-sm">{{ plan.title }}</p>
                            <p v-if="plan.description" class="mt-1 text-xs text-muted-foreground whitespace-pre-wrap">{{ plan.description }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge :class="statusClass(plan.status)">{{ statusLabel(plan.status) }}</Badge>
                            <button
                                v-if="can('medical_record.delete')"
                                class="text-red-500 hover:text-red-700"
                                @click="deleteTreatmentPlan(plan.id)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                        <span v-if="plan.doctor">الطبيب: {{ plan.doctor.name }}</span>
                        <span v-if="plan.start_date">من: {{ formatDate(plan.start_date) }}</span>
                        <span v-if="plan.end_date">إلى: {{ formatDate(plan.end_date) }}</span>
                    </div>
                    <div v-if="plan.status !== 'completed' && plan.status !== 'cancelled'" class="mt-3 flex gap-2">
                        <button
                            v-if="can('medical_record.update')"
                            class="text-xs text-blue-600 hover:underline"
                            @click="updateTreatmentPlanStatus(plan.id, plan.status === 'new' ? 'in_progress' : 'completed')"
                        >
                            {{ plan.status === 'new' ? 'بدء التنفيذ' : 'إكمال' }}
                        </button>
                    </div>
                </div>
            </div>
            <p v-else class="py-4 text-center text-sm text-muted-foreground">لا توجد خطط علاج</p>
        </div>

        <div class="glass-panel-soft space-y-5 p-5">
            <div class="flex items-center justify-between border-b border-border/50 pb-3">
                <h3 class="text-sm font-semibold">المتابعات ({{ record.follow_ups?.length ?? 0 }})</h3>
                <Button
                    v-if="can('medical_record.update')"
                    variant="outline"
                    size="sm"
                    @click="showFollowUpDialog = true"
                >
                    <Plus class="me-1 size-3.5" />
                    إضافة متابعة
                </Button>
            </div>

            <div v-if="record.follow_ups?.length > 0" class="space-y-3">
                <div
                    v-for="followUp in record.follow_ups"
                    :key="followUp.id"
                    class="rounded-lg border border-border/50 p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-medium">{{ formatDate(followUp.follow_up_date) }}</p>
                            <p v-if="followUp.notes" class="mt-1 text-xs text-muted-foreground whitespace-pre-wrap">{{ followUp.notes }}</p>
                            <p v-if="followUp.recommended_action" class="mt-1 text-xs text-primary">الإجراء: {{ followUp.recommended_action }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge :class="statusClass(followUp.status)">{{ statusLabel(followUp.status) }}</Badge>
                            <button
                                v-if="can('medical_record.delete')"
                                class="text-red-500 hover:text-red-700"
                                @click="deleteFollowUp(followUp.id)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-muted-foreground">
                        <span v-if="followUp.doctor">الطبيب: {{ followUp.doctor.name }}</span>
                    </div>
                    <div v-if="followUp.status === 'scheduled'" class="mt-3 flex gap-2">
                        <button
                            v-if="can('medical_record.update')"
                            class="text-xs text-emerald-600 hover:underline"
                            @click="updateFollowUpStatus(followUp.id, 'completed')"
                        >
                            تمّت
                        </button>
                        <button
                            v-if="can('medical_record.update')"
                            class="text-xs text-red-600 hover:underline"
                            @click="updateFollowUpStatus(followUp.id, 'missed')"
                        >
                            فائتة
                        </button>
                    </div>
                </div>
            </div>
            <p v-else class="py-4 text-center text-sm text-muted-foreground">لا توجد متابعات</p>
        </div>

        <div class="glass-panel-soft p-5">
            <h3 class="mb-3 text-sm font-semibold border-b border-border/50 pb-3">معلومات السجل</h3>
            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-xs">
                <div>
                    <dt class="text-muted-foreground">رقم السجل</dt>
                    <dd class="font-mono font-medium">{{ record.record_number }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">أنشئ بواسطة</dt>
                    <dd class="font-medium">{{ record.creator?.name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">تاريخ الإنشاء</dt>
                    <dd class="font-medium">{{ formatDateTime(record.created_at) }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">آخر تحديث</dt>
                    <dd class="font-medium">{{ formatDateTime(record.updated_at) }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <Dialog v-model:open="showTreatmentPlanDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>إضافة خطة علاج</DialogTitle>
                <DialogDescription>أضف خطة علاج جديدة لهذا السجل الطبي</DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitTreatmentPlan" class="space-y-4">
                <div class="flex flex-col gap-1.5">
                    <Label>العنوان *</Label>
                    <Input v-model="treatmentPlanForm.title" placeholder="عنوان خطة العلاج" />
                    <InputError :message="treatmentPlanForm.errors.title" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>الوصف</Label>
                    <textarea v-model="treatmentPlanForm.description" rows="3" class="flex w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                </div>
                <div class="grid gap-3 grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <Label>تاريخ البداية</Label>
                        <Input v-model="treatmentPlanForm.start_date" type="date" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>تاريخ النهاية</Label>
                        <Input v-model="treatmentPlanForm.end_date" type="date" />
                    </div>
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>الحالة</Label>
                    <select v-model="treatmentPlanForm.status" class="flex h-10 w-full rounded-lg border border-border bg-background px-3 text-sm">
                        <option value="new">جديد</option>
                        <option value="in_progress">قيد التنفيذ</option>
                        <option value="completed">مكتمل</option>
                    </select>
                </div>
                <DialogFooter>
                    <Button variant="outline" type="button" @click="showTreatmentPlanDialog = false">إلغاء</Button>
                    <Button type="submit" :disabled="treatmentPlanForm.processing">
                        {{ treatmentPlanForm.processing ? 'جارٍ الحفظ...' : 'حفظ' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="showFollowUpDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>إضافة متابعة</DialogTitle>
                <DialogDescription>أضف متابعة جديدة لهذا السجل الطبي</DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitFollowUp" class="space-y-4">
                <div class="flex flex-col gap-1.5">
                    <Label>تاريخ المتابعة *</Label>
                    <Input v-model="followUpForm.follow_up_date" type="date" />
                    <InputError :message="followUpForm.errors.follow_up_date" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>ملاحظات</Label>
                    <textarea v-model="followUpForm.notes" rows="3" class="flex w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>الإجراء الموصى به</Label>
                    <Input v-model="followUpForm.recommended_action" placeholder="الإجراء الموصى به..." />
                </div>
                <DialogFooter>
                    <Button variant="outline" type="button" @click="showFollowUpDialog = false">إلغاء</Button>
                    <Button type="submit" :disabled="followUpForm.processing">
                        {{ followUpForm.processing ? 'جارٍ الحفظ...' : 'حفظ' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
