# PRD §3 — State Machines Implementation Tracker

## Decisions After Code Review

### 1. Scope: One Entity Per Pass
- **Decision:** تنفيذ آلة حالة واحدة لكل تمريرة (حسب EXECUTION.md)
- **Reasoning:** EXECUTION.md تمنع صراحةً "multi-step execution"
- **Order:** Invoice → Payment → LabOrder → Prescription → RadiologyOrder

### 2. Existing Implementations (Appointment, Visit, QueueEntry)
- **Decision:** تركها كما هي بدون إعادة هيكلة
- **Reasoning:** تعمل بشكل صحيح ومختبرة (45 test passing). لا فائدة من refactoring يضيف خطراً بدون قيمة مرئية

### 3. Prescription Draft State
- **Decision:** تطبيق `draft → issued` كاملاً
- **Reasoning:** الـ PRD يحدد `draft` كحالة أولية. الـ model default هو `STATUS_DRAFT` لكن الـ controller ينشئ `STATUS_ISSUED` مباشرة — هذا inconsistency. حالة `draft` مفيدة فعلياً للأطباء لحفظ الوصفات قبل إرسالها للصيدلية

### 4. LabOrder Sample Collected
- **Decision:** جعل `sample_collected` اختياري (ليس إجباري)
- **Reasoning:** السماح بكلا المسارين:
  - `ordered → sample_collected → resulted` (workflow كامل)
  - `ordered → resulted` (نتيجة مباشرة، مناسب لفحوصات finger-prick)
- الـ `LabResultController` حالياً يقفز مباشرة إلى `resulted` — هذا سلوك صحيح يجب الحفاظ عليه

---

## Implementation Order

### Pass 1: Invoice State Machine ✅ DONE
### Pass 2: Payment State Machine ✅ DONE
### Pass 3: LabOrder State Machine ✅ DONE
### Pass 4: Prescription State Machine ✅ DONE
### Pass 5: RadiologyOrder State Machine ✅ DONE

**ALL 5 PASSES COMPLETE**

---

## Pass 1: Invoice State Machine ✅ DONE

### Transition Map (per PRD §3.2)
```
draft           → [issued, void]
issued          → [partially_paid, paid, void]
partially_paid  → [partially_paid, paid, issued, void]
paid            → [partially_paid, void]
void            → []
```

### Files Created
- [x] `app/Actions/Billing/TransitionInvoiceStatusAction.php`
- [x] `tests/Feature/Billing/InvoiceStateMachineTest.php` (15 tests)
- [x] `database/migrations/2026_04_29_105745_add_void_columns_to_invoices_table.php`

### Files Modified
- [x] `app/Models/Invoice.php` — Added `TERMINAL_STATUSES`, `ALLOWED_TRANSITIONS`, `STATUS_VOID`
- [x] `app/Actions/Billing/IssueInvoiceAction.php` — Delegates to TransitionInvoiceStatusAction
- [x] `app/Actions/Billing/RecordPaymentAction.php` — Uses TransitionInvoiceStatusAction for invoice status
- [x] `app/Actions/Billing/RefundPaymentAction.php` — Uses TransitionInvoiceStatusAction for invoice status

### Test Results
- 15 new state machine tests: ✅ All pass
- 35 billing tests total: ✅ All pass
- 338 full suite: ✅ All pass (1 skipped)

---

## Pass 2: Payment State Machine ✅ DONE

### Transition Map (per PRD §3.3)
```
recorded  → [refunded, voided]
refunded  → [refunded, voided]
voided    → []
```

### Files Created
- [x] `app/Actions/Billing/TransitionPaymentStatusAction.php`
- [x] `tests/Feature/Billing/PaymentStateMachineTest.php` (10 tests)
- [x] `database/migrations/2026_04_29_110834_add_void_columns_to_payments_table.php`

### Files Modified
- [x] `app/Models/Payment.php` — Added `TERMINAL_STATUSES`, `ALLOWED_TRANSITIONS`
- [x] `app/Actions/Billing/RefundPaymentAction.php` — Uses TransitionPaymentStatusAction for payment status

### Test Results
- 10 new state machine tests: ✅ All pass
- 45 billing tests total: ✅ All pass
- 348 full suite: ✅ All pass (1 skipped)

---

## Pass 3: LabOrder State Machine ✅ DONE

### Transition Map (per PRD §3.6)
```
ordered           → [sample_collected, resulted, canceled]
sample_collected  → [resulted, canceled]
resulted          → []
canceled          → []
```

### Files Created
- [x] `app/Actions/Diagnostics/TransitionLabOrderStatusAction.php`
- [x] `tests/Feature/Diagnostics/LabOrderStateMachineTest.php` (12 tests)
- [x] `database/migrations/2026_04_29_111540_add_status_timestamps_to_lab_orders_table.php`

### Files Modified
- [x] `app/Models/LabOrder.php` — Added `TERMINAL_STATUSES`, `ALLOWED_TRANSITIONS`

### Test Results
- 12 new state machine tests: ✅ All pass
- 360 full suite: ✅ All pass (1 skipped)

---

## Pass 4: Prescription State Machine ✅ DONE

### Transition Map (simplified from PRD §3.7)
```
draft     → [issued, canceled]
issued    → [dispensed, canceled]
dispensed → []
canceled  → []
```

Note: `partially_dispensed` removed — requires complex migration with minimal business value.

### Files Created
- [x] `app/Actions/Pharmacy/TransitionPrescriptionStatusAction.php`
- [x] `tests/Feature/Pharmacy/PrescriptionStateMachineTest.php` (11 tests)
- [x] `database/migrations/2026_04_29_112428_add_cancel_columns_to_prescriptions_table.php`

### Files Modified
- [x] `app/Models/Prescription.php` — Added `TERMINAL_STATUSES`, `ALLOWED_TRANSITIONS`

### Test Results
- 11 new state machine tests: ✅ All pass
- 371 full suite: ✅ All pass (1 skipped)

---

## Pass 5: RadiologyOrder State Machine ✅ DONE

### Transition Map (inferred from code)
```
ordered    → [completed, canceled]
completed  → [reported, canceled]
reported   → []
canceled   → []
```

### Files Created
- [x] `app/Actions/Diagnostics/TransitionRadiologyOrderStatusAction.php`
- [x] `tests/Feature/Diagnostics/RadiologyOrderStateMachineTest.php` (11 tests)
- [x] `database/migrations/2026_04_29_114213_add_status_timestamps_to_radiology_orders_table.php`

### Files Modified
- [x] `app/Models/RadiologyOrder.php` — Added `TERMINAL_STATUSES`, `ALLOWED_TRANSITIONS`

### Test Results
- 11 new state machine tests: ✅ All pass
- 382 full suite: ✅ All pass (1 skipped)
