<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PatientImportExportController from '@/actions/App/Http/Controllers/Patients/PatientImportExportController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useToast } from '@/composables/useToast';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const toast = useToast();

const activeTab = ref<'export' | 'import'>('export');
const selectedFile = ref<File | null>(null);
const isDragging = ref(false);
const isUploading = ref(false);

const isOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

const handleDragOver = (event: DragEvent): void => {
    event.preventDefault();
    isDragging.value = true;
};

const handleDragLeave = (): void => {
    isDragging.value = false;
};

const handleDrop = (event: DragEvent): void => {
    event.preventDefault();
    isDragging.value = false;

    const files = event.dataTransfer?.files;

    if (files && files.length > 0) {
        validateAndSetFile(files[0]);
    }
};

const handleFileSelect = (event: Event): void => {
    const target = event.target as HTMLInputElement;

    if (target.files && target.files.length > 0) {
        validateAndSetFile(target.files[0]);
    }
};

const validateAndSetFile = (file: File): void => {
    const allowedTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
    ];
    const allowedExtensions = ['.xlsx', '.xls', '.csv'];
    const extension = '.' + file.name.split('.').pop()?.toLowerCase();

    if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(extension)) {
        toast.error('Invalid file type. Please upload an Excel (xlsx, xls) or CSV file.');

        return;
    }

    if (file.size > 10 * 1024 * 1024) {
        toast.error('File size exceeds 10MB limit.');

        return;
    }

    selectedFile.value = file;
};

const handleExport = (): void => {
    router.get(PatientImportExportController.export.url(), {}, {
        onSuccess: () => {
            toast.success('Export started. Your file will download shortly.');
            isOpen.value = false;
        },
        onError: () => {
            toast.error('Export failed. Please try again.');
        },
    });
};

const handleImport = async (): Promise<void> => {
    if (!selectedFile.value) {
        toast.error('Please select a file to import.');

        return;
    }

    isUploading.value = true;

    const formData = new FormData();
    formData.append('file', selectedFile.value);

    router.post(PatientImportExportController.import.url(), formData, {
        forceFormData: true,
        onSuccess: () => {
            toast.success('Import job queued. Processing will begin shortly.');
            selectedFile.value = null;
            isUploading.value = false;
            isOpen.value = false;
        },
        onError: () => {
            toast.error('Import failed. Please check your file and try again.');
            isUploading.value = false;
        },
    });
};

const clearFile = (): void => {
    selectedFile.value = null;
};

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
</script>

<template>
    <Dialog :open="isOpen" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Import / Export Patients</DialogTitle>
                <DialogDescription>
                    Import patients from Excel/CSV or export your patient data.
                </DialogDescription>
            </DialogHeader>

            <div class="flex gap-2 border-b border-border/60">
                <button
                    type="button"
                    :class="[
                        'flex-1 px-4 py-2 text-sm font-medium transition-colors',
                        activeTab === 'export'
                            ? 'border-b-2 border-primary text-primary'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="activeTab = 'export'"
                >
                    <Download class="mr-2 inline-block size-4" />
                    Export
                </button>
                <button
                    type="button"
                    :class="[
                        'flex-1 px-4 py-2 text-sm font-medium transition-colors',
                        activeTab === 'import'
                            ? 'border-b-2 border-primary text-primary'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="activeTab = 'import'"
                >
                    <Upload class="mr-2 inline-block size-4" />
                    Import
                </button>
            </div>

            <div v-if="activeTab === 'export'" class="space-y-4 py-2">
                <div class="rounded-xl border border-border/60 bg-background/40 p-4">
                    <div class="flex items-start gap-3">
                        <FileSpreadsheet class="mt-0.5 size-5 text-muted-foreground" />
                        <div>
                            <h4 class="text-sm font-medium">Excel Export</h4>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Export all patients to an Excel file (.xlsx) with complete demographic data.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-border/60 bg-background/40 p-3">
                    <p class="text-xs text-muted-foreground">
                        <strong>Columns included:</strong> File Number, First Name, Last Name, Date of Birth, Gender, Phone, Email, National ID, Emergency Contact, Notes, Created At
                    </p>
                </div>
            </div>

            <div v-if="activeTab === 'import'" class="space-y-4 py-2">
                <div
                    :class="[
                        'rounded-xl border-2 border-dashed p-6 text-center transition-colors',
                        isDragging
                            ? 'border-primary bg-primary/5'
                            : 'border-border/60 bg-background/40',
                    ]"
                    @dragover="handleDragOver"
                    @dragleave="handleDragLeave"
                    @drop="handleDrop"
                >
                    <Upload class="mx-auto mb-3 size-8 text-muted-foreground" />
                    <p class="text-sm font-medium">
                        Drag & drop your file here
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        or click to browse
                    </p>
                    <input
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        class="absolute inset-0 cursor-pointer opacity-0"
                        @change="handleFileSelect"
                    />
                </div>

                <div v-if="selectedFile" class="flex items-center justify-between gap-2 rounded-lg border border-border/60 bg-background/40 p-3">
                    <div class="flex items-center gap-2">
                        <FileSpreadsheet class="size-4 text-muted-foreground" />
                        <div>
                            <p class="text-sm font-medium">{{ selectedFile.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ formatFileSize(selectedFile.size) }}</p>
                        </div>
                    </div>
                    <Button type="button" variant="ghost" size="icon" class="h-7 w-7" @click="clearFile">
                        <X class="size-4" />
                    </Button>
                </div>

                <div class="rounded-xl border border-border/60 bg-background/40 p-3">
                    <p class="text-xs text-muted-foreground">
                        <strong>Required columns:</strong> first_name, last_name
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        <strong>Optional:</strong> file_number, date_of_birth, gender, phone, email, national_id, emergency_contact_name, emergency_contact_phone, notes
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="isOpen = false">Cancel</Button>
                <Button
                    v-if="activeTab === 'export'"
                    variant="clay"
                    :disabled="isUploading"
                    @click="handleExport"
                >
                    <Download class="mr-2 size-4" />
                    Download Excel
                </Button>
                <Button
                    v-if="activeTab === 'import'"
                    variant="clay"
                    :disabled="!selectedFile || isUploading"
                    @click="handleImport"
                >
                    <Upload class="mr-2 size-4" />
                    {{ isUploading ? 'Uploading...' : 'Start Import' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
