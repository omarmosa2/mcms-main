# خطة تحسين Dashboard الاحترافية

## نظرة عامة
إعادة تصميم Dashboard ليكون مركز إنتاجية يومي احترافي، سهل الاستخدام طوال اليوم، واضح غير مشتت، مع مميزات متقدمة.

---

## المرحلة 1: Backend - تعديل CacheService

### الملف: `app/Services/Cache/CacheService.php`

#### التعديلات على `getDashboardStats()`:

إضافة البيانات التالية للمصفوفة المُرجعة:

```php
$today = today();
$now = now();
$oneHourLater = $now->copy()->addHour();

// مواعيد اليوم بالتفصيل
$todayAppointments = Appointment::query()
    ->forClinic($clinicId)
    ->whereDate('scheduled_for', $today);

'today_appointments_by_status' => (clone $todayAppointments)
    ->selectRaw('status, COUNT(*) as count')
    ->groupBy('status')
    ->pluck('count', 'status')
    ->toArray(),

// المواعيد القادمة (خلال الساعة القادمة)
$upcomingAppointments = (clone $todayAppointments)
    ->whereBetween('scheduled_for', [$now, $oneHourLater])
    ->with(['patient', 'doctor'])
    ->orderBy('scheduled_for')
    ->get()
    ->map(function ($appointment) {
        return [
            'id' => $appointment->id,
            'time' => $appointment->scheduled_for?->format('H:i'),
            'patient_name' => trim($appointment->patient?->first_name.' '.$appointment->patient?->last_name),
            'doctor_name' => $appointment->doctor?->name,
            'status' => $appointment->status,
        ];
    })->values()->all(),

// المرضى الذين ينتظرون أكثر من 30 دقيقة
$todayQueueEntries = QueueEntry::query()
    ->forClinic($clinicId)
    ->where('queue_date', $today);

'long_waiting_patients' => (clone $todayQueueEntries)
    ->where('status', 'waiting')
    ->where('created_at', '<=', $now->copy()->subMinutes(30))
    ->with(['patient'])
    ->get()
    ->map(function ($entry) {
        return [
            'id' => $entry->id,
            'queue_number' => $entry->queue_number,
            'patient_name' => trim($entry->patient?->first_name.' '.$entry->patient?->last_name),
            'waiting_minutes' => $entry->created_at?->diffInMinutes($now) ?? 0,
        ];
    })->values()->all(),

// الفواتير المعلقة اليوم
$pendingInvoices = Invoice::query()
    ->forClinic($clinicId)
    ->whereDate('created_at', $today)
    ->where('payment_status', 'pending');

'pending_invoices_today' => (clone $pendingInvoices)->count(),
'pending_invoices_amount_today' => (clone $pendingInvoices)->sum('total_amount'),

// إيرادات آخر 7 أيام
'last_7_days_revenue' => Invoice::query()
    ->forClinic($clinicId)
    ->where('created_at', '>=', now()->subDays(7))
    ->selectRaw($driver === 'sqlite'
        ? "strftime('%Y-%m-%d', created_at) as day, SUM(total_amount) as total"
        : "DATE(created_at) as day, SUM(total_amount) as total"
    )
    ->groupBy('day')
    ->orderBy('day')
    ->pluck('total', 'day')
    ->toArray(),

// مرضى جدد آخر 7 أيام
'last_7_days_patients' => Patient::query()
    ->forClinic($clinicId)
    ->where('created_at', '>=', now()->subDays(7))
    ->selectRaw($driver === 'sqlite'
        ? "strftime('%Y-%m-%d', created_at) as day, COUNT(*) as count"
        : "DATE(created_at) as day, COUNT(*) as count"
    )
    ->groupBy('day')
    ->orderBy('day')
    ->pluck('count', 'day')
    ->toArray(),
```

---

## المرحلة 2: Frontend - إعادة تصميم Dashboard.vue

### الملف: `resources/js/pages/Dashboard.vue`

#### الهيكل الجديد:

```vue
<template>
    <Head title="لوحة التحكم" />
    
    <div class="dashboard-rtl">
        <!-- 1. رأس الصفحة الترحيبي -->
        <section class="welcome-header">
            <div>
                <h1>مرحباً، {{ auth.user.name }}</h1>
                <p>{{ currentDate }}</p>
            </div>
            <div class="quick-actions">
                <button>➕ موعد جديد</button>
                <button>👤 مريض جديد</button>
                <button>💰 فاتورة</button>
                <button>📋 تقرير</button>
            </div>
        </section>

        <!-- 2. شريط الملخص اليومي -->
        <section class="daily-summary">
            <div class="summary-card">
                <span class="value">{{ stats.today_appointments }}</span>
                <span class="label">موعد اليوم</span>
            </div>
            <div class="summary-card">
                <span class="value">{{ stats.pending_queue }}</span>
                <span class="label">في الانتظار</span>
            </div>
            <div class="summary-card">
                <span class="value">{{ stats.pending_invoices_today }}</span>
                <span class="label">فاتورة معلقة</span>
            </div>
            <div class="summary-card">
                <span class="value">{{ stats.active_visits }}</span>
                <span class="label">زيارة نشطة</span>
            </div>
        </section>

        <!-- 3. المحتوى الرئيسي (عمودين) -->
        <section class="main-content">
            <!-- العمود الأيمن -->
            <div class="right-column">
                <!-- رسم بياني للإيرادات -->
                <article class="chart-card">
                    <h2>الإيرادات (7 أيام)</h2>
                    <Chart type="line" ... />
                </article>
                
                <!-- المواعيد القادمة -->
                <article class="upcoming-appointments">
                    <h2>📅 المواعيد القادمة</h2>
                    <ul>
                        <li v-for="apt in stats.upcoming_appointments">
                            <span class="time">{{ apt.time }}</span>
                            <span class="patient">{{ apt.patient_name }}</span>
                            <span class="doctor">{{ apt.doctor_name }}</span>
                        </li>
                    </ul>
                </article>
            </div>

            <!-- العمود الأيسر -->
            <div class="left-column">
                <!-- تنبيهات عاجلة -->
                <article class="urgent-alerts">
                    <h2>🔴 تنبيهات عاجلة</h2>
                    <div v-for="patient in stats.long_waiting_patients" class="alert-item">
                        ⏰ {{ patient.patient_name }} ينتظر {{ patient.waiting_minutes }} دقيقة
                    </div>
                    <div v-if="stats.pending_invoices_today > 5" class="alert-item">
                        💰 {{ stats.pending_invoices_today }} فواتير معلقة اليوم
                    </div>
                </article>

                <!-- قائمة الانتظار -->
                <article class="queue-status">
                    <h2>🟡 حالة الانتظار</h2>
                    <p>{{ stats.pending_queue }} مريض في الانتظار</p>
                    <p>{{ stats.long_waiting_patients.length }} مريض > 30 دقيقة</p>
                </article>
            </div>
        </section>

        <!-- 4. الوصول السريع للوحدات -->
        <section class="quick-access">
            <h2>🧭 الوصول السريع</h2>
            <div class="module-grid">
                <Link v-for="module in visibleModuleCards" :href="module.href" class="module-card">
                    <component :is="module.icon" />
                    <h3>{{ module.title }}</h3>
                    <p>{{ module.metric }}</p>
                </Link>
            </div>
        </section>
    </div>
</template>
```

---

## المرحلة 3: التنسيقات CSS/ Tailwind

### إضافات لـ `app.css`:

```css
/* دعم RTL */
.dashboard-rtl {
    direction: rtl;
    font-family: 'Tajawal', sans-serif;
}

/* رأس الصفحة */
.welcome-header {
    @apply glass-panel-lux p-6 md:p-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4;
}

/* شريط الملخص */
.daily-summary {
    @apply grid grid-cols-2 md:grid-cols-4 gap-4;
}

.summary-card {
    @apply metric-tile p-5 text-center;
}

.summary-card .value {
    @apply text-4xl font-bold text-gradient-mint;
}

.summary-card .label {
    @apply text-sm text-muted-foreground mt-1;
}

/* الإجراءات السريعة */
.quick-actions {
    @apply flex flex-wrap gap-2;
}

.quick-actions button {
    @apply pattern-button-clay px-4 py-2 text-sm font-medium;
}

/* المحتوى الرئيسي */
.main-content {
    @apply grid gap-4 xl:grid-cols-[1.58fr_0.92fr];
}

/* بطاقات التنبيهات */
.urgent-alerts {
    @apply glass-panel-soft p-5 rounded-2xl;
}

.alert-item {
    @apply mt-3 p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-700 dark:text-red-300;
}

/* بطاقات الوحدات */
.module-grid {
    @apply grid gap-4 md:grid-cols-3 lg:grid-cols-6;
}

.module-card {
    @apply glass-panel-soft p-4 text-center hover-lift transition-all;
}
```

---

## المرحلة 4: المكونات الجديدة (اختياري)

### يمكن إنشاء مكونات منفصلة لـ:
- `resources/js/components/Dashboard/DailySummaryBar.vue`
- `resources/js/components/Dashboard/QuickActions.vue`
- `resources/js/components/Dashboard/UpcomingAppointments.vue`
- `resources/js/components/Dashboard/UrgentAlerts.vue`

**ملاحظة:** يمكن دمج كل شيء في Dashboard.vue مباشرة لتبسيط البنية.

---

## المرحلة 5: RTL والخطوط

### التأكد من:
1. خط Tajawal محمل في `app.css`
2. `direction: rtl` في الـ Dashboard
3. جميع الهوامش والحشو معكوسة (ml ↔ mr)
4. الأيقونات في الجهة الصحيحة

---

## ملخص التحسينات

| العنصر | قبل | بعد |
|--------|-----|-----|
| **اللغة** | إنجليزي | عربي RTL |
| **التركيز** | عام ومشتت | إنتاجية يومية |
| **الإحصائيات** | ثابتة وعامة | بيانات حقيقية اليوم |
| **الإجراءات** | روابط فقط | نماذج منبثقة سريعة |
| **الرسوم البيانية** | 4 رسوم في الأسفل | رسم واحد رئيسي + بيانات اليوم |
| **التنبيهات** | لا يوجد | تنبيهات عاجلة واضحة |
| **التصميم** | مزدحم | منظم مع مسافات واسعة |

---

## خطوات التنفيذ

1. ✅ تحليل الكود الحالي
2. ⏳ تعديل `CacheService.php` لإضافة البيانات الجديدة
3. ⏳ تعديل `DashboardController.php` (إذا لزم)
4. ⏳ إعادة تصميم `Dashboard.vue` بالكامل
5. ⏳ إضافة التنسيقات CSS/ Tailwind
6. ⏳ اختبار الواجهة
7. ⏳ تشغيل Pint
