<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_requires_authentication(): void
    {
        $response = $this->get(route('patients.export'));
        $response->assertRedirect(route('login'));
    }

    public function test_import_view_requires_authentication(): void
    {
        $response = $this->get(route('patients.import'));
        $response->assertRedirect(route('login'));
    }

    public function test_import_status_requires_authentication(): void
    {
        $response = $this->get(route('patients.import.status'));
        $response->assertRedirect(route('login'));
    }
}
