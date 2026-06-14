<script setup lang="ts">
import { ref, watch } from 'vue';
import { Form, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    FileText,
    HeartPulse,
    Paperclip,
    Pill,
    ShieldAlert,
} from 'lucide-vue-next';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import InputError from '@/components/InputError.vue';
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
import { usePermissions } from '@/composables/usePermissions';
import type { Patient } from './types';

const props = defineProps<{
    patient: Patient | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { can } = usePermissions();

const detailedPatient = ref<Patient | null>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);

const fetchPatientDetails = async (patientId: number): Promise<Patient> => {
    const response = await fetch(PatientController.show.url(patientId), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Failed to load patient details');
    }

    const payload = (await response.json()) as { data?: Patient };

    if (payload.data === undefined) {
        throw new Error('Invalid patient payload');
    }

    return payload.data;
};

watch(
    () => props.patient,
    async (newPatient) => {
        if (newPatient === null) {
            detailedPatient.value = null;
            isLoading.value = false;
            error.value = null;
            return;
        }

        detailedPatient.value = newPatient;
        isLoading.value = true;
        error.value = null;

        try {
            const fetched = await fetchPatientDetails(newPatient.id);

            if (props.patient?.id === newPatient.id) {
                detailedPatient.value = fetched;
            }
        } catch {
            if (props.patient?.id === newPatient.id) {
                error.value = 'تعذر تحميل الملف الكامل للمريض.';
            }
        } finally {
            if (props.patient?.id === newPatient.id) {
                isLoading.value = false;
            }
        }
    },
    { immediate: true },
);

const formatBytes = (sizeBytes: number): string => {
    if (sizeBytes < 1024) {
        return `${sizeBytes} B`;
    }

    if (sizeBytes < 1024 * 1024) {
        return `${(sizeBytes / 1024).toFixed(1)} KB`;
    }

    return `${(sizeBytes / (1024 * 1024)).toFixed(1)} MB`;
};

const formatDate = (value: string | null): string => {
    if (value === null) {
        return '-';
    }

    return new Intl.DateTimeFormat('ar-SY').format(new Date(value));
};

const formatDateTime = (value: string | null): string => {
    if (value === null) {
        return '-';
    }

    return new Intl.DateTimeFormat('ar-SY', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const patientGenderLabel = (gender: string | null): string => {
    const labels: Record<string, string> = {
        male: 'ذكر',
        female: 'أنثى',
        other: 'آخر',
    };

    return labels[gender ?? ''] ?? 'غير محدد';
};

const patientInitial = (patient: Patient | null): string => {
    return (patient?.full_name ?? 'م').slice(0, 1);
};

const emergencyContactLabel = (patient: Patient): string => {
    if (!patient.emergency_contact_name) {
        return 'غير محدد';
    }

    if (!patient.emergency_contact_phone) {
        return `${patient.emergency_contact_name} (بدون هاتف)`;
    }

    return `${patient.emergency_contact_name} (${patient.emergency_contact_phone})`;
};

const refreshDetails = async (): Promise<void> => {
    if (detailedPatient.value !== null) {
        try {
            const fetched = await fetchPatientDetails(detailedPatient.value.id);
            detailedPatient.value = fetched;
        } catch {
            // Keep existing data on refresh failure.
        }
    }
};
</script>

<template>
    <Dialog
        :open="patient !== null"
        @update:open="(open) => !open && emit('close')"
    >
        <DialogContent size="2xl" class="max-h-[92vh] bg-card p-0" dir="rtl">
            <DialogHeader class="border-b border-border px-6 py-5 text-right">
                <div class="flex items-start justify-between gap-4 pl-10">
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-lg font-black text-primary"
                        >
                            {{ patientInitial(detailedPatient ?? patient) }}
                        </span>
                        <div class="min-w-0">
                            <DialogTitle
                                class="truncate text-2xl font-black text-foreground"
                            >
                                {{
                                    detailedPatient?.full_name ??
                                    patient?.full_name ??
                                    'عرض ملف المريض'
                                }}
                            </DialogTitle>
                            <DialogDescription
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                ملف المريض الكامل، بيانات التواصل، والتنبيهات
                                الطبية.
                            </DialogDescription>
                        </div>
                    </div>

                    <span
                        v-if="detailedPatient"
                        class="mt-1 inline-flex shrink-0 items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-bold text-primary"
                    >
                        <FileText class="size-3.5" />
                        ملف رقم {{ detailedPatient.file_number }}
                    </span>
                </div>
            </DialogHeader>

            <div class="max-h-[68vh] space-y-5 overflow-y-auto p-6">
                <div
                    v-if="isLoading"
                    class="rounded-xl border border-border bg-muted/40 p-4"
                >
                    <div
                        class="h-3 w-2/3 animate-pulse rounded bg-muted-foreground/20 motion-reduce:animate-none"
                    />
                    <div
                        class="mt-2 h-3 w-1/2 animate-pulse rounded bg-muted-foreground/20 motion-reduce:animate-none"
                    />
                    <div
                        class="mt-2 h-3 w-4/5 animate-pulse rounded bg-muted-foreground/20 motion-reduce:animate-none"
                    />
                </div>

                <p
                    v-if="error !== null"
                    class="flex items-center gap-2 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-semibold text-destructive"
                >
                    <AlertTriangle class="size-4" />
                    {{ error }}
                </p>

                <template v-if="detailedPatient">
                    <section
                        class="rounded-xl border border-border bg-muted/40 p-4"
                    >
                        <h3 class="mb-4 text-sm font-black text-foreground">
                            البيانات الأساسية
                        </h3>

                        <dl class="grid gap-3 md:grid-cols-3">
                            <div
                                class="rounded-lg border border-border/70 bg-card px-4 py-3"
                            >
                                <dt
                                    class="text-xs font-semibold text-muted-foreground"
                                >
                                    رقم الملف
                                </dt>
                                <dd
                                    class="mt-1 font-bold text-foreground tabular-nums"
                                >
                                    {{ detailedPatient.file_number }}
                                </dd>
                            </div>
                            <div
                                class="rounded-lg border border-border/70 bg-card px-4 py-3"
                            >
                                <dt
                                    class="text-xs font-semibold text-muted-foreground"
                                >
                                    الجنس
                                </dt>
                                <dd class="mt-1 font-bold text-foreground">
                                    {{
                                        patientGenderLabel(
                                            detailedPatient.gender,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div
                                class="rounded-lg border border-border/70 bg-card px-4 py-3"
                            >
                                <dt
                                    class="text-xs font-semibold text-muted-foreground"
                                >
                                    العمر
                                </dt>
                                <dd class="mt-1 font-bold text-foreground">
                                    {{ detailedPatient.age ?? 'غير متوفر' }}
                                </dd>
                            </div>
                            <div
                                class="rounded-lg border border-border/70 bg-card px-4 py-3"
                            >
                                <dt
                                    class="text-xs font-semibold text-muted-foreground"
                                >
                                    تاريخ الميلاد
                                </dt>
                                <dd class="mt-1 font-bold text-foreground">
                                    {{
                                        detailedPatient.date_of_birth ??
                                        'غير محدد'
                                    }}
                                </dd>
                            </div>
                            <div
                                class="rounded-lg border border-border/70 bg-card px-4 py-3"
                            >
                                <dt
                                    class="text-xs font-semibold text-muted-foreground"
                                >
                                    رقم الهوية
                                </dt>
                                <dd class="mt-1 font-bold text-foreground">
                                    {{
                                        detailedPatient.national_id ??
                                        'غير محدد'
                                    }}
                                </dd>
                            </div>
                            <div
                                class="rounded-lg border border-border/70 bg-card px-4 py-3"
                            >
                                <dt
                                    class="text-xs font-semibold text-muted-foreground"
                                >
                                    تاريخ الإنشاء
                                </dt>
                                <dd class="mt-1 font-bold text-foreground">
                                    {{ formatDate(detailedPatient.created_at) }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        class="rounded-xl border border-border bg-card p-4"
                    >
                        <h3 class="mb-4 text-sm font-black text-foreground">
                            بيانات التواصل
                        </h3>

                        <dl class="grid gap-3 md:grid-cols-2">
                            <div
                                class="rounded-lg border border-border/70 bg-muted/40 px-4 py-3"
                            >
                                <dt
                                    class="text-xs font-semibold text-muted-foreground"
                                >
                                    الهاتف
                                </dt>
                                <dd
                                    class="mt-1 font-bold text-foreground"
                                    dir="ltr"
                                >
                                    {{ detailedPatient.phone ?? 'غير محدد' }}
                                </dd>
                            </div>
                            <div
                                class="rounded-lg border border-border/70 bg-muted/40 px-4 py-3"
                            >
                                <dt
                                    class="text-xs font-semibold text-muted-foreground"
                                >
                                    البريد الإلكتروني
                                </dt>
                                <dd
                                    class="mt-1 truncate font-bold text-foreground"
                                    dir="ltr"
                                >
                                    {{ detailedPatient.email ?? 'غير محدد' }}
                                </dd>
                            </div>
                            <div
                                class="rounded-lg border border-border/70 bg-muted/40 px-4 py-3 md:col-span-2"
                            >
                                <dt
                                    class="text-xs font-semibold text-muted-foreground"
                                >
                                    جهة اتصال الطوارئ
                                </dt>
                                <dd class="mt-1 font-bold text-foreground">
                                    {{ emergencyContactLabel(detailedPatient) }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section class="grid gap-3 md:grid-cols-3">
                        <div
                            class="rounded-xl border border-border bg-card p-4"
                        >
                            <div class="mb-3 flex items-center gap-2">
                                <HeartPulse class="size-4 text-primary" />
                                <h3 class="text-sm font-black text-foreground">
                                    أمراض مزمنة
                                </h3>
                            </div>
                            <ul
                                v-if="
                                    detailedPatient.chronic_conditions.length >
                                    0
                                "
                                class="space-y-2"
                            >
                                <li
                                    v-for="(
                                        item, index
                                    ) in detailedPatient.chronic_conditions"
                                    :key="`view-chronic-${index}`"
                                    class="rounded-lg border border-border/70 bg-muted/40 px-3 py-2 text-sm font-semibold text-foreground"
                                >
                                    {{ item }}
                                </li>
                            </ul>
                            <p
                                v-else
                                class="text-sm font-semibold text-muted-foreground"
                            >
                                غير محددة
                            </p>
                        </div>

                        <div
                            class="rounded-xl border border-border bg-card p-4"
                        >
                            <div class="mb-3 flex items-center gap-2">
                                <ShieldAlert class="size-4 text-warning" />
                                <h3 class="text-sm font-black text-foreground">
                                    الحساسية
                                </h3>
                            </div>
                            <ul
                                v-if="detailedPatient.allergies.length > 0"
                                class="space-y-2"
                            >
                                <li
                                    v-for="(
                                        item, index
                                    ) in detailedPatient.allergies"
                                    :key="`view-allergy-${index}`"
                                    class="rounded-lg border border-border/70 bg-muted/40 px-3 py-2 text-sm font-semibold text-foreground"
                                >
                                    {{ item }}
                                </li>
                            </ul>
                            <p
                                v-else
                                class="text-sm font-semibold text-muted-foreground"
                            >
                                غير محددة
                            </p>
                        </div>

                        <div
                            class="rounded-xl border border-border bg-card p-4"
                        >
                            <div class="mb-3 flex items-center gap-2">
                                <Pill class="size-4 text-info" />
                                <h3 class="text-sm font-black text-foreground">
                                    أدوية حالية
                                </h3>
                            </div>
                            <ul
                                v-if="
                                    detailedPatient.current_medications.length >
                                    0
                                "
                                class="space-y-2"
                            >
                                <li
                                    v-for="(
                                        item, index
                                    ) in detailedPatient.current_medications"
                                    :key="`view-medication-${index}`"
                                    class="rounded-lg border border-border/70 bg-muted/40 px-3 py-2 text-sm font-semibold text-foreground"
                                >
                                    {{ item }}
                                </li>
                            </ul>
                            <p
                                v-else
                                class="text-sm font-semibold text-muted-foreground"
                            >
                                غير محددة
                            </p>
                        </div>
                    </section>

                    <section
                        class="rounded-xl border border-border bg-card p-4"
                    >
                        <h3 class="mb-3 text-sm font-black text-foreground">
                            ملاحظات
                        </h3>
                        <p
                            class="leading-7"
                            :class="
                                detailedPatient.notes === null
                                    ? 'font-semibold text-muted-foreground'
                                    : 'text-foreground'
                            "
                        >
                            {{ detailedPatient.notes ?? 'لا توجد ملاحظات' }}
                        </p>
                    </section>

                    <section
                        class="rounded-xl border border-border bg-card p-4"
                    >
                        <div
                            class="mb-4 flex items-center justify-between gap-3"
                        >
                            <div class="flex items-center gap-2">
                                <Paperclip class="size-4 text-primary" />
                                <h3 class="text-sm font-black text-foreground">
                                    المرفقات
                                </h3>
                            </div>
                            <span
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                {{ detailedPatient.attachments.length }} ملف
                            </span>
                        </div>

                        <Form
                            v-if="can('patient.update')"
                            v-bind="
                                PatientController.storeAttachment.form(
                                    detailedPatient.id,
                                )
                            "
                            class="mb-4 grid gap-2 sm:grid-cols-[1fr_auto]"
                            :options="{
                                preserveState: true,
                                preserveScroll: true,
                            }"
                            @success="refreshDetails"
                            #default="{ errors, processing }"
                        >
                            <Input
                                type="file"
                                name="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="h-10 rounded-lg"
                            />
                            <Button
                                type="submit"
                                variant="default"
                                :disabled="processing"
                            >
                                رفع
                            </Button>
                            <InputError
                                :message="errors.file"
                                class="sm:col-span-2"
                            />
                        </Form>

                        <div
                            v-if="detailedPatient.attachments.length > 0"
                            class="space-y-2"
                        >
                            <div
                                v-for="attachment in detailedPatient.attachments"
                                :key="`view-attachment-${attachment.id}`"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border/70 bg-muted/40 px-4 py-3"
                            >
                                <div class="min-w-0 space-y-1">
                                    <p
                                        class="truncate text-sm font-bold text-foreground"
                                    >
                                        {{ attachment.original_name }}
                                    </p>
                                    <p
                                        class="text-xs font-semibold text-muted-foreground"
                                    >
                                        {{
                                            attachment.mime_type ??
                                            'نوع غير معروف'
                                        }}
                                        ·
                                        {{ formatBytes(attachment.size_bytes) }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        تم الرفع:
                                        {{
                                            formatDateTime(
                                                attachment.uploaded_at,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a
                                        :href="attachment.download_url"
                                        class="inline-flex h-9 items-center rounded-lg border border-border bg-card px-3 text-xs font-bold text-foreground transition-colors hover:bg-muted"
                                    >
                                        تحميل
                                    </a>
                                    <Link
                                        v-if="can('patient.update')"
                                        :href="
                                            PatientController.destroyAttachment(
                                                [
                                                    detailedPatient.id,
                                                    attachment.id,
                                                ],
                                            )
                                        "
                                        method="delete"
                                        as="button"
                                        class="inline-flex h-9 items-center rounded-lg border border-destructive/30 bg-destructive/10 px-3 text-xs font-bold text-destructive transition-colors hover:bg-destructive/15"
                                        @success="refreshDetails"
                                    >
                                        حذف
                                    </Link>
                                </div>
                            </div>
                        </div>
                        <p
                            v-else
                            class="text-sm font-semibold text-muted-foreground"
                        >
                            لا توجد مرفقات.
                        </p>
                    </section>
                </template>
            </div>

            <DialogFooter class="border-t border-border px-6 py-4">
                <Button type="button" variant="outline" @click="emit('close')">
                    إغلاق
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
