<script setup lang="ts">
import { Dialog, DialogBody, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { appointmentStatusClass, appointmentStatusDotClass, appointmentStatusLabel } from './appointmentHelpers';
import type { Appointment } from './types';

const props = defineProps<{
    appointment: Appointment | null;
}>();

defineEmits<{
    close: [];
}>();
</script>

<template>
    <Dialog :open="props.appointment !== null" @update:open="(open: boolean) => !open && $emit('close')">
        <DialogContent size="md">
            <DialogHeader>
                <DialogTitle>عرض تفاصيل الموعد</DialogTitle>
                <DialogDescription>تفاصيل الموعد.</DialogDescription>
            </DialogHeader>

            <DialogBody>
                <dl v-if="props.appointment" class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">المريض</dt>
                        <dd class="text-sm">{{ props.appointment.patient?.full_name ?? '-' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الطبيب</dt>
                        <dd class="text-sm">{{ props.appointment.doctor?.name ?? '-' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">التاريخ</dt>
                        <dd class="text-sm">{{ new Date(props.appointment.scheduled_for).toLocaleString('ar-SA') }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">المدة</dt>
                        <dd class="text-sm">{{ props.appointment.duration_minutes }} دقيقة</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الحالة</dt>
                        <dd class="text-sm">
                            <span
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium capitalize"
                                :class="appointmentStatusClass(props.appointment.status)"
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :class="appointmentStatusDotClass(props.appointment.status)"
                                ></span>
                                {{ appointmentStatusLabel(props.appointment.status) }}
                            </span>
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">سبب الإلغاء</dt>
                        <dd class="text-sm">{{ props.appointment.cancel_reason ?? 'غير محدد' }}</dd>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">ملاحظات</dt>
                        <dd class="text-sm leading-6 text-muted-foreground">{{ props.appointment.notes ?? 'لا توجد ملاحظات' }}</dd>
                    </div>
                </dl>
            </DialogBody>

            <DialogFooter>
                <Button type="button" variant="ghost" class="text-[#6B7280] hover:bg-[#F9FAFB] hover:text-[#374151]" @click="$emit('close')">إغلاق</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>