<?php

namespace Tests\Feature\Diagnostics;

use App\Models\Clinic;
use App\Models\LabTestTemplate;
use App\Models\RadiologyStudyType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagnosticsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_lab_test_template(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $this->actingAs($user);

        $response = $this->postJson(route('diagnostics.lab-templates.store'), [
            'name' => 'Complete Blood Count',
            'code' => 'CBC001',
            'category' => 'hematology',
            'unit' => 'cells/uL',
            'min_reference' => 4.5,
            'max_reference' => 11.0,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('lab_test_templates', [
            'clinic_id' => $clinic->id,
            'code' => 'CBC001',
            'category' => 'hematology',
        ]);
    }

    public function test_can_list_lab_test_templates(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        LabTestTemplate::factory()->for($clinic)->count(3)->create();
        $this->actingAs($user);

        $response = $this->getJson(route('diagnostics.lab-templates.index'));

        $response->assertOk();
        $response->assertJsonCount(3, 'data.data');
    }

    public function test_can_filter_lab_templates_by_category(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        LabTestTemplate::factory()->for($clinic)->create(['category' => 'hematology']);
        LabTestTemplate::factory()->for($clinic)->create(['category' => 'chemistry']);
        $this->actingAs($user);

        $response = $this->getJson(route('diagnostics.lab-templates.index', ['category' => 'hematology']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
    }

    public function test_lab_template_is_within_reference_range(): void
    {
        $clinic = Clinic::factory()->create();
        $template = LabTestTemplate::factory()->for($clinic)->create([
            'min_reference' => 4.0,
            'max_reference' => 10.0,
        ]);

        $this->assertTrue($template->isWithinReferenceRange(7.0));
        $this->assertFalse($template->isWithinReferenceRange(12.0));
    }

    public function test_can_create_radiology_study_type(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $this->actingAs($user);

        $response = $this->postJson(route('diagnostics.radiology-study-types.store'), [
            'name' => 'Chest X-Ray',
            'code' => 'CXR001',
            'description' => 'Standard chest radiograph',
            'requires_contrast' => false,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('radiology_study_types', [
            'clinic_id' => $clinic->id,
            'code' => 'CXR001',
        ]);
    }

    public function test_can_list_radiology_study_types(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        RadiologyStudyType::factory()->for($clinic)->count(3)->create();
        $this->actingAs($user);

        $response = $this->getJson(route('diagnostics.radiology-study-types.index'));

        $response->assertOk();
        $response->assertJsonCount(3, 'data.data');
    }

    public function test_can_filter_radiology_study_types_requiring_contrast(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        RadiologyStudyType::factory()->for($clinic)->create(['requires_contrast' => true]);
        RadiologyStudyType::factory()->for($clinic)->create(['requires_contrast' => false]);
        $this->actingAs($user);

        $response = $this->getJson(route('diagnostics.radiology-study-types.index'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data.data');
    }

    public function test_diagnostics_are_scoped_to_clinic(): void
    {
        $clinic1 = Clinic::factory()->create();
        $clinic2 = Clinic::factory()->create();

        LabTestTemplate::factory()->for($clinic1)->create(['code' => 'CLINIC1-LAB']);
        LabTestTemplate::factory()->for($clinic2)->create(['code' => 'CLINIC2-LAB']);

        $user = User::factory()->for($clinic1)->create();
        $this->actingAs($user);

        $response = $this->getJson(route('diagnostics.lab-templates.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.code', 'CLINIC1-LAB');
    }

    public function test_lab_template_active_scope(): void
    {
        $clinic = Clinic::factory()->create();
        LabTestTemplate::factory()->for($clinic)->create(['is_active' => true]);
        LabTestTemplate::factory()->for($clinic)->create(['is_active' => false]);

        $active = LabTestTemplate::query()->forClinic($clinic->id)->active()->get();
        $this->assertCount(1, $active);
    }

    public function test_radiology_study_type_active_scope(): void
    {
        $clinic = Clinic::factory()->create();
        RadiologyStudyType::factory()->for($clinic)->create(['is_active' => true]);
        RadiologyStudyType::factory()->for($clinic)->create(['is_active' => false]);

        $active = RadiologyStudyType::query()->forClinic($clinic->id)->active()->get();
        $this->assertCount(1, $active);
    }
}
