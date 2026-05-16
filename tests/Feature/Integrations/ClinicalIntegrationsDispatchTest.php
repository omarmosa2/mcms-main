<?php

namespace Tests\Feature\Feature\Integrations;

use Tests\TestCase;

class ClinicalIntegrationsDispatchTest extends TestCase
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
