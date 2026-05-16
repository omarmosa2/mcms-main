# MCMS — سياق المشروع (Project Context)

> هذا الملف يحفظ كل المعلومات المهمة عن النظام حتى يمكن استئناف العمل من أي نافذة أو جلسة جديدة.

---

## معلومات النظام

- **المسار**: `C:\Users\IT\Desktop\mcms`
- **الإطار**: Laravel 13 (PHP 8.4)
- **الواجهة**: Inertia.js v3 + Vue 3 + Tailwind CSS v4
- **قاعدة البيانات**: SQLite (`database/database.sqlite`)
- **المصادقة**: Laravel Fortify v1
- **الاختبارات**: PHPUnit v12
- **التنسيق**: Laravel Pint
- **اللغة الافتراضية**: العربية (RTL)
- **نوع التطبيق**: Multi-Tenant (كل مستخدم يتبع clinic_id)

---

## البنية المعمارية

### نمط التصميم
- **Actions Pattern**: كل عملية لها Action class في `app/Actions/`
- **Form Requests**: التحقق في `app/Http/Requests/`
- **API Resources**: تحويل البيانات في `app/Http/Resources/`
- **Domain-Driven**: `app/Domain/` يحتوي على منطق المجال
- **Services**: `app/Services/` للخدمات المشتركة
- **Jobs**: `app/Jobs/` للعمليات غير المتزامنة

### نموذج Base Model
```php
abstract class BaseModel extends Model {
    use HasClinic;
    protected $guarded = [];
}
```
⚠️ **ملاحظة مهمة**: لا يوجد `SoftDeletes` حالياً — الحذف نهائي (Hard Delete).

---

## الجداول الرئيسية

### الأساسية
| الجدول | الوصف | مفاتيح خارجية |
|--------|-------|---------------|
| `users` | المستخدمون | clinic_id |
| `clinics` | العيادات | - |
| `roles` | الأدوار | clinic_id |
| `permissions` | الصلاحيات | clinic_id |
| `role_user` | ربط مستخدم-دور | - |
| `permission_role` | ربط دور-صلاحية | - |

### المرضى والطبية
| الجدول | الوصف | مفاتيح خارجية |
|--------|-------|---------------|
| `patients` | بيانات المرضى | clinic_id |
| `patient_allergies` | حساسيات | patient_id, clinic_id |
| `patient_medications` | أدوية مزمنة | patient_id, clinic_id |
| `patient_chronic_conditions` | أمراض مزمنة | patient_id, clinic_id |
| `patient_attachments` | مرفقات | patient_id, clinic_id |

### المواعيد والزيارات
| الجدول | الوصف | مفاتيح خارجية |
|--------|-------|---------------|
| `appointments` | المواعيد | clinic_id, patient_id, doctor_id→users |
| `queue_entries` | قائمة الانتظار | clinic_id, patient_id, appointment_id, assigned_doctor_id |
| `visits` | الزيارات السريرية | clinic_id, patient_id, doctor_id, appointment_id, queue_entry_id |
| `visit_vital_signs` | العلامات الحيوية | visit_id |
| `visit_diagnoses` | التشخيصات | visit_id |

### المالية
| الجدول | الوصف | مفاتيح خارجية |
|--------|-------|---------------|
| `invoices` | الفواتير | clinic_id, patient_id, visit_id, appointment_id, issued_by→users |
| `invoice_items` | بنود الفاتورة | clinic_id, invoice_id |
| `payments` | المدفوعات | clinic_id, invoice_id, received_by→users |
| `payment_plans` | خطط الدفع | clinic_id, invoice_id |
| `installments` | الأقساط | clinic_id, payment_plan_id |
| `expenses` | المصروفات | clinic_id, category_id |
| `expense_categories` | أقسام المصروفات | clinic_id |
| `cashboxes` | الصناديق النقدية | clinic_id |
| `salaries` | الرواتب | clinic_id |
| `accounts` | الحسابات المحاسبية | clinic_id |
| `journal_entries` | القيود المحاسبية | clinic_id |
| `journal_entry_lines` | بنود القيود | journal_entry_id |

### الصيدلية والمختبر والأشعة
| الجدول | الوصف | مفاتيح خارجية |
|--------|-------|---------------|
| `pharmacy_drugs` | الأدوية | clinic_id |
| `drug_batches` | دفعات الأدوية | clinic_id, drug_id |
| `prescriptions` | الوصفات | clinic_id, patient_id, visit_id, doctor_id |
| `prescription_items` | بنود الوصفة | prescription_id |
| `pharmacy_dispenses` | صرف الأدوية | clinic_id, prescription_id |
| `pharmacy_dispense_items` | بنود الصرف | dispense_id |
| `lab_orders` | طلبات المختبر | clinic_id, patient_id, visit_id |
| `lab_results` | نتائج المختبر | lab_order_id |
| `lab_test_templates` | قوالب الفحوصات | clinic_id |
| `radiology_orders` | طلبات الأشعة | clinic_id, patient_id, visit_id |
| `radiology_reports` | تقارير الأشعة | radiology_order_id |
| `radiology_images` | صور الأشعة | radiology_order_id |
| `radiology_study_types` | أنواع الدراسات | clinic_id |

### المخزون والموردين
| الجدول | الوصف | مفاتيح خارجية |
|--------|-------|---------------|
| `suppliers` | الموردون | clinic_id |
| `purchase_orders` | طلبات الشراء | clinic_id, supplier_id |
| `purchase_order_items` | بنود الشراء | purchase_order_id |
| `stock_adjustments` | تسويات المخزون | clinic_id |
| `inventory_returns` | مرتجعات المخزون | clinic_id |
| `inventory_alerts` | تنبيهات المخزون | clinic_id |

### الأقسام والأطباء
| الجدول | الوصف | مفاتيح خارجية |
|--------|-------|---------------|
| `departments` | الأقسام | clinic_id |
| `doctor_profiles` | ملفات الأطباء | clinic_id, user_id |

### النظام والتدقيق
| الجدول | الوصف | مفاتيح خارجية |
|--------|-------|---------------|
| `audit_logs` | سجل النشاطات | clinic_id, user_id, auditable (polymorphic) |
| `security_policies` | سياسات الأمان | clinic_id |
| `sensitive_access_logs` | سجلات الوصول الحساس | clinic_id, user_id |
| `compliance_runs` | عمليات الامتثال | clinic_id |
| `auth_attempt_logs` | سجل محاولات الدخول | - |
| `user_invitations` | دعوات المستخدمين | clinic_id |
| `branding_settings` | إعدادات العلامة | clinic_id |
| `workflows` | سير العمل | clinic_id |
| `workflow_steps` | خطوات سير العمل | workflow_id |
| `workflow_instances` | حالات سير العمل | clinic_id, workflow_id |
| `workflow_approvals` | الموافقات | workflow_instance_id |
| `idempotency_records` | سجلات منع التكرار | clinic_id |
| `number_ranges` | نطاقات الأرقام | clinic_id |
| `queue_number_seq` | تسلسل أرقام الانتظار | clinic_id |
| `appointment_reminders` | تذكيرات المواعيد | appointment_id |
| `external_integrations` | التكاملات الخارجية | clinic_id |
| `patient_portal_tokens` | رموز بوابة المريض | clinic_id, patient_id |

---

## حالات المواعيد (Appointment Statuses)

```
scheduled → confirmed → arrived → completed
                              ↓
                         canceled / no_show
```

**TERMINAL_STATUSES**: `completed`, `canceled`, `no_show` (لا يمكن تعديلها)

---

## حالات الفواتير (Invoice Statuses)

```
draft → issued → partially_paid → paid
```

---

## حالات الزيارات (Visit Statuses)

```
started → in_progress → completed
```

---

## حالات قائمة الانتظار (Queue Statuses)

```
waiting → called → in_service → completed
                          ↓
                     skipped / canceled
```

---

## الأدوار والصلاحيات

### الأدوار المعرفة
- `super_admin` — وصول كامل
- `admin` — إدارة العمليات
- `clinic_admin` — إدارة العيادة
- `doctor` — رؤية جدول المواعيد فقط
- `receptionist` — إضافة/تعديل المواعيد والمرضى
- `accountant` — الفواتير والمدفوعات

### الصلاحيات (نمط التسمية)
- `patient.view`, `patient.create`, `patient.update`, `patient.delete`
- `appointment.view`, `appointment.create`, `appointment.update`, `appointment.delete`, `appointment.arrival`
- `billing.view`, `billing.generate`
- `payment.record`, `payment.refund`
- `visit.start`, `visit.update`, `visit.complete`
- `queue.view`, `queue.manage`
- `reports.view`, `reports.financial`

---

## الملفات الرئيسية

### Controllers
```
app/Http/Controllers/
├── DashboardController.php
├── Appointments/AppointmentController.php
├── Patients/PatientController.php
├── Patients/PatientImportExportController.php
├── Billing/InvoiceController.php
├── Billing/PaymentController.php
├── Visits/VisitController.php
├── Queue/QueueEntryController.php
├── Doctors/DoctorProfileController.php
├── Departments/DepartmentController.php
├── Rbac/RoleController.php
├── Security/UserController.php
├── Reports/ReportController.php
├── Reports/AuditReportController.php
├── Accounting/*.php
├── Cashbox/CashboxController.php
├── Expenses/ExpenseController.php
├── Salaries/SalaryController.php
├── Pharmacy/*.php
├── Lab/*.php
├── Radiology/*.php
├── Inventory/InventoryController.php
├── Financial/*.php
├── Portal/PatientPortalController.php
├── Settings/*.php
└── Diagnostics/DiagnosticsController.php
```

### Actions (مختارة)
```
app/Actions/
├── Appointments/
│   ├── CreateAppointmentAction.php
│   ├── UpdateAppointmentAction.php
│   ├── DeleteAppointmentAction.php
│   ├── ListAppointmentsAction.php
│   ├── ShowAppointmentAction.php
│   └── TransitionAppointmentStatusAction.php
├── Patients/
│   ├── CreatePatientAction.php
│   ├── UpdatePatientAction.php
│   ├── DeletePatientAction.php
│   ├── ListPatientsAction.php
│   └── ShowPatientAction.php
├── Billing/
│   ├── CreateInvoiceAction.php
│   ├── UpdateInvoiceAction.php
│   ├── IssueInvoiceAction.php
│   ├── DeleteInvoiceAction.php
│   ├── ListInvoicesAction.php
│   └── ShowInvoiceAction.php
├── Payments/
│   ├── RecordPaymentAction.php
│   └── RefundPaymentAction.php
└── Audit/
    └── LogAuditAction.php
```

### Frontend Pages
```
resources/js/pages/
├── Dashboard.vue
├── appointments/Index.vue
├── patients/Index.vue
├── patients/Import.vue
├── billing/Index.vue
├── visits/Index.vue
├── queue/Index.vue
├── doctors/Index.vue
├── departments/Index.vue
├── reports/Index.vue
├── reports/Audit.vue
├── settings/
│   ├── Profile.vue
│   ├── Security.vue
│   ├── Notifications.vue
│   ├── Appearance.vue
│   └── Compliance.vue
├── Accounting/
│   └── Reports/Index.vue
├── pharmacy/
│   ├── Drugs/Index.vue
│   └── Prescriptions/Index.vue
├── lab/
│   ├── Orders/Index.vue
│   └── Results/Index.vue
├── radiology/
│   ├── Orders/Index.vue
│   └── Reports/Index.vue
├── inventory/
│   ├── Batches/Index.vue
│   ├── Adjustments/Index.vue
│   └── Returns/Index.vue
├── financial/
│   ├── PaymentPlans/Index.vue
│   └── Installments/Index.vue
├── cashbox/Index.vue
├── expenses/Index.vue
├── salaries/Index.vue
├── roles/Index.vue
├── users/Index.vue
├── portal/Index.vue
└── help/
    ├── Index.vue
    └── Article.vue
```

### Routes
```
routes/
├── web.php              ← نقطة الدخول الرئيسية
├── appointments.php
├── patients.php
├── billing.php
├── visits.php
├── queue.php
├── doctors.php
├── departments.php
├── invoices.php
├── accounts.php
├── roles.php
├── users.php
├── salaries.php
├── cashbox.php
├── expenses.php
├── reports.php
├── pharmacy.php
├── lab.php
├── radiology.php
├── inventory.php
├── financial.php
├── portal.php
├── settings.php
├── notifications.php
├── help.php
├── diagnostics.php
├── monitoring.php
├── api-docs.php
└── console.php
```

---

## الأوامر المتاحة

### تشغيل التطوير
```bash
composer run dev        # تشغيل Vite + PHP server + Pail
npm run dev             # Vite dev server فقط
npm run build           # بناء الإنتاج
php artisan serve       # PHP server
```

### الاختبارات
```bash
php artisan test --compact                          # كل الاختبارات
php artisan test --compact tests/Feature/ExampleTest.php  # ملف واحد
php artisan test --compact --filter=testName        # فلتر بالاسم
```

### التنسيق
```bash
vendor/bin/pint --format agent    # تنسيق كل الملفات
vendor/bin/pint --dirty --format agent  # الملفات المعدلة فقط
```

### Wayfinder (توليد TypeScript)
```bash
php artisan wayfinder:generate    # توليد دوال المسارات
```

---

## نقاط القوة الحالية

✅ بنية Actions/Services نظيفة وقابلة للاختبار
✅ RBAC كامل مع middleware على كل route
✅ Audit Log شامل (old_values, new_values, IP, user_agent)
✅ Idempotency في المدفوعات (منع التكرار)
✅ Multi-tenant بـ clinic_id في كل جدول
✅ Foreign Keys مع cascade/nullOnDelete
✅ Validation في Form Requests
✅ Inertia v3 مع deferred props
✅ Tailwind v4 مع تصميم RTL عربي
✅ Dashboard شامل مع رسوم بيانية
✅ استيراد/تصدير للمرضى (Excel)
✅ Queue system للعمليات الثقيلة
✅ حساسية البيانات: national_id مشفر + hash

---

## نقاط الضعف الحالية

❌ لا يوجد Soft Delete — الحذف نهائي
❌ لا يوجد فحص تداخل المواعيد (double booking)
❌ لا يوجد جدول دوام الأطباء
❌ لا يوجد صفحة تفصيلية للمريض
❌ لا يوجد تصدير CSV أو PDF
❌ لا يوجد معاينة قبل الاستيراد
❌ لا يوجد نسخ احتياطي
❌ لا يوجد سلة محذوفات
❌ رسائل الخطأ بالإنجليزية والنظام بالعربي
❌ رقم الهاتف بدون حد أدنى
❌ الطبيب يرى كل المواعيد وليس مواعيده فقط
❌ لا يوجد ذكاء اصطناعي
❌ لا يوجد إشعارات تذكير بالمواعيد
❌ لا يوجد تتبع No-Show Rate

---

## خطة التطوير (ملخص)

### المرحلة 1: إصلاح الأخطاء المنطقية
1. منع التداخل الزمني
2. جدول دوام الأطباء
3. Soft Delete
4. سلة المحذوفات
5. تقييد حذف الفواتير
6. تحقق رقم الهاتف
7. نسخ احتياطي

### المرحلة 2: تحسينات الواجهات
1. صفحة المريض التفصيلية
2. تخصيص رسائل الحذف
3. تعريب رسائل الخطأ
4. تصدير متعدد الصيغ
5. معاينة الاستيراد
6. تقييد حجم الملفات
7. تحسين الجدول على الموبايل
8. عرض المرضى الجدد اليوم

### المرحلة 3: الذكاء الاصطناعي والميزات المتقدمة
1. التنبيه الذكي للتأخر
2. اقتراح أوقات شاغرة
3. تصنيف الشكاوى
4. كشف No-Show
5. مساعد التقرير الطبي
6. تصدير/استيراد شامل
7. تقييد رؤية الطبيب
8. إشعارات ذكية

---

## ملاحظات مهمة للتنفيذ

- **الملف الكامل**: `DEVELOPMENT_PLAN.md` يحتوي على كل التفاصيل والخطوات
- **عند البدء بمهمة**: اقرأ `DEVELOPMENT_PLAN.md` أولاً لفهم السياق
- **بعد كل تغيير**: شغّل `vendor/bin/pint --format agent` ثم الاختبارات ذات الصلة
- **لا تحذف ملفات الاختبار**: هذه ليست ملفات مؤقتة بل أساسية للنظام
- **اتبع نمط Actions**: كل عملية جديدة يجب أن تكون Action class
- **استخدم Wayfinder**: للمسارات في الـ Frontend بدلاً من URLs ثابتة
