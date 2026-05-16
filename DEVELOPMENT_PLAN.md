# خطة تطوير نظام إدارة المراكز الطبية (MCMS)

> **التقنية**: Laravel 13 + Inertia v3 + Vue 3 + Tailwind CSS v4
> **الحالة**: نظام قائم يحتاج إصلاحات منطقية + تحسينات واجهات + ميزات AI

---

## المرحلة 1: إصلاح الأخطاء المنطقية الأساسية

### 1.1 منع حجز موعدين متعارضين (Double Booking)

**المشكلة**: يمكن حجز موعدين لنفس المريض بنفس الوقت، أو حجز طبيبين لنفس الوقت.

**الملفات المتأثرة**:
- `app/Actions/Appointments/CreateAppointmentAction.php`
- `app/Actions/Appointments/UpdateAppointmentAction.php`
- `app/Http/Requests/Appointments/StoreAppointmentRequest.php`

**الخطوات**:
1. في `CreateAppointmentAction` قبل إنشاء الموعد:
   - فحص تداخل المريض: هل لدى هذا المريض موعد آخر بنفس الوقت؟
   - فحص تداخل الطبيب: هل لدى هذا الطبيب موعد آخر بنفس الوقت؟
   - معادلة التداخل: `existing_start < new_end AND existing_end > new_start`
2. إضافة رسالة خطأ واضحة: "المريض لديه موعد آخر بنفس الوقت" / "الطبيب لديه موعد آخر بنفس الوقت"
3. إضافة فحص في `UpdateAppointmentAction` عند تغيير الوقت
4. إضافة قاعدة فريدة في قاعدة البيانات (unique constraint) كحماية أخيرة

**مثال للكود**:
```php
$startTime = $payload['scheduled_for'];
$endTime = now($startTime)->addMinutes($payload['duration_minutes'] ?? 30);

// فحص تداخل المريض
$patientConflict = Appointment::query()
    ->where('patient_id', $payload['patient_id'])
    ->whereNotIn('status', Appointment::TERMINAL_STATUSES)
    ->where('scheduled_for', '<', $endTime)
    ->whereRaw('DATE_ADD(scheduled_for, INTERVAL duration_minutes MINUTE) > ?', [$startTime])
    ->exists();

// فحص تداخل الطبيب
$doctorConflict = $payload['doctor_id']
    ? Appointment::query()
        ->where('doctor_id', $payload['doctor_id'])
        ->whereNotIn('status', Appointment::TERMINAL_STATUSES)
        ->where('scheduled_for', '<', $endTime)
        ->whereRaw('DATE_ADD(scheduled_for, INTERVAL duration_minutes MINUTE) > ?', [$startTime])
        ->exists()
    : false;
```

---

### 1.2 جدول دوام الأطباء (Doctor Schedules)

**المشكلة**: يمكن حجز موعد في أي وقت (حتى الساعة 3 صباحاً) بدون التحقق من دوام الطبيب.

**الخطوات**:
1. إنشاء migration جديد: `create_doctor_schedules_table`
   - `doctor_id` (foreign → users)
   - `day_of_week` (0-6 أو sunday-monday)
   - `start_time` (time)
   - `end_time` (time)
   - `is_available` (boolean)
2. إنشاء Model: `DoctorSchedule`
3. إنشاء Form Request: `StoreDoctorScheduleRequest`
4. إنشاء Controller: `DoctorScheduleController`
5. إنشاء Actions: `CreateDoctorScheduleAction`, `UpdateDoctorScheduleAction`
6. في `CreateAppointmentAction` + `UpdateAppointmentAction`:
   - فحص هل اليوم من أيام دوام الطبيب؟
   - فحص هل الوقت ضمن ساعات الدوام؟
7. إضافة واجهة Vue لإدارة جدول الدوام

**هيكل الجدول**:
```php
Schema::create('doctor_schedules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
    $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
    $table->tinyInteger('day_of_week'); // 0=Sunday, 6=Saturday
    $table->time('start_time');
    $table->time('end_time');
    $table->boolean('is_available')->default(true);
    $table->timestamps();

    $table->unique(['clinic_id', 'doctor_id', 'day_of_week']);
});
```

---

### 1.3 Soft Delete عالمي

**المشكلة**: الحذف نهائي ولا يمكن استرجاع أي عنصر.

**الخطوات**:
1. إضافة `deleted_at` عبر migrations جديدة للجداول:
   - `patients`
   - `appointments`
   - `invoices`
   - `invoice_items`
   - `payments`
   - `visits`
   - `queue_entries`
   - `doctor_profiles`
   - `departments`
2. إضافة `use SoftDeletes;` في `BaseModel`
3. تحديث جميع `Delete*Action` لاستخدام `$model->delete()` بدلاً من الحذف الفعلي
4. تحديث جميع `List*Action` لاستخدام `withoutTrashed()` افتراضياً
5. إضافة `withTrashed()` في صفحة الـ Trash

---

### 1.4 سلة المحذوفات (Trash / Recycle Bin)

**الخطوات**:
1. إنشاء Route: `/trash` → `TrashController`
2. إنشاء صفحة Vue: `resources/js/pages/trash/Index.vue`
3. إنشاء Actions: `RestoreItemAction`, `ForceDeleteItemAction`, `EmptyTrashAction`
4. عرض العناصر المحذوفة مع:
   - اسم العنصر
   - نوعه (مريض، موعد، فاتورة...)
   - تاريخ الحذف
   - من حذف
   - زر "استرجاع" + زر "حذف نهائي"
5. إضافة زر "سلة المحذوفات" في الـ Sidebar

---

### 1.5 تقييد حذف الفواتير

**المشكلة**: يمكن حذف فاتورة عليها مدفوعات.

**الخطوات**:
1. في `DeleteInvoiceAction` (أو ما يعادله):
   - فحص `payments_count`
   - إذا > 0: رمي خطأ "لا يمكن حذف فاتورة عليها مدفوعات"
2. البديل: السماح بالإلغاء (void) بدلاً من الحذف

---

### 1.6 تحقق رقم الهاتف

**المشكلة**: رقم الهاتف يقبل حرف واحد فقط.

**الخطوات**:
1. في `StorePatientRequest` و `UpdatePatientRequest`:
   - تغيير `'phone' => ['nullable', 'string', 'max:30']`
   - إلى: `'phone' => ['nullable', 'string', 'min:8', 'max:30', 'regex:/^[0-9+\s()-]+$/']`
2. إضافة رسالة خطأ عربية: "رقم الهاتف يجب أن يكون 8 أرقام على الأقل"

---

### 1.7 نسخ احتياطي تلقائي (Backup)

**الخطوات**:
1. إنشاء Command: `php artisan make:command DatabaseBackup`
2. الكود:
   - `sqlite backup` → نسخ ملف `database.sqlite`
   - أو `mysqldump` إذا تم تغيير لـ MySQL
   - ضغط الملف بـ gzip
   - تخزين في `storage/app/backups/`
   - حذف النسخ الأقدم من 30 يوم
3. جدولة في `routes/console.php`:
   ```php
   Schedule::command('backup:run')->dailyAt('02:00');
   ```
4. إضافة صفحة لإدارة النسخ الاحتياطية (عرض + تحميل + استعادة)

---

## المرحلة 2: تحسينات الواجهات وسهولة الاستخدام

### 2.1 صفحة المريض التفصيلية

**المشكلة**: لا توجد صفحة تعرض كل معلومات المريض في مكان واحد.

**الخطوات**:
1. إنشاء Route: `GET /patients/{patientId}` → `PatientController@show` (يعدل الحالي)
2. في `ShowPatientAction`:
   - تحميل المريض مع: appointments, visits, invoices, allergies, medications, chronicConditions, attachments
3. إنشاء صفحة Vue: `resources/js/pages/patients/Show.vue`
4. تصميم الصفحة بأقسام (Tabs):
   - **تبويب المعلومات**: البيانات الشخصية، جهة الطوارئ، ملاحظات
   - **تبويب المواعيد**: جدول المواعيد القادمة والسابقة
   - **تبويب الزيارات**: سجل الزيارات مع الملاحظات السريرية
   - **تبويب الفواتير**: الفواتير والمدفوعات والرصيد
   - **تبويب طبي**: الحساسيات، الأدوية المزمنة، الأمراض المزمنة
   - **تبويب المرفقات**: الملفات المرفقة
5. إضافة زر "عرض الملف الكامل" في جدول المرضى

---

### 2.2 تخصيص رسائل الحذف

**المشكلة**: رسالة الحذف عامة ولا تذكر اسم العنصر.

**الخطوات**:
1. في `deleteAppointment` في `appointments/Index.vue`:
   ```js
   description: `هل أنت متأكد من حذف موعد "${appointment.appointment_number}" للمريض "${appointment.patient?.full_name}"؟`
   ```
2. نفس النمط في:
   - `patients/Index.vue` → اسم المريض + رقم الملف
   - `billing/Index.vue` → رقم الفاتورة + اسم المريض
   - `doctors/Index.vue` → اسم الطبيب
   - `departments/Index.vue` → اسم القسم

---

### 2.3 تعريب رسائل الخطأ

**الخطوات**:
1. إنشاء ملف `lang/ar/validation.php`
2. ترجمة جميع الرسائل:
   ```php
   return [
       'required' => 'حقل :attribute مطلوب.',
       'min.string' => 'حقل :attribute يجب أن يكون :min حرف على الأقل.',
       'max.string' => 'حقل :attribute يجب ألا يتجاوز :max حرف.',
       'date' => 'حقل :attribute يجب أن يكون تاريخاً صحيحاً.',
       'after_or_equal' => 'حقل :attribute يجب أن يكون اليوم أو لاحقاً.',
       'before_or_equal' => 'حقل :attribute يجب أن يكون اليوم أو سابقاً.',
       'email' => 'حقل :attribute يجب أن يكون بريداً إلكترونياً صحيحاً.',
       'exists' => 'القيمة المختارة لـ :attribute غير صحيحة.',
       'unique' => 'هذه القيمة مستخدمة مسبقاً في :attribute.',
       'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً.',
       'numeric' => 'حقل :attribute يجب أن يكون رقماً.',
       'in' => 'القيمة المختارة لـ :attribute غير صحيحة.',
   ];
   ```
3. ترجمة الرسائل المخصصة في Form Requests
4. تعيين `locale => 'ar'` في `config/app.php`

---

### 2.4 تصدير متعدد الصيغ (Excel, CSV, PDF)

**المشكلة**: التصدير متاح فقط بصيغة Excel للمرضى.

**الخطوات**:
1. إنشاء Exports جديدة:
   - `AppointmentExport.php`
   - `InvoiceExport.php`
   - `PatientExport.php` (موجود — يحتاج تحديث)
2. إضافة دعم CSV:
   ```php
   // في Controller
   $format = $request->query('format', 'xlsx');
   $extension = $format === 'csv' ? 'csv' : 'xlsx';
   $writer = $format === 'csv' ? Excel::CSV : Excel::XLSX;
   ```
3. إضافة دعم PDF:
   - استخدام `barryvdh/laravel-dompdf`
   - إنشاء View Blade لكل نوع
4. إضافة أزرار التصدير في كل صفحة جدول:
   ```vue
   <Button @click="exportData('xlsx')">Excel</Button>
   <Button @click="exportData('csv')">CSV</Button>
   <Button @click="exportData('pdf')">PDF</Button>
   ```

---

### 2.5 معاينة الاستيراد قبل الحفظ

**المشكلة**: لا يمكن رؤية البيانات قبل الحفظ الفعلي.

**الخطوات**:
1. تعديل `PatientImport` لدعم وضع المعاينة:
   - إضافة `preview()` method ترجع أول 10 صفوف
2. في Controller:
   - رفع الملف → قراءة الصفوف الأولى → إرجاعها كـ JSON
   - عرضها في جدول Vue مع تحديد الأخطاء
   - زر "تأكيد الاستيراد" → يرسل نفس الملف للـ Job
3. واجهة Vue:
   - جدول Preview مع أعمدة ملونة (أخضر = صحيح، أحمر = خطأ)
   - ملخص: "10 صفوف صالحة، 2 صفوف بها أخطاء"
   - زر "استيراد الصالح فقط" أو "إلغاء"

---

### 2.6 تقييد حجم ملفات الاستيراد

**الخطوات**:
1. في `StorePatientImportRequest`:
   ```php
   'file' => ['required', 'file', 'mimes:xlsx,csv', 'max:5120'], // 5MB
   ```
2. في `php.ini` التأكد من `upload_max_filesize = 10M`

---

### 2.7 تحسين الجدول على الموبايل

**الخطوات**:
1. إضافة `data-label` لكل `<td>` (موجود جزئياً)
2. CSS للموبايل:
   ```css
   @media (max-width: 768px) {
       .ui-table thead { display: none; }
       .ui-table tbody tr {
           display: block;
           border: 1px solid var(--border);
           margin-bottom: 8px;
           padding: 12px;
       }
       .ui-table td {
           display: flex;
           justify-content: space-between;
           padding: 6px 0;
       }
       .ui-table td::before {
           content: attr(data-label);
           font-weight: 600;
       }
   }
   ```
3. تطبيق على جميع الصفحات: appointments, patients, billing, queue, visits

---

### 2.8 عرض المرضى الجدد اليوم في Dashboard

**الخطوات**:
1. في `CacheService::getDashboardStats`:
   ```php
   'today_new_patients' => Patient::where('clinic_id', $clinicId)
       ->whereDate('created_at', today())
       ->count(),
   ```
2. في `Dashboard.vue`:
   - إضافة كارد جديد في قسم "ملخص اليوم":
   ```vue
   <div class="flex items-center gap-2">
       <Users class="size-4 text-muted-foreground" />
       <span class="text-sm text-muted-foreground">مرضى جدد اليوم</span>
       <span class="text-lg font-bold">{{ chartStats?.today_new_patients ?? 0 }}</span>
   </div>
   ```

---

## المرحلة 3: الذكاء الاصطناعي والميزات المتقدمة

### 3.1 التنبيه الذكي للمواعيد المتأخرة

**الفكرة**: تحليل نمط تأخر الأطباء وإرسال تنبيه استباقي.

**الخطوات**:
1. إنشاء Service: `app/Services/AI/AppointmentAnalyticsService.php`
2. تحليل البيانات:
   - متوسط وقت الوصول الفعلي vs الموعد
   - أيام التأخر المتكرر
   - الأطباء الأكثر تأخراً
3. Command: `php artisan analyze:appointment-patterns` (يعمل يومياً)
4. عرض النتائج في Dashboard كـ "تنبيه ذكي"
5. إرسال إشعار للموظف: "الدكتور X يتأخر عادة 15 دقيقة يوم الأحد — اقترح تعديل المواعيد"

---

### 3.2 اقتراح أوقات شاغرة للمريض

**الفكرة**: عند حجز موعد، اقتراح 3 أوقات قريبة شاغرة للطبيب.

**الخطوات**:
1. إنشاء Service: `app/Services/AI/AvailableSlotsService.php`
2. المنطق:
   - جلب جدول الطبيب من `doctor_schedules`
   - جلب مواعيده الحالية
   - حساب الفجوات (gaps)
   - إرجاع أقرب 3 فجوات
3. في واجهة الحجز (Sheet):
   - بعد اختيار الطبيب → عرض "أوقات مقترحة" أسفل حقل التاريخ
   - زر "اختيار هذا الوقت" يملأ الحقل تلقائياً
4. API endpoint: `GET /appointments/available-slots?doctor_id=X&date=Y`

**مثال للرد**:
```json
{
    "slots": [
        { "start": "2025-04-30 09:00", "end": "09:30" },
        { "start": "2025-04-30 10:30", "end": "11:00" },
        { "start": "2025-04-30 14:00", "end": "14:30" }
    ]
}
```

---

### 3.3 تصنيف الشكاوى تلقائياً

**الفكرة**: تحليل نص الشكوى وتصنيفها للقسم المناسب.

**الخطوات**:
1. إنشاء Service: `app/Services/AI/ComplaintClassifierService.php`
2. استخدام كلمات مفتاحية أو API ذكاء اصطناعي:
   ```php
   $categories = [
       'cardiology' => ['صدر', 'قلب', 'ضيق تنفس', 'خفقان'],
       'orthopedics' => ['عظم', 'كسر', 'مفصل', 'ظهر', 'ركبة'],
       'dermatology' => ['جلد', 'طفح', 'حكة', 'بقعة'],
       'general' => ['حمى', 'صداع', 'غثيان'],
   ];
   ```
3. في صفحة الزيارة أو قائمة الانتظار:
   - حقل "الشكوى الرئيسية"
   - عند الكتابة → تصنيف تلقائي يظهر كـ badge
   - اقتراح القسم/الطبيب المناسب
4. إذا تم استخدام GPT API:
   - إرسال النص → استلام التصنيف
   - تخزين النتيجة مع الزيارة

---

### 3.4 كشف الغياب المتكرر (No-Show Detection)

**الفكرة**: تقرير بالمرضى الذين يتغيبون بنسبة عالية.

**الخطوات**:
1. إنشاء Service: `app/Services/AI/NoShowAnalyticsService.php`
2. المنطق:
   ```php
   $patients = Patient::query()
       ->selectRaw('patient_id, COUNT(*) as total, 
           SUM(CASE WHEN status = "no_show" THEN 1 ELSE 0 END) as no_shows')
       ->from('appointments')
       ->groupBy('patient_id')
       ->havingRaw('no_shows / total > 0.3')
       ->get();
   ```
3. إنشاء Command: `php artisan analyze:no-show-patterns`
4. صفحة تقرير: `/reports/no-show-patients`
5. إجراءات:
   - إرسال تذكير إضافي (SMS/Email) لهؤلاء المرضى
   - إضافة ملاحظة في ملف المريض
   - اقتراح حجز مسبق الدفع

---

### 3.5 مساعد التقرير الطبي (AI Medical Report Assistant)

**الفكرة**: زر يولد مسودة تقرير طبي من البيانات السريرية.

**الخطوات**:
1. إنشاء Service: `app/Services/AI/MedicalReportAssistantService.php`
2. المنطق:
   - جمع: chief_complaint, vital_signs, diagnoses, treatment_plan
   - إرسال لـ GPT API مع prompt محدد
   - إرجاع مسودة تقرير منسقة
3. في صفحة الزيارة (`visits/Index.vue`):
   - زر "🤖 صياغة تقرير أولي"
   - نافذة منبثقة تعرض المسودة
   - زر "نسخ" + زر "تطبيق"
4. **إخلاء مسؤولية واضح**:
   > "هذا التقرير مولّ آلياً ويجب مراجعته من الطبيب قبل اعتماده"
5. تخزين `ai_generated: true` في metadata للتتبع

**مثال Prompt**:
```
أنت مساعد طبي. اكتب تقرير طبي موجز بالعربية بناءً على:
- الشكوى: {chief_complaint}
- العلامات الحيوية: {vitals}
- التشخيص: {diagnosis}
- خطة العلاج: {treatment}

الصيغة: تقرير طبي رسمي مع أقسام واضحة.
```

---

### 3.6 تصدير/استيراد شامل للعيادة

**الخطوات**:
1. إنشاء Command: `php artisan clinic:export`
   - تصدير جميع الجداول كـ JSON
   - ضغط كـ ZIP
   - تخزين في `storage/app/exports/`
2. إنشاء Command: `php artisan clinic:import {file}`
   - فك الضغط
   - قراءة JSON
   - استيراد بترتيب صحيح (departments → doctors → patients → ...)
   - تجاهل المكرر
   - تقرير بالنتائج
3. واجهة Vue:
   - صفحة `/settings/backup`
   - زر "تصدير شامل"
   - زر "استيراد" مع رفع ملف

---

### 3.7 تقييد رؤية الطبيب لجدوله فقط

**المشكلة**: الطبيب يرى كل المواعيد حالياً.

**الخطوات**:
1. في `ListAppointmentsAction`:
   ```php
   if ($user->hasRole('doctor')) {
       $query->where('doctor_id', $user->id);
   }
   ```
2. نفس النمط في:
   - `ListVisitsAction`
   - `QueueEntryController`
3. إضافة badge في Dashboard: "مواعيدك اليوم: X"

---

### 3.8 إشعارات ذكية

**الخطوات**:
1. إنشاء Notification: `AppointmentReminderNotification`
2. جدولة:
   - T-24h: إشعار للمريض (email/SMS)
   - T-2h: إشعار للمريض
   - T-1h: إشعار للطبيب "لديك X مواعيد خلال الساعة القادمة"
3. في `routes/console.php`:
   ```php
   Schedule::command('appointments:send-reminders')->everyThirtyMinutes();
   ```
4. Command: `SendAppointmentReminders` (موجود جزئياً — يحتاج تطوير)

---

## ملخص الأولويات

| الأولوية | المهمة | المرحلة | الجهد التقريبي |
|----------|--------|---------|----------------|
| 🔴 | منع التداخل الزمني | 1 | 4 ساعات |
| 🔴 | جدول دوام الأطباء | 1 | 8 ساعات |
| 🔴 | Soft Delete + Trash | 1 | 6 ساعات |
| 🔴 | صفحة المريض التفصيلية | 2 | 10 ساعات |
| 🟡 | تعريب رسائل الخطأ | 2 | 3 ساعات |
| 🟡 | تصدير متعدد الصيغ | 2 | 6 ساعات |
| 🟡 | معاينة الاستيراد | 2 | 6 ساعات |
| 🟡 | تصنيف الشكاوى | 3 | 8 ساعات |
| 🟡 | كشف No-Show | 3 | 4 ساعات |
| 🟡 | مساعد التقرير الطبي | 3 | 8 ساعات |
| 🟢 | نسخ احتياطي | 1 | 4 ساعات |
| 🟢 | إشعارات ذكية | 3 | 6 ساعات |
