# خطة التنفيذ — المرحلة 1 و 2

> MCMS — Medical Center Management System
> التاريخ: 2026-04-29

---

## ✅ ما تم التحقق منه (موجود ويعمل)

| المهمة | الحالة | التفاصيل |
|--------|--------|----------|
| 1.1 منع التداخل الزمني | ✅ | `CreateAppointmentAction` + `UpdateAppointmentAction` |
| 1.2 جدول دوام الأطباء (Backend) | ✅ | Model + Migration + Controller + Actions + Service + Routes |
| 1.4 سلة المحذوفات | ✅ | TrashController + Vue page + Routes |
| 1.5 تقييد حذف الفواتير | ✅ | `DeleteInvoiceAction` يفحص المدفوعات |
| 1.6 تحقق رقم الهاتف | ✅ | `min:8` + `regex:/^[0-9+\s()-]+$/` في Store/UpdatePatientRequest |
| 1.7 نسخ احتياطي تلقائي | ✅ | `routes/console.php` — يومي 2:00 صباحاً |
| 2.7 تحسين الجدول على الموبايل | ✅ | CSS شامل في `app.css` |

---

## ❌ ما يحتاج تنفيذ

### 1.3 Soft Delete شامل

**المشكلة**: 9 نماذج فقط من 65 لديها `SoftDeletes`. 56 نموذج بدون حماية الحذف.

**الخطوات**:

#### أ) إضافة SoftDeletes لـ 26 نموذج رئيسي:
```
User, Role, Permission, Expense, ExpenseCategory, Salary, Cashbox,
Account, JournalEntry, DoctorSchedule, PatientAllergy, PatientMedication,
PatientChronicCondition, PatientAttachment, VisitDiagnosis, VisitVitalSign,
LabOrder, LabResult, LabTestTemplate, RadiologyOrder, RadiologyReport,
RadiologyStudyType, Prescription, PrescriptionItem, PharmacyDrug, DrugBatch,
PharmacyDispense, PharmacyDispenseItem, Supplier, PurchaseOrder,
PurchaseOrderItem, StockAdjustment, InventoryReturn, InventoryAlert,
PaymentPlan, Installment
```

#### ب) إنشاء migration واحد شامل يضيف `deleted_at` لكل الجداول المتبقية:
```php
// جداول تحتاج deleted_at:
users, roles, permissions, expenses, expense_categories, salaries,
cashboxes, accounts, journal_entries, doctor_schedules,
patient_allergies, patient_medications, patient_chronic_conditions,
patient_attachments, visit_diagnoses, visit_vital_signs,
lab_orders, lab_results, lab_test_templates,
radiology_orders, radiology_reports, radiology_study_types, radiology_images,
prescriptions, prescription_items, pharmacy_drugs, drug_batches,
pharmacy_dispenses, pharmacy_dispense_items,
suppliers, purchase_orders, purchase_order_items,
stock_adjustments, inventory_returns, inventory_alerts,
payment_plans, installments,
workflows, workflow_steps, workflow_instances, workflow_approvals,
number_ranges, branding_settings, security_policies, external_integrations
```

#### ج) تحديث جميع List*Actions لاستخدام `withoutTrashed()`:
```php
// نمط موحد في كل List*Action:
$query = Model::query()->forClinic($clinicId)->withoutTrashed();
```

**الملفات المتأثرة** (~30 ملف Action):
- كل `List*Action.php` في `app/Actions/`

---

### 1.2 صفحة Vue لإدارة جدول دوام الأطباء

**الملف الجديد**: `resources/js/pages/doctor-schedules/Index.vue`

**المحتوى**:
- جدول يعرض أيام الأسبوع (أحد-سبت)
- لكل يوم: وقت البدء، وقت الانتهاء، حالة التوفر
- زر حفظ لكل يوم أو حفظ شامل
- اختيار الطبيب من dropdown
- تصميم RTL عربي

**الـ Backend موجود**:
- Controller: `DoctorScheduleController`
- Actions: `CreateDoctorScheduleAction`, `UpdateDoctorScheduleAction`, `DeleteDoctorScheduleAction`, `ListDoctorSchedulesAction`
- Routes: `routes/doctor-schedules.php`

**الـ Routes المطلوبة**:
```php
// إضافة في web.php أو doctor-schedules.php
Route::get('/doctor-schedules', [DoctorScheduleController::class, 'indexPage'])
    ->middleware(['auth', 'verified'])
    ->name('doctor-schedules.index');
```

---

### 2.1 صفحة المريض التفصيلية

**الملف الجديد**: `resources/js/pages/patients/Show.vue`

**التصميم**: صفحة بتبويبات (Tabs):
1. **المعلومات**: بيانات شخصية، جهة طوارئ، ملاحظات
2. **المواعيد**: جدول المواعيد القادمة والسابقة
3. **الزيارات**: سجل الزيارات مع ملاحظات سريرية
4. **الفواتير**: الفواتير والمدفوعات والرصيد
5. **الطبي**: حساسيات، أدوية مزمنة، أمراض مزمنة
6. **المرفقات**: ملفات مرفقة

**الـ Backend**:
- `ShowPatientAction` موجود — يحتاج تحديث لتحميل كل العلاقات
- Route موجود: `GET /patients/{patientId}` → `PatientController@show`

---

### 2.2 تخصيص رسائل الحذف

**الملفات المتأثرة**:
- `resources/js/pages/appointments/Index.vue` — سطر ~557
- `resources/js/pages/patients/Index.vue` — سطر ~652
- `resources/js/pages/billing/Index.vue`
- `resources/js/pages/doctors/Index.vue`
- `resources/js/pages/departments/Index.vue`
- `resources/js/pages/visits/Index.vue`
- `resources/js/pages/queue/Index.vue`
- `resources/js/pages/expenses/Index.vue`
- `resources/js/pages/salaries/Index.vue`

**النمط**:
```js
// قبل (عام):
description: 'هل أنت متأكد من حذف هذا الموعد؟'

// بعد (مخصص):
description: `هل أنت متأكد من حذف موعد "${appointment.appointment_number}" للمريض "${appointment.patient?.full_name}"؟`
```

---

### 2.3 تعريب رسائل الخطأ

**ملف جديد**: `lang/ar/validation.php`

**المحتوى**:
```php
<?php

return [
    'required' => 'حقل :attribute مطلوب.',
    'required_if' => 'حقل :attribute مطلوب عندما :other يساوي :value.',
    'required_unless' => 'حقل :attribute مطلوب ما لم :other في :values.',
    'required_with' => 'حقل :attribute مطلوب عندما يكون :values موجوداً.',
    'required_with_all' => 'حقل :attribute مطلوب عندما تكون :values موجودة.',
    'required_without' => 'حقل :attribute مطلوب عندما لا يكون :values موجوداً.',
    'required_without_all' => 'حقل :attribute مطلوب عندما لا تكون أي من :values موجودة.',
    'min.string' => 'حقل :attribute يجب أن يكون :min حرف على الأقل.',
    'max.string' => 'حقل :attribute يجب ألا يتجاوز :max حرف.',
    'min.numeric' => 'حقل :attribute يجب أن يكون :min على الأقل.',
    'max.numeric' => 'حقل :attribute يجب ألا يتجاوز :max.',
    'min.array' => 'حقل :attribute يجب أن يحتوي على :min عنصر على الأقل.',
    'max.array' => 'حقل :attribute يجب ألا يتجاوز :max عنصر.',
    'date' => 'حقل :attribute يجب أن يكون تاريخاً صحيحاً.',
    'date_format' => 'حقل :attribute يجب أن يكون بصيغة :format.',
    'after_or_equal' => 'حقل :attribute يجب أن يكون اليوم أو لاحقاً.',
    'before_or_equal' => 'حقل :attribute يجب أن يكون اليوم أو سابقاً.',
    'after' => 'حقل :attribute يجب أن يكون بعد :date.',
    'before' => 'حقل :attribute يجب أن يكون قبل :date.',
    'email' => 'حقل :attribute يجب أن يكون بريداً إلكترونياً صحيحاً.',
    'exists' => 'القيمة المختارة لـ :attribute غير صحيحة.',
    'unique' => 'هذه القيمة مستخدمة مسبقاً في :attribute.',
    'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً.',
    'numeric' => 'حقل :attribute يجب أن يكون رقماً.',
    'in' => 'القيمة المختارة لـ :attribute غير صحيحة.',
    'not_in' => 'القيمة المختارة لـ :attribute غير صحيحة.',
    'string' => 'حقل :attribute يجب أن يكون نصاً.',
    'array' => 'حقل :attribute يجب أن يكون مصفوفة.',
    'boolean' => 'حقل :attribute يجب أن يكون true أو false.',
    'confirmed' => 'تأكيد حقل :attribute غير متطابق.',
    'same' => 'حقل :attribute يجب أن يطابق :other.',
    'different' => 'حقل :attribute يجب أن يختلف عن :other.',
    'file' => 'حقل :attribute يجب أن يكون ملفاً.',
    'image' => 'حقل :attribute يجب أن يكون صورة.',
    'mimes' => 'حقل :attribute يجب أن يكون ملفاً من نوع: :values.',
    'mimetypes' => 'حقل :attribute يجب أن يكون ملفاً من نوع: :values.',
    'size.string' => 'حقل :attribute يجب أن يكون :size حرف.',
    'size.numeric' => 'حقل :attribute يجب أن يكون :size.',
    'size.array' => 'حقل :attribute يجب أن يحتوي على :size عنصر.',
    'between.numeric' => 'حقل :attribute يجب أن يكون بين :min و :max.',
    'between.string' => 'حقل :attribute يجب أن يكون بين :min و :max حرف.',
    'between.array' => 'حقل :attribute يجب أن يحتوي على بين :min و :max عنصر.',
    'digits' => 'حقل :attribute يجب أن يكون :digits رقم.',
    'digits_between' => 'حقل :attribute يجب أن يكون بين :min و :max رقم.',
    'regex' => 'صيغة حقل :attribute غير صحيحة.',
    'url' => 'حقل :attribute يجب أن يكون رابطاً صحيحاً.',
    'ip' => 'حقل :attribute يجب أن يكون عنوان IP صحيحاً.',
    'ipv4' => 'حقل :attribute يجب أن يكون عنوان IPv4 صحيحاً.',
    'ipv6' => 'حقل :attribute يجب أن يكون عنوان IPv6 صحيحاً.',
    'json' => 'حقل :attribute يجب أن يكون نص JSON صحيح.',
    'timezone' => 'حقل :attribute يجب أن يكون منطقة زمنية صحيحة.',
    'distinct' => 'حقل :attribute يحتوي على قيمة مكررة.',
    'unique' => 'هذه القيمة مسجلة مسبقاً.',
    'present' => 'حقل :attribute يجب أن يكون موجوداً.',
    'not_regex' => 'صيغة حقل :attribute غير صحيحة.',
    'prohibited' => 'حقل :attribute محظور.',
    'prohibited_if' => 'حقل :attribute محظور عندما :other يساوي :value.',
    'prohibited_unless' => 'حقل :attribute محظور ما لم :other في :values.',
    'custom' => [],
    'attributes' => [],
];
```

**أيضاً**: تعيين `locale => 'ar'` في `config/app.php`

---

### 2.4 تصدير متعدد الصيغ (CSV, PDF)

**الخطوات**:

#### أ) إضافة دعم CSV:
في `PatientImportExportController` (وأي controller تصدير آخر):
```php
$format = $request->query('format', 'xlsx');
$extension = $format === 'csv' ? 'csv' : 'xlsx';
return Excel::download(new PatientExport($data), "patients.{$extension}");
```

#### ب) إضافة دعم PDF:
- تثبيت `barryvdh/laravel-dompdf`
- إنشاء Export classes للـ PDF
- إنشاء Blade views للتصدير

#### ج) تحديث الواجهات:
إضافة أزرار في كل صفحة جدول:
```vue
<Button @click="exportData('xlsx')">Excel</Button>
<Button @click="exportData('csv')">CSV</Button>
<Button @click="exportData('pdf')">PDF</Button>
```

---

### 2.5 معاينة الاستيراد قبل الحفظ

**الخطوات**:

#### أ) تعديل `PatientImport`:
```php
public function preview(UploadedFile $file): Collection
{
    $rows = (new FastExcel)->import($file);
    return collect($rows)->take(10);
}
```

#### ب) إضافة endpoint في Controller:
```php
public function preview(Request $request)
{
    $file = $request->file('file');
    $preview = $this->patientImport->preview($file);
    return response()->json(['rows' => $preview]);
}
```

#### ج) واجهة Vue:
- رفع الملف → عرض أول 10 صفوف في جدول
- أعمدة ملونة (أخضر = صحيح، أحمر = خطأ)
- ملخص: "X صفوف صالحة، Y صفوف بها أخطاء"
- زر "استيراد الصالح فقط" أو "إلغاء"

---

### 2.8 مرضى جدد اليوم في Dashboard

**الخطوات**:

#### أ) Backend — تحديث `CacheService::getDashboardStats`:
```php
'today_new_patients' => Patient::query()
    ->forClinic($clinicId)
    ->whereDate('created_at', today())
    ->withoutTrashed()
    ->count(),
```

#### ب) Frontend — إضافة كارد في `Dashboard.vue`:
```vue
<div class="flex items-center gap-2">
    <Users class="size-4 text-muted-foreground" />
    <span class="text-sm text-muted-foreground">مرضى جدد اليوم</span>
    <span class="text-lg font-bold tabular-nums">{{ chartStats?.today_new_patients ?? 0 }}</span>
</div>
```

---

### 3.7 تقييد رؤية الطبيب لجدوله فقط

**الخطوات**:

#### أ) `ListAppointmentsAction`:
```php
public function handle(int $clinicId, int $userId, ...): LengthAwarePaginator
{
    $query = Appointment::query()->forClinic($clinicId);

    // تقييد الطبيب ليرى مواعيده فقط
    $user = User::find($userId);
    if ($user->hasRole('doctor')) {
        $query->where('doctor_id', $userId);
    }

    // ... باقي الكود
}
```

#### ب) نفس النمط في:
- `ListVisitsAction`
- `ListQueueEntriesAction` (إذا لزم)

---

## ترتيب التنفيذ المقترح

1. **SoftDeletes + Migrations** (1.3) — الأساس لكل شيء آخر
2. **lang/ar/validation.php** (2.3) — سريع وأساسي
3. **تخصيص رسائل الحذف** (2.2) — سريع
4. **تقييد رؤية الطبيب** (3.7) — أمني مهم
5. **مرضى جدد اليوم** (2.8) — سريع
6. **صفحة جدول دوام الأطباء** (1.2) — واجهة جديدة
7. **صفحة المريض التفصيلية** (2.1) — واجهة كبيرة
8. **تصدير متعدد الصيغ** (2.4) — يحتاج dependency جديد
9. **معاينة الاستيراد** (2.5) — يحتاج تعديل Import class

---

## ملاحظات هامة

- **لا تحذف ملفات الاختبار** — أساسية للنظام
- **شغّل `vendor/bin/pint --format agent`** بعد كل تعديل PHP
- **شغّل الاختبارات** بعد كل تغيير: `php artisan test --compact --filter=testName`
- **اتبع نمط Actions** — كل عملية جديدة = Action class
- **استخدم Wayfinder** للمسارات في الـ Frontend
