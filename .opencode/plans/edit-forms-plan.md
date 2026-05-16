# خطة توحيد فورمات التعديل - Inertia Form

## 1. صفحة Roles (`resources/js/pages/roles/Index.vue`)

### التغييرات المطلوبة:

#### أ) تعديل الـ imports
```diff
- import { Form, Head, router } from '@inertiajs/vue3';
+ import { Form, Head } from '@inertiajs/vue3';
+ import RoleController from '@/actions/App/Http/Controllers/RBAC/RoleController';
```

#### ب) تحديث `openEditDialog` لتهيئة الصلاحيات
```diff
  const openEditDialog = (role: Role) => {
      editingRole.value = role;
+     editingPermissions.value = role.permissions.map(p => p.id);
  };
```

#### ج) استبدال فورم التعديل بالكامل
استبدال الـ Form الحالي في الـ Dialog:
```vue
<Form
    v-if="editingRole"
    v-bind="RoleController.update.form(editingRole.id)"
    class="space-y-4"
    reset-on-success
    @success="closeEditDialog"
    v-slot="{ errors, processing }"
>
    <div class="grid gap-2">
        <Label for="edit_role_name">Role Name</Label>
        <Input
            id="edit_role_name"
            name="name"
            :default-value="editingRole.name"
            required
            class="pattern-field-clay"
        />
        <InputError :message="errors.name" />
    </div>

    <div class="grid gap-2">
        <Label for="edit_role_description">Description</Label>
        <textarea
            id="edit_role_description"
            name="description"
            rows="2"
            class="pattern-field-clay"
            :default-value="editingRole.description ?? ''"
        />
    </div>

    <div class="grid gap-2 rounded-xl border border-border/60 bg-background/40 p-3">
        <Label class="text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground">
            Permissions
        </Label>
        <div class="max-h-48 space-y-2 overflow-y-auto">
            <div v-for="(perms, group) in permissions" :key="group" class="mb-3">
                <p class="mb-1 text-xs font-semibold capitalize text-foreground/70">
                    {{ group }}
                </p>
                <div v-for="perm in perms" :key="perm.id" class="ml-3 flex items-center gap-2">
                    <input
                        type="checkbox"
                        v-model="editingPermissions"
                        :value="perm.id"
                        :id="`edit_perm_${perm.id}`"
                        class="size-4 rounded border-border"
                    />
                    <Label :for="`edit_perm_${perm.id}`" class="text-xs font-normal">
                        {{ perm.name }}
                    </Label>
                </div>
            </div>
        </div>
        <InputError :message="errors.permissions" />
        <div v-for="id in editingPermissions" :key="`hidden-edit-${id}`" style="display:none">
            <input type="hidden" name="permissions[]" :value="id" />
        </div>
    </div>

    <DialogFooter>
        <Button type="button" variant="ghost" @click="closeEditDialog()">
            Cancel
        </Button>
        <Button type="submit" variant="clay" :disabled="processing">
            Save Changes
        </Button>
    </DialogFooter>
</Form>
```

#### د) حذف الدالة `submitEditForm`
```diff
- const submitEditForm = (event: Event) => {
-     event.preventDefault();
-     if (!editingRole.value) { return; }
-     const form = event.target as HTMLFormElement;
-     const formData = new FormData(form);
-     editingPermissions.value.forEach(id => formData.append('permissions[]', id.toString()));
-     router.put(`${ROLES_URL}/${editingRole.value.id}`, formData, {
-         onSuccess: () => {
-             toast.success('Role updated successfully');
-             closeEditDialog();
-         },
-         onError: () => {
-             toast.error('Failed to update role');
-         },
-     });
- };
```

#### هـ) حذف المتغير `ROLES_URL`
```diff
- const ROLES_URL = RoleController.index.url();
```

---

## 2. صفحة Doctor Schedules (`resources/js/pages/doctor-schedules/Index.vue`)

### التغييرات المطلوبة:

#### أ) إضافة imports
```diff
- import { Head, useForm, usePage } from '@inertiajs/vue3';
+ import { Form, Head, usePage } from '@inertiajs/vue3';
+ import DoctorScheduleController from '@/actions/App/Http/Controllers/DoctorSchedules/DoctorScheduleController';
+ import { Pencil } from 'lucide-vue-next';
+ import {
+     Dialog,
+     DialogContent,
+     DialogDescription,
+     DialogFooter,
+     DialogHeader,
+     DialogTitle,
+ } from '@/components/ui/dialog';
```

#### ب) إضافة state للتعديل
```ts
const editingSchedule = ref<{
    id: number;
    doctor_id: number;
    day_of_week: number;
    start_time: string;
    end_time: string;
    is_available: boolean;
} | null>(null);
const isEditDialogOpen = ref(false);

const openEditDialog = (schedule: typeof props.schedules.data[number]) => {
    editingSchedule.value = {
        id: schedule.id,
        doctor_id: schedule.doctor_id,
        day_of_week: schedule.day_of_week,
        start_time: schedule.start_time,
        end_time: schedule.end_time,
        is_available: schedule.is_available,
    };
    isEditDialogOpen.value = true;
};

const closeEditDialog = () => {
    editingSchedule.value = null;
    isEditDialogOpen.value = false;
};
```

#### ج) حذف دالة `updateSchedule` القديمة و `useForm`
```diff
- const form = useForm({ ... });
- function updateSchedule(scheduleId: number, data: { ... }) { ... }
```

#### د) إضافة زر Edit في الجدول
```vue
<Button
    variant="neumorphic"
    size="icon"
    class="size-8"
    @click="openEditDialog(schedule)"
>
    <Pencil class="size-4" />
</Button>
```

#### هـ) إضافة Dialog التعديل
```vue
<Dialog :open="isEditDialogOpen" @update:open="(open) => !open && closeEditDialog()">
    <DialogContent class="glass-panel-lux sm:max-w-lg">
        <DialogHeader>
            <DialogTitle>تعديل جدول الدوام</DialogTitle>
            <DialogDescription>تحديث أوقات دوام الطبيب.</DialogDescription>
        </DialogHeader>

        <Form
            v-if="editingSchedule"
            v-bind="DoctorScheduleController.update.form(editingSchedule.id)"
            class="space-y-4"
            @success="closeEditDialog"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label>الطبيب</Label>
                <Select name="doctor_id" :default-value="editingSchedule.doctor_id">
                    <SelectTrigger>
                        <SelectValue placeholder="اختر الطبيب" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="doctor in doctors" :key="doctor.id" :value="doctor.id">
                            {{ doctor.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.doctor_id" />
            </div>

            <div class="grid gap-2">
                <Label>اليوم</Label>
                <Select name="day_of_week" :default-value="editingSchedule.day_of_week">
                    <SelectTrigger>
                        <SelectValue placeholder="اختر اليوم" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="day in DAYS" :key="day.value" :value="day.value">
                            {{ day.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.day_of_week" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label>وقت البدء</Label>
                    <Input name="start_time" type="time" :default-value="editingSchedule.start_time" />
                    <InputError :message="errors.start_time" />
                </div>
                <div class="grid gap-2">
                    <Label>وقت الانتهاء</Label>
                    <Input name="end_time" type="time" :default-value="editingSchedule.end_time" />
                    <InputError :message="errors.end_time" />
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Switch name="is_available" :default-value="editingSchedule.is_available" />
                <Label class="text-sm">متاح</Label>
            </div>

            <DialogFooter>
                <Button type="button" variant="ghost" @click="closeEditDialog()">
                    إلغاء
                </Button>
                <Button type="submit" variant="clay" :disabled="processing">
                    حفظ التغييرات
                </Button>
            </DialogFooter>
        </Form>
    </DialogContent>
</Dialog>
```

---

## ملخص الملفات المعدلة

| الملف | التغيير |
|-------|---------|
| `resources/js/pages/roles/Index.vue` | تحويل من `router.put()` إلى `RoleController.update.form()` |
| `resources/js/pages/doctor-schedules/Index.vue` | إضافة Dialog تعديل + استخدام `DoctorScheduleController.update.form()` |
