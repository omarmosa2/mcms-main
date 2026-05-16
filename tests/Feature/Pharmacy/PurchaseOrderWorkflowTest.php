<?php

namespace Tests\Feature\Feature\Pharmacy;

use Tests\TestCase;

class PurchaseOrderWorkflowTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
