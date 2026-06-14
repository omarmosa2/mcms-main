<script setup lang="ts">
import { Building2, UsersRound } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Department } from './types';

defineProps<{
    open: boolean;
    department: Department | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const handleClose = (): void => {
    emit('update:open', false);
};

const formatDate = (value: string | null): string => {
    if (value === null) {
        return '-';
    }

    return new Intl.DateTimeFormat('ar-SY').format(new Date(value));
};
</script>

<template>
    <Dialog :open="open" @update:open="(val: boolean) => !val && handleClose()">
        <DialogContent size="lg" class="bg-card p-0" dir="rtl">
            <DialogHeader class="border-b border-border px-6 py-5 text-right">
                <div class="flex items-start justify-between gap-4 pl-10">
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                        >
                            <Building2 class="size-6" />
                        </span>
                        <div class="min-w-0">
                            <DialogTitle
                                class="truncate text-2xl font-black text-foreground"
                            >
                                {{ department?.name ?? 'تفاصيل العيادة' }}
                            </DialogTitle>
                            <DialogDescription
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                ملف العيادة الكامل وسياقها التشغيلي.
                            </DialogDescription>
                        </div>
                    </div>

                    <span
                        v-if="department"
                        class="mt-1 inline-flex shrink-0 items-center gap-2 rounded-full border px-3 py-1 text-xs font-bold"
                        :class="
                            department.is_active
                                ? 'border-success/20 bg-success/10 text-success'
                                : 'border-destructive/20 bg-destructive/10 text-destructive'
                        "
                    >
                        <span class="size-2 rounded-full bg-current"></span>
                        {{ department.is_active ? 'نشطة' : 'غير نشطة' }}
                    </span>
                </div>
            </DialogHeader>

            <div v-if="department" class="space-y-5 p-6">
                <section
                    class="rounded-xl border border-border bg-muted/40 p-4"
                >
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h3 class="text-sm font-black text-foreground">
                            البيانات الأساسية
                        </h3>
                        <span
                            class="inline-flex items-center gap-2 text-xs font-semibold text-muted-foreground"
                        >
                            <UsersRound class="size-4" />
                            {{ department.doctor_profiles_count }} طبيب
                        </span>
                    </div>

                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div
                            class="rounded-lg border border-border/70 bg-card px-4 py-3"
                        >
                            <dt
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                الرمز
                            </dt>
                            <dd class="mt-1 font-bold text-foreground">
                                {{ department.code ?? '-' }}
                            </dd>
                        </div>

                        <div
                            class="rounded-lg border border-border/70 bg-card px-4 py-3"
                        >
                            <dt
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                الأطباء المرتبطون
                            </dt>
                            <dd
                                class="mt-1 font-bold text-foreground tabular-nums"
                            >
                                {{ department.doctor_profiles_count }}
                            </dd>
                        </div>

                        <div
                            class="rounded-lg border border-border/70 bg-card px-4 py-3"
                        >
                            <dt
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                آخر تحديث بواسطة
                            </dt>
                            <dd class="mt-1 font-bold text-foreground">
                                {{ department.updater?.name ?? '-' }}
                            </dd>
                        </div>

                        <div
                            class="rounded-lg border border-border/70 bg-card px-4 py-3"
                        >
                            <dt
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                تاريخ آخر تحديث
                            </dt>
                            <dd class="mt-1 font-bold text-foreground">
                                {{ formatDate(department.updated_at) }}
                            </dd>
                        </div>

                        <div
                            class="rounded-lg border border-border/70 bg-card px-4 py-3 sm:col-span-2"
                        >
                            <dt
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                الوصف
                            </dt>
                            <dd
                                class="mt-2 leading-7 text-foreground"
                                :class="
                                    department.description === null
                                        ? 'text-muted-foreground'
                                        : ''
                                "
                            >
                                {{ department.description ?? 'لا يوجد وصف' }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>

            <DialogFooter class="border-t border-border px-6 py-4">
                <Button type="button" variant="outline" @click="handleClose">
                    إغلاق
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
