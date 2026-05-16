<?php

namespace Tests\Feature\Feature\Portal;

use Tests\TestCase;

class PatientPortalAppointmentManagementTest extends TestCase
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
