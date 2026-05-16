<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_docs_page_is_accessible(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('api.docs'));

        $response->assertOk();
    }

    public function test_api_docs_spec_endpoint_returns_yaml(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('api.docs.spec'));

        $response->assertOk();
        $response->assertHeaderContains('Content-Type', 'text/yaml');
    }

    public function test_openapi_spec_file_exists(): void
    {
        $this->assertTrue(File::exists(base_path('docs/openapi.yaml')));
    }

    public function test_openapi_spec_has_required_fields(): void
    {
        $content = File::get(base_path('docs/openapi.yaml'));

        $this->assertStringContainsString('openapi:', $content);
        $this->assertStringContainsString('info:', $content);
        $this->assertStringContainsString('title: MCMS API', $content);
        $this->assertStringContainsString('paths:', $content);
        $this->assertStringContainsString('components:', $content);
    }

    public function test_openapi_spec_covers_all_modules(): void
    {
        $content = File::get(base_path('docs/openapi.yaml'));

        $requiredTags = [
            'Patients',
            'Appointments',
            'Queue',
            'Visits',
            'Billing',
            'Financial',
            'Inventory',
            'Diagnostics',
            'Departments',
            'Doctors',
            'Users',
            'Roles',
            'Expenses',
            'Cashbox',
            'Salaries',
            'Reports',
            'Monitoring',
            'Settings',
        ];

        foreach ($requiredTags as $tag) {
            $this->assertStringContainsString($tag, $content, "Missing tag: {$tag}");
        }
    }

    public function test_openapi_spec_has_security_schemes(): void
    {
        $content = File::get(base_path('docs/openapi.yaml'));

        $this->assertStringContainsString('securitySchemes:', $content);
        $this->assertStringContainsString('bearerAuth:', $content);
    }

    public function test_openapi_spec_has_schemas(): void
    {
        $content = File::get(base_path('docs/openapi.yaml'));

        $requiredSchemas = ['Patient', 'QueueEntry', 'Payment', 'PaginationMeta'];

        foreach ($requiredSchemas as $schema) {
            $this->assertStringContainsString($schema.':', $content, "Missing schema: {$schema}");
        }
    }

    public function test_api_docs_route_is_registered(): void
    {
        $this->assertNotNull(route('api.docs'));
        $this->assertNotNull(route('api.docs.spec'));
    }
}
