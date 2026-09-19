<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\FacebookProvider;
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
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
        $this->assertSame('https://example.com/avatar.jpg', $user->facebook_avatar_url);
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('profile.edit'))->assertOk()->assertSee('https://example.com/avatar.jpg');
    }

    public function test_facebook_callback_uses_the_configured_ca_bundle(): void
    {
        config()->set('services.meta.ca_bundle', 'C:/certificates/meta-ca.pem');
        $this->mockFacebookUser('facebook-tls', 'citoyen.tls@gmail.com');

        $this->get(route('facebook.callback'))
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
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

    public function test_facebook_login_verifies_a_previously_created_account_with_the_same_email(): void
    {
        $citizen = User::factory()->unverified()->create([
            'email' => 'citoyen.facebook@gmail.com',
            'facebook_id' => 'facebook-existing',
        ]);
        $this->mockFacebookUser('facebook-existing', $citizen->email);

        $this->get(route('facebook.callback'))
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertTrue($citizen->fresh()->hasVerifiedEmail());
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_facebook_login_does_not_verify_a_different_spotlight_email(): void
    {
        $citizen = User::factory()->unverified()->create([
            'email' => 'nouvelle.adresse@gmail.com',
            'facebook_id' => 'facebook-existing',
        ]);
        $this->mockFacebookUser('facebook-existing', 'ancienne.adresse@gmail.com');

        $this->get(route('facebook.callback'))
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertFalse($citizen->fresh()->hasVerifiedEmail());
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
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

    public function test_facebook_cannot_replace_an_existing_linked_identity(): void
    {
        $citizen = User::factory()->create([
            'email' => 'citoyen@gmail.com',
            'facebook_id' => 'facebook-original',
        ]);
        $this->mockFacebookUser('facebook-different', $citizen->email);

        $this->get(route('facebook.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame('facebook-original', $citizen->refresh()->facebook_id);
    }

    public function test_blocked_citizen_is_not_linked_or_authenticated(): void
    {
        $citizen = User::factory()->create([
            'email' => 'citoyen@gmail.com',
            'is_blocked' => true,
        ]);
        $this->mockFacebookUser('facebook-blocked', $citizen->email);

        $this->get(route('facebook.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull($citizen->refresh()->facebook_id);
    }

    private function mockFacebookUser(string $id, string $email): void
    {
        $facebookUser = Mockery::mock(SocialiteUser::class);
        $facebookUser->shouldReceive('getId')->andReturn($id);
        $facebookUser->shouldReceive('getEmail')->andReturn($email);
        $facebookUser->shouldReceive('getName')->andReturn('Utilisateur Facebook');
        $facebookUser->shouldReceive('getNickname')->andReturnNull();
        $facebookUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

        $provider = Mockery::mock(FacebookProvider::class);
        $provider->shouldReceive('setHttpClient')->once()
            ->withArgs(fn (Client $client) => $client->getConfig('verify') === (config('services.meta.ca_bundle') ?: true))
            ->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($facebookUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('facebook')
            ->andReturn($provider);
    }
}
