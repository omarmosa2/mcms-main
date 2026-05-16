# Rollback Procedure: Database Constraints

## Changes Made

### 1. Migration (`2026_04_22_091134_add_financial_constraints.php`)
- MySQL/MariaDB CHECK constraints for financial amounts
- Unsigned foreign key constraints for clinic_id, patient_id, etc.

### 2. Model Validation - Invoice Model
- `validateFinancialAmounts()` method on Invoice model
- Prevents negative total_amount, paid_amount, balance_amount
- Prevents paid_amount exceeding total_amount
- Prevents balance_amount exceeding total_amount

### 3. Model Validation - Payment Model
- `validateFinancialAmounts()` method on Payment model
- Prevents non-positive amount (must be > 0)
- Prevents negative refund_amount
- Prevents refund_amount exceeding amount

## Rollback Procedure

### Step 1: Revert Migration
```bash
php artisan migrate:rollback --step=1
```

### Step 2: Remove Model Validation
Remove the `booted()` method and `validateFinancialAmounts()` method from:

**Invoice Model:**
- Remove `use Illuminate\Validation\Rule;` if only used for this
- Remove the entire `booted()` method
- Remove `validateFinancialAmounts()` method

**Payment Model:**
- Remove the entire `booted()` method
- Remove `validateFinancialAmounts()` method

### Step 3: Revert Foreign Key Types
The migration changes bigInteger to unsignedBigInteger for foreign keys. To revert:
```php
Schema::table('invoices', function (Blueprint $table) {
    $table->bigInteger('clinic_id')->change();
    $table->bigInteger('patient_id')->change();
    $table->bigInteger('visit_id')->nullable()->change();
});
```

## Testing After Rollback

Ensure these tests still pass:
- Invoice factory creates valid invoices
- Payment factory creates valid payments
- No exceptions on normal operations
