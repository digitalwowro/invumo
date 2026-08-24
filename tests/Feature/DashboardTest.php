<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use DatabaseMigrations;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_legacy_dashboard_redirects_authenticated_users_to_the_company_landing()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('home'));
    }

    public function test_dashboard_receives_laravel_resolved_romanian_strings(): void
    {
        $user = User::factory()->create(['language_code' => 'ro']);
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $user, 'Exemplu SRL');

        $this->actingAs($user)
            ->get(route('companies.dashboard', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('translations.title', 'Panou de control')
                ->where('company.name', 'Exemplu SRL'));
    }
}
