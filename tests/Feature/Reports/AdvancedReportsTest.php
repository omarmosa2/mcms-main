<?php

namespace Tests\Feature\Feature\Reports;

use Tests\TestCase;

class AdvancedReportsTest extends TestCase
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
