# RBAC MATRIX

SUPER ADMIN:
- all access

CLINIC ADMIN:
- patients.*
- appointments.*
- queue.*
- visits.*
- billing.*
- payments.*
- reports.view

RECEPTIONIST:
- patient.create/view
- appointment.create/update/arrival
- queue.manage/call_next

DOCTOR:
- queue.view
- visit.start/update/complete
- medical.notes.create

ACCOUNTANT:
- billing.view/generate
- payment.record/refund
- reports.financial