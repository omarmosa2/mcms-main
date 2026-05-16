# MCMS — Enterprise Product Requirements Document (PRD)

**Version:** 2.0 — Engineering Blueprint  
**Date:** 2026-04-22  
**Status:** Authoritative System Design Document  
**Audience:** Engineering Leads, Architects, Senior Developers  

---

## 1. Executive Summary

### 1.1 System Purpose
MCMS is a multi-tenant, ambulatory clinic management platform that orchestrates the complete patient journey — scheduling, queuing, clinical visits, diagnostics, pharmacy, billing, and accounting — within a single bounded-context modular monolith.

### 1.2 Business Constraints
- **Regulatory:** Patient data must be encrypted at rest. All access must be auditable. Data retention policies must be enforceable per clinic.
- **Consistency:** Financial transactions (invoices, payments, journal entries) must be ACID-compliant. Stock deductions must never go negative.
- **Availability:** System must remain operational during peak hours (08:00–20:00). Scheduled maintenance only during 02:00–05:00.
- **Isolation:** Zero cross-clinic data leakage. Each clinic is a fully isolated tenant.

### 1.3 Target Scale
| Metric | Current | Target (12 months) |
|--------|---------|-------------------|
| Clinics | 1–10 | 100+ |
| Users per clinic | 5–50 | 200+ |
| Patients per clinic | 1,000–10,000 | 500,000+ |
| Daily appointments | 50–200 | 2,000+ |
| Concurrent sessions | 20–100 | 500+ |
| API requests/sec | ~10 | ~200 |

### 1.4 Non-Negotiables
1. No module may directly query another module's tables
2. All state transitions must pass through explicit state machines with guards
3. All cross-domain communication must occur via events or service interfaces
4. All financial operations must be within database transactions with row-level locking
5. All data access must be scoped to `clinic_id` at the query level

---

## 2. Architecture — Modular Monolith with Strict Boundaries

### 2.1 Domain Boundaries (Bounded Contexts)

The system is divided into 8 domains. Each domain owns its data, its models, and its business logic. Cross-domain access is only permitted through published interfaces (Events or Service Contracts).

```
┌──────────────────────────────────────────────────────────────────────┐
│                         PRESENTATION LAYER                           │
│  Vue 3 SPA (Inertia.js) / API Gateway / Patient Portal               │
├──────────────────────────────────────────────────────────────────────┤
│                         APPLICATION LAYER                            │
│  Controllers → Form Requests → Actions → Domain Services             │
├──────────────────┬──────────────────┬────────────────────────────────┤
│   CLINICAL       │   SCHEDULING     │        FINANCIAL               │
│   Domain         │   Domain         │        Domain                  │
│                  │                  │                                │
│  • Patient       │  • Appointment   │  • Invoice                     │
│  • Visit         │  • QueueEntry    │  • Payment                     │
│  • Diagnosis     │  • Reminder      │  • JournalEntry                │
│  • VitalSign     │                  │  • Account                     │
│  • Allergy       │                  │  • Cashbox                     │
│  • Medication    │                  │  • Expense                     │
│  • Condition     │                  │  • Salary                      │
│                  │                  │                                │
│  OWNED TABLES:   │  OWNED TABLES:   │  OWNED TABLES:                 │
│  patients,       │  appointments,   │  invoices, payments,           │
│  visits,         │  queue_entries,  │  journal_entries, accounts,    │
│  diagnoses,      │  reminders       │  cashboxes, expenses, salaries │
│  vitals,         │                  │                                │
│  allergies,      │                  │                                │
│  medications     │                  │                                │
├──────────────────┼──────────────────┼────────────────────────────────┤
│   INVENTORY      │   DIAGNOSTICS    │        IDENTITY & ACCESS       │
│   Domain         │   Domain         │        Domain                  │
│                  │                  │                                │
│  • Drug          │  • LabOrder      │  • User                        │
│  • Prescription  │  • LabResult     │  • Role                        │
│  • Dispense      │  • RadOrder      │  • Permission                  │
│  • Supplier      │  • RadReport     │  • SecurityPolicy              │
│  • PurchaseOrder │  • RadImage      │  • AuditLog                    │
│  • Alert         │                  │                                │
│  OWNED TABLES:   │  OWNED TABLES:   │  OWNED TABLES:                 │
│  pharmacy_drugs, │  lab_orders,     │  users, roles, permissions,    │
│  prescriptions,  │  lab_results,    │  security_policies, audit_logs │
│  dispenses,      │  radiology_*     │                                │
│  suppliers,      │                  │                                │
│  purchase_orders │                  │                                │
├──────────────────┴──────────────────┴────────────────────────────────┤
│                         INFRASTRUCTURE LAYER                         │
│  MySQL / Redis / Queue / Filesystem / External APIs                  │
└──────────────────────────────────────────────────────────────────────┘
```

### 2.2 Domain Contracts — What Each Domain Exposes

| Domain | Publishes (Events) | Exposes (Service Interface) | Consumes From |
|--------|-------------------|---------------------------|---------------|
| Clinical | `PatientCreated`, `VisitCompleted`, `VisitStarted` | `PatientService.getById()`, `VisitService.getActive()` | Scheduling (patient lookup), Financial (visit reference), Inventory (prescription source) |
| Scheduling | `AppointmentCreated`, `AppointmentCompleted`, `AppointmentCanceled`, `QueueEntryCalled` | `AppointmentService.getById()`, `QueueService.getNext()` | Clinical (appointment→visit link), Financial (appointment→invoice link) |
| Financial | `InvoiceIssued`, `PaymentRecorded`, `PaymentRefunded`, `JournalPosted` | `InvoiceService.getById()`, `PaymentService.getForInvoice()` | Clinical (visit→invoice link), Inventory (dispense→invoice link) |
| Inventory | `PrescriptionDispensed`, `StockBelowThreshold`, `PurchaseOrderReceived` | `DrugService.getById()`, `DrugService.checkStock()` | Clinical (prescription creation), Financial (dispense billing) |
| Diagnostics | `LabResultReady`, `RadiologyReportCompleted` | `LabService.getOrder()`, `RadiologyService.getOrder()` | Clinical (visit→order link), Financial (order→invoice link) |
| Identity | `UserCreated`, `RoleAssigned`, `SecurityPolicyUpdated` | `UserService.getById()`, `PermissionService.has()` | All domains (actor resolution) |

### 2.3 Cross-Domain Access Rules

**STRICT RULE:** No domain may directly `SELECT`, `INSERT`, `UPDATE`, or `DELETE` from another domain's tables.

| Pattern | Allowed? | Example |
|---------|----------|---------|
| Domain A reads Domain B table directly | ❌ FORBIDDEN | `Invoice::where('visit_id', ...)` |
| Domain A calls Domain B service interface | ✅ ALLOWED | `VisitService::getById($visitId)` |
| Domain A listens to Domain B event | ✅ ALLOWED | `InvoiceCreatedListener` on `VisitCompleted` |
| Domain A stores foreign key to Domain B | ✅ ALLOWED (read-only reference) | `Invoice.visit_id` — used for context, not for joins |
| Domain A writes to Domain B table | ❌ FORBIDDEN | Only Domain B's own Actions may mutate its tables |

### 2.4 Data Ownership — Authoritative Source of Truth

| Entity | Owning Domain | Who Can Write | Who Can Read |
|--------|--------------|---------------|--------------|
| Patient | Clinical | Clinical only | Clinical, Scheduling (reference), Financial (reference), Inventory (reference) |
| Visit | Clinical | Clinical only | Clinical, Financial (reference), Inventory (prescription source), Diagnostics (order source) |
| Appointment | Scheduling | Scheduling only | Scheduling, Clinical (reference), Financial (reference) |
| QueueEntry | Scheduling | Scheduling only | Scheduling, Clinical (reference) |
| Invoice | Financial | Financial only | Financial, Clinical (reference) |
| Payment | Financial | Financial only | Financial only |
| JournalEntry | Financial | Financial only | Financial only |
| Account | Financial | Financial only | Financial only |
| Cashbox | Financial | Financial only | Financial only |
| Expense | Financial | Financial only | Financial only |
| Salary | Financial | Financial only | Financial only |
| Drug | Inventory | Inventory only | Inventory, Clinical (reference during prescription) |
| Prescription | Inventory | Inventory only | Inventory, Clinical (reference) |
| Dispense | Inventory | Inventory only | Inventory, Financial (for billing) |
| LabOrder | Diagnostics | Diagnostics only | Diagnostics, Clinical (reference), Financial (reference) |
| LabResult | Diagnostics | Diagnostics only | Diagnostics, Clinical (reference) |
| RadiologyOrder | Diagnostics | Diagnostics only | Diagnostics, Clinical (reference), Financial (reference) |
| User | Identity | Identity only | All domains (actor context only) |
| Role/Permission | Identity | Identity only | All domains (via PermissionService) |
| AuditLog | Identity | Identity only | Identity, Reports (read-only) |

**Violation = Design Defect.** Any code that violates these ownership rules must be refactored to use the domain's service interface or event system.

---

## 3. State Machines — Formal Definitions

Every stateful entity has an explicit state machine with guards. No status field may be mutated outside its state machine.

### 3.1 Appointment State Machine

```
States: scheduled, confirmed, arrived, completed, canceled, no_show
Initial: scheduled
Terminal: completed, canceled, no_show

Transitions:
┌─────────────┬──────────────┬──────────────────────────────────────────┐
│ From        │ To           │ Guard / Side Effect                      │
├─────────────┼──────────────┼──────────────────────────────────────────┤
│ scheduled   │ confirmed    │ scheduled_for > now()                    │
│ confirmed   │ arrived      │ —                                        │
│ arrived     │ completed    │ —                                        │
│ arrived     │ canceled     │ cancel_reason required                   │
│ arrived     │ no_show      │ —                                        │
│ *           │ canceled     │ NOT in terminal states                   │
└─────────────┴──────────────┴──────────────────────────────────────────┘

Events Published:
- AppointmentCreated (on creation)
- AppointmentConfirmed (on scheduled→confirmed)
- AppointmentArrived (on confirmed→arrived)
- AppointmentCompleted (on arrived→completed)
- AppointmentCanceled (on any→canceled)
- AppointmentNoShow (on arrived→no_show)
```

**Implementation Contract:**
```php
interface AppointmentStateMachine {
    public function canTransition(Appointment $apt, string $to): bool;
    public function transition(Appointment $apt, string $to, array $context = []): Appointment;
    public function getTransitions(Appointment $apt): array;
}
```

### 3.2 Invoice State Machine

```
States: draft, issued, partially_paid, paid, void
Initial: draft
Terminal: paid, void

Transitions:
┌─────────────────┬─────────────────┬─────────────────────────────────────┐
│ From            │ To              │ Guard / Side Effect                 │
├─────────────────┼─────────────────┼─────────────────────────────────────┤
│ draft           │ issued          │ items.count >= 1                    │
│ issued          │ partially_paid  │ payment.amount < balance            │
│ issued          │ paid            │ payment.amount >= balance           │
│ partially_paid  │ partially_paid  │ payment.amount < remaining balance  │
│ partially_paid  │ paid            │ payment.amount >= remaining balance │
│ partially_paid  │ issued          │ refund applied, balance > 0         │
│ paid            │ partially_paid  │ refund applied, balance > 0         │
│ *               │ void            │ NOT in void state                   │
└─────────────────┴─────────────────┴─────────────────────────────────────┘

Events Published:
- InvoiceCreated (on creation)
- InvoiceIssued (on draft→issued) → triggers JournalEntry auto-post
- PaymentRecorded (on any paid state change) → triggers JournalEntry auto-post
- PaymentRefunded (on refund) → recalculates balance
- InvoiceVoided (on *→void)
```

**Implementation Contract:**
```php
interface InvoiceStateMachine {
    public function canTransition(Invoice $inv, string $to): bool;
    public function transition(Invoice $inv, string $to, array $context = []): Invoice;
    public function calculateStatus(Invoice $inv): string; // derived from payments
}
```

### 3.3 Payment State Machine

```
States: recorded, refunded, voided
Initial: recorded
Terminal: voided

Transitions:
┌──────────┬──────────┬─────────────────────────────────────┐
│ From     │ To       │ Guard / Side Effect                 │
├──────────┼──────────┼─────────────────────────────────────┤
│ recorded │ refunded │ refund_amount <= original_amount    │
│ refunded │ refunded │ cumulative_refund <= original_amount│
│ *        │ voided   │ NOT voided                          │
└──────────┴──────────┴─────────────────────────────────────┘
```

### 3.4 Visit State Machine

```
States: started, in_progress, completed
Initial: started
Terminal: completed

Transitions:
┌─────────────┬──────────────┬──────────────────────────────────────────┐
│ From        │ To           │ Guard / Side Effect                      │
├─────────────┼──────────────┼──────────────────────────────────────────┤
│ started     │ in_progress  │ —                                        │
│ in_progress │ completed    │ Side effect: sync queue_entry→completed  │
│             │              │ Side effect: sync appointment→completed  │
└─────────────┴──────────────┴──────────────────────────────────────────┘

Events Published:
- VisitStarted (on creation)
- VisitInProgress (on started→in_progress)
- VisitCompleted (on in_progress→completed) → triggers downstream invoicing
```

### 3.5 Queue Entry State Machine

```
States: waiting, called, in_service, completed, skipped, canceled
Initial: waiting
Terminal: completed, skipped, canceled

Transitions:
┌─────────────┬──────────────┬──────────────────────────────────────────┐
│ From        │ To           │ Guard / Side Effect                      │
├─────────────┼──────────────┼──────────────────────────────────────────┤
│ waiting     │ called       │ call-next selects highest priority       │
│ waiting     │ skipped      │ —                                        │
│ waiting     │ canceled     │ —                                        │
│ called      │ in_service   │ visit started from this queue entry      │
│ in_service  │ completed    │ visit completed                          │
└─────────────┴──────────────┴──────────────────────────────────────────┘
```

### 3.6 Lab Order State Machine

```
States: ordered, sample_collected, resulted, canceled
Initial: ordered
Terminal: resulted, canceled

Transitions:
┌───────────────────┬───────────────────┬─────────────────────────────────┐
│ From              │ To                │ Guard / Side Effect             │
├───────────────────┼───────────────────┼─────────────────────────────────┤
│ ordered           │ sample_collected  │ —                               │
│ sample_collected  │ resulted          │ result_value not null           │
│ ordered           │ canceled          │ NOT resulted                    │
│ sample_collected  │ canceled          │ NOT resulted                    │
└───────────────────┴───────────────────┴─────────────────────────────────┘

Events Published:
- LabOrderCreated (on creation)
- LabSampleCollected (on ordered→sample_collected)
- LabResultReady (on sample_collected→resulted) → triggers LIS dispatch
```

### 3.7 Prescription State Machine

```
States: draft, issued, partially_dispensed, dispensed, canceled
Initial: draft
Terminal: dispensed, canceled

Transitions:
┌───────────────────┬───────────────────┬─────────────────────────────────┐
│ From              │ To                │ Guard / Side Effect             │
├───────────────────┼───────────────────┼─────────────────────────────────┤
│ draft             │ issued            │ items.count >= 1                │
│ issued            │ partially_dispensed│ partial dispense                │
│ issued            │ dispensed         │ all items dispensed             │
│ partially_dispensed│ dispensed        │ remaining items dispensed       │
│ partially_dispensed│ partially_dispensed│ more partial dispense         │
│ *                 │ canceled          │ NOT dispensed                   │
└───────────────────┴───────────────────┴─────────────────────────────────┘
```

### 3.8 State Machine Enforcement Rule

**ALL status mutations MUST go through the domain's state machine.** Direct `model->status = 'x'` assignments are forbidden in production code. The state machine must:
1. Validate the transition is allowed
2. Execute any guard checks
3. Apply the transition within a database transaction
4. Publish the corresponding domain event
5. Log the transition to the audit trail

---

## 4. Event System — Domain Event Architecture

### 4.1 Event Registry

Every domain event is a named, versioned class that implements `DomainEvent` interface.

```php
interface DomainEvent {
    public function getEventName(): string;    // e.g. "appointment.completed"
    public function getAggregateId(): string;  // e.g. "apt_12345"
    public function getOccurredAt(): DateTimeImmutable;
    public function getMetadata(): array;
    public function serialize(): string;
}
```

### 4.2 Complete Event Catalog

| Event | Published By | Payload | Consumers |
|-------|-------------|---------|-----------|
| `PatientCreated` | Clinical | patient_id, clinic_id, file_number | Scheduling (index patient), Identity (audit) |
| `PatientUpdated` | Clinical | patient_id, changed_fields | Scheduling (cache invalidation) |
| `AppointmentCreated` | Scheduling | appointment_id, patient_id, doctor_id, scheduled_for | Clinical (link to visit), Financial (pre-billing), Notification (reminder scheduling) |
| `AppointmentConfirmed` | Scheduling | appointment_id, confirmed_at | Notification (send confirmation) |
| `AppointmentArrived` | Scheduling | appointment_id, arrived_at | Queue (auto-enqueue) |
| `AppointmentCompleted` | Scheduling | appointment_id, completed_at | Financial (trigger invoice generation) |
| `AppointmentCanceled` | Scheduling | appointment_id, cancel_reason, canceled_at | Queue (cancel linked entry), Notification (notify patient) |
| `QueueEntryCreated` | Scheduling | queue_id, patient_id, priority, queue_number | — |
| `QueueEntryCalled` | Scheduling | queue_id, called_by, called_at | Clinical (notify doctor) |
| `VisitStarted` | Clinical | visit_id, patient_id, doctor_id, queue_entry_id | — |
| `VisitCompleted` | Clinical | visit_id, patient_id, doctor_id, completed_at | Financial (auto-generate invoice), Inventory (trigger prescription billing) |
| `InvoiceIssued` | Financial | invoice_id, patient_id, total_amount, issued_at | Notification (send to patient), Accounting (auto-post journal) |
| `PaymentRecorded` | Financial | payment_id, invoice_id, amount, method, paid_at | Accounting (auto-post journal), Cashbox (update daily total) |
| `PaymentRefunded` | Financial | payment_id, invoice_id, refund_amount, refunded_at | Accounting (reverse journal entry), Cashbox (update daily total) |
| `PrescriptionDispensed` | Inventory | dispense_id, prescription_id, total_amount, items | Financial (create billing line items) |
| `StockBelowThreshold` | Inventory | drug_id, current_stock, min_stock_level | Notification (alert pharmacist) |
| `LabResultReady` | Diagnostics | result_id, lab_order_id, patient_id, test_name | Clinical (notify ordering doctor), Diagnostics (dispatch to LIS) |
| `RadiologyReportCompleted` | Diagnostics | report_id, order_id, patient_id | Clinical (notify ordering doctor) |
| `UserCreated` | Identity | user_id, email, role_name, clinic_id | Audit (log creation) |
| `SecurityPolicyUpdated` | Identity | clinic_id, policy_changes | All (cache invalidation) |

### 4.3 Event Dispatch Rules

1. **Events are published AFTER the database transaction commits.** Use Laravel's `afterCommit()` on jobs or dispatch events post-transaction.
2. **Events are immutable.** Once published, an event cannot be changed.
3. **Event handlers are idempotent.** Processing the same event twice must not cause side effects.
4. **Failed event handlers retry with exponential backoff.** Max 3 retries, then dead-letter queue.
5. **Events are stored in an `domain_events` table** for replay capability (event sourcing foundation).

### 4.4 Event Store Schema

```sql
CREATE TABLE domain_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id CHAR(36) NOT NULL UNIQUE,          -- UUID
    event_name VARCHAR(100) NOT NULL,            -- e.g. "appointment.completed"
    aggregate_type VARCHAR(50) NOT NULL,         -- e.g. "appointment"
    aggregate_id VARCHAR(50) NOT NULL,           -- e.g. "12345"
    payload JSON NOT NULL,
    metadata JSON,
    occurred_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_aggregate (aggregate_type, aggregate_id),
    INDEX idx_event_name (event_name),
    INDEX idx_occurred (occurred_at)
);
```

---

## 5. Security Architecture — Deep Implementation

### 5.1 Authentication Flow

```
┌──────────┐     ┌───────────┐     ┌──────────────┐     ┌─────────────┐
│  Client   │────►│ Fortify   │────►│ AuthAttempt  │────►│ Session +   │
│  Browser  │     │ Login     │     │ Log Listener │     │ 2FA Check   │
└──────────┘     └───────────┘     └──────────────┘     └─────────────┘
                                                              │
                                                              ▼
                                                    ┌─────────────────┐
                                                    │ Security Policy │
                                                    │ Middleware      │
                                                    └─────────────────┘
```

**Detailed Flow:**
1. POST `/login` with email + password
2. Fortify validates credentials (bcrypt, 12 rounds)
3. `LogAuthenticationAttempts` listener records attempt (IP, UA, status)
4. If 2FA enabled → redirect to `/two-factor-challenge`
5. On success → session created, `EnforceSecurityPolicy` middleware activates
6. Middleware checks: session lifetime, idle timeout, forced 2FA compliance
7. Any violation → session invalidated, redirect to login

### 5.2 Authorization — RBAC + Resource-Level Access

**Current Gap:** RBAC only checks route-level permissions. No resource-level access control.

**Required Enhancement:**

| Access Level | Current | Required |
|-------------|---------|----------|
| Route access | ✅ `permission:patient.view` | ✅ Keep |
| Resource ownership | ❌ Missing | ✅ User can only access resources in their clinic |
| Resource-level permission | ❌ Missing | ✅ Doctor can only edit their own visits |
| Field-level permission | ❌ Missing | ✅ Only admin can view `national_id` |
| Read audit | ❌ Missing | ✅ All reads of sensitive data logged |

**Enhanced Permission Matrix with Resource Scope:**

| Permission | Scope | Description |
|-----------|-------|-------------|
| `patient.view` | Clinic-wide | View any patient in clinic |
| `patient.view.own` | Self | View only patients created by user |
| `patient.national_id.view` | Field-level | Decrypt and view national_id field |
| `visit.update` | Assigned | Update only visits where user is the assigned doctor |
| `visit.update.any` | Clinic-wide | Update any visit (admin only) |
| `invoice.view` | Clinic-wide | View any invoice |
| `invoice.view.patient` | Patient-scoped | View invoices for a specific patient |
| `payment.refund` | Amount-limited | Refund up to $X without additional approval |
| `payment.refund.unlimited` | No limit | Refund any amount (manager only) |

### 5.3 Sensitive Data Handling

| Data Type | Storage | Access Control | Audit |
|-----------|---------|---------------|-------|
| National ID | AES-256-CBC encrypted + SHA-256 hash | `patient.national_id.view` permission required | Every decrypt logged to `sensitive_access_logs` |
| Portal Token | SHA-256 hash stored, plain token returned once | Token is bearer credential, 7-day TTL, single-use | Token creation and usage logged |
| Password | Bcrypt, 12 rounds | Never exposed, reset via Fortify | Reset attempts logged |
| 2FA Secret | Encrypted by Fortify | Only visible during setup | Setup/confirmation logged |
| Patient Attachments | Local filesystem (S3 in production) | `patient.view` permission + clinic scope | Download logged to audit trail |
| Financial Data | Plain (decimal) | `billing.view` / `payment.record` permissions | All mutations logged |

### 5.4 Security Headers (Production)

| Header | Value | Purpose |
|--------|-------|---------|
| X-Frame-Options | DENY | Prevent clickjacking |
| X-Content-Type-Options | nosniff | Prevent MIME sniffing |
| Referrer-Policy | strict-origin-when-cross-origin | Control referrer leakage |
| Permissions-Policy | camera=(), microphone=(), geolocation=() | Restrict browser features |
| Content-Security-Policy | `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';` | Prevent XSS |
| Strict-Transport-Security | max-age=31536000; includeSubDomains | Force HTTPS |
| X-XSS-Protection | 0 | Delegate to CSP |

---

## 6. Performance & Scalability Strategy

### 6.1 Caching Strategy

| Layer | What to Cache | TTL | Invalidation Trigger |
|-------|--------------|-----|---------------------|
| L1: Application | Security policies per clinic | 5 min | `SecurityPolicyUpdated` event |
| L1: Application | RBAC permissions per user | Session | Role/permission change |
| L1: Application | Chart of accounts per clinic | 1 hour | Account CRUD |
| L2: Query | Dashboard aggregations (today's stats) | 1 min | New appointment/payment/visit |
| L2: Query | Patient search results | 30 sec | Patient create/update |
| L2: Query | Queue list (today) | 10 sec | Queue entry status change |
| L3: CDN | Static assets (JS, CSS, images) | 1 year | Deploy (filename hash change) |

**Cache Implementation:**
```php
// Security policy — cached per clinic
Cache::remember(
    "security_policy:{$clinicId}",
    now()->addMinutes(5),
    fn() => SecurityPolicy::forClinic($clinicId)->first()
);

// Invalidated on update:
Cache::forget("security_policy:{$clinicId}");
```

### 6.2 Query Strategy

**Rule:** Every list query MUST have:
1. `WHERE clinic_id = ?` (tenant isolation)
2. `LIMIT` (pagination)
3. Appropriate indexes

**Required Indexes:**

```sql
-- Appointments
CREATE INDEX idx_appointments_clinic_status_date ON appointments(clinic_id, status, scheduled_for);
CREATE INDEX idx_appointments_clinic_patient ON appointments(clinic_id, patient_id);
CREATE INDEX idx_appointments_clinic_doctor ON appointments(clinic_id, doctor_id);

-- Queue entries
CREATE INDEX idx_queue_clinic_date_status ON queue_entries(clinic_id, queue_date, status);
CREATE INDEX idx_queue_clinic_date_priority ON queue_entries(clinic_id, queue_date, priority DESC, queue_number);

-- Visits
CREATE INDEX idx_visits_clinic_status_date ON visits(clinic_id, status, started_at);
CREATE INDEX idx_visits_clinic_patient ON visits(clinic_id, patient_id);
CREATE INDEX idx_visits_clinic_doctor ON visits(clinic_id, doctor_id);

-- Invoices
CREATE INDEX idx_invoices_clinic_status_date ON invoices(clinic_id, status, created_at);
CREATE INDEX idx_invoices_clinic_patient ON invoices(clinic_id, patient_id);

-- Payments
CREATE INDEX idx_payments_clinic_invoice ON payments(clinic_id, invoice_id);
CREATE INDEX idx_payments_clinic_date ON payments(clinic_id, paid_at);

-- Audit logs
CREATE INDEX idx_audit_clinic_action_date ON audit_logs(clinic_id, action, occurred_at);
CREATE INDEX idx_audit_clinic_user ON audit_logs(clinic_id, user_id);

-- Patients
CREATE INDEX idx_patients_clinic_file ON patients(clinic_id, file_number);
CREATE INDEX idx_patients_clinic_name ON patients(clinic_id, first_name, last_name);
```

### 6.3 Read/Write Separation

**Current:** Single database connection for all operations.

**Target (at 100+ concurrent users):**
- **Write connection:** Primary MySQL instance
- **Read connections:** 1–2 read replicas for:
  - Dashboard queries
  - Report generation
  - List views (index pages)
  - Patient search

**Implementation via Laravel:**
```php
// config/database.php
'mysql' => [
    'read' => [
        'host' => env('DB_READ_HOST', '127.0.0.1'),
    ],
    'write' => [
        'host' => env('DB_WRITE_HOST', '127.0.0.1'),
    ],
],
```

### 6.4 Queue Number Generation — Concurrency Fix

**Current Problem:** `max(queue_number) + 1` with `lockForUpdate` serializes all check-ins.

**Solution:** Use a per-clinic, per-day sequence table with atomic increment.

```sql
CREATE TABLE queue_sequences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clinic_id INT NOT NULL,
    queue_date DATE NOT NULL,
    current_number INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uk_clinic_date (clinic_id, queue_date)
);

-- Atomic increment:
INSERT INTO queue_sequences (clinic_id, queue_date, current_number)
VALUES (?, ?, 1)
ON DUPLICATE KEY UPDATE current_number = LAST_INSERT_ID(current_number + 1);

SELECT LAST_INSERT_ID(); -- Returns the new number
```

This eliminates the `lockForUpdate` + `max()` bottleneck.

---

## 7. Observability & Monitoring

### 7.1 Structured Logging

**All logs MUST be JSON-formatted.** No plain text logs in production.

```json
{
    "timestamp": "2026-04-22T10:30:00.000Z",
    "level": "info",
    "channel": "app",
    "message": "Appointment status transitioned",
    "context": {
        "clinic_id": 1,
        "user_id": 5,
        "appointment_id": 12345,
        "from_status": "scheduled",
        "to_status": "confirmed",
        "request_id": "req_abc123",
        "ip": "192.168.1.100"
    }
}
```

**Log Channels:**

| Channel | Level | Destination | Purpose |
|---------|-------|-------------|---------|
| `app` | info+ | File + ELK | Application events |
| `error` | error+ | File + Sentry | Errors and exceptions |
| `audit` | info+ | Dedicated file + SIEM | Audit trail |
| `security` | warning+ | Dedicated file + SIEM | Security events |
| `query` | debug | File (local only) | Slow query log |
| `queue` | info+ | File + monitoring | Job processing |

### 7.2 Monitoring Dashboard

**Required Metrics:**

| Metric | Type | Alert Threshold |
|--------|------|----------------|
| Request latency (p95) | Gauge | > 2 seconds |
| Request latency (p99) | Gauge | > 5 seconds |
| Error rate (5xx) | Rate | > 1% over 5 min |
| Queue depth | Gauge | > 1000 pending jobs |
| Database connections | Gauge | > 80% of max |
| Cache hit rate | Rate | < 70% |
| Disk usage | Gauge | > 80% |
| Failed login attempts | Rate | > 10/min per IP |
| Audit log volume | Rate | Sudden spike = investigate |

### 7.3 Alerting Rules

| Alert | Condition | Action |
|-------|-----------|--------|
| High error rate | 5xx > 1% over 5 min | PagerDuty → on-call engineer |
| Slow queries | Query > 3 seconds | Log + alert to engineering Slack |
| Queue backlog | > 1000 pending for 10 min | Auto-scale queue workers |
| Database connection pool | > 80% utilized | Alert + investigate connection leaks |
| Failed logins | > 10/min from single IP | Auto-block IP via firewall |
| Disk space | > 80% | Alert + auto-cleanup old logs |
| Compliance purge failure | Command fails | Alert to security team |

### 7.4 Request Tracing

Every request gets a unique `X-Request-ID` header. This ID flows through:
1. HTTP middleware → attached to all logs
2. Database queries → logged in slow query log
3. Queue jobs → passed via job payload
4. External API calls → passed as header

```php
// Middleware
public function handle(Request $request, Closure $next): Response {
    $requestId = Str::uuid()->toString();
    $request->headers->set('X-Request-ID', $requestId);
    Log::withContext(['request_id' => $requestId]);
    return $next($request);
}
```

---

## 8. API Governance

### 8.1 Versioning Strategy

**Current:** No versioning.
**Required:** URL-based versioning for all API routes.

```
/api/v1/patients
/api/v1/appointments
/api/v1/billing/invoices
```

Web routes (Inertia) remain unversioned since they're tightly coupled to the frontend.

### 8.2 Standard Response Format

**Success:**
```json
{
    "success": true,
    "data": { ... },
    "meta": {
        "request_id": "req_abc123",
        "timestamp": "2026-04-22T10:30:00.000Z"
    }
}
```

**Paginated List:**
```json
{
    "success": true,
    "data": [ ... ],
    "meta": {
        "current_page": 1,
        "per_page": 25,
        "total": 150,
        "last_page": 6,
        "request_id": "req_abc123"
    }
}
```

**Error:**
```json
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "The given data was invalid.",
        "details": {
            "email": ["The email has already been taken."]
        },
        "request_id": "req_abc123"
    }
}
```

### 8.3 API Contract Rules

1. **All responses use the standard envelope** — no raw arrays
2. **All errors include a machine-readable `code`** — frontend can handle programmatically
3. **All list endpoints support pagination** — `?page=1&per_page=25`
4. **All list endpoints support filtering** — `?status=active&from=2026-01-01`
5. **All list endpoints support sorting** — `?sort=-created_at` (descending)
6. **Rate limiting on all endpoints** — default 60 req/min per user
7. **Idempotency keys on POST/PUT** — `Idempotency-Key` header for financial operations

### 8.4 Rate Limiting Matrix

| Endpoint Group | Limit | Window |
|---------------|-------|--------|
| Authentication (login, 2FA) | 5 | 1 minute |
| Password reset | 3 | 1 minute |
| Financial (payments, refunds) | 30 | 1 minute |
| CRUD operations | 60 | 1 minute |
| Reports/exports | 10 | 1 minute |
| Portal (token-based) | 20 | 1 minute |
| Security policies | 12 | 1 minute |
| Invitations | 10 | 1 minute |

---

## 9. Workflow Engine — Approval & Multi-Step Processes

### 9.1 Why a Workflow Engine

Current hardcoded approval logic (expense approve/reject, salary approve/pay) cannot handle:
- Multi-level approvals (manager → director → finance)
- Conditional routing (amount > $1000 → requires director approval)
- Timeout escalation (no response in 24h → auto-escalate)
- Parallel approvals (multiple approvers needed)

### 9.2 Workflow Definition Schema

```sql
CREATE TABLE workflows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clinic_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,           -- e.g. "expense_approval"
    trigger_event VARCHAR(100) NOT NULL,  -- e.g. "expense.created"
    definition JSON NOT NULL,             -- workflow steps
    is_active BOOLEAN DEFAULT true,
    created_at DATETIME,
    updated_at DATETIME
);

CREATE TABLE workflow_instances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id BIGINT UNSIGNED NOT NULL,
    clinic_id INT NOT NULL,
    aggregate_type VARCHAR(50) NOT NULL,  -- e.g. "expense"
    aggregate_id BIGINT UNSIGNED NOT NULL,
    current_step VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'running', -- running, completed, rejected, timed_out
    context JSON,
    started_at DATETIME,
    completed_at DATETIME
);

CREATE TABLE workflow_steps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instance_id BIGINT UNSIGNED NOT NULL,
    step_name VARCHAR(50) NOT NULL,
    assigned_to_role VARCHAR(50),         -- e.g. "admin"
    assigned_to_user INT,
    status VARCHAR(20) DEFAULT 'pending', -- pending, approved, rejected, timed_out
    approved_at DATETIME,
    notes TEXT
);
```

### 9.3 Workflow Definitions

**Expense Approval Workflow:**
```json
{
    "name": "expense_approval",
    "steps": [
        {
            "name": "manager_review",
            "assigned_role": "admin",
            "timeout_hours": 24,
            "escalate_to": "clinic_admin",
            "conditions": {
                "amount_threshold": null
            }
        },
        {
            "name": "finance_review",
            "assigned_role": "clinic_admin",
            "timeout_hours": 48,
            "conditions": {
                "amount_threshold": 1000
            }
        }
    ],
    "on_complete": { "action": "approve_expense" },
    "on_reject": { "action": "reject_expense" },
    "on_timeout": { "action": "auto_approve", "max_timeout_hours": 72 }
}
```

### 9.4 Workflow Execution Rules

1. Workflows are triggered by domain events
2. Each step is assigned to a role or specific user
3. Steps have configurable timeouts with escalation
4. All step actions are logged to audit trail
5. Workflow state is persisted — survives server restarts

---

## 10. DevOps & Deployment

### 10.1 Environment Strategy

| Environment | Purpose | Database | Data | Access |
|------------|---------|----------|------|--------|
| `local` | Development | SQLite | Fake data | Developer only |
| `testing` | CI/CD tests | SQLite (in-memory) | Factories | CI pipeline |
| `staging` | Pre-production | MySQL | Anonymized production copy | QA team |
| `production` | Live | MySQL (primary + replicas) | Real data | End users |

### 10.2 Deployment Pipeline

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│  Push to  │───►│  CI:     │───►│  CI:     │───►│  Deploy  │───►│  Health  │
│  main     │    │  Lint    │    │  Test    │    │  Staging │    │  Check   │
└──────────┘    └──────────┘    └──────────┘    └──────────┘    └──────────┘
                                                              │
                                                              ▼
                                                    ┌─────────────────┐
                                                    │ Manual Approval │
                                                    └────────┬────────┘
                                                             │
                                                             ▼
                                                    ┌─────────────────┐
                                                    │  Deploy Prod    │
                                                    │  (Blue-Green)   │
                                                    └────────┬────────┘
                                                             │
                                                             ▼
                                                    ┌─────────────────┐
                                                    │  Smoke Tests    │
                                                    │  Auto-Rollback  │
                                                    └─────────────────┘
```

### 10.3 Deployment Steps

```yaml
# .github/workflows/deploy.yml (simplified)
steps:
  - name: Checkout
  - name: Setup PHP 8.4
  - name: Composer install --no-dev --optimize-autoloader
  - name: npm ci && npm run build
  - name: php artisan config:cache
  - name: php artisan route:cache
  - name: php artisan view:cache
  - name: php artisan migrate --force
  - name: php artisan db:seed --class=NumberRangeSeeder --force
  - name: Deploy to server (rsync / Docker push)
  - name: php artisan optimize:clear
  - name: Health check: curl -f http://app/health
  - name: If health check fails → auto-rollback
```

### 10.4 Rollback Strategy

1. **Database migrations are forward-only.** No down migrations in production.
2. **Code rollback:** Previous Docker image / deployment artifact is redeployed.
3. **Data rollback:** If migration corrupted data, restore from backup.
4. **Blue-green deployment:** New version deploys alongside old. Traffic switches only after health check passes.

### 10.5 Backup Strategy

| Backup Type | Frequency | Retention | Storage |
|------------|-----------|-----------|---------|
| Database full | Daily 02:00 | 30 days | S3 (encrypted) |
| Database incremental | Every 6 hours | 7 days | S3 (encrypted) |
| File storage | Daily 02:30 | 30 days | S3 |
| Configuration | On change | 90 days | Git + S3 |

**Recovery Time Objective (RTO):** 4 hours  
**Recovery Point Objective (RPO):** 6 hours

### 10.6 Health Check Endpoint

```
GET /health

Response 200:
{
    "status": "ok",
    "checks": {
        "database": "ok",
        "cache": "ok",
        "queue": "ok",
        "storage": "ok",
        "disk_usage": "45%"
    },
    "timestamp": "2026-04-22T10:30:00.000Z"
}

Response 503 (any check fails):
{
    "status": "degraded",
    "checks": {
        "database": "ok",
        "cache": "failed: connection refused",
        ...
    }
}
```

---

## 11. Failure Scenarios & Recovery

### 11.1 Failure Matrix

| Scenario | Impact | Detection | Recovery |
|----------|--------|-----------|----------|
| Database connection lost | All writes fail, reads fail | Health check fails | Auto-retry (3x), then alert. Reads from cache if available. |
| Queue worker dies | Reminders, LIS/PACS dispatch delayed | Queue depth monitoring | Supervisor auto-restarts. Jobs retry on restart. |
| Stock goes negative (race condition) | Inventory inconsistency | Stock audit command | Rollback transaction. Alert. Manual reconciliation. |
| Double payment recorded | Financial inconsistency | Payment idempotency check | Void duplicate. Refund. Audit log. |
| Clinic data leak (bug) | Security breach | Audit log anomaly | Revoke access. Notify affected clinic. Patch. |
| Cache poisoning | Wrong data served | Cache validation | Flush cache. Rebuild from source. |
| Deployment breaks feature | Users affected | Health check + smoke tests | Auto-rollback to previous version. |
| Disk full | Logs can't write, uploads fail | Disk monitoring | Auto-cleanup old logs. Alert. Scale storage. |

### 11.2 Consistency Guarantees

| Operation | Consistency Model | Mechanism |
|-----------|------------------|-----------|
| Payment recording | Strong (ACID) | Database transaction + `lockForUpdate` |
| Stock deduction | Strong (ACID) | Database transaction + `lockForUpdate` + check |
| Invoice status update | Strong (ACID) | Database transaction |
| Journal entry posting | Strong (ACID) | Database transaction, balanced debit/credit |
| Appointment reminder | Eventual | Queue job with retry |
| LIS/PACS dispatch | Eventual | Queue job with retry + dead-letter |
| Audit log entry | Strong (ACID) | Same transaction as the operation |
| Cache invalidation | Eventual | Event-driven, 5-min TTL fallback |

### 11.3 Idempotency

**All financial operations MUST be idempotent.**

```php
// Payment recording with idempotency key
public function recordPayment(int $invoiceId, array $data, string $idempotencyKey): Payment {
    // Check if already processed
    $existing = Payment::where('idempotency_key', $idempotencyKey)->first();
    if ($existing) {
        return $existing; // Return existing, don't create duplicate
    }

    return DB::transaction(function () use (...) {
        // ... record payment
        $payment->idempotency_key = $idempotencyKey;
        $payment->save();
        return $payment;
    });
}
```

---

## 12. Testing Strategy — Enterprise Grade

### 12.1 Test Pyramid

```
                    ┌─────────┐
                    │  E2E    │  ~10%  (critical user journeys)
                    ├─────────┤
                    │Integration│ ~30%  (controller → action → DB)
                    ├─────────┤
                    │  Unit   │  ~60%  (actions, services, models, state machines)
                    └─────────┘
```

### 12.2 Required Test Coverage by Layer

| Layer | What to Test | Target Coverage |
|-------|-------------|----------------|
| State Machines | All transitions, all guards, invalid transitions | 100% |
| Actions | Happy path, validation failures, edge cases | 90%+ |
| Services | Cross-domain interactions, event publishing | 90%+ |
| Models | Scopes, relationships, casts, accessors | 80%+ |
| Controllers | Request validation, permission checks, response format | 80%+ |
| Middleware | Auth, permission, security policy, clinic scoping | 90%+ |
| Jobs | Retry logic, failure handling, idempotency | 80%+ |
| Events | Payload structure, listener registration | 100% |

### 12.3 Critical Test Cases

**State Machine Tests:**
```php
// AppointmentStateMachineTest
public function test_cannot_transition_from_completed(): void
{
    $appointment = Appointment::factory()->completed()->create();
    $this->assertFalse($this->machine->canTransition($appointment, 'scheduled'));
}

public function test_arrived_requires_cancel_reason_for_cancellation(): void
{
    $appointment = Appointment::factory()->arrived()->create();
    $this->expectException(ValidationException::class);
    $this->machine->transition($appointment, 'canceled', []);
}
```

**Financial Consistency Tests:**
```php
// PaymentRecordingTest
public function test_cannot_pay_more_than_balance(): void
{
    $invoice = Invoice::factory()->issued()->create(['total_amount' => 100, 'paid_amount' => 0]);
    $this->expectException(ValidationException::class);
    $this->action->handle($invoice->clinic_id, $invoice->id, 1, ['amount' => 150, 'method' => 'cash']);
}

public function test_journal_entry_balances_on_invoice_issue(): void
{
    $invoice = Invoice::factory()->withItems()->draft()->create();
    $this->action->handle($invoice->clinic_id, $invoice->id, 1, []);
    $entry = JournalEntry::forReference($invoice)->first();
    $this->assertTrue($entry->isBalanced());
}
```

**Concurrency Tests:**
```php
// QueueEnqueueConcurrencyTest
public function test_concurrent_enqueue_assigns_unique_numbers(): void
{
    $results = [];
    $barrier = new Barrier(10);

    for ($i = 0; $i < 10; $i++) {
        $results[] = $this->runInThread(fn() => $this->action->handle(1, 1, ['patient_id' => 1]));
    }

    $numbers = collect($results)->pluck('queue_number')->unique();
    $this->assertCount(10, $numbers); // All unique
}
```

---

## 13. Improvement Roadmap — Phased Execution

### Phase 1: Foundation (Weeks 1-3) — BLOCKS ALL NEW FEATURES

| # | Action | Owner | Acceptance Criteria |
|---|--------|-------|-------------------|
| 1.1 | Implement state machine classes for Appointment, Invoice, Payment, Visit | Backend Lead | All transitions guarded, tested, no direct status mutations |
| 1.2 | Define domain boundaries + enforce via code review | Architect | No cross-domain table access in any PR |
| 1.3 | Add composite indexes on all high-traffic queries | DBA / Backend | Query execution time < 50ms for all list endpoints |
| 1.4 | Implement server-side pagination on all list endpoints | Backend | No endpoint returns > 100 records without pagination |
| 1.5 | Add structured JSON logging + request ID tracing | DevOps | Every log line has request_id, clinic_id, user_id |
| 1.6 | Create health check endpoint | Backend | `/health` returns 200 with all dependency checks |
| 1.7 | Fix queue number generation concurrency | Backend | 10 concurrent check-ins produce unique numbers |

### Phase 2: Event System + Security (Weeks 4-6)

| # | Action | Owner | Acceptance Criteria |
|---|--------|-------|-------------------|
| 2.1 | Implement domain event system with event store | Backend Lead | All state transitions publish events, events stored in `domain_events` |
| 2.2 | Convert implicit cross-module logic to event listeners | Backend | No direct model calls across domains |
| 2.3 | Implement resource-level access control | Backend | Doctor can only edit own visits, national_id requires special permission |
| 2.4 | Add read audit for sensitive data access | Backend | Every national_id view logged to `sensitive_access_logs` |
| 2.5 | Implement Redis for cache, sessions, queue | DevOps | All three switched from database to Redis |
| 2.6 | Add rate limiting to all API endpoints | Backend | All endpoints have configurable rate limits |
| 2.7 | Implement idempotency keys for financial operations | Backend | Duplicate payment submissions don't create duplicates |

### Phase 3: Performance + Observability (Weeks 7-9)

| # | Action | Owner | Acceptance Criteria |
|---|--------|-------|-------------------|
| 3.1 | Implement application caching layer | Backend | Dashboard loads < 200ms with 100K records |
| 3.2 | Set up monitoring dashboard (Grafana/Prometheus) | DevOps | All metrics from Section 7.2 visible |
| 3.3 | Configure alerting rules | DevOps | Alerts fire within 1 minute of threshold breach |
| 3.4 | Implement read replica support | DBA | Read queries route to replica, writes to primary |
| 3.5 | Add E2E tests for critical journeys | QA | Appointment→Visit→Invoice→Payment flow tested |
| 3.6 | Implement CI/CD pipeline | DevOps | Push to main → auto-deploy to staging → manual → production |
| 3.7 | Add blue-green deployment | DevOps | Zero-downtime deployments with auto-rollback |

### Phase 4: Workflow Engine + Scale (Weeks 10-12)

| # | Action | Owner | Acceptance Criteria |
|---|--------|-------|-------------------|
| 4.1 | Implement workflow engine for approvals | Backend Lead | Expense approval uses configurable workflow, not hardcoded |
| 4.2 | Add multi-level approval support | Backend | Workflows support conditional routing and escalation |
| 4.3 | Implement S3 filesystem driver | DevOps | All file uploads go to S3, not local disk |
| 4.4 | Add API versioning (`/api/v1/`) | Backend | All API routes versioned, old routes deprecated |
| 4.5 | Implement real LIS HL7 adapter | Integration Team | Real HL7 v2 messages sent to external LIS |
| 4.6 | Implement real PACS DICOM adapter | Integration Team | Real DICOMweb messages sent to external PACS |
| 4.7 | Implement real SMS/WhatsApp provider | Integration Team | Appointment reminders sent via Twilio or equivalent |

---

## 14. Engineering Rules — Non-Negotiable

These rules are enforced by code review. Violations block merges.

### 14.1 Code Rules

1. **No direct cross-domain table access.** Use service interfaces or events.
2. **No direct status mutations.** Use state machines.
3. **No financial operations outside transactions.** All payments, invoices, journal entries must be in `DB::transaction()`.
4. **No `Model::all()` or unpaginated list queries.** Every list must have `limit()`.
5. **No `$guarded = []` on models.** Use `$fillable` explicitly.
6. **No business logic in controllers.** All logic in Actions or Services.
7. **No hardcoded clinic_id.** Always from `auth()->user()->clinic_id`.
8. **No raw SQL unless absolutely necessary.** Use Eloquent or Query Builder with parameterized queries.

### 14.2 Testing Rules

1. **No PR without tests.** Every new feature must have tests.
2. **State machines must have 100% transition coverage.**
3. **Financial operations must have concurrency tests.**
4. **No decrease in overall test coverage.**

### 14.3 Security Rules

1. **No sensitive data in logs.** National IDs, passwords, tokens must be masked.
2. **All endpoints require permission checks.** No unauthenticated CRUD.
3. **All data access scoped to clinic_id.** No exceptions.
4. **All security policy changes require audit logging.**

---

## 15. Glossary

| Term | Definition |
|------|-----------|
| Domain | A bounded context with clear ownership of data and business logic |
| Aggregate | A cluster of domain objects treated as a single unit (e.g., Invoice + InvoiceItems) |
| State Machine | Formal definition of allowed state transitions with guards |
| Domain Event | An immutable fact that something happened in the system |
| Service Interface | A contract that a domain exposes for other domains to use |
| Idempotency | An operation that produces the same result regardless of how many times it's executed |
| RTO | Recovery Time Objective — maximum acceptable downtime |
| RPO | Recovery Point Objective — maximum acceptable data loss |
| ACID | Atomicity, Consistency, Isolation, Durability — database transaction properties |

---

**Document End.** This is the authoritative engineering blueprint for MCMS. All development, refactoring, and feature work must conform to these specifications.
