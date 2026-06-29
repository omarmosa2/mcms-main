<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { onMounted, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Security/UserController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type User = {
    id: number;
    name: string;
    email: string;
    username: string | null;
    is_active: boolean;
    is_super_admin: boolean;
    roles: string[];
    role_names: string[];
    two_factor_enabled: boolean;
    created_at: string | null;
};

type Role = {
    id: number;
    name: string;
    description: string | null;
    is_system: boolean;
};

const props = defineProps<{ user: User | null; roles: Role[]; canResetPassword: boolean }>();

const emit = defineEmits<{ close: [] }>();

const editForm = useForm({
    name: '',
    email: '',
    role_name: '',
    is_active: true,
});

const resetPasswordForm = useForm({
    password: '',
    password_confirmation: '',
});

const submitEdit = (): void => {
    if (!props.user) return;
    editForm.put(UserController.update.url(props.user.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};

const resetPassword = (userId: number): void => {
    resetPasswordForm.post(UserController.resetPassword.url(userId), {
        preserveScroll: true,
        onSuccess: () => resetPasswordForm.reset(),
    });
};

const populateEditForm = (): void => {
    if (props.user) {
        editForm.name = props.user.name;
        editForm.email = props.user.email;
        editForm.role_name = props.user.role_names[0] ?? '';
        editForm.is_active = props.user.is_active;
        editForm.clearErrors();
    }
};

watch(
    () => props.user,
    (newUser) => {
        if (newUser) {
            populateEditForm();
        }
    },
);

onMounted(() => {
    if (props.user) {
        populateEditForm();
    }
});
</script>

<template>
    <Dialog :open="user !== null" @update:open="(open) => !open && emit('close')">
        <DialogContent class="max-w-[520px] bg-card rounded-xl">
            <DialogHeader class="p-6 pb-4 border-b border-border">
                <DialogTitle class="text-base font-medium text-foreground">تعديل المستخدم</DialogTitle>
                <DialogDescription class="text-sm text-muted-foreground mt-0.5">تعديل بيانات المستخدم والأدوار</DialogDescription>
            </DialogHeader>

            <form
                v-if="user"
                @submit.prevent="submitEdit"
                class="p-6 space-y-4 max-h-[60vh] overflow-y-auto"
            >
                <div class="flex flex-col gap-1.5">
                    <Label for="edit_name" class="text-sm font-medium text-foreground">
                        الاسم الكامل
                        <span class="text-destructive mr-1">*</span>
                    </Label>
                    <Input
                        id="edit_name"
                        v-model="editForm.name"
                        required
                        class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                    />
                    <InputError :message="editForm.errors.name" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="edit_email" class="text-sm font-medium text-foreground">
                        البريد الإلكتروني
                        <span class="text-destructive mr-1">*</span>
                    </Label>
                    <Input
                        id="edit_email"
                        v-model="editForm.email"
                        type="email"
                        required
                        class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                    />
                    <InputError :message="editForm.errors.email" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="edit_role_name" class="text-sm font-medium text-foreground">
                        الدور
                        <span class="text-destructive mr-1">*</span>
                    </Label>
                    <select
                        id="edit_role_name"
                        v-model="editForm.role_name"
                        required
                        class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors appearance-none cursor-pointer"
                    >
                        <option value="">اختر دوراً</option>
                        <option
                            v-for="role in roles"
                            :key="role.id"
                            :value="role.name"
                        >
                            {{ role.name }}
                        </option>
                    </select>
                    <InputError :message="editForm.errors.role_name" />
                </div>

                <div class="flex items-center gap-2">
                    <input
                        id="edit_is_active"
                        v-model="editForm.is_active"
                        type="checkbox"
                        class="size-4 rounded border-border"
                    />
                    <Label for="edit_is_active" class="text-sm font-normal text-foreground">
                        حساب نشط
                    </Label>
                </div>

                <section v-if="canResetPassword" class="space-y-3 rounded-lg border border-warning/30 bg-warning/5 p-4">
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">إعادة ضبط كلمة المرور</h3>
                        <p class="mt-1 text-xs text-muted-foreground">اترك الحقول فارغة إن لم ترغب بتغيير كلمة المرور.</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="edit_password" class="text-sm font-medium text-foreground">كلمة المرور الجديدة</Label>
                        <Input
                            id="edit_password"
                            v-model="resetPasswordForm.password"
                            type="password"
                            autocomplete="new-password"
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                        />
                        <InputError :message="resetPasswordForm.errors.password" />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="edit_password_confirmation" class="text-sm font-medium text-foreground">تأكيد كلمة المرور الجديدة</Label>
                        <Input
                            id="edit_password_confirmation"
                            v-model="resetPasswordForm.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                        />
                        <InputError :message="resetPasswordForm.errors.password_confirmation" />
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        :disabled="resetPasswordForm.processing"
                        @click="resetPassword(user.id)"
                    >
                        إعادة ضبط كلمة المرور
                    </Button>
                </section>

                <DialogFooter class="flex items-center justify-between p-6 pt-4 gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        class="h-9 px-4 rounded-lg text-muted-foreground text-sm font-medium hover:bg-muted hover:text-foreground transition-colors duration-150 active:scale-[0.98]"
                        :disabled="editForm.processing"
                        @click="emit('close')"
                    >
                        إلغاء
                    </Button>
                    <Button
                        type="submit"
                        variant="default"
                        :disabled="editForm.processing"
                        class="h-9 px-4 rounded-lg bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 active:scale-[0.98] transition-all duration-150"
                    >
                        حفظ التغييرات
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
