# 🏥 Medical Center Management System – Full Features Specification

## 📌 الهدف
تحويل النظام من هيكل تقني إلى نظام تشغيلي كامل لإدارة مركز طبي متعدد العيادات ضمن مبنى واحد.

---

# 🧱 1. CORE MODULES (أساسية)

## 👥 Patients
- إنشاء ملف مريض
- بيانات شخصية (اسم، رقم، عمر، جنس)
- أمراض مزمنة
- حساسية
- أدوية حالية
- مرفقات
- سجل زيارات

---

## 🧑‍⚕️ Doctors & Staff
- ملف طبيب
- الاختصاص
- العيادة
- جدول العمل
- مدة المعاينة
- حالة الطبيب (نشط/إجازة)

---

## 🏥 Departments / Clinics
- تعريف العيادات داخل المركز
- ربط الأطباء بها
- تقارير لكل عيادة

---

## 📅 Appointments
- حجز موعد
- تقويم يومي/أسبوعي
- حالات:
  - scheduled
  - confirmed
  - checked_in
  - no_show
  - canceled
- منع التضارب

---

## 🏢 Reception / Check-in
- تسجيل وصول المريض
- فتح زيارة
- ربط الموعد
- تحويل للعيادة

---

## 🚶 Queue System
- دور لكل عيادة
- أولوية (عادي/طارئ)
- استدعاء المريض
- تتبع وقت الانتظار

---

## 🩺 Visits
- سبب الزيارة
- ملاحظات الطبيب
- تشخيص
- خطة علاج
- طلبات
- وصفة
- حالة:
  - opened
  - in_progress
  - completed

---

## 💊 Prescriptions
- أدوية
- جرعة
- مدة
- طباعة
- ربط بالزيارة

---

## 🧪 Orders (Lab / Radiology)
- طلب تحليل
- طلب صورة
- حالة الطلب
- نتائج
- رفع ملفات

---

## 💰 Billing
- إنشاء فاتورة
- ربط بالخدمات
- خصومات
- حالات:
  - draft
  - issued
  - paid
  - partial

---

## 💳 Payments
- نقدي / تحويل / بطاقة
- دفعات جزئية
- Refund
- تتبع الذمم

---

# 💰 2. FINANCIAL MODULE

## 🧾 Expenses
- نوع المصروف
- مبلغ
- تاريخ
- ملاحظات
- مرفقات

---

## 💵 Salaries
- راتب أساسي
- Bonus
- Deduction
- حالة الدفع

---

## 🏦 Cashbox
- رصيد افتتاحي
- دخل
- مصاريف
- رصيد نهائي
- إغلاق يومي

---

## 📊 Financial Reports
- دخل يومي
- دخل شهري
- مصاريف
- صافي الربح
- دخل حسب طبيب/عيادة

---

# 🟡 3. OPTIONAL MODULES

## 💊 Pharmacy
- مخزون
- بيع من وصفة
- تنبيهات نقص

## 🧪 Laboratory
- إدارة التحاليل
- إدخال نتائج
- طباعة تقارير

## 📦 Inventory
- مواد طبية
- حركة دخول/خروج

## 🔔 Notifications
- تذكير موعد
- إشعارات داخلية

---

# 🔐 4. SECURITY

## RBAC
- Admin
- Doctor
- Reception
- Accountant

## Audit Logs
- كل العمليات
- من قام بها
- متى

---

# 🔄 5. WORKFLOW (رحلة المريض)

1. تسجيل مريض
2. حجز موعد
3. وصول (Check-in)
4. دخول الدور
5. زيارة الطبيب
6. وصفة / طلب تحليل
7. إنشاء فاتورة
8. دفع
9. إغلاق الزيارة

---

# 📊 6. DASHBOARD (Admin)

- عدد المرضى اليوم
- دخل اليوم
- المرضى المنتظرين
- أفضل طبيب
- أفضل عيادة

---

# 🧠 7. STATE MACHINES

## Appointment
scheduled → confirmed → checked_in → completed

## Visit
opened → in_progress → completed

## Invoice
draft → issued → paid

---

# 🎯 الهدف النهائي
نظام يدير:
- التشغيل الطبي
- الإدارة المالية
- تدفق المرضى
- الأداء العام للمركز
