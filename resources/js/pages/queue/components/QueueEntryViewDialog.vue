<script setup lang="ts">
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
import type { QueueEntry } from './types';

const props = defineProps<{
    queueEntry: QueueEntry | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const queueStatusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        waiting: 'في الانتظار',
        in_service: 'قيد الخدمة',
        completed: 'مكتمل',
        skipped: 'تم التخطي',
        canceled: 'ملغي',
    };

    return labels[status] ?? status;
};
</script>

<template>
    <Dialog
        :open="props.queueEntry !== null"
        @update:open="(open) => !open && emit('close')"
    >
        <DialogContent size="md">
            <DialogHeader>
                <DialogTitle>عرض سجل الطابور</DialogTitle>
                <DialogDescription>
                    تفاصيل سجل الطابور الكاملة.
                </DialogDescription>
            </DialogHeader>

            <DialogBody>
                <dl
                    v-if="props.queueEntry"
                    class="grid gap-3 sm:grid-cols-2"
                >
                <div class="space-y-1">
                    <dt
                        class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                    >
                        المريض
                    </dt>
                    <dd class="text-sm">
                        {{ props.queueEntry.patient?.full_name ?? '-' }}
                    </dd>
                </div>
                <div class="space-y-1">
                    <dt
                        class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                    >
                        الطبيب
                    </dt>
                    <dd class="text-sm">
                        {{ props.queueEntry.assigned_doctor?.name ?? '-' }}
                    </dd>
                </div>
                <div class="space-y-1">
                    <dt
                        class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                    >
                        تاريخ الطابور
                    </dt>
                    <dd class="text-sm">
                        {{ props.queueEntry.queue_date }}
                    </dd>
                </div>
                <div class="space-y-1">
                    <dt
                        class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                    >
                        الأولوية
                    </dt>
                    <dd class="text-sm">
                        {{ props.queueEntry.priority }}
                    </dd>
                </div>
                <div class="space-y-1">
                    <dt
                        class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                    >
                        الحالة
                    </dt>
                    <dd class="text-sm capitalize">
                        {{ queueStatusLabel(props.queueEntry.status) }}
                    </dd>
                </div>
                <div class="space-y-1">
                    <dt
                        class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                    >
                        الموعد
                    </dt>
                    <dd class="text-sm">
                        {{
                            props.queueEntry.appointment
                                ?.appointment_number ?? '-'
                        }}
                    </dd>
                </div>
                <div class="space-y-1 sm:col-span-2">
                    <dt
                        class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                    >
                        ملاحظات
                    </dt>
                    <dd class="text-sm leading-6 text-muted-foreground">
                        {{ props.queueEntry.notes ?? 'لا توجد ملاحظات' }}
                    </dd>
                </div>
                </dl>
            </DialogBody>

            <DialogFooter>
                <Button
                    type="button"
                    variant="ghost"
                    class="text-[#6B7280] hover:bg-[#F9FAFB] hover:text-[#374151]"
                    @click="emit('close')"
                >
                    إغلاق
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>