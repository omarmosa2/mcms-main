# MCMS SYSTEM

Medical Center Management System

## TYPE
Operational clinic system (NOT medical diagnosis)

## MODULES
- Patients
- Appointments
- Queue
- Visits
- Billing
- Payments
- RBAC
- Audit
- Reports

## MULTI-CLINIC RULE
Every table MUST include:
clinic_id

Strict isolation required.

## WORKFLOWS

Appointment:
scheduled → confirmed → arrived → completed / canceled / no_show

Visit:
started → in_progress → completed

Invoice:
draft → issued → partially_paid → paid

## RULES
- No skipping states
- No cross-clinic access
- No AI diagnosis logic
- All actions must be auditable

## HARD RULE
Every database schema MUST be explicitly defined before implementation.
No guessing allowed.