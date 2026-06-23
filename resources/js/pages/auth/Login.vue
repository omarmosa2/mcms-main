<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'تسجيل الدخول',
        description: 'أدخل اسم المستخدم أو بريدك الإلكتروني وكلمة المرور للمتابعة',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="تسجيل الدخول" />

    <div
        v-if="status"
        class="mb-4 rounded-lg border border-success-300/60 bg-success-500/10 px-4 py-2 text-center text-sm font-medium text-success-700 dark:border-success-500/35 dark:text-success-100"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
        dir="rtl"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="username" class="text-[0.83rem] font-semibold">
                    اسم المستخدم أو البريد الإلكتروني
                </Label>
                <Input
                    id="username"
                    type="text"
                    name="username"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="username"
                    placeholder="اسم المستخدم أو email@example.com"
                    class="pattern-field-clay h-10"
                />
                <InputError :message="errors.username" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password" class="text-[0.83rem] font-semibold">
                        كلمة المرور
                    </Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm font-semibold text-primary hover:text-primary/80"
                        :tabindex="5"
                    >
                        نسيت كلمة المرور؟
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="كلمة المرور"
                    class="pattern-field-clay h-10"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center gap-2">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>تذكرني</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-3 h-10 w-full rounded-xl text-sm font-semibold"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                تسجيل الدخول
            </Button>
        </div>

        <div
            class="text-center text-sm text-muted-foreground"
            v-if="canRegister"
        >
            ليس لديك حساب؟
            <TextLink
                :href="register()"
                :tabindex="5"
                class="font-semibold text-primary hover:text-primary/80"
            >
                إنشاء حساب
            </TextLink>
        </div>
    </Form>
</template>
