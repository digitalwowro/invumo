<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $account = Account::query()->where('owner_user_id', $user->id)->firstOrFail();

        $this->assertSame('en', $user->language_code);
        $this->assertSame('test@example.com', $user->email_normalized);
        $this->assertSame('free', $account->plan()->firstOrFail()->code);
        $this->assertTrue(Plan::query()->where('code', 'free')->where('active', true)->exists());
    }

    public function test_registration_rejects_email_case_variants(): void
    {
        User::factory()->create(['email' => 'person@example.com']);

        $this->post(route('register.store'), [
            'name' => 'Other Person',
            'email' => 'Person@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');
    }
}
