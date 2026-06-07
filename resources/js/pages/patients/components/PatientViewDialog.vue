<script setup lang="ts">
import { ref, watch } from 'vue';
import { Form, Link } from '@inertiajs/vue3';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogBody,
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

const formatDateTime = (value: string | null): string => {
    if (value === null) {
        return '-';
    }

    return new Date(value).toLocaleString('ar-SA');
};

const patientGenderLabel = (gender: string | null): string => {
    const labels: Record<string, string> = {
        male: 'ذكر',
        female: 'أنثى',
        other: 'آخر',
    };

    return labels[gender ?? ''] ?? 'غير محدد';
};

const refreshDetails = async () => {
    if (detailedPatient.value !== null) {
        try {
            const fetched = await fetchPatientDetails(detailedPatient.value.id);
            detailedPatient.value = fetched;
        } catch {
            // Keep existing data on refresh failure
        }
    }
};
</script>

<template>
    <Dialog :open="patient !== null" @update:open="(open) => !open && emit('close')">
        <DialogContent class="max-w-[520px] bg-white rounded-xl">
            <DialogHeader class="p-6 pb-4 border-b border-[#E5E7EB]">
                <DialogTitle class="text-base font-medium text-[#1A1A1A]">عرض ملف المريض</DialogTitle>
                <DialogDescription class="text-sm text-[#6B7280] mt-0.5">عرض ملف المريض الكامل</DialogDescription>
            </DialogHeader>

            <DialogBody class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                <div v-if="isLoading" class="rounded-lg border border-[#E5E7EB]/70 bg-[#F9FAFB] p-4">
                    <div class="h-3 w-2/3 rounded bg-[#E5E7EB] animate-pulse motion-reduce:animate-none motion-reduce:opacity-30" />
                    <div class="h-3 w-1/2 rounded bg-[#E5E7EB] animate-pulse motion-reduce:animate-none motion-reduce:opacity-30 mt-2" />
                    <div class="h-3 w-4/5 rounded bg-[#E5E7EB] animate-pulse motion-reduce:animate-none motion-reduce:opacity-30 mt-2" />
                </div>

                <p v-if="error !== null" class="rounded-lg border border-[#DC2626]/35 bg-[#FEF2F2] px-3 py-2 text-sm text-[#DC2626]">
                    {{ error }}
                </p>

                <div v-if="detailedPatient" class="space-y-4">
                <div class="divide-y divide-[#E5E7EB] rounded-xl border border-[#E5E7EB] bg-white">
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">رقم الملف</span>
                        <span class="flex-1 text-sm font-medium text-[#1A1A1A] font-mono">{{ detailedPatient.file_number }}</span>
                    </div>
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">الاسم الكامل</span>
                        <span class="flex-1 text-sm font-medium text-[#1A1A1A]">{{ detailedPatient.full_name }}</span>
                    </div>
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">الجنس</span>
                        <span class="flex-1 text-sm text-[#6B7280] capitalize">{{ patientGenderLabel(detailedPatient.gender) }}</span>
                    </div>
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">تاريخ الميلاد</span>
                        <span class="flex-1 text-sm text-[#6B7280]">{{ detailedPatient.date_of_birth ?? 'غير محدد' }}</span>
                    </div>
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">العمر</span>
                        <span class="flex-1 text-sm text-[#6B7280]">{{ detailedPatient.age ?? 'غير متوفر' }}</span>
                    </div>
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">الهاتف</span>
                        <span class="flex-1 text-sm text-[#6B7280]">{{ detailedPatient.phone ?? 'غير محدد' }}</span>
                    </div>
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">البريد الإلكتروني</span>
                        <span class="flex-1 text-sm text-[#6B7280] truncate">{{ detailedPatient.email ?? 'غير محدد' }}</span>
                    </div>
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">رقم الهوية</span>
                        <span class="flex-1 text-sm text-[#6B7280]">{{ detailedPatient.national_id ?? 'غير محدد' }}</span>
                    </div>
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">جهة اتصال الطوارئ</span>
                        <span class="flex-1 text-sm text-[#6B7280]">
                            {{ detailedPatient.emergency_contact_name ? `${detailedPatient.emergency_contact_name} (${detailedPatient.emergency_contact_phone ?? 'بدون هاتف'})` : 'غير محدد' }}
                        </span>
                    </div>
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">ملاحظات</span>
                        <span class="flex-1 text-sm leading-relaxed text-[#6B7280]">{{ detailedPatient.notes ?? 'لا توجد ملاحظات' }}</span>
                    </div>
                </div>

                <div class="rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-4">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h4 class="text-xs font-semibold tracking-wider uppercase text-[#9CA3AF]">أمراض مزمنة</h4>
                    </div>
                    <ul v-if="detailedPatient.chronic_conditions.length > 0" class="space-y-1">
                        <li v-for="(item, index) in detailedPatient.chronic_conditions" :key="`view-chronic-${index}`" class="rounded-lg border border-[#E5E7EB] bg-white px-3 py-1.5 text-sm text-[#6B7280]">{{ item }}</li>
                    </ul>
                    <p v-else class="text-sm text-[#9CA3AF]">غير محددة</p>
                </div>

                <div class="rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-4">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h4 class="text-xs font-semibold tracking-wider uppercase text-[#9CA3AF]">حساسية</h4>
                    </div>
                    <ul v-if="detailedPatient.allergies.length > 0" class="space-y-1">
                        <li v-for="(item, index) in detailedPatient.allergies" :key="`view-allergy-${index}`" class="rounded-lg border border-[#E5E7EB] bg-white px-3 py-1.5 text-sm text-[#6B7280]">{{ item }}</li>
                    </ul>
                    <p v-else class="text-sm text-[#9CA3AF]">غير محددة</p>
                </div>

                <div class="rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-4">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h4 class="text-xs font-semibold tracking-wider uppercase text-[#9CA3AF]">أدوية حالية</h4>
                    </div>
                    <ul v-if="detailedPatient.current_medications.length > 0" class="space-y-1">
                        <li v-for="(item, index) in detailedPatient.current_medications" :key="`view-medication-${index}`" class="rounded-lg border border-[#E5E7EB] bg-white px-3 py-1.5 text-sm text-[#6B7280]">{{ item }}</li>
                    </ul>
                    <p v-else class="text-sm text-[#9CA3AF]">غير محددة</p>
                </div>

                <div class="rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-4">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h4 class="text-xs font-semibold tracking-wider uppercase text-[#9CA3AF]">المرفقات</h4>
                    </div>

                    <Form
                        v-if="can('patient.update')"
                        v-bind="PatientController.storeAttachment.form(detailedPatient.id)"
                        class="flex flex-col gap-2 sm:flex-row sm:gap-2"
                        :options="{ preserveState: true, preserveScroll: true }"
                        @success="refreshDetails"
                        #default="{ errors, processing }"
                    >
                        <Input
                            type="file"
                            name="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="w-full h-10 rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm text-[#1A1A1A] focus:outline-none focus:border-[#0EA5E9] focus:ring-2 focus:ring-[#0EA5E9]/10 transition-colors"
                        />
                        <Button
                            type="submit"
                            variant="default"
                            class="h-10 px-4 rounded-lg bg-[#0EA5E9] text-white text-sm font-medium hover:bg-[#0284C7] transition-colors duration-150"
                            :disabled="processing"
                        >
                            رفع
                        </Button>
                        <InputError :message="errors.file" class="sm:col-span-2" />
                    </Form>

                    <div v-if="detailedPatient.attachments.length > 0" class="space-y-2 mt-2">
                        <div v-for="attachment in detailedPatient.attachments" :key="`view-attachment-${attachment.id}`" class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-[#E5E7EB] bg-[#F9FAFB] px-3 py-2">
                            <div class="space-y-0.5">
                                <p class="text-sm font-medium text-[#374151]">{{ attachment.original_name }}</p>
                                <p class="text-xs text-[#9CA3AF]">{{ attachment.mime_type ?? 'نوع غير معروف' }} - {{ formatBytes(attachment.size_bytes) }}</p>
                                <p class="text-xs text-[#9CA3AF]">تم الرفع: {{ formatDateTime(attachment.uploaded_at) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a
                                    :href="attachment.download_url"
                                    class="inline-flex h-9 items-center rounded-full border border-[#E5E7EB] bg-white px-3 text-xs font-medium text-[#6B7280] transition-colors duration-150 hover:bg-[#F9FAFB] hover:text-[#374151]"
                                >
                                    تحميل
                                </a>
                                <Link
                                    v-if="can('patient.update')"
                                    :href="PatientController.destroyAttachment([detailedPatient.id, attachment.id])"
                                    method="delete"
                                    as="button"
                                    class="inline-flex h-9 items-center rounded-full border border-[#DC2626]/30 bg-[#FEF2F2] px-3 text-xs font-medium text-[#DC2626] transition-colors duration-150 hover:bg-[#FEF2F2]/80"
                                    @success="refreshDetails"
                                >
                                    حذف
                                </Link>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-[#9CA3AF]">لا توجد مرفقات.</p>
                </div>
                </div>
            </DialogBody>

            <DialogFooter class="flex items-center justify-between p-6 pt-4 border-t border-[#E5E7EB]">
                <Button type="button" variant="ghost" class="h-9 px-4 rounded-lg text-[#6B7280] text-sm font-medium hover:bg-[#F9FAFB] hover:text-[#374151] transition-colors duration-150 active:scale-[0.98]" @click="emit('close')">إغلاق</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
