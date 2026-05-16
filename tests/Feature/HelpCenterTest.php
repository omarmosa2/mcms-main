<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::factory()->create();
        $this->user = User::factory()->create(['clinic_id' => $this->clinic->id]);

        $role = Role::factory()->create(['name' => 'test_admin_'.uniqid(), 'clinic_id' => $this->clinic->id]);
        $this->user->roles()->attach($role, ['clinic_id' => $this->clinic->id, 'assigned_by' => $this->user->id]);
    }

    public function test_help_index_requires_authentication(): void
    {
        $response = $this->get(route('help.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_help_index_returns_inertia_response(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('help.index'));
        $response->assertOk();
    }

    public function test_help_article_requires_authentication(): void
    {
        $response = $this->get(route('help.article', ['slug' => 'getting-started']));
        $response->assertRedirect(route('login'));
    }

    public function test_help_article_returns_inertia_response(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('help.article', ['slug' => 'getting-started']));
        $response->assertOk();
    }

    public function test_help_article_includes_slug(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('help.article', ['slug' => 'getting-started']));
        $response->assertInertia(fn ($page) => $page
            ->component('help/Article')
            ->where('slug', 'getting-started'));
    }
}
