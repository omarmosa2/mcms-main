<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import DepartmentController from '@/actions/App/Http/Controllers/Departments/DepartmentController';
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
import { usePermissions } from '@/composables/usePermissions';
import type { Department } from './types';

defineProps<{
    open: boolean;
    department: Department | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { can } = usePermissions();

const handleClose = () => {
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" @update:open="(val: boolean) => !val && handleClose()">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>تعديل القسم</DialogTitle>
                <DialogDescription>
                    تحديث اسم القسم، الحالة، والوصف.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-if="department && can('department.update')"
                v-bind="DepartmentController.update.form(department.id)"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                @success="handleClose"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="edit_department_name">الاسم</Label>
                        <Input
                            id="edit_department_name"
                            name="name"
                            :value="department.name"
                            required
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit_department_code">الرمز</Label>
                        <Input
                            id="edit_department_code"
                            name="code"
                            :value="department.code ?? ''"
                            class="pattern-field-clay"
                        />
                        <InputError :message="errors.code" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="edit_department_description">الوصف</Label>
                    <textarea
                        id="edit_department_description"
                        name="description"
                        rows="3"
                        class="pattern-field-clay"
                        :value="department.description ?? ''"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div
                    class="flex items-center gap-2 rounded-xl border border-border/60 bg-background/50 px-3 py-2"
                >
                    <input type="hidden" name="is_active" value="0" />
                    <input
                        id="edit_department_is_active"
                        name="is_active"
                        type="checkbox"
                        value="1"
                        class="size-4 rounded border-border"
                        :checked="department.is_active"
                    />
                    <Label for="edit_department_is_active" class="text-sm">
                        قسم نشط
                    </Label>
                </div>
                <InputError :message="errors.is_active" />

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="neumorphic"
                        :disabled="processing"
                        @click="handleClose"
                    >
                        إلغاء
                    </Button>
                    <Button
                        type="submit"
                        variant="clay"
                        :disabled="processing"
                    >
                        حفظ التغييرات
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>