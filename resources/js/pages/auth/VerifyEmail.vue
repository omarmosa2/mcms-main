<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Verify email',
        description:
            'Please verify your email address by clicking on the link we just emailed to you.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Email verification" />

    <div
        v-if="status === 'verification-link-sent'"
        class="pattern-alert-glass mb-4 border-success-300/60 px-4 py-2 text-center text-sm font-medium text-success-700 dark:border-success-500/35 dark:text-success-100"
    >
        A new verification link has been sent to the email address you provided
        during registration.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button
            :disabled="processing"
            variant="clay"
            class="h-10 rounded-xl px-5"
        >
            <Spinner v-if="processing" />
            Resend verification email
        </Button>

        <TextLink
            :href="logout()"
            as="button"
            class="mx-auto block text-sm font-semibold text-primary hover:text-primary/80"
        >
            Log out
        </TextLink>
    </Form>
</template>
