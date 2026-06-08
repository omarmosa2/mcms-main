<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    FileText,
    Plus,
    Save,
    Trash2,
} from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PatientOption = {
    id: number;
    first_name: string;
    last_name: string;
    file_number: number;
};

type MedicalRecord = {
    id: number;
    patient_id: number;
    primary_diagnosis: string | null;
};

const { patients, medical_record } = defineProps<{
    patients: PatientOption[];
    medical_record: MedicalRecord | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'مساحة الطبيب', href: '/doctor/workspace' },
            { title: 'الوصفات الطبية', href: '/doctor/prescriptions' },
            { title: 'وصفة جديدة', href: '/doctor/prescriptions/create' },
        ],
    },
});

type MedicationItem = {
    medication_name: string;
    dosage: string;
    frequency: string;
    duration: string;
    quantity: number;
    instructions: string;
};

const form = useForm({
    patient_id: (medical_record?.patient_id ?? null) as number | null,
    medical_record_id: (medical_record?.id ?? null) as number | null,
    diagnosis: (medical_record?.primary_diagnosis ?? '') as string,
    notes: '',
    items: [] as MedicationItem[],
});

function addMedication() {
    form.items.push({
        medication_name: '',
        dosage: '',
        frequency: '',
        duration: '',
        quantity: 1,
        instructions: '',
    });
}

function removeMedication(index: number) {
    form.items.splice(index, 1);
}

function submit() {
    form.post('/doctor/prescriptions');
}
</script>

<template>
    <Head title="وصفة طبية جديدة" />

    <div class="container-modern space-y-6 py-6" dir="rtl">
        <!-- Header -->
        <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Link href="/doctor/prescriptions" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" />
                    العودة
                </Link>
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10">
                        <FileText class="size-5 text-primary" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-[#111827]">وصفة طبية جديدة</h1>
                        <p class="text-sm text-muted-foreground">إنشاء وصفة طبية للمريض</p>
                    </div>
                </div>
            </div>
        </section>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Patient & Diagnosis -->
            <div class="rounded-2xl border border-[#E2ECF6] bg-white p-5 space-y-5">
                <h3 class="text-sm font-semibold text-slate-900 border-b border-border/50 pb-3">بيانات الوصفة</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <Label>المريض *</Label>
                        <select
                            v-model="form.patient_id"
                            class="flex h-10 w-full rounded-lg border border-border bg-background px-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                        >
                            <option :value="null">اختر المريض</option>
                            <option v-for="patient in patients" :key="patient.id" :value="patient.id">
                                {{ patient.first_name }} {{ patient.last_name }} (#{{ patient.file_number }})
                            </option>
                        </select>
                        <InputError :message="form.errors.patient_id" />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label>التشخيص</Label>
                        <textarea
                            v-model="form.diagnosis"
                            rows="2"
                            class="flex w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                            placeholder="التشخيص..."
                        />
                        <InputError :message="form.errors.diagnosis" />
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label>ملاحظات الطبيب</Label>
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        class="flex w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                        placeholder="ملاحظات إضافية..."
                    />
                    <InputError :message="form.errors.notes" />
                </div>
            </div>

            <!-- Medications -->
            <div class="rounded-2xl border border-[#E2ECF6] bg-white p-5 space-y-5">
                <div class="flex items-center justify-between border-b border-border/50 pb-3">
                    <h3 class="text-sm font-semibold text-slate-900">الأدوية</h3>
                    <Button type="button" variant="outline" size="sm" @click="addMedication">
                        <Plus class="me-1 size-3.5" />
                        إضافة دواء
                    </Button>
                </div>

                <div v-if="form.items.length === 0" class="py-8 text-center">
                    <FileText class="mx-auto size-10 text-slate-200 mb-3" />
                    <p class="text-sm text-slate-400">لم يتم إضافة أي أدوية بعد</p>
                    <Button type="button" variant="outline" size="sm" class="mt-3" @click="addMedication">
                        <Plus class="me-1 size-3.5" />
                        إضافة أول دواء
                    </Button>
                </div>

                <div
                    v-for="(item, index) in form.items"
                    :key="index"
                    class="space-y-3 rounded-xl border border-[#E2ECF6] bg-[#FAFCFE] p-4"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500">دواء #{{ index + 1 }}</span>
                        <button type="button" class="text-red-500 hover:text-red-700" @click="removeMedication(index)">
                            <Trash2 class="size-4" />
                        </button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="flex flex-col gap-1.5 lg:col-span-2">
                            <Label>اسم الدواء *</Label>
                            <Input v-model="item.medication_name" placeholder="اسم الدواء" />
                            <InputError :message="form.errors[`items.${index}.medication_name`]" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label>الكمية *</Label>
                            <Input v-model.number="item.quantity" type="number" min="1" />
                            <InputError :message="form.errors[`items.${index}.quantity`]" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="flex flex-col gap-1.5">
                            <Label>الجرعة *</Label>
                            <Input v-model="item.dosage" placeholder="مثال: 500mg" />
                            <InputError :message="form.errors[`items.${index}.dosage`]" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label>عدد المرات يومياً *</Label>
                            <Input v-model="item.frequency" placeholder="مثال: 3 مرات" />
                            <InputError :message="form.errors[`items.${index}.frequency`]" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label>مدة الاستخدام</Label>
                            <Input v-model="item.duration" placeholder="مثال: 7 أيام" />
                            <InputError :message="form.errors[`items.${index}.duration`]" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label>تعليمات خاصة</Label>
                        <Input v-model="item.instructions" placeholder="تعليمات إضافية..." />
                        <InputError :message="form.errors[`items.${index}.instructions`]" />
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3">
                <Link href="/doctor/prescriptions" class="inline-flex items-center gap-2 rounded-xl border border-border/60 px-4 py-2.5 text-sm font-medium transition-colors hover:bg-muted/50">
                    إلغاء
                </Link>
                <Button type="submit" :disabled="form.processing || form.items.length === 0">
                    <Save class="me-2 size-4" />
                    {{ form.processing ? 'جارٍ الحفظ...' : 'حفظ الوصفة الطبية' }}
                </Button>
            </div>
        </form>
    </div>
</template>
