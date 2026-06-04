# Phase 1 Audit Report — Dialogs, Modals, Sheets, Action Buttons

## Summary
- **Total pages scanned**: 24 pages
- **Total dialogs/modals/sheets found**: 38
- **Total action buttons found**: 60+
- **Critical issues**: 19
- **Minor issues**: 25
- **OK (no issues)**: 6

---

## 1. Dialogs / Modals / Sheets / Drawers

### 1.1 patients/Index.vue
| # | Type | Trigger | Content | Issues |
|---|------|---------|---------|--------|
| D1 | Sheet (create) | `isCreateSheetOpen` button | Full patient creation form (file_number, names, DOB, gender, phone, email, national_id, notes, chronic_conditions[], allergies[], current_medications[]) | **CRITICAL**: Uses Sheet instead of Dialog. No loading state on submit button. No auto-focus. No field validation feedback inside dialog. Mixed English/Arabic labels. |
| D2 | Dialog (view) | `viewingPatient !== null` | Read-only patient profile with visit history, attachments, medical lists | **MINOR**: No close button. Title is entity name not action-oriented. No proper Header/Body/Footer structure. |
| D3 | Dialog (edit) | `editingPatient !== null` | Edit patient form with all fields + medical arrays | **CRITICAL**: No loading state. Uses inline Form with `@success` close — no explicit loading feedback. Footer buttons not standardized. |
| D4 | ConfirmationDialog (delete) | `isConfirmOpen` | Generic confirmation via `useConfirm()` | **OK**: Uses existing ConfirmationDialog. But should use new DeleteDialog template. |

### 1.2 doctors/Index.vue
| # | Type | Trigger | Content | Issues |
|---|------|---------|---------|--------|
| D5 | Sheet (create) | `isCreateSheetOpen` | Doctor profile creation (user select, department, specialty, license, duration, status, bio) | **CRITICAL**: Sheet instead of Dialog. No loading state. |
| D6 | Dialog (view) | `viewingProfile !== null` | Read-only doctor profile | **MINOR**: No close button. Title not action-oriented. |
| D7 | Dialog (edit) | `editingProfile !== null` | Edit doctor profile form | **CRITICAL**: No loading state. Footer not standardized. |
| D8 | ConfirmationDialog (delete) | `isConfirmOpen` | Generic confirmation | **OK**: Uses useConfirm pattern. |

### 1.3 appointments/Index.vue
| # | Type | Trigger | Content | Issues |
|---|------|---------|---------|--------|
| D9 | Sheet (create) | `isCreateSheetOpen` | Appointment creation (patient, doctor, date, time, duration, notes) | **CRITICAL**: Sheet instead of Dialog. `isQuickAddOpen = true` by default — always open on load. |
| D10 | Dialog (view) | `viewingAppointment !== null` | Read-only appointment details | **MINOR**: No close button. |
| D11 | Dialog (edit) | `editingAppointment !== null` | Edit appointment form | **CRITICAL**: No loading state. |
| D12 | ConfirmationDialog (delete) | `isConfirmOpen` | Generic confirmation | **OK** |

### 1.4 queue/Index.vue
| # | Type | Trigger | Content | Issues |
|---|------|---------|---------|--------|
| D13 | Sheet (create) | `isCreateSheetOpen` | Queue entry creation | **CRITICAL**: Sheet instead of Dialog. |
| D14 | Dialog (view) | `viewingQueueEntry !== null` | Read-only queue entry details | **MINOR**: No close button. |
| D15 | ConfirmationDialog (delete) | `isConfirmOpen` | Generic confirmation | **OK** |

### 1.5 visits/Index.vue
| # | Type | Trigger | Content | Issues |
|---|------|---------|---------|--------|
| D16 | Sheet (create) | `isCreateSheetOpen` | Visit creation (patient, doctor, queue, appointment, complaint, notes) | **CRITICAL**: Sheet instead of Dialog. No loading state. |
| D17 | Dialog (view) | `viewingVisit !== null` | Read-only visit details | **MINOR**: No close button. |
| D18 | Dialog (edit) | `editingVisit !== null` | Edit visit form | **CRITICAL**: No loading state. |
| D19 | ConfirmationDialog (delete) | `isConfirmOpen` | Generic confirmation | **OK** |

### 1.6 users/Index.vue
| # | Type | Trigger | Content | Issues |
|---|------|---------|---------|--------|
| D20 | Sheet (create) | `isCreateSheetOpen` | User creation (name, email, password, roles, active) | **CRITICAL**: Sheet instead of Dialog. |
| D21 | Dialog (view) | `viewingUser !== null` | Read-only user details | **MINOR**: No close button. |
| D22 | Dialog (edit) | `editingUser !== null` | Edit user form | **CRITICAL**: No loading state. |
| D23 | ConfirmationDialog (delete) | `isConfirmOpen` | Generic confirmation | **OK** |

### 1.7 departments/Index.vue
| # | Type | Trigger | Content | Issues |
|---|------|---------|---------|--------|
| D24 | Sheet (create) | `isCreateSheetOpen` → **NOW**: `AddDepartmentDialog` | Department creation | **FIXED**: Replaced Sheet with AddDepartmentDialog |
| D25 | Dialog (view) | `viewingDepartment !== null` | Read-only department details | **MINOR**: No close button. Title not action-oriented. |
| D26 | Dialog (edit) | `editingDepartment !== null` | Edit department form (name, code, description, is_active checkbox) | **CRITICAL**: No loading state. Footer not standardized. |
| D27 | ConfirmationDialog (delete) | `isConfirmOpen` | Generic confirmation | **OK** |

### 1.8 roles/Index.vue
| # | Type | Trigger | Content | Issues |
|---|------|---------|---------|--------|
| D28 | Dialog (view) | `viewingRole !== null` | Read-only role details with permissions list | **CRITICAL**: English text ("Role Details", "System", "Custom", "No description"). No close button. |
| D29 | Dialog (edit) | `editingRole !== null` | Edit role form (name, description, permissions checkboxes) | **CRITICAL**: English text ("Edit Role", "Save changes"). No loading state. |

### 1.9 billing/Index.vue
| # | Type | Trigger | Content | Issues |
|---|------|---------|---------|--------|
| D30 | Sheet (create) | `isCreateSheetOpen` | Invoice creation (patient, visit, items, amounts, notes) | **CRITICAL**: Sheet instead of Dialog. Complex form with dynamic items. |
| D31 | Dialog (view) | `viewingInvoice !== null` | Read-only invoice with line items and payments | **MINOR**: No close button. |
| D32 | Dialog (edit) | `editingInvoice !== null` | Edit invoice form | **CRITICAL**: No loading state. |
| D33 | ConfirmationDialog (delete) | `isConfirmOpen` | Generic confirmation | **OK** |

### 1.10 cashbox/Index.vue
| # | Type | Trigger | Content | Issues |
|---|------|---------|---------|--------|
| D34 | Sheet (create) | `isCreateSheetOpen` | Cashbox creation | **CRITICAL**: Sheet instead of Dialog. |
| D35 | Dialog (view) | `viewingBox !== null` | Read-only cashbox details | **MINOR**: No close button. |
| D36 | Dialog (edit) | `editingBox !== null` | Edit cashbox form | **CRITICAL**: No loading state. |
| D37 | ConfirmationDialog (delete) | `isConfirmOpen` | Generic confirmation | **OK** |

### 1.11 expenses/Index.vue
| # | Type | Trigger | Content | Issues |
|---|------|---------|---------|--------|
| D38 | Sheet (create) | `isCreateSheetOpen` | Expense creation | **CRITICAL**: Sheet instead of Dialog. |
| ... | Dialog (view/edit) | `viewingExpense`/`editingExpense` | View/Edit expense | Same pattern as above |
| ... | ConfirmationDialog (delete) | `isConfirmOpen` | Generic confirmation | **OK** |

### 1.12 settings/Security.vue
| # | Type | Trigger | Content | Issues |
|---|------|---------|---------|--------|
| D39 | TwoFactorSetupModal | `showSetupModal` | 2FA setup QR code + verification | **MINOR**: Custom modal, not using ui/dialog. English text. |
| D40 | TwoFactorRecoveryCodes | Component | Recovery codes display | **MINOR**: English text. |

### 1.13 settings/Profile.vue
| # | Type | Trigger | Content | Issues |
|---|------|---------|---------|--------|
| D41 | DeleteUser | Component | Delete account confirmation | **CRITICAL**: English text. Custom implementation. |

---

## 2. Action Buttons Audit

### 2.1 Add/Create Buttons
| Page | Button | Style | Issues |
|------|--------|-------|--------|
| patients | "إضافة مريض" (Sheet trigger) | `variant="clay"` | Opens Sheet not Dialog. Icon on wrong side (LTR). |
| doctors | "إضافة طبيب" | `variant="clay"` | Opens Sheet not Dialog. |
| appointments | "موعد جديد" | `variant="clay"` | Opens Sheet. `isQuickAddOpen = true` always open. |
| queue | "تسجيل دخول" | `variant="clay"` | Opens Sheet. |
| visits | "بدء زيارة" | `variant="clay"` | Opens Sheet. |
| users | "إضافة مستخدم" | `variant="clay"` | Opens Sheet. |
| departments | "إنشاء قسم" | `variant="clay"` | **FIXED**: Now opens AddDepartmentDialog. |
| billing | "فاتورة جديدة" | `variant="clay"` | Opens Sheet. |
| cashbox | "فتح صندوق" | `variant="clay"` | Opens Sheet. |
| expenses | "مصروف جديد" | `variant="clay"` | Opens Sheet. |

### 2.2 Edit Buttons (in tables)
| Page | Pattern | Issues |
|------|---------|--------|
| patients | Eye + Pencil + Trash2 always visible | **CRITICAL**: Buttons permanently visible, not hover-only. Trash2 is filled red. |
| doctors | Not found (no action column visible) | **MINOR**: Missing action buttons entirely. |
| appointments | Eye + Pencil icons | **MINOR**: Always visible, not hover-only. |
| queue | No action buttons | **OK**: No edit/delete needed. |
| visits | No action buttons | **OK**. |
| users | No action buttons visible | **MINOR**: Missing. |
| departments | No action buttons visible | **MINOR**: Missing. |
| roles | No action buttons | **OK**: System roles. |
| billing | No action buttons | **MINOR**: Missing. |

### 2.3 Delete Buttons
| Page | Pattern | Issues |
|------|---------|--------|
| patients | `Trash2` icon, filled red, always visible | **CRITICAL**: Permanently visible red button. Should be hover-only icon. |
| doctors | Not visible | **OK**: No delete button found. |
| appointments | Not visible | **OK**. |
| departments | Not visible | **OK**. |
| users | Not visible | **OK**. |
| billing | Not visible | **OK**. |
| cashbox | Not visible | **OK**. |
| expenses | Not visible | **OK**. |

### 2.4 View Buttons
| Page | Pattern | Issues |
|------|---------|--------|
| patients | `Eye` icon, always visible | **MINOR**: Should be hover-only. |
| appointments | `Eye` icon, always visible | **MINOR**: Should be hover-only. |

---

## 3. Critical Issues Summary

1. **Sheets used instead of Dialogs** — 10+ create forms use Sheet (slide panel) instead of centered Dialog
2. **No loading states** — All edit dialogs lack loading spinner + disabled state on submit
3. **Permanently visible red delete buttons** — patients page shows filled red Trash2 always
4. **Action buttons not hover-only** — Table action buttons always visible instead of showing on row hover
5. **English text in dialogs** — roles/Index.vue, settings pages have English titles/descriptions
6. **No close button** — Most view dialogs lack the X close button
7. **Titles not action-oriented** — "تفاصيل القسم" instead of "عرض تفاصيل القسم"
8. **`isQuickAddOpen = true`** — appointments page always shows create form on load
9. **No auto-focus** — No dialog auto-focuses first input on open
10. **Destructive dialogs don't prevent overlay close** — ConfirmationDialog allows click-outside close for destructive actions
11. **Custom wrappers** — TwoFactorSetupModal, DeleteUser use custom implementations
12. **No error states per field** — Inline forms don't show per-field error borders
13. **Missing Header/Body/Footer structure** — Many dialogs don't follow the standard structure
14. **No skeleton loading** — No pages have skeleton loaders for data fetch
15. **No empty states** — Some tables show empty rows instead of proper empty state
16. **Inconsistent button variants** — `variant="clay"`, `variant="neumorphic"` used instead of standard 4 variants
17. **Icons on wrong side** — RTL should have icons on RIGHT of text, many have them on left
18. **No bulk actions bar** — No page implements bulk selection with action bar
19. **Direct fetch calls** — AddPatientDialog/EditPatientDialog use direct fetch instead of store actions

## 4. Minor Issues Summary

1. View dialogs lack close button (X)
2. Titles not action-oriented Arabic
3. Description lines missing or too long
4. Footer dividers missing
5. Body max-height not set (no scroll)
6. Input heights inconsistent (some 40px, some different)
7. Focus states not standardized
8. Error states not showing red borders
9. Required field asterisks missing
10. Field gaps inconsistent (some 16px, some different)
11. Status badges use filled colors instead of light bg + text
12. Table headers not uppercase
13. Table row heights inconsistent
14. No zebra striping (correct per spec, but some have it)
15. Search inputs not 260px fixed width
16. Pagination not standardized
17. Stat cards have inconsistent layout
18. Chart colors not standardized to teal
19. Quick action buttons different sizes
20. Page headers missing subtitles
21. Breadcrumbs not shown on all pages
22. Section dividers missing
23. Card borders inconsistent
24. Section padding inconsistent
25. Mobile responsive issues in some tables

## 5. Files Requiring Changes

### Dialog/Modal Files:
- `resources/js/pages/patients/Index.vue` — D1, D2, D3, D4
- `resources/js/pages/doctors/Index.vue` — D5, D6, D7, D8
- `resources/js/pages/appointments/Index.vue` — D9, D10, D11, D12
- `resources/js/pages/queue/Index.vue` — D13, D14, D15
- `resources/js/pages/visits/Index.vue` — D16, D17, D18, D19
- `resources/js/pages/users/Index.vue` — D20, D21, D22, D23
- `resources/js/pages/departments/Index.vue` — D24 (FIXED), D25, D26, D27
- `resources/js/pages/roles/Index.vue` — D28, D29
- `resources/js/pages/billing/Index.vue` — D30, D31, D32, D33
- `resources/js/pages/cashbox/Index.vue` — D34, D35, D36, D37
- `resources/js/pages/expenses/Index.vue` — D38+
- `resources/js/pages/settings/Security.vue` — D39, D40
- `resources/js/pages/settings/Profile.vue` — D41

### Component Files:
- `resources/js/components/ui/dialog/` — Base wrapper needs verification
- `resources/js/components/ui/confirmation-dialog/` — Needs destructive overlay fix
- `resources/js/components/TwoFactorSetupModal.vue` — Custom modal
- `resources/js/components/DeleteUser.vue` — Custom implementation
- `resources/js/components/dialogs/` — New dialog templates (partially done)

### Button/Action Files:
- All page Index.vue files — Action button patterns need standardization
