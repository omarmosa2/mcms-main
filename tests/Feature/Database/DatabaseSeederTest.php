<?php

namespace Tests\Feature\Database;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_all_domain_models(): void
    {
        $this->seed();

        $this->assertGreaterThan(0, Clinic::query()->count());
        $this->assertGreaterThan(0, User::query()->count());
        $this->assertGreaterThan(0, Patient::query()->count());
        $this->assertGreaterThan(0, Appointment::query()->count());
        $this->assertGreaterThan(0, Invoice::query()->count());
        $this->assertGreaterThan(0, InvoiceItem::query()->count());
        $this->assertGreaterThan(0, Payment::query()->count());
        $this->assertGreaterThan(0, Role::query()->count());
        $this->assertGreaterThan(0, Permission::query()->count());
        $this->assertGreaterThan(0, AuditLog::query()->count());

        $this->assertGreaterThan(0, DB::table('role_user')->count());
        $this->assertGreaterThan(0, DB::table('permission_role')->count());

        $this->assertDatabaseHas('users', [
            'email' => 'demo.admin@example.com',
        ]);

        $invoice = Invoice::query()->with(['items', 'payments'])->first();

        $this->assertNotNull($invoice);
        $this->assertGreaterThan(0, $invoice->items->count());
        $this->assertGreaterThan(0, (float) $invoice->total_amount);
        $this->assertGreaterThanOrEqual(0, (float) $invoice->balance_amount);

        $auditLog = AuditLog::query()->first();

        $this->assertNotNull($auditLog);
        $this->assertNotNull($auditLog->auditable_type);
        $this->assertNotNull($auditLog->auditable_id);
    }
}
