<script setup lang="ts">
import {
    Building2,
    Calendar,
    Calculator,
    Clock,
    FileText,
    Hash,
    Phone,
    Stethoscope,
    User,
    UserCircle,
} from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
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
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import {
    appointmentStatusClass,
    appointmentStatusDotClass,
    appointmentStatusLabel,
} from './appointmentHelpers';
import type { Appointment } from './types';

const props = defineProps<{
    appointment: Appointment | null;
}>();

defineEmits<{
    close: [];
}>();

const formatDateTime = (iso: string): string => {
    return new Date(iso).toLocaleDateString('ar-SA', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatTime = (iso: string): string => {
    return new Date(iso).toLocaleTimeString('ar-SA', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    });
};

function startVisit(): void {
    if (!props.appointment) return;
    router.visit(AppointmentController.startVisit.url(props.appointment.id));
}
</script>

<template>
    <Dialog
        :open="props.appointment !== null"
        @update:open="(open: boolean) => !open && $emit('close')"
    >
        <DialogContent class="max-h-[calc(100vh-2rem)]" size="lg">
            <DialogHeader>
                <DialogTitle>تفاصيل الموعد</DialogTitle>
                <DialogDescription>
                    <span
                        v-if="props.appointment"
                        class="font-mono text-xs"
                    >
                        {{ props.appointment.appointment_number }}
                    </span>
                </DialogDescription>
            </DialogHeader>

            <DialogBody v-if="props.appointment">
                <div class="mb-4 flex items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium"
                        :class="
                            appointmentStatusClass(props.appointment.status)
                        "
                    >
                        <span
                            class="size-2 rounded-full"
                            :class="
                                appointmentStatusDotClass(
                                    props.appointment.status,
                                )
                            "
                        ></span>
                        {{
                            appointmentStatusLabel(props.appointment.status)
                        }}
                    </span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex items-start gap-3 rounded-lg border border-border/50 bg-muted/30 p-3">
                        <User class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div>
                            <p class="text-[0.65rem] font-semibold text-muted-foreground">
                                المريض
                            </p>
                            <p class="text-sm font-medium text-foreground">
                                {{ props.appointment.patient?.full_name ?? '-' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border border-border/50 bg-muted/30 p-3">
                        <Hash class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div>
                            <p class="text-[0.65rem] font-semibold text-muted-foreground">
                                رقم الملف
                            </p>
                            <p class="text-sm font-medium text-foreground">
                                {{ props.appointment.patient?.file_number ?? '-' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border border-border/50 bg-muted/30 p-3">
                        <UserCircle class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div>
                            <p class="text-[0.65rem] font-semibold text-muted-foreground">
                                العمر
                            </p>
                            <p class="text-sm font-medium text-foreground">
                                {{ props.appointment.patient?.age ?? '-' }} سنة
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border border-border/50 bg-muted/30 p-3">
                        <Phone class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div>
                            <p class="text-[0.65rem] font-semibold text-muted-foreground">
                                رقم الجوال
                            </p>
                            <p class="text-sm font-medium text-foreground">
                                {{ props.appointment.patient?.phone ?? '-' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border border-border/50 bg-muted/30 p-3">
                        <Calendar class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div>
                            <p class="text-[0.65rem] font-semibold text-muted-foreground">
                                التاريخ
                            </p>
                            <p class="text-sm font-medium text-foreground">
                                {{ formatDateTime(props.appointment.scheduled_for) }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border border-border/50 bg-muted/30 p-3">
                        <Clock class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div>
                            <p class="text-[0.65rem] font-semibold text-muted-foreground">
                                الوقت
                            </p>
                            <p class="text-sm font-medium text-foreground">
                                {{ formatTime(props.appointment.scheduled_for) }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border border-border/50 bg-muted/30 p-3">
                        <Clock class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div>
                            <p class="text-[0.65rem] font-semibold text-muted-foreground">
                                المدة
                            </p>
                            <p class="text-sm font-medium text-foreground">
                                {{ props.appointment.duration_minutes }} دقيقة
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border border-border/50 bg-muted/30 p-3">
                        <Calculator class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div>
                            <p class="text-[0.65rem] font-semibold text-muted-foreground">
                                النوع
                            </p>
                            <p class="text-sm font-medium text-foreground">
                                {{
                                    props.appointment.appointment_type === 'review'
                                        ? 'مراجعة'
                                        : 'كشفية أولى'
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border border-border/50 bg-muted/30 p-3">
                        <Building2 class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div>
                            <p class="text-[0.65rem] font-semibold text-muted-foreground">
                                العيادة
                            </p>
                            <p class="text-sm font-medium text-foreground">
                                {{ props.appointment.doctor?.clinic?.name ?? '-' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border border-border/50 bg-muted/30 p-3">
                        <Stethoscope class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div>
                            <p class="text-[0.65rem] font-semibold text-muted-foreground">
                                الطبيب
                            </p>
                            <p class="text-sm font-medium text-foreground">
                                {{ props.appointment.doctor?.name ?? '-' }}
                            </p>
                            <p
                                v-if="props.appointment.doctor?.specialty"
                                class="text-xs text-muted-foreground"
                            >
                                {{ props.appointment.doctor.specialty }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="props.appointment.cancel_reason"
                        class="flex items-start gap-3 rounded-lg border border-[var(--accent-coral-soft)]/60 bg-[var(--accent-coral-soft)]/20 p-3 sm:col-span-2"
                    >
                        <FileText class="mt-0.5 size-4 shrink-0 text-[var(--accent-coral-strong)]" />
                        <div>
                            <p class="text-[0.65rem] font-semibold text-[var(--accent-coral-strong)]/70">
                                سبب الإلغاء
                            </p>
                            <p class="text-sm font-medium text-foreground">
                                {{ props.appointment.cancel_reason }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="props.appointment.notes"
                        class="flex items-start gap-3 rounded-lg border border-border/50 bg-muted/30 p-3 sm:col-span-2"
                    >
                        <FileText class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div>
                            <p class="text-[0.65rem] font-semibold text-muted-foreground">
                                ملاحظات
                            </p>
                            <p class="text-sm leading-6 text-foreground">
                                {{ props.appointment.notes }}
                            </p>
                        </div>
                    </div>
                </div>
            </DialogBody>

            <DialogFooter>
                <Button
                    type="button"
                    variant="default"
                    class="gap-1.5"
                    @click="startVisit"
                >
                    <Stethoscope class="size-4" />
                    بدء الزيارة
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    @click="$emit('close')"
                >
                    إغلاق
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
