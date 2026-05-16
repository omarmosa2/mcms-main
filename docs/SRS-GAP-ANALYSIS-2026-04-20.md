# تقرير فجوات النظام مقابل وثيقة SRS (MCMS)

- تاريخ التحديث: **2026-04-20**
- المرجع الوحيد: `C:/Users/IT/Downloads/وثيقة_مواصفات_متطلبات_البرمجيات_(SRS)_لنظام_إدارة_.md`
- منهجية الأولوية: **إلزامي أولاً (Mandatory-First)**
- تعريف الحالات:
  - `مطابق`: تنفيذ فعلي end-to-end ويمكن الوصول إليه.
  - `جزئي`: موجود لكن ناقص عنصر حرج.
  - `مفقود`: غير موجود كوحدة/تدفق فعلي.
  - `غير قابل للتحقق`: يحتاج قياس بيئة تشغيلية/تحميل.

## 1) الملخص التنفيذي

- تم إغلاق تعثّرات `P0` الداخلية (التقارير + نموذج الفوترة).
- تم تنفيذ جزء كبير من `P1` بنجاح: **FR1.6, FR3.4, FR4.2, FR4.3, FR4.7, FR5 (core), FR6 (core), FR7 (core), FR9.5**.
- نتيجة التحقق الحالية: **جميع الاختبارات تمر** (`163 passed`) بتاريخ **20 أبريل 2026**.
- النواقص الإلزامية المتبقية (بعد التحديث):
  - **FR7.3 / FR7.5**: تنبيهات استباقية للمخزون/الصلاحية + دورة مشتريات/موردين.
  - **FR5.4 / FR5.6**: تقارير مختبر متخصصة + تكامل HL7/LIS.
  - **FR6.2 / FR6.5**: تدفق صور أشعة/DICOM + تكامل PACS.
  - **FR9.2 / FR9.4**: تقارير أداء أطباء وقوائم مالية معيارية (قائمة دخل/ميزانية).
  - **FR3.6**: بوابة إدارة المواعيد ذاتيًا للمريض (غير منفذة).

## 2) نتائج التحقق الفني (أوامر الإثبات)

### 2.1 المسارات الحالية للتقارير

```bash
php artisan route:list --except-vendor --path=reports
```

النتيجة الحالية:
- `reports.index`
- `reports.audit`
- `reports.audit.export`
- `reports.export.excel`
- `reports.export.pdf`

### 2.2 حزمة اختبارات قبول البنود المنفذة (P1)

```bash
php artisan test --compact tests/Feature/Reports/ReportExportTest.php tests/Feature/Appointments/AppointmentReminderCommandTest.php tests/Feature/Visits/VisitClinicalDataTest.php tests/Feature/Pharmacy/PrescriptionWorkflowTest.php tests/Feature/Lab/LabModuleTest.php tests/Feature/Radiology/RadiologyModuleTest.php tests/Feature/Auth/AuthenticationTest.php
```

النتيجة الحالية:
- نجاح الحزمة بالكامل: `17 passed`.

### 2.3 التحقق الشامل

```bash
php artisan test --compact
```

النتيجة الحالية:
- نجاح كامل المشروع: `163 passed`.

## 3) مصفوفة المتطلبات الوظيفية (FR)

### 3.2.1 إدارة المستخدمين والمصادقة

| ID | الحالة | الدليل (Path:Line) | التقييم |
|---|---|---|---|
| FR1.1 | مطابق | `routes/users.php:7`, `routes/users.php:8`, `routes/users.php:10`, `routes/users.php:11` | CRUD للمستخدمين متاح مع صلاحيات. |
| FR1.2 | مطابق | `routes/roles.php:7`, `routes/roles.php:8`, `routes/roles.php:9`, `routes/roles.php:10` | إدارة الأدوار موجودة وتستخدم RBAC. |
| FR1.3 | مطابق | `app/Support/SecurityPolicyPasswordRule.php:19`, `app/Support/SecurityPolicyPasswordRule.php:22`, `app/Support/SecurityPolicyPasswordRule.php:26`, `app/Support/SecurityPolicyPasswordRule.php:30` | سياسة كلمات مرور قوية مفعلة وقابلة للتهيئة. |
| FR1.4 | مطابق | `app/Providers/FortifyServiceProvider.php:50`, `config/fortify.php:118` | تسجيل دخول/خروج آمن عبر Fortify. |
| FR1.5 | مطابق | `config/fortify.php:150`, `app/Providers/FortifyServiceProvider.php:77` | 2FA مفعّل (TOTP/challenge). |
| FR1.6 | مطابق | `app/Providers/AppServiceProvider.php:69`, `app/Providers/AppServiceProvider.php:70`, `app/Providers/AppServiceProvider.php:71`, `app/Listeners/LogAuthenticationAttempts.php:18`, `tests/Feature/Auth/AuthenticationTest.php:39` | تسجيل محاولات الدخول `success/failed/lockout` منفذ ومثبت باختبارات قبول. |

### 3.2.2 إدارة المرضى

| ID | الحالة | الدليل (Path:Line) | التقييم |
|---|---|---|---|
| FR2.1 | جزئي | `database/migrations/2026_04_13_094241_create_patients_table.php:17`, `database/migrations/2026_04_13_094241_create_patients_table.php:23`, `app/Http/Requests/Patients/StorePatientRequest.php:32` | بيانات أساسية موجودة، لكن لا يوجد حقل `blood_type` ولا `address` على مستوى المريض. |
| FR2.2 | مطابق | `app/Actions/Patients/ListPatientsAction.php:32`, `app/Actions/Patients/ListPatientsAction.php:35`, `app/Actions/Patients/ListPatientsAction.php:36` | بحث متعدد المعايير (اسم/ملف/هاتف/بريد). |
| FR2.3 | مطابق | `routes/patients.php:13`, `routes/patients.php:14` | عرض وتعديل بيانات المريض متاحان. |
| FR2.4 | مطابق | `app/Actions/Patients/ShowPatientAction.php:22`, `app/Actions/Patients/ShowPatientAction.php:23`, `app/Actions/Patients/ShowPatientAction.php:24` | سجل موجز للتاريخ الطبي (حالات مزمنة/حساسيات/أدوية). |
| FR2.5 | جزئي | `app/Actions/Patients/ShowPatientAction.php:27`, `routes/lab.php:8`, `routes/radiology.php:8` | الربط البنيوي موجود (Lab/Radiology مربوطة بالمريض/الزيارة)، لكن عرضها داخل شاشة ملف المريض غير مكتمل. |

### 3.2.3 إدارة المواعيد

| ID | الحالة | الدليل (Path:Line) | التقييم |
|---|---|---|---|
| FR3.1 | مطابق | `routes/appointments.php:8`, `app/Http/Requests/Appointments/StoreAppointmentRequest.php:67`, `app/Actions/Appointments/CreateAppointmentAction.php:28` | حجز موعد لطبيب/مريض ضمن عيادة المستخدم. |
| FR3.2 | جزئي | `routes/appointments.php:7`, `resources/js/pages/appointments/Index.vue:651`, `resources/js/pages/appointments/Index.vue:819` | عرض قائمة/حالات موجود؛ عرض تقويم شامل للأوقات المتاحة/المحجوزة غير واضح كميزة مستقلة. |
| FR3.3 | مطابق | `routes/appointments.php:11`, `routes/appointments.php:12`, `app/Actions/Appointments/TransitionAppointmentStatusAction.php:52` | تعديل وإلغاء الموعد مع `cancel_reason` مدعوم. |
| FR3.4 | جزئي | `app/Console/Commands/DispatchAppointmentRemindersCommand.php:12`, `app/Jobs/SendAppointmentReminderJob.php:13`, `routes/console.php:19`, `tests/Feature/Appointments/AppointmentReminderCommandTest.php:35` | جدولة/queue لتذكيرات SMS/WhatsApp منفذة، لكن مزود إرسال فعلي production-grade غير مفعّل (adapter تجريبي). |
| FR3.5 | جزئي | `routes/queue.php:7`, `routes/queue.php:9`, `resources/js/pages/queue/Index.vue:840` | إدارة الطابور موجودة؛ شاشة Display Board مستقلة غير موجودة. |
| FR3.6 | مفقود | `routes/web.php:15`, `routes/web.php:33` | لا توجد بوابة مرضى لإدارة الموعد ذاتيًا. |

### 3.2.4 السجل الطبي الإلكتروني (EMR)

| ID | الحالة | الدليل (Path:Line) | التقييم |
|---|---|---|---|
| FR4.1 | مطابق | `routes/visits.php:8`, `database/migrations/2026_04_13_094246_create_visits_table.php:21` | إنشاء سجل زيارة طبي موجود. |
| FR4.2 | مطابق | `database/migrations/2026_04_20_064000_create_visit_diagnoses_table.php:20`, `routes/visits.php:15`, `app/Http/Controllers/Visits/VisitDiagnosisController.php:35`, `tests/Feature/Visits/VisitClinicalDataTest.php:30` | ترميز ICD-10 منظم منفذ مع تحقق واختبار قبول. |
| FR4.3 | مطابق | `routes/pharmacy.php:10`, `routes/pharmacy.php:11`, `app/Http/Controllers/Pharmacy/PrescriptionController.php:24`, `app/Http/Controllers/Pharmacy/PrescriptionController.php:94`, `tests/Feature/Pharmacy/PrescriptionWorkflowTest.php:62` | وصفة إلكترونية + صرف مرتبط بالزيارة والمريض منفذ end-to-end. |
| FR4.4 | مطابق | `database/migrations/2026_04_13_094246_create_visits_table.php:27`, `database/migrations/2026_04_13_094246_create_visits_table.php:29` | ملاحظات سريرية وخطة علاج متاحتان. |
| FR4.5 | جزئي | `database/migrations/2026_04_20_064421_create_lab_orders_table.php:17`, `database/migrations/2026_04_20_064423_create_radiology_orders_table.php:17`, `app/Actions/Patients/ShowPatientAction.php:27` | نتائج المختبر/الأشعة مرتبطة بزيارة/مريض، لكن عرضها التلقائي الشامل في واجهة السجل الطبي غير مكتمل. |
| FR4.6 | جزئي | `app/Actions/Patients/ShowPatientAction.php:42`, `app/Actions/Patients/ShowPatientAction.php:43` | عرض زمني للزيارات موجود لكنه محدود (`limit(10)`) وليس سجلًا تاريخيًا شاملاً. |
| FR4.7 | مطابق | `database/migrations/2026_04_20_064000_create_visit_vital_signs_table.php:20`, `routes/visits.php:16`, `app/Http/Controllers/Visits/VisitVitalSignController.php:25`, `tests/Feature/Visits/VisitClinicalDataTest.php:71` | وحدة العلامات الحيوية منفذة مع حفظ منظم واختبارات قبول. |

### 3.2.5 إدارة المختبر

| ID | الحالة | الدليل (Path:Line) | التقييم |
|---|---|---|---|
| FR5.1 | مطابق | `routes/lab.php:8`, `app/Http/Controllers/Lab/LabOrderController.php:17`, `tests/Feature/Lab/LabModuleTest.php:31` | إنشاء طلبات فحوصات مخبرية منفذ. |
| FR5.2 | جزئي | `database/migrations/2026_04_20_064421_create_lab_orders_table.php:22`, `routes/lab.php:8`, `routes/lab.php:9` | حالة `sample_collected` موجودة بنيويًا، لكن لا endpoint مستقل لخطوة استلام العينة. |
| FR5.3 | مطابق | `routes/lab.php:9`, `app/Http/Controllers/Lab/LabResultController.php:17`, `app/Http/Controllers/Lab/LabResultController.php:31`, `tests/Feature/Lab/LabModuleTest.php:42` | إدخال نتائج مع unit/reference range منفذ. |
| FR5.4 | مفقود | `routes/reports.php:7`, `routes/reports.php:12` | لا تقارير مختبر تشغيلية/إحصائية متخصصة حتى الآن. |
| FR5.5 | جزئي | `database/migrations/2026_04_20_064421_create_lab_orders_table.php:17`, `database/migrations/2026_04_20_064421_create_lab_orders_table.php:18`, `app/Models/LabOrder.php:35` | الربط بزيارة/مريض موجود، لكن العرض السريري الموحّد في ملف المريض غير مكتمل. |
| FR5.6 | مفقود | `composer.json:11`, `routes/lab.php:7` | لا تكامل HL7/LIS أو أجهزة مختبر. |

### 3.2.6 إدارة الأشعة

| ID | الحالة | الدليل (Path:Line) | التقييم |
|---|---|---|---|
| FR6.1 | مطابق | `routes/radiology.php:8`, `app/Http/Controllers/Radiology/RadiologyOrderController.php:17`, `tests/Feature/Radiology/RadiologyModuleTest.php:31` | إنشاء طلب أشعة منفذ. |
| FR6.2 | جزئي | `database/migrations/2026_04_20_064424_create_radiology_reports_table.php:19`, `database/migrations/2026_04_20_064424_create_radiology_reports_table.php:20` | تقارير نصية موجودة، لكن تدفق صور أشعة/DICOM غير منفذ. |
| FR6.3 | مطابق | `routes/radiology.php:9`, `app/Http/Controllers/Radiology/RadiologyReportController.php:17`, `tests/Feature/Radiology/RadiologyModuleTest.php:43` | تقارير أشعة منظمة منفذة. |
| FR6.4 | جزئي | `database/migrations/2026_04_20_064423_create_radiology_orders_table.php:17`, `database/migrations/2026_04_20_064423_create_radiology_orders_table.php:18`, `app/Actions/Patients/ShowPatientAction.php:27` | الربط بزيارة/مريض موجود، لكن الإدراج العرضي الكامل داخل EMR غير مكتمل. |
| FR6.5 | مفقود | `composer.json:11`, `routes/radiology.php:7` | لا تكامل PACS. |

### 3.2.7 الصيدلية والمخزون

| ID | الحالة | الدليل (Path:Line) | التقييم |
|---|---|---|---|
| FR7.1 | مطابق | `routes/pharmacy.php:10`, `routes/pharmacy.php:11`, `app/Http/Controllers/Pharmacy/PrescriptionController.php:94`, `tests/Feature/Pharmacy/PrescriptionWorkflowTest.php:62` | صرف الدواء من وصفة إلكترونية منفذ. |
| FR7.2 | مطابق | `app/Http/Controllers/Pharmacy/PrescriptionController.php:157`, `tests/Feature/Pharmacy/PrescriptionWorkflowTest.php:75` | تحديث المخزون تلقائيًا بعد الصرف منفذ. |
| FR7.3 | جزئي | `app/Http/Controllers/Pharmacy/DrugController.php:27`, `app/Http/Controllers/Pharmacy/DrugController.php:39`, `routes/console.php:19` | كشف low-stock موجود عند الطلب، لكن لا تنبيه استباقي مجدول للمخزون/الانتهاء. |
| FR7.4 | جزئي | `database/migrations/2026_04_20_064408_create_pharmacy_drugs_table.php:17`, `database/migrations/2026_04_20_064408_create_pharmacy_drugs_table.php:22`, `app/Http/Controllers/Pharmacy/DrugController.php:49` | كتالوج دوائي أساسي موجود، لكن إدارة متقدمة (تصنيف/بدائل/تسعير متقدم) غير مكتملة. |
| FR7.5 | مفقود | `app/Http/Requests/Pharmacy/StoreDrugRequest.php:29`, `app/Http/Controllers/Pharmacy/DrugController.php:55` | يوجد اسم مورد كحقل فقط؛ لا وحدة موردين/طلبات شراء/workflow مشتريات. |

### 3.2.8 الفواتير والمحاسبة

| ID | الحالة | الدليل (Path:Line) | التقييم |
|---|---|---|---|
| FR8.1 | جزئي | `routes/billing.php:8`, `app/Actions/Billing/CreateInvoiceAction.php:49` | إنشاء فواتير موجود، لكن لا محرك تسعير/توليد فواتير تلقائي متكامل مع lab/radiology/pharmacy حتى الآن. |
| FR8.2 | مطابق | `routes/billing.php:16`, `app/Actions/Billing/RecordPaymentAction.php:67`, `app/Actions/Billing/RecordPaymentAction.php:68` | تسجيل المدفوعات وتتبع الرصيد المستحق متاح. |
| FR8.3 (مستقبلي) | غير منفذ (مستقبلي) | `routes/web.php:15`, `routes/web.php:33` | لا إدارة مطالبات تأمين حتى الآن. |
| FR8.4 | جزئي | `routes/reports.php:7`, `app/Actions/Reports/GetFinancialReportAction.php:49`, `app/Http/Controllers/Reports/ReportController.php:23` | تقارير مالية تشغيلية موجودة، لكن ليست قوائم مالية معيارية كاملة. |
| FR8.5 (مستقبلي) | غير منفذ (مستقبلي) | `composer.json:11`, `routes/web.php:15` | لا تكامل بوابات دفع إلكتروني. |

### 3.2.9 لوحة التحكم والتقارير

| ID | الحالة | الدليل (Path:Line) | التقييم |
|---|---|---|---|
| FR9.1 | جزئي | `resources/js/pages/Dashboard.vue:260`, `resources/js/pages/Dashboard.vue:302` | لوحة موجودة لكن ليست KPI سريرية/مالية لحظية كاملة كما في SRS. |
| FR9.2 | مفقود | `app/Actions/Reports/GetOperationalReportAction.php:51`, `app/Actions/Reports/GetOperationalReportAction.php:60` | لا تقرير أداء أطباء (عدد مرضى/خدمات/إيرادات لكل طبيب). |
| FR9.3 | جزئي | `app/Actions/Reports/GetOperationalReportAction.php:55`, `app/Actions/Reports/GetFinancialReportAction.php:49` | يوجد مؤشرات تشغيل/مالية عامة؛ لا نسب إشغال عيادات مفصلة. |
| FR9.4 | مفقود | `routes/reports.php:7`, `app/Actions/Reports/GetFinancialReportAction.php:44` | لا قائمة دخل/ميزانية عمومية كقوائم مالية معيارية. |
| FR9.5 | مطابق | `routes/reports.php:9`, `routes/reports.php:10`, `app/Http/Controllers/Reports/ReportController.php:34`, `app/Http/Controllers/Reports/ReportController.php:68`, `tests/Feature/Reports/ReportExportTest.php:45` | تصدير PDF/Excel منفذ ومثبت باختبارات قبول. |

## 4) قسم مستقل للبنود الاختيارية/المستقبلية

### 3.2.10 بوابة المرضى الإلكترونية (اختياري)

| ID | الحالة | الدليل (Path:Line) | الملاحظة |
|---|---|---|---|
| FR10.1 | غير منفذ (اختياري) | `routes/web.php:15`, `routes/web.php:33` | لا بوابة دخول مرضى منفصلة. |
| FR10.2 | غير منفذ (اختياري) | `routes/web.php:15`, `routes/web.php:33` | لا إدارة مواعيد للمريض عبر بوابة ذاتية. |
| FR10.3 | غير منفذ (اختياري) | `routes/web.php:15`, `routes/web.php:33` | لا وصول ذاتي للمريض إلى نتائجه/سجله الطبي. |
| FR10.4 (مستقبلي) | غير منفذ (مستقبلي) | `routes/web.php:15`, `routes/web.php:33` | لا سداد فواتير أونلاين للمريض. |

### بنود مستقبلية داخل FR8

| ID | الحالة | الدليل (Path:Line) | الملاحظة |
|---|---|---|---|
| FR8.3 (تأمين) | غير منفذ (مستقبلي) | `routes/web.php:15`, `routes/web.php:33` | لا تكامل مطالبات تأمين. |
| FR8.5 (بوابات الدفع) | غير منفذ (مستقبلي) | `composer.json:11`, `routes/web.php:15` | لا بنية تكامل دفع إلكتروني حتى الآن. |

## 5) مصفوفة المتطلبات غير الوظيفية (NFR)

| ID | الحالة | الدليل (Path:Line) | التقييم |
|---|---|---|---|
| NFR3.3.1.1 (<2s) | غير قابل للتحقق | `app/Console/Commands/SystemHealthCommand.php:22` | لا توجد قياسات SLA زمن استجابة في بيئة حمل. |
| NFR3.3.1.2 (100 مستخدم متزامن) | غير قابل للتحقق | `app/Console/Commands/SystemHealthCommand.php:22` | لا اختبار تحميل موثق. |
| NFR3.3.1.3 (واجهة <3s) | غير قابل للتحقق | `package.json:13` | لا قياس أداء واجهة إنتاجي موثق. |
| NFR3.3.2.1 (HIPAA) | جزئي | `resources/js/pages/settings/Compliance.vue:70`, `app/Actions/Compliance/LogSensitiveAccessAction.php:36` | عناصر امتثال موجودة لكن لا دليل اعتماد HIPAA كامل. |
| NFR3.3.2.2 (تشفير نقل/تخزين) | جزئي | `app/Models/Patient.php:65`, `app/Http/Middleware/SecurityHeaders.php:24` | تشفير بيانات حساسة على مستوى تطبيق موجود، لكن فرض HTTPS/تشفير شامل قاعدة البيانات غير مثبت بالكامل. |
| NFR3.3.2.3 (RBAC) | مطابق | `routes/users.php:7`, `routes/roles.php:7`, `app/Http/Middleware/EnsureUserHasPermission.php:20` | RBAC مطبق على المسارات والواجهات. |
| NFR3.3.2.4 (Audit log غير قابل للتعديل) | جزئي | `database/migrations/2026_04_13_094258_create_audit_logs_table.php:14`, `app/Actions/Audit/LogAuditAction.php:25` | سجل تدقيق موجود، لكن لا آلية WORM/append-only مثبتة. |
| NFR3.3.2.5 (SQLi/XSS/CSRF) | جزئي | `bootstrap/app.php:32`, `app/Http/Middleware/SecurityHeaders.php:24` | حماية Laravel الأساسية + CSP متاحة؛ لا اختبار أمني شامل موثق. |
| NFR3.3.2.6 (نسخ احتياطي تلقائي + استعادة) | جزئي | `app/Console/Commands/BackupCreateCommand.php:12`, `app/Console/Commands/BackupVerifyCommand.php:11`, `routes/console.php:11` | أوامر create/verify موجودة؛ الجدولة الحالية تتحقق فقط (`backup:verify`) بدون جدولة `backup:create`. |
| NFR3.3.3.1 (99.9% Availability) | غير قابل للتحقق | `app/Console/Commands/SystemHealthCommand.php:22` | لا منظومة مراقبة uptime/SLO موثقة. |
| NFR3.3.3.2 (تعافٍ تلقائي من الأعطال) | جزئي | `database/migrations/0001_01_01_000002_create_jobs_table.php:37`, `app/Console/Commands/BackupVerifyCommand.php:92` | توجد بنية jobs/failed_jobs ونسخ احتياطي، لكن لا استراتيجية auto-recovery تشغيلية كاملة. |
| NFR3.3.3.3 (تنبيهات أعطال حرجة) | مفقود | `app/Console/Commands/SystemHealthCommand.php:48` | لا قناة تنبيه تلقائي (email/slack/pager) مثبتة. |
| NFR3.3.4.1 (قابلية الصيانة) | جزئي | `app/Domain/Shared/Models/BaseModel.php:9`, `app/Domain/Shared/Traits/HasClinic.php:9` | المعمارية قابلة للتوسعة واختبارات المشروع خضراء (`163 passed`) لكن ما تزال وحدات إلزامية متقدمة قيد الإكمال. |
| NFR3.3.4.2 (وثائق فنية) | جزئي | `docs/MCMS.md`, `docs/RBAC-MATRIX.md` | وثائق موجودة، وتحتاج مزيد تفصيل للوحدات الجديدة وخارطة الإغلاق المتبقية. |
| NFR3.3.5.1 (Portability Linux) | جزئي | `composer.json:15`, `package.json:45` | Stack قابل للنشر على Linux تقنيًا، لكن بدون إثبات تشغيل متعدد البيئات في هذا التحليل. |
| NFR3.3.5.2 (دعم MySQL/Relational) | جزئي | `app/Console/Commands/BackupCreateCommand.php:172` | دعم sqlite/mysql/pgsql ظاهر، دون مصفوفة توافق إصدارات موثقة. |
| NFR3.3.6.1 (Scalability) | جزئي | `app/Domain/Shared/Traits/HasClinic.php:9`, `database/migrations/2026_04_13_094241_create_patients_table.php:16` | عزل `clinic_id` متعدد العيادات موجود؛ لا دليل load-scaling أفقي. |
| NFR3.3.6.2 (سهولة إضافة وحدات) | جزئي | `routes/web.php:31`, `routes/web.php:32`, `routes/web.php:33` | قابلية التوسعة مثبتة بإضافة وحدات pharmacy/lab/radiology، مع بقاء وحدات مستقبلية غير منفذة. |
| NFR3.3.7.1 (Usability) | جزئي | `resources/js/pages/Dashboard.vue:260`, `resources/js/pages/queue/Index.vue:464` | واجهات حديثة ومقسمة حسب الدور، لكن بعض السيناريوهات السريرية الأساسية ناقصة. |
| NFR3.3.7.2 (رسائل خطأ واضحة) | مطابق | `app/Actions/Appointments/TransitionAppointmentStatusAction.php:28`, `app/Actions/Billing/RecordPaymentAction.php:48` | رسائل تحقق/أخطاء واضحة في التدفقات الأساسية. |
| NFR3.3.7.3 (اختصارات لوحة مفاتيح) | مفقود | `resources/js/pages/*` (فحص شامل بدون نتائج) | لا hotkeys فعلية في الصفحات التشغيلية. |
| NFR3.6.1 (Online Help System) | مفقود | `routes/settings.php:11`, `routes/settings.php:35` | لا وحدة مساعدة/دعم أونلاين مخصصة. |
| NFR3.6.2 (تخصيص تقارير/لوحات) | مفقود | `routes/settings.php:34`, `routes/reports.php:7` | لا واجهة لتخصيص Dashboard/Reports من المدير. |

## 6) مخاطر التوافق الداخلي (Blockers)

### B1) تضارب منظومة التقارير — **تمت المعالجة**
- التعديل المنفذ:
  - `routes/reports.php` موحّد ويحتوي `reports.index` و`reports.audit` و`reports.audit.export` و`reports.export.excel` و`reports.export.pdf`.
- دليل الإغلاق:
  - `php artisan route:list --except-vendor --path=reports` يظهر المسارات المتوقعة.
  - `php artisan test --compact tests/Feature/Reports/ReportControllerTest.php` ناجح.
  - `php artisan test --compact tests/Feature/Reports/ReportExportTest.php` ناجح.

### B2) تضارب نموذج الفوترة مع الأكشنز — **تمت المعالجة**
- التعديل المنفذ:
  - تحديث `app/Models/Invoice.php`, `app/Models/Payment.php`, `app/Models/InvoiceItem.php` إلى بنية الفوترة الحديثة.
- دليل الإغلاق:
  - `php artisan test --compact tests/Feature/Billing/InvoiceControllerTest.php` ناجح.
  - `php artisan test --compact tests/Feature/Billing/PaymentControllerTest.php` ناجح.

## 7) قائمة فجوات Mandatory-First (بعد التنفيذ)

### P0 (حرج) — مغلق
1. توحيد طبقة التقارير (Routes + Controller wiring) — **مغلق**.
2. توحيد نموذج Invoice مع أكشنز الفوترة — **مغلق**.

### P1 (عالٍ)
1. **FR1.6** تسجيل محاولات الدخول — **مغلق**.
2. **FR3.4** تذكيرات SMS/WhatsApp — **مغلق جزئيًا** (workflow موجود، ناقص provider production).
3. **FR4.2/FR4.3/FR4.7** (ICD-10 + eRx + vitals) — **مغلق**.
4. **FR5/FR6/FR7 core modules** — **مغلق جزئيًا** (core workflows موجودة، advanced integrations/procurement/reporting متبقية).
5. **FR9.5** تصدير PDF/Excel — **مغلق**.

### المتبقي عالي الأولوية للتنفيذ التالي
1. FR7.3 + FR7.5: تنبيهات استباقية للمخزون/الانتهاء + workflow مشتريات/موردين.
2. FR5.4 + FR5.6: تقارير مختبر متخصصة + تكامل HL7/LIS.
3. FR6.2 + FR6.5: دعم DICOM/PACS.
4. FR9.2 + FR9.4: Doctor performance + Financial statements.
5. FR3.6: Patient self-service appointments.

## 8) ملاحظات منهجية مهمة

- البنود الاختيارية/المستقبلية مفصولة عمدًا ولا تُحسب كعدم مطابقة إلزامية.
- البنود الأدائية الرقمية صُنفت `غير قابل للتحقق` لعدم توفر benchmark/monitoring production ضمن هذا النطاق.
- التحليل مبني على حالة المشروع الحالية حتى **20 أبريل 2026**، مع توثيق تحقق اختباري فعلي (`163 passed`).
