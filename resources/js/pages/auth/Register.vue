<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

withDefaults(
    defineProps<{
        invitationToken?: string | null;
        publicRegistrationEnabled?: boolean;
    }>(),
    {
        invitationToken: null,
        publicRegistrationEnabled: false,
    },
);

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Enter your details below to create your account',
    },
});
</script>

<template>
    <Head title="Register" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <input
            v-if="invitationToken"
            type="hidden"
            name="invitation_token"
            :value="invitationToken"
        />

        <div
            v-if="!publicRegistrationEnabled"
            class="rounded-xl border border-amber-300/55 bg-amber-100/65 px-3 py-2 text-xs leading-5 text-amber-900 dark:border-amber-500/35 dark:bg-amber-500/15 dark:text-amber-100"
        >
            Registration is invitation-only. Use the secure invitation link provided by your clinic administrator.
        </div>

        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="name" class="text-[0.83rem] font-semibold">
                    Name
                </Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Full name"
                    class="pattern-field-clay h-10"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email" class="text-[0.83rem] font-semibold">
                    Email address
                </Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                    class="pattern-field-clay h-10"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password" class="text-[0.83rem] font-semibold">
                    Password
                </Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Password"
                    class="pattern-field-clay h-10"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label
                    for="password_confirmation"
                    class="text-[0.83rem] font-semibold"
                >
                    Confirm password
                </Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                    class="pattern-field-clay h-10"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 h-10 w-full rounded-xl text-sm font-semibold"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Create account
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink
                :href="login()"
                class="font-semibold text-primary hover:text-primary/80"
                :tabindex="6"
                >Log in</TextLink
            >
        </div>
    </Form>
</template>
