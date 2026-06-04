<script setup lang="ts">
import { AlertTriangle, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Form } from '@inertiajs/vue3';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import { Button } from '@/components/ui/button';
import { Dialog, DialogBody, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DialogOverlay } from '@/components/ui/overlay';
import { Spinner } from '@/components/ui/spinner';
import type { Patient } from './types';

const props = defineProps<{
    patient: Patient | null;
    loading?: boolean;
}>();

const emit = defineEmits<{
    close: [];
    confirm: [];
    'update:open': [value: boolean];
}>();

const isModalOpen = ref(false);

watch(
    () => props.patient,
    (newPatient) => {
        isModalOpen.value = newPatient !== null;
    },
    { immediate: true },
);

const handleOpenChange = (open: boolean) => {
    emit('update:open', open);
    if (!open) {
        emit('close');
    }
};

const handleConfirm = () => {
    emit('confirm');
};

const patientLabel = (patient: Patient): string => {
    return patient.full_name || `${patient.first_name} ${patient.last_name}`.trim() || patient.file_number || '—';
};
</script>

<template>
    <Dialog :open="isModalOpen && patient !== null" @update:open="handleOpenChange">
        <DialogContent
            :show-close-button="!loading"
            :close-on-overlay="!loading"
            class="max-w-[420px]"
        >
            <DialogHeader>
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#DC2626]/10">
                        <AlertTriangle class="h-5 w-5 text-[#DC2626]" />
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <DialogTitle class="text-base font-medium text-[#1A1A1A]">
                            حذف المريض
                        </DialogTitle>
                        <DialogDescription class="text-sm">
                            هل أنت متأكد من حذف المريض "{{ patient ? patientLabel(patient) : '' }}"
                            (ملف: {{ patient?.file_number ?? '—' }})؟ لا يمكن التراجع عن هذا الإجراء.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <DialogBody>
                <div class="mt-3 text-xs text-[#DC2626] bg-[#FEF2F2] rounded-lg px-3 py-2">
                    ⚠ تحذير: هذا الإجراء نهائي ولا يمكن التراجع عنه.
                </div>
            </DialogBody>

            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    class="h-9 px-4 rounded-lg text-[#6B7280] text-sm font-medium hover:bg-[#F9FAFB] hover:text-[#1A1A1A] transition-all duration-150"
                    :disabled="loading"
                    @click="handleOpenChange(false)"
                >
                    إلغاء
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    class="h-9 px-4 rounded-lg bg-[#DC2626] text-white text-sm font-medium hover:bg-[#B91C1C] active:scale-[0.98] disabled:opacity-40 disabled:cursor-not-allowed transition-all duration-150"
                    :disabled="loading"
                    @click="handleConfirm"
                >
                    <Spinner v-if="loading" class="size-4 animate-spin motion-reduce:animate-none motion-reduce:opacity-50" />
                    <Trash2 v-else class="size-4 text-white" />
                    {{ loading ? 'جارٍ الحذف...' : 'حذف' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
