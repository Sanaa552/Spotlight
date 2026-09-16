<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class FacebookAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_facebook_can_create_a_citizen_account(): void
    {
        $this->mockFacebookUser('facebook-1', 'citoyen.facebook@gmail.com');

        $this->get(route('facebook.callback'))
            ->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'citoyen.facebook@gmail.com')->firstOrFail();

        $this->assertTrue($user->isCitoyen());
        $this->assertAuthenticatedAs($user);
    }

    public function test_facebook_can_connect_an_existing_citizen(): void
    {
        $citizen = User::factory()->create([
            'email' => 'citoyen@gmail.com',
            'role' => Role::Citoyen,
        ]);
        $this->mockFacebookUser('facebook-2', $citizen->email);

        $this->get(route('facebook.callback'))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($citizen);
        $this->assertSame('facebook-2', $citizen->refresh()->facebook_id);
    }

    public function test_facebook_cannot_connect_a_moderator(): void
    {
        $moderator = User::factory()->create([
            'email' => 'moderateur@gmail.com',
            'role' => Role::Moderateur,
        ]);
        $this->mockFacebookUser('facebook-3', $moderator->email);

        $this->get(route('facebook.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull($moderator->refresh()->facebook_id);
    }

    public function test_facebook_cannot_connect_the_super_administrator(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@gmail.com',
            'role' => Role::Administrateur,
        ]);
        $this->mockFacebookUser('facebook-4', $admin->email);

        $this->get(route('facebook.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull($admin->refresh()->facebook_id);
    }

    private function mockFacebookUser(string $id, string $email): void
    {
        $facebookUser = Mockery::mock(SocialiteUser::class);
        $facebookUser->shouldReceive('getId')->andReturn($id);
        $facebookUser->shouldReceive('getEmail')->andReturn($email);
        $facebookUser->shouldReceive('getName')->andReturn('Utilisateur Facebook');
        $facebookUser->shouldReceive('getNickname')->andReturnNull();
        $facebookUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($facebookUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('facebook')
            ->andReturn($provider);
    }
}
