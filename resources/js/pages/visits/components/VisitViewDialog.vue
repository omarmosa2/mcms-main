<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { visitStatusClass, visitStatusDotClass, visitStatusLabel, formatDateTime } from './helpers';
import type { Visit } from './types';

defineProps<{
    visit: Visit | null;
}>();

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <Dialog :open="visit !== null" @update:open="(open) => !open && emit('close')">
        <DialogContent class="sm:max-w-2xl" aria-label="تفاصيل الزيارة">
            <DialogHeader>
                <DialogTitle>{{ visit?.visit_number ?? 'تفاصيل الزيارة' }}</DialogTitle>
                <DialogDescription>لقطة سريعة للزيارة.</DialogDescription>
            </DialogHeader>

            <dl v-if="visit" class="grid gap-3 rounded-lg border border-slate-100/80 bg-slate-50/40 p-4 sm:grid-cols-2">
                <div class="space-y-1">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-slate-400 uppercase">المريض</dt>
                    <dd class="text-sm text-slate-700">{{ visit.patient?.full_name ?? '-' }}</dd>
                </div>
                <div class="space-y-1">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-slate-400 uppercase">الطبيب</dt>
                    <dd class="text-sm text-slate-700">{{ visit.doctor?.name ?? '-' }}</dd>
                </div>
                <div class="space-y-1">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-slate-400 uppercase">الموعد</dt>
                    <dd class="text-sm text-slate-700">{{ visit.appointment?.appointment_number ?? '-' }}</dd>
                </div>
                <div class="space-y-1">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-slate-400 uppercase">رقم الطابور</dt>
                    <dd class="text-sm text-slate-700">{{ visit.queue_entry?.queue_number ?? '-' }}</dd>
                </div>
                <div class="space-y-1">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-slate-400 uppercase">الحالة</dt>
                    <dd class="text-sm">
                        <span
                            class="inline-flex items-center gap-1 rounded-full border border-slate-100/80 px-2.5 py-1 text-xs font-medium capitalize"
                            :class="visitStatusClass(visit.status)"
                        >
                            <span class="size-1.5 rounded-full" :class="visitStatusDotClass(visit.status)"></span>
                            {{ visitStatusLabel(visit.status) }}
                        </span>
                    </dd>
                </div>
                <div class="space-y-1">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-slate-400 uppercase">بدأت في</dt>
                    <dd class="text-sm text-slate-700">{{ formatDateTime(visit.started_at) }}</dd>
                </div>
                <div class="space-y-1 sm:col-span-2">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-slate-400 uppercase">الشكوى الرئيسية</dt>
                    <dd class="text-sm leading-6 text-slate-500">{{ visit.chief_complaint ?? 'غير محددة' }}</dd>
                </div>
                <div class="space-y-1 sm:col-span-2">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-slate-400 uppercase">ملاحظات سريرية</dt>
                    <dd class="text-sm leading-6 text-slate-500">{{ visit.clinical_notes ?? 'غير موجودة' }}</dd>
                </div>
                <div class="space-y-1 sm:col-span-2">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-slate-400 uppercase">ملاحظات التشخيص</dt>
                    <dd class="text-sm leading-6 text-slate-500">{{ visit.diagnosis_notes ?? 'غير موجودة' }}</dd>
                </div>
                <div class="space-y-1 sm:col-span-2">
                    <dt class="text-[0.65rem] font-semibold tracking-normal text-slate-400 uppercase">خطة العلاج</dt>
                    <dd class="text-sm leading-6 text-slate-500">{{ visit.treatment_plan ?? 'غير موجودة' }}</dd>
                </div>
            </dl>

            <DialogFooter>
                <Button type="button" variant="ghost" class="h-9 rounded-md" @click="emit('close')">إغلاق</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>