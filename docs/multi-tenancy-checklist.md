# Multi-Tenancy Security Code Review Checklist

## Overview

This checklist ensures all code changes maintain proper multi-tenancy isolation. Every PR that touches models, controllers, or actions MUST complete this checklist.

## Required Checks

### 1. Model Access Patterns

- [ ] **Direct queries must use `->forClinic($clinicId)`**
  ```php
  // ❌ WRONG - bypasses clinic scoping
  $patient = Patient::find($patientId);
  $patient->invoices;

  // ✅ CORRECT - explicit scoping
  $patient = Patient::query()->forClinic($clinicId)->find($patientId);
  ```

- [ ] **When creating records, clinic_id must be set**
  ```php
  // ✅ CORRECT - clinic_id explicitly set
  Patient::create(['clinic_id' => $clinicId, ...]);

  // ✅ ALSO CORRECT - auto-set via trait (when auth available)
  // The HasClinic trait auto-sets clinic_id from auth()->user()->clinic_id
  Patient::create([...]);
  ```

- [ ] **Relationships must be scoped when accessed**
  ```php
  // ❌ WRONG - may leak data
  $patient->invoices;

  // ✅ CORRECT - scope relationship query
  $patient->invoices()->forClinic($clinicId)->get();
  ```

### 2. Action Validation

- [ ] **Actions MUST receive clinicId as parameter**
  ```php
  // ❌ WRONG - no clinic context
  public function handle(int $patientId): Patient

  // ✅ CORRECT - clinic context provided
  public function handle(int $clinicId, int $patientId): Patient
  ```

- [ ] **All queries in Actions must be scoped**
  ```php
  // ✅ All queries scoped
  $patient = Patient::query()
      ->forClinic($clinicId)
      ->findOrFail($patientId);
  ```

### 3. Controller Validation

- [ ] **Controllers must get clinicId from authenticated user**
  ```php
  // ✅ CORRECT
  $clinicId = $request->user()?->clinic_id;
  if ($clinicId === null) {
      abort(403, 'Clinic context required');
  }
  ```

- [ ] **Route model binding should be avoided for multi-tenant models**
  ```php
  // ❌ RISKY - route model binding bypasses clinic scoping
  public function show(Patient $patient)

  // ✅ SAFER - explicit lookup with scoping
  public function show(Request $request, int $patientId)
  ```

### 4. Middleware Protection

- [ ] **Protected routes must use EnsureUserHasPermission middleware**
  ```php
  Route::middleware(['auth', 'can:patient.manage'])->group(...)
  ```

- [ ] **API routes should validate clinic context**
  ```php
  // In controller or middleware
  if ($request->user()?->clinic_id === null) {
      abort(403);
  }
  ```

### 5. Test Validation

- [ ] **Security tests MUST verify cross-clinic access fails**
  ```php
  public function test_cannot_access_other_clinic_patient(): void
  {
      $clinicA = Clinic::factory()->create();
      $clinicB = Clinic::factory()->create();
      $patientB = Patient::factory()->create(['clinic_id' => $clinicB->id]);

      // User from clinic A trying to access patient from clinic B
      $this->actingAs($this->createUserForClinic($clinicA));

      $this->expectException(ModelNotFoundException::class);
      app(ShowPatientAction::class)->handle($clinicA->id, $patientB->id, $userA->id);
  }
  ```

### 6. Common Pitfalls

| Pitfall | Prevention |
|---------|------------|
| Forgetting `->forClinic()` on query | Use static analysis, code review |
| Accessing relationships without scope | Always scope relationship queries |
| Route model binding for multi-tenant models | Use explicit ID parameter and lookup |
| Creating records without clinic_id | Use factory or explicit create with clinic_id |

## Review Questions

1. **Can a user from Clinic A access/modify data from Clinic B?**
   - If YES → **BLOCK PR**

2. **Are all queries properly scoped to the current user's clinic?**
   - If NO → **BLOCK PR**

3. **Are new Actions tested for cross-clinic access?**
   - If NO → **BLOCK PR**

## Automated Checks

Run these commands before submitting PR:

```bash
# Run multi-tenancy tests
php artisan test --filter MultiTenancySecurityTest

# Run all queue tests (includes clinic validation)
php artisan test --filter QueueEntry

# Run tests with coverage (if available)
php artisan test --coverage
```

## Security Incident Response

If you identify a multi-tenancy breach:

1. **IMMEDIATELY** escalate to security team
2. Do NOT commit or push the vulnerable code
3. Document the exact code path that allows cross-clinic access
4. Create a test that reproduces the vulnerability
5. Fix FIRST, then submit PR with fix and test

---

**Last Updated:** 2026-04-22
**Version:** 1.0
