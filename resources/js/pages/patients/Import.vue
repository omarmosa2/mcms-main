<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle, Download, FileSpreadsheet, Upload, XCircle, Eye, AlertTriangle } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import PatientImportExportController from '@/actions/App/Http/Controllers/Patients/PatientImportExportController';
import InternalPageHero from '@/components/InternalPageHero.vue';
import { Button } from '@/components/ui/button';
import { useToast } from '@/composables/useToast';

type ImportStatus = {
    status: 'idle' | 'processing' | 'completed' | 'failed';
    progress: number;
    imported: number;
    failed: number;
    message: string;
    errors: Array<{ row: number; errors: Record<string, string> }>;
} | null;

type PreviewRow = Record<string, unknown>;

type ValidationError = {
    row: number;
    errors: Record<string, string>;
};

type PreviewData = {
    headings: string[];
    rows: PreviewRow[];
    total_rows: number;
    validation_errors: ValidationError[];
};

const { import_status } = defineProps<{
    import_status: ImportStatus;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'المرضى', href: PatientController.index() },
            { title: 'استيراد', href: PatientImportExportController.importView() },
        ],
    },
});

const toast = useToast();

const selectedFile = ref<File | null>(null);
const isDragging = ref(false);
const isUploading = ref(false);
const isPreviewing = ref(false);
const currentStatus = ref<ImportStatus>(import_status);
const previewData = ref<PreviewData | null>(null);
const previewFilePath = ref<string | null>(null);
let pollInterval: ReturnType<typeof setInterval> | null = null;

const heroMetrics = computed(() => [
    {
        label: 'الحالة',
        value: currentStatus.value?.status === 'completed' ? 'مكتمل' : currentStatus.value?.status === 'processing' ? 'قيد التنفيذ' : 'خامل',
        hint: currentStatus.value?.message ?? 'لا توجد عملية استيراد جارية',
    },
    {
        label: 'تم استيرادها',
        value: String(currentStatus.value?.imported ?? 0),
        hint: 'صفوف تم استيرادها بنجاح',
    },
    {
        label: 'فشلت',
        value: String(currentStatus.value?.failed ?? 0),
        hint: 'صفوف فشلت في التحقق',
    },
    {
        label: 'التقدم',
        value: `${currentStatus.value?.progress ?? 0}%`,
        hint: 'نسبة اكتمال الاستيراد',
    },
]);

const validRowsCount = computed(() => {
    if (!previewData.value) {
return 0;
}

    const errorRows = new Set(previewData.value.validation_errors.map(e => e.row));

    return previewData.value.rows.filter((_, i) => !errorRows.has(i + 2)).length;
});

const invalidRowsCount = computed(() => {
    if (!previewData.value) {
return 0;
}

    return previewData.value.validation_errors.length;
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
    const allowedExtensions = ['.xlsx', '.xls', '.csv'];
    const extension = '.' + file.name.split('.').pop()?.toLowerCase();

    if (!allowedExtensions.includes(extension)) {
        toast.error('نوع الملف غير صالح. يرجى رفع ملف Excel (xlsx, xls) أو CSV.');

        return;
    }

    if (file.size > 10 * 1024 * 1024) {
        toast.error('حجم الملف يتجاوز حد 10 ميجابايت.');

        return;
    }

    selectedFile.value = file;
    previewData.value = null;
    previewFilePath.value = null;
};

const handlePreview = async (): Promise<void> => {
    if (!selectedFile.value) {
        toast.error('يرجى اختيار ملف للمعاينة.');

        return;
    }

    isPreviewing.value = true;

    const formData = new FormData();
    formData.append('file', selectedFile.value);

    try {
        const response = await fetch(PatientImportExportController.importPreview.url(), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
            credentials: 'same-origin',
        });

        const data = await response.json();

        if (data.success) {
            previewData.value = data.preview;
            previewFilePath.value = data.file_path;
            toast.success(`تم تحميل المعاينة: ${data.preview.total_rows} صف`);
        } else {
            toast.error(data.message || 'فشل في معاينة الملف');
        }
    } catch {
        toast.error('فشل في معاينة الملف. يرجى المحاولة مرة أخرى.');
    } finally {
        isPreviewing.value = false;
    }
};

const handleImport = (): void => {
    if (!selectedFile.value) {
        toast.error('يرجى اختيار ملف للاستيراد.');

        return;
    }

    isUploading.value = true;

    const formData = new FormData();
    formData.append('file', selectedFile.value);

    if (previewFilePath.value) {
        formData.append('file_path', previewFilePath.value);
    }

    router.post(PatientImportExportController.import.url(), formData, {
        forceFormData: true,
        onSuccess: () => {
            toast.success('تم جدولة عملية الاستيراد. ستبدأ المعالجة قريباً.');
            selectedFile.value = null;
            previewData.value = null;
            previewFilePath.value = null;
            startPolling();
        },
        onError: () => {
            toast.error('فشل الاستيراد. يرجى التحقق من الملف والمحاولة مرة أخرى.');
            isUploading.value = false;
        },
    });
};

const handleExport = (): void => {
    router.get(PatientImportExportController.export.url(), {}, {
        onSuccess: () => {
            toast.success('بدأ التصدير. سيتم تنزيل ملفك قريباً.');
        },
        onError: () => {
            toast.error('فشل التصدير. يرجى المحاولة مرة أخرى.');
        },
    });
};

const fetchStatus = (): void => {
    fetch(PatientImportExportController.importStatus.url(), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    })
        .then((res) => res.json())
        .then((data) => {
            currentStatus.value = data;
        })
        .catch(() => {
            // Ignore fetch errors
        });
};

const startPolling = (): void => {
    if (pollInterval !== null) {
        clearInterval(pollInterval);
    }

    pollInterval = setInterval(() => {
        fetchStatus();

        if (currentStatus.value?.status === 'completed' || currentStatus.value?.status === 'failed') {
            stopPolling();
            isUploading.value = false;

            if (currentStatus.value.status === 'completed') {
                toast.success(currentStatus.value.message);
            } else {
                toast.error(currentStatus.value.message);
            }
        }
    }, 2000);
};

const stopPolling = (): void => {
    if (pollInterval !== null) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

onMounted(() => {
    if (currentStatus.value?.status === 'processing') {
        startPolling();
    }
});

onUnmounted(() => {
    stopPolling();
});

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const statusColor = (status: string): string => {
    if (status === 'completed') {
        return 'text-success-600 dark:text-success-400';
    }

    if (status === 'processing') {
        return 'text-amber-600 dark:text-amber-400';
    }

    if (status === 'failed') {
        return 'text-destructive';
    }

    return 'text-muted-foreground';
};

const translateHeading = (heading: string): string => {
    const translations: Record<string, string> = {
        first_name: 'الاسم الأول',
        last_name: 'اسم العائلة',
        file_number: 'رقم الملف',
        date_of_birth: 'تاريخ الميلاد',
        gender: 'الجنس',
        phone: 'الهاتف',
        email: 'البريد الإلكتروني',
        national_id: 'رقم الهوية',
        emergency_contact_name: 'اسم جهة الاتصال',
        emergency_contact_phone: 'هاتف جهة الاتصال',
        notes: 'ملاحظات',
    };

    return translations[heading] || heading;
};

const hasErrorForRow = (rowIndex: number): boolean => {
    if (!previewData.value) {
return false;
}

    const actualRowNumber = rowIndex + 2;

    return previewData.value.validation_errors.some(e => e.row === actualRowNumber);
};

const getErrorsForRow = (rowIndex: number): string[] => {
    if (!previewData.value) {
return [];
}

    const actualRowNumber = rowIndex + 2;
    const error = previewData.value.validation_errors.find(e => e.row === actualRowNumber);

    return error ? Object.values(error.errors) : [];
};
</script>

<template>
    <Head title="استيراد المرضى" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6" dir="rtl">
        <InternalPageHero
            kicker="استيراد وتصدير البيانات"
            title="واجهة استيراد المرضى"
            description="استيراد سجلات المرضى من ملفات Excel/CSV أو تصدير بيانات المرضى الحالية للنسخ الاحتياطي والتقارير."
            :metrics="heroMetrics"
        />

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="glass-panel-soft p-5">
                <h3 class="pattern-typographic-title mb-4 text-[0.76rem]">
                    استيراد المرضى
                </h3>

                <div
                    :class="[
                        'rounded-xl border-2 border-dashed p-8 text-center transition-colors',
                        isDragging
                            ? 'border-primary bg-primary/5'
                            : 'border-border/60 bg-background/40',
                    ]"
                    @dragover="handleDragOver"
                    @dragleave="handleDragLeave"
                    @drop="handleDrop"
                >
                    <Upload class="mx-auto mb-3 size-10 text-muted-foreground" />
                    <p class="text-sm font-medium">
                        اسحب وأفلت ملفك هنا
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        أو انقر لتصفح الملفات (xlsx, xls, csv - حد أقصى 10 ميجابايت)
                    </p>
                    <input
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        class="absolute inset-0 cursor-pointer opacity-0"
                        @change="handleFileSelect"
                    />
                </div>

                <div v-if="selectedFile" class="mt-4 flex items-center justify-between gap-2 rounded-lg border border-border/60 bg-background/40 p-3">
                    <div class="flex items-center gap-2">
                        <FileSpreadsheet class="size-4 text-muted-foreground" />
                        <div>
                            <p class="text-sm font-medium">{{ selectedFile.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ formatFileSize(selectedFile.size) }}</p>
                        </div>
                    </div>
                    <Button type="button" variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="selectedFile = null; previewData = null; previewFilePath = null">
                        مسح
                    </Button>
                </div>

                <div class="mt-4 flex gap-2">
                    <Button
                        variant="neumorphic"
                        class="flex-1"
                        :disabled="!selectedFile || isPreviewing"
                        @click="handlePreview"
                    >
                        <Eye class="ms-2 size-4" />
                        {{ isPreviewing ? 'جاري المعاينة...' : 'معاينة البيانات' }}
                    </Button>
                    <Button
                        variant="clay"
                        class="flex-1"
                        :disabled="!selectedFile || isUploading"
                        @click="handleImport"
                    >
                        <Upload class="ms-2 size-4" />
                        {{ isUploading ? 'جاري الرفع...' : 'بدء الاستيراد' }}
                    </Button>
                </div>

                <div v-if="previewData" class="mt-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold">معاينة البيانات</h4>
                        <div class="flex gap-3 text-xs">
                            <span class="text-success-600 dark:text-success-400">
                                ✓ {{ validRowsCount }} صف صالح
                            </span>
                            <span v-if="invalidRowsCount > 0" class="text-destructive">
                                ✗ {{ invalidRowsCount }} صف به أخطاء
                            </span>
                        </div>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        عرض أول 50 صف من أصل {{ previewData.total_rows }} صف
                    </p>

                    <div class="max-h-80 overflow-auto rounded-lg border border-border/60">
                        <table class="w-full text-xs">
                            <thead class="sticky top-0 bg-muted">
                                <tr>
                                    <th class="px-2 py-1.5 text-right">#</th>
                                    <th v-for="heading in previewData.headings" :key="heading" class="px-2 py-1.5 text-right">
                                        {{ translateHeading(heading) }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, index) in previewData.rows"
                                    :key="index"
                                    :class="[
                                        'border-t border-border/40',
                                        hasErrorForRow(index) ? 'bg-destructive/5' : '',
                                    ]"
                                >
                                    <td class="px-2 py-1.5 font-mono text-muted-foreground">{{ index + 2 }}</td>
                                    <td
                                        v-for="heading in previewData.headings"
                                        :key="heading"
                                        class="px-2 py-1.5"
                                    >
                                        {{ row[heading] ?? '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="previewData.validation_errors.length > 0" class="rounded-lg border border-destructive/30 bg-destructive/5 p-3">
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="size-4 text-destructive" />
                            <h5 class="text-sm font-semibold text-destructive">أخطاء التحقق</h5>
                        </div>
                        <div class="mt-2 max-h-40 overflow-y-auto space-y-1">
                            <div
                                v-for="error in previewData.validation_errors.slice(0, 10)"
                                :key="error.row"
                                class="text-xs text-destructive"
                            >
                                <strong>صف {{ error.row }}:</strong> {{ Object.values(error.errors).join('، ') }}
                            </div>
                            <p v-if="previewData.validation_errors.length > 10" class="text-xs text-muted-foreground">
                                ... و {{ previewData.validation_errors.length - 10 }} أخطاء أخرى
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-border/60 bg-background/40 p-3">
                    <p class="text-xs font-semibold uppercase tracking-normal text-muted-foreground">
                        الأعمدة المطلوبة
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        <code class="rounded bg-muted px-1 py-0.5">first_name</code>,
                        <code class="rounded bg-muted px-1 py-0.5">last_name</code>
                    </p>
                    <p class="mt-2 text-xs font-semibold uppercase tracking-normal text-muted-foreground">
                        أعمدة اختيارية
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        <code class="rounded bg-muted px-1 py-0.5">file_number</code>,
                        <code class="rounded bg-muted px-1 py-0.5">date_of_birth</code>,
                        <code class="rounded bg-muted px-1 py-0.5">gender</code>,
                        <code class="rounded bg-muted px-1 py-0.5">phone</code>,
                        <code class="rounded bg-muted px-1 py-0.5">email</code>,
                        <code class="rounded bg-muted px-1 py-0.5">national_id</code>,
                        <code class="rounded bg-muted px-1 py-0.5">emergency_contact_name</code>,
                        <code class="rounded bg-muted px-1 py-0.5">emergency_contact_phone</code>,
                        <code class="rounded bg-muted px-1 py-0.5">notes</code>
                    </p>
                </div>
            </section>

            <section class="glass-panel-soft p-5">
                <h3 class="pattern-typographic-title mb-4 text-[0.76rem]">
                    تصدير المرضى
                </h3>

                <div class="rounded-xl border border-border/60 bg-background/40 p-4">
                    <div class="flex items-start gap-3">
                        <FileSpreadsheet class="mt-0.5 size-5 text-muted-foreground" />
                        <div>
                            <h4 class="text-sm font-medium">تصدير Excel (.xlsx)</h4>
                            <p class="mt-1 text-xs text-muted-foreground">
                                تصدير جميع المرضى إلى ملف Excel مع بيانات ديموغرافية كاملة بما في ذلك أرقام الهوية المشفرة.
                            </p>
                        </div>
                    </div>
                </div>

                <Button variant="clay" class="mt-4 w-full" @click="handleExport">
                    <Download class="ms-2 size-4" />
                    تنزيل Excel
                </Button>

                <div v-if="currentStatus" class="mt-6 space-y-3">
                    <h4 class="text-sm font-semibold">حالة الاستيراد</h4>

                    <div :class="['rounded-lg border p-3', currentStatus.status === 'completed' ? 'border-success-300/60 bg-success-50/50 dark:border-success-500/35 dark:bg-success-500/10' : currentStatus.status === 'processing' ? 'border-warning-300/60 bg-warning-50/50 dark:border-warning-500/35 dark:bg-warning-500/10' : currentStatus.status === 'failed' ? 'border-destructive/30 bg-destructive/5' : 'border-border/60 bg-background/40']">
                        <div class="flex items-center gap-2">
                            <CheckCircle v-if="currentStatus.status === 'completed'" class="size-4 text-success-600 dark:text-success-400" />
                            <XCircle v-else-if="currentStatus.status === 'failed'" class="size-4 text-destructive" />
                            <Upload v-else-if="currentStatus.status === 'processing'" class="size-4 animate-spin motion-reduce:animate-none motion-reduce:opacity-50 text-amber-600 dark:text-amber-400" />

                            <span :class="['text-sm font-medium', statusColor(currentStatus.status)]">
                                {{ currentStatus.message }}
                            </span>
                        </div>

                        <div v-if="currentStatus.status === 'completed' || currentStatus.status === 'failed'" class="mt-2 grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-muted-foreground">تم استيرادها:</span>
                                <span class="me-1 font-semibold text-success-600 dark:text-success-400">{{ currentStatus.imported }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">فشلت:</span>
                                <span class="me-1 font-semibold text-destructive">{{ currentStatus.failed }}</span>
                            </div>
                        </div>

                        <div v-if="currentStatus.errors && currentStatus.errors.length > 0" class="mt-3 max-h-48 overflow-y-auto rounded border border-border/60 bg-background/60 p-2">
                            <p class="text-xs font-semibold text-muted-foreground">الأخطاء:</p>
                            <div v-for="(error, idx) in currentStatus.errors" :key="idx" class="mt-1 text-xs text-destructive">
                                صف {{ error.row }}: {{ Object.values(error.errors).join('، ') }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <Button variant="neumorphic" size="sm" @click="router.get(PatientController.index())">
            <ArrowLeft class="ms-2 size-4" />
            العودة إلى المرضى
        </Button>
    </div>
</template>
