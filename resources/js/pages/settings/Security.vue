<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import SecurityPolicyController from '@/actions/App/Http/Controllers/Settings/SecurityPolicyController';
import UserInvitationController from '@/actions/App/Http/Controllers/Settings/UserInvitationController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { edit } from '@/routes/security';
import { disable, enable } from '@/routes/two-factor';

type SecurityPolicy = {
    password_min_length: number;
    require_mixed_case: boolean;
    require_numbers: boolean;
    require_symbols: boolean;
    session_lifetime_minutes: number;
    idle_timeout_minutes: number;
    force_two_factor: boolean;
    confirm_password_for_security_actions: boolean;
    audit_retention_days: number;
    sensitive_access_retention_days: number;
};

type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
    canManageSecurityPolicies?: boolean;
    policy?: SecurityPolicy | null;
    invitation_roles?: string[];
    pending_invitations?: Array<{
        id: number;
        email: string;
        full_name: string | null;
        role_name: string;
        expires_at: string | null;
        invitation_url: string;
    }>;
    latest_invitation_url?: string | null;
};

withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
    canManageSecurityPolicies: false,
    policy: null,
    invitation_roles: () => [],
    pending_invitations: () => [],
    latest_invitation_url: null,
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'إعدادات الأمان',
                href: edit(),
            },
        ],
    },
});

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <Head title="إعدادات الأمان" />

    <h1 class="sr-only">إعدادات الأمان</h1>

    <div class="glass-panel-soft space-y-6 p-5">
        <Heading
            tone="typographic"
            variant="small"
            title="تحديث كلمة المرور"
            description="تأكد من استخدام كلمة مرور طويلة وعشوائية لحماية حسابك"
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="current_password">كلمة المرور الحالية</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="pattern-field-clay mt-1 block w-full"
                    autocomplete="current-password"
                    placeholder="كلمة المرور الحالية"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">كلمة المرور الجديدة</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="pattern-field-clay mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="كلمة المرور الجديدة"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">تأكيد كلمة المرور</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="pattern-field-clay mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="تأكيد كلمة المرور الجديدة"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    variant="clay"
                    class="min-h-[44px]"
                    data-test="update-password-button"
                >
                    حفظ كلمة المرور
                </Button>
            </div>
        </Form>
    </div>

    <div v-if="canManageTwoFactor" class="glass-panel-soft space-y-6 p-5">
        <Heading
            tone="typographic"
            variant="small"
            title="المصادقة الثنائية"
            description="إدارة إعدادات المصادقة الثنائية لحسابك"
        />

        <div
            v-if="!twoFactorEnabled"
            class="flex flex-col items-start justify-start space-y-4"
        >
            <p class="text-sm text-muted-foreground">
                عند تفعيل المصادقة الثنائية، سيُطلب منك إدخال رمز آمن أثناء تسجيل الدخول. يمكن الحصول على هذا الرمز من تطبيق يدعم TOTP على هاتفك.
            </p>

            <div>
                <Button
                    v-if="hasSetupData"
                    variant="clay"
                    class="min-h-[44px]"
                    @click="showSetupModal = true"
                >
                    <ShieldCheck />متابعة الإعداد
                </Button>
                <Form
                    v-else
                    v-bind="enable.form()"
                    @success="showSetupModal = true"
                    #default="{ processing }"
                >
                    <Button type="submit" variant="clay" :disabled="processing" class="min-h-[44px]">
                        تفعيل المصادقة الثنائية
                    </Button>
                </Form>
            </div>
        </div>

        <div v-else class="flex flex-col items-start justify-start space-y-4">
            <p class="text-sm text-muted-foreground">
                سيُطلب منك إدخال رمز آمن وعشوائي أثناء تسجيل الدخول، يمكنك الحصول عليه من التطبيق المثبت على هاتفك.
            </p>

            <div class="relative inline">
                <Form v-bind="disable.form()" #default="{ processing }">
                    <Button
                        variant="destructive"
                        type="submit"
                        :disabled="processing"
                        class="min-h-[44px]"
                    >
                        تعطيل المصادقة الثنائية
                    </Button>
                </Form>
            </div>

            <TwoFactorRecoveryCodes />
        </div>

        <TwoFactorSetupModal
            v-model:isOpen="showSetupModal"
            :requiresConfirmation="requiresConfirmation"
            :twoFactorEnabled="twoFactorEnabled"
        />
    </div>

    <div v-if="canManageSecurityPolicies" class="glass-panel-soft space-y-6 p-5">
        <Heading
            tone="typographic"
            variant="small"
            title="سياسة أمان العيادة"
            description="إعدادات كلمات المرور، مدة الجلسة، وسياسات الاحتفاظ بالسجلات"
        />

        <Form
            v-bind="SecurityPolicyController.update.form()"
            class="space-y-4"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-3 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="password_min_length">الحد الأدنى لطول كلمة المرور</Label>
                    <Input
                        id="password_min_length"
                        name="password_min_length"
                        type="number"
                        min="8"
                        max="32"
                        class="pattern-field-clay"
                        :default-value="policy?.password_min_length ?? 12"
                    />
                    <InputError :message="errors.password_min_length" />
                </div>
                <div class="grid gap-2">
                    <Label for="session_lifetime_minutes">مدة الجلسة (بالدقائق)</Label>
                    <Input
                        id="session_lifetime_minutes"
                        name="session_lifetime_minutes"
                        type="number"
                        min="15"
                        max="1440"
                        class="pattern-field-clay"
                        :default-value="policy?.session_lifetime_minutes ?? 120"
                    />
                    <InputError :message="errors.session_lifetime_minutes" />
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="idle_timeout_minutes">مهلة الخمول (بالدقائق)</Label>
                    <Input
                        id="idle_timeout_minutes"
                        name="idle_timeout_minutes"
                        type="number"
                        min="5"
                        max="480"
                        class="pattern-field-clay"
                        :default-value="policy?.idle_timeout_minutes ?? 30"
                    />
                    <InputError :message="errors.idle_timeout_minutes" />
                </div>
                <div class="grid gap-2">
                    <Label for="audit_retention_days">مدة الاحتفاظ بالسجلات (بالأيام)</Label>
                    <Input
                        id="audit_retention_days"
                        name="audit_retention_days"
                        type="number"
                        min="30"
                        class="pattern-field-clay"
                        :default-value="policy?.audit_retention_days ?? 365"
                    />
                    <InputError :message="errors.audit_retention_days" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="sensitive_access_retention_days">مدة الاحتفاظ بسجلات الوصول الحساس (بالأيام)</Label>
                <Input
                    id="sensitive_access_retention_days"
                    name="sensitive_access_retention_days"
                    type="number"
                    min="30"
                    class="pattern-field-clay"
                    :default-value="policy?.sensitive_access_retention_days ?? 365"
                />
                <InputError :message="errors.sensitive_access_retention_days" />
            </div>

            <div class="grid gap-2 md:grid-cols-2">
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="require_mixed_case" value="0" />
                    <input
                        type="checkbox"
                        name="require_mixed_case"
                        value="1"
                        :checked="policy?.require_mixed_case ?? true"
                    />
                    اشتراط أحرف كبيرة وصغيرة
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="require_numbers" value="0" />
                    <input
                        type="checkbox"
                        name="require_numbers"
                        value="1"
                        :checked="policy?.require_numbers ?? true"
                    />
                    اشتراط أرقام
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="require_symbols" value="0" />
                    <input
                        type="checkbox"
                        name="require_symbols"
                        value="1"
                        :checked="policy?.require_symbols ?? true"
                    />
                    اشتراط رموز
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="force_two_factor" value="0" />
                    <input
                        type="checkbox"
                        name="force_two_factor"
                        value="1"
                        :checked="policy?.force_two_factor ?? false"
                    />
                    إلزام المصادقة الثنائية
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="hidden"
                        name="confirm_password_for_security_actions"
                        value="0"
                    />
                    <input
                        type="checkbox"
                        name="confirm_password_for_security_actions"
                        value="1"
                        :checked="policy?.confirm_password_for_security_actions ?? true"
                    />
                    تأكيد كلمة المرور لإجراءات الأمان
                </label>
            </div>

            <Button :disabled="processing" variant="clay" class="min-h-[44px]">
                حفظ السياسة
            </Button>
        </Form>

        <Heading
            tone="typographic"
            variant="small"
            title="دعوات المسؤولين"
            description="إنشاء روابط دعوة لموظفي العيادة"
        />

        <Form
            v-bind="UserInvitationController.store.form()"
            class="grid gap-3 rounded-xl border border-border/70 bg-background/65 p-4 md:grid-cols-3"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="invite_email">البريد الإلكتروني</Label>
                <Input
                    id="invite_email"
                    name="email"
                    type="email"
                    required
                    class="pattern-field-clay"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="invite_full_name">الاسم الكامل</Label>
                <Input
                    id="invite_full_name"
                    name="full_name"
                    type="text"
                    class="pattern-field-clay"
                />
                <InputError :message="errors.full_name" />
            </div>

            <div class="grid gap-2">
                <Label for="invite_role_name">الدور</Label>
                <select
                    id="invite_role_name"
                    name="role_name"
                    required
                    class="pattern-field-clay h-10 px-3 py-1.5"
                >
                    <option value="">اختر الدور</option>
                    <option
                        v-for="role in invitation_roles"
                        :key="`invite-role-${role}`"
                        :value="role"
                    >
                        {{ role }}
                    </option>
                </select>
                <InputError :message="errors.role_name" />
            </div>

            <div class="md:col-span-3">
                <Button :disabled="processing" variant="clay" class="min-h-[44px]">
                    إنشاء دعوة
                </Button>
            </div>
        </Form>

        <p
            v-if="latest_invitation_url"
            class="rounded-xl border border-success-300/50 bg-success-50 px-3 py-2 text-xs leading-5 text-success-800 dark:border-success-500/35 dark:bg-success-500/15 dark:text-success-100"
        >
            آخر رابط دعوة: {{ latest_invitation_url }}
        </p>

        <div class="space-y-2">
            <p class="text-sm font-semibold">الدعوات المعلّقة</p>
            <div
                v-for="invitation in pending_invitations"
                :key="`pending-invitation-${invitation.id}`"
                class="rounded-xl border border-border/70 bg-background/65 p-3 text-sm"
            >
                <p class="font-semibold">{{ invitation.email }} ({{ invitation.role_name }})</p>
                <p class="text-xs text-muted-foreground">
                    تنتهي: {{ invitation.expires_at ?? 'لا تنتهي' }}
                </p>
                <p class="mt-1 break-all text-xs text-info-700 dark:text-info-300">
                    {{ invitation.invitation_url }}
                </p>
            </div>
            <p
                v-if="pending_invitations.length === 0"
                class="text-sm text-muted-foreground"
            >
                لا توجد دعوات نشطة.
            </p>
        </div>
    </div>
</template>
