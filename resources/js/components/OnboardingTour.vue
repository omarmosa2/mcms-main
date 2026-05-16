<script setup lang="ts">
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { Lightbulb, SkipForward, ChevronRight } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const currentStep = ref(0);

const isOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

const tourSteps = [
    {
        element: '#app-sidebar',
        popover: {
            title: 'Welcome to MCMS!',
            description: 'Let\'s take a quick tour of the system. Use the sidebar to navigate between modules.',
        },
    },
    {
        element: '[data-tour="dashboard"]',
        popover: {
            title: 'Dashboard',
            description: 'Your central hub for clinic analytics, patient statistics, and quick actions.',
        },
    },
    {
        element: '[data-tour="patients"]',
        popover: {
            title: 'Patient Management',
            description: 'Create, view, and manage patient records. Import/export data in bulk.',
        },
    },
    {
        element: '[data-tour="queue"]',
        popover: {
            title: 'Queue Management',
            description: 'Manage real-time patient flow. Enqueue patients, call next, and track status.',
        },
    },
    {
        element: '[data-tour="appointments"]',
        popover: {
            title: 'Appointments',
            description: 'Schedule and manage patient appointments with doctors.',
        },
    },
    {
        element: '[data-tour="billing"]',
        popover: {
            title: 'Billing & Invoices',
            description: 'Create invoices, process payments, and manage financial records.',
        },
    },
    {
        element: '[data-tour="notifications"]',
        popover: {
            title: 'Notifications',
            description: 'Click the bell icon to view your notifications and alerts.',
        },
    },
    {
        element: '[data-tour="settings"]',
        popover: {
            title: 'Settings',
            description: 'Configure your profile, security, notifications, and system preferences.',
        },
    },
];

const startTour = (): void => {
    const driverObj = driver({
        showProgress: true,
        steps: tourSteps,
        onNextClick: () => {
            driverObj.moveNext();
        },
        onPrevClick: () => {
            driverObj.movePrevious();
        },
        onCloseClick: () => {
            driverObj.destroy();
            isOpen.value = false;
        },
        onDestroyStarted: () => {
            isOpen.value = false;
            driverObj.destroy();
        },
    });

    driverObj.drive();
};

onMounted(() => {
    if (isOpen.value) {
        startTour();
    }
});

watch(() => isOpen.value, (newValue) => {
    if (newValue) {
        setTimeout(() => startTour(), 300);
    }
});
</script>

<template>
    <Dialog :open="isOpen" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Lightbulb class="size-5 text-amber-500" />
                    Welcome to MCMS!
                </DialogTitle>
                <DialogDescription>
                    Take a guided tour to learn about the system's key features.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-2">
                <div class="rounded-xl border border-border/60 bg-background/40 p-3">
                    <p class="text-sm">
                        This tour will walk you through the main features of the Medical Clinic Management System.
                    </p>
                </div>

                <div class="space-y-2">
                    <div v-for="(step, index) in tourSteps" :key="index" class="flex items-center gap-3 rounded-lg p-2 text-sm">
                        <span
                            :class="[
                                'flex size-6 items-center justify-center rounded-full text-xs font-medium',
                                index <= currentStep
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground',
                            ]"
                        >
                            {{ index + 1 }}
                        </span>
                        <span>{{ step.popover.title }}</span>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex items-center justify-between">
                <Button variant="ghost" size="sm" @click="isOpen = false">
                    <SkipForward class="mr-2 size-4" />
                    Skip Tour
                </Button>
                <Button variant="clay" size="sm" @click="startTour">
                    Start Tour
                    <ChevronRight class="ml-2 size-4" />
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
