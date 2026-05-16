<?php

namespace Tests\Feature\Inventory;

use App\Models\Clinic;
use App\Models\DrugBatch;
use App\Models\InventoryReturn;
use App\Models\PharmacyDrug;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_adjust_stock_upwards(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $drug = PharmacyDrug::factory()->for($clinic)->create(['current_stock' => 10]);
        $this->actingAs($user);

        $response = $this->postJson(route('inventory.adjust-stock'), [
            'drug_id' => $drug->id,
            'quantity_change' => 50,
            'reason' => 'received',
        ]);

        $response->assertStatus(201);
        $drug->refresh();
        $this->assertEquals(60, $drug->current_stock);
        $this->assertDatabaseHas('stock_adjustments', [
            'pharmacy_drug_id' => $drug->id,
            'quantity_change' => 50,
            'reason' => 'received',
        ]);
    }

    public function test_can_adjust_stock_downwards(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $drug = PharmacyDrug::factory()->for($clinic)->create(['current_stock' => 100]);
        $this->actingAs($user);

        $response = $this->postJson(route('inventory.adjust-stock'), [
            'drug_id' => $drug->id,
            'quantity_change' => -30,
            'reason' => 'damaged',
        ]);

        $response->assertStatus(201);
        $drug->refresh();
        $this->assertEquals(70, $drug->current_stock);
    }

    public function test_stock_cannot_go_below_zero(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $drug = PharmacyDrug::factory()->for($clinic)->create(['current_stock' => 5]);
        $this->actingAs($user);

        $this->postJson(route('inventory.adjust-stock'), [
            'drug_id' => $drug->id,
            'quantity_change' => -10,
            'reason' => 'expired',
        ]);

        $drug->refresh();
        $this->assertEquals(0, $drug->current_stock);
    }

    public function test_can_create_drug_batch(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $drug = PharmacyDrug::factory()->for($clinic)->create(['current_stock' => 10]);
        $this->actingAs($user);

        $response = $this->postJson(route('inventory.batches.store'), [
            'drug_id' => $drug->id,
            'batch_number' => 'BATCH-001',
            'quantity' => 100,
            'expiry_date' => now()->addMonths(12)->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('drug_batches', [
            'pharmacy_drug_id' => $drug->id,
            'batch_number' => 'BATCH-001',
            'quantity' => 100,
        ]);
        $drug->refresh();
        $this->assertEquals(110, $drug->current_stock);
    }

    public function test_can_list_batches(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $drug = PharmacyDrug::factory()->for($clinic)->create();
        DrugBatch::factory()->for($clinic)->for($drug, 'drug')->count(3)->create();
        $this->actingAs($user);

        $response = $this->getJson(route('inventory.batches.index'));

        $response->assertOk();
        $response->assertJsonCount(3, 'data.data');
    }

    public function test_can_filter_batches_not_expired(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $drug = PharmacyDrug::factory()->for($clinic)->create();
        DrugBatch::factory()->for($clinic)->for($drug, 'drug')->create();
        DrugBatch::factory()->for($clinic)->for($drug, 'drug')->expired()->create();
        $this->actingAs($user);

        $response = $this->getJson(route('inventory.batches.index', ['not_expired' => true]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
    }

    public function test_can_consume_from_batches_fifo(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $drug = PharmacyDrug::factory()->for($clinic)->create(['current_stock' => 200]);

        $batch1 = DrugBatch::factory()->for($clinic)->for($drug, 'drug')->create([
            'quantity' => 100,
            'expiry_date' => now()->addMonths(6),
        ]);
        $batch2 = DrugBatch::factory()->for($clinic)->for($drug, 'drug')->create([
            'quantity' => 100,
            'expiry_date' => now()->addMonths(12),
        ]);

        $this->actingAs($user);

        $response = $this->postJson(route('inventory.batches.consume'), [
            'drug_id' => $drug->id,
            'quantity' => 150,
        ]);

        $response->assertOk();
        $batch1->refresh();
        $batch2->refresh();
        $this->assertEquals(0, $batch1->quantity);
        $this->assertEquals(50, $batch2->quantity);
        $drug->refresh();
        $this->assertEquals(50, $drug->current_stock);
    }

    public function test_cannot_consume_more_than_available(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $drug = PharmacyDrug::factory()->for($clinic)->create(['current_stock' => 10]);
        DrugBatch::factory()->for($clinic)->for($drug, 'drug')->create(['quantity' => 10]);
        $this->actingAs($user);

        $response = $this->postJson(route('inventory.batches.consume'), [
            'drug_id' => $drug->id,
            'quantity' => 20,
        ]);

        $response->assertStatus(422);
    }

    public function test_can_process_inventory_return(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $drug = PharmacyDrug::factory()->for($clinic)->create(['current_stock' => 100]);
        $this->actingAs($user);

        $response = $this->postJson(route('inventory.returns.store'), [
            'drug_id' => $drug->id,
            'quantity' => 10,
            'reason' => 'expired',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('inventory_returns', [
            'pharmacy_drug_id' => $drug->id,
            'quantity' => 10,
            'reason' => 'expired',
        ]);
        $drug->refresh();
        $this->assertEquals(90, $drug->current_stock);
    }

    public function test_can_list_stock_adjustments(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $drug = PharmacyDrug::factory()->for($clinic)->create();
        StockAdjustment::factory()->for($clinic)->for($drug, 'drug')->for($user, 'adjustedBy')->count(3)->create();
        $this->actingAs($user);

        $response = $this->getJson(route('inventory.adjustments.index'));

        $response->assertOk();
        $response->assertJsonCount(3, 'data.data');
    }

    public function test_can_list_inventory_returns(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $drug = PharmacyDrug::factory()->for($clinic)->create();
        InventoryReturn::factory()->for($clinic)->for($drug, 'drug')->count(3)->create();
        $this->actingAs($user);

        $response = $this->getJson(route('inventory.returns.index'));

        $response->assertOk();
        $response->assertJsonCount(3, 'data.data');
    }

    public function test_batch_is_expired(): void
    {
        $clinic = Clinic::factory()->create();
        $drug = PharmacyDrug::factory()->for($clinic)->create();
        $batch = DrugBatch::factory()->for($clinic)->for($drug, 'drug')->expired()->create();

        $this->assertTrue($batch->isExpired());
    }

    public function test_batch_remaining_days(): void
    {
        $clinic = Clinic::factory()->create();
        $drug = PharmacyDrug::factory()->for($clinic)->create();
        $batch = DrugBatch::factory()->for($clinic)->for($drug, 'drug')->create([
            'expiry_date' => now()->addDays(30),
        ]);

        $this->assertGreaterThanOrEqual(29, $batch->remainingDays());
    }

    public function test_inventory_is_scoped_to_clinic(): void
    {
        $clinic1 = Clinic::factory()->create();
        $clinic2 = Clinic::factory()->create();

        $drug1 = PharmacyDrug::factory()->for($clinic1)->create(['current_stock' => 100]);
        $drug2 = PharmacyDrug::factory()->for($clinic2)->create(['current_stock' => 50]);

        DrugBatch::factory()->for($clinic1)->for($drug1, 'drug')->create(['batch_number' => 'CLINIC1-001']);
        DrugBatch::factory()->for($clinic2)->for($drug2, 'drug')->create(['batch_number' => 'CLINIC2-001']);

        $user = User::factory()->for($clinic1)->create();
        $this->actingAs($user);

        $response = $this->getJson(route('inventory.batches.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.batch_number', 'CLINIC1-001');
    }
}
