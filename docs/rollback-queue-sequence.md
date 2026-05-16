# Rollback Procedure: Queue Number Sequence

## Overview

This document describes how to rollback the queue number sequence implementation if needed.

## What Was Changed

### 1. Database Schema
- **New Table:** `queue_number_seq`
  - Stores sequence counters per clinic per date
  - Ensures unique queue number allocation

### 2. Models
- **New Model:** `app/Models/QueueNumberSequence.php`
  - Manages sequence table operations

### 3. Actions
- **Modified:** `app/Actions/Queue/EnqueuePatientAction.php`
  - Changed from `MAX(queue_number) + 1` to sequence-based allocation
  - Removed retry logic (no longer needed)

### 4. Tests
- **New Test File:** `tests/Feature/QueueNumberConcurrencyTest.php`
  - Comprehensive concurrency tests

## Rollback Steps

### Step 1: Revert Code Changes

#### Restore EnqueuePatientAction.php
```bash
git checkout HEAD -- app/Actions/Queue/EnqueuePatientAction.php
```

Or manually restore the original implementation that uses `MAX() + 1` with retry logic.

#### Remove QueueNumberSequence Model
```bash
rm app/Models/QueueNumberSequence.php
```

#### Remove Test File (Optional)
```bash
rm tests/Feature/QueueNumberConcurrencyTest.php
```

### Step 2: Rollback Database Migration

```bash
php artisan migrate:rollback --step=1
```

Or specifically drop the sequence table:

```bash
php artisan tinker
>>> Schema::dropIfExists('queue_number_seq');
```

### Step 3: Verify Rollback

1. **Check table is dropped:**
```bash
php artisan tinker
>>> DB::table('queue_number_seq')->exists();
// Should throw "table doesn't exist" error
```

2. **Test queue enrollment:**
```bash
php artisan tinker
>>> $action = app(\App\Actions\Queue\EnqueuePatientAction::class);
>>> $entry = $action->handle(1, 1, ['patient_id' => 1, 'queue_date' => '2026-04-22']);
// Should work with old MAX() + 1 logic
```

3. **Run existing queue tests:**
```bash
php artisan test --filter QueueEntry
```

## Data Impact

- **No data loss:** Existing `queue_entries` table is unaffected
- **Sequence table:** Only contains sequence counters, safe to drop
- **Queue numbers:** Existing queue numbers remain valid

## Re-implementation

If you need to re-implement later:

```bash
php artisan migrate
```

Ensure `QueueNumberSequence` model is restored and `EnqueuePatientAction` uses the sequence-based approach.

## Troubleshooting

### Issue: Migration rollback fails
**Solution:** Manually drop the table:
```sql
DROP TABLE IF EXISTS queue_number_seq;
```

### Issue: Queue numbers duplicate after rollback
**Solution:** This indicates the old code wasn't properly restored. Re-check `EnqueuePatientAction.php` for the `MAX() + 1` logic with retry.

### Issue: Tests fail after rollback
**Solution:** Remove or update `QueueNumberConcurrencyTest.php` as it depends on the sequence model.

## Contact

For questions or issues with this rollback, contact the backend team.

---
**Date Created:** 2026-04-22  
**Version:** 1.0
