<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SpotlightVerifyEmail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class FacebookAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_facebook_citizen_completes_phone_before_dashboard(): void
    {
        Notification::fake();
        $this->mockFacebookUser('facebook-1', 'citoyen.facebook@gmail.com');

        $this->get(route('facebook.callback'))->assertRedirect(route('facebook.profile.edit'));

        $user = User::where('facebook_id', 'facebook-1')->firstOrFail();
        $this->assertTrue($user->isCitoyen());
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame(User::EMAIL_SOURCE_FACEBOOK, $user->email_source);
        $this->assertSame('https://example.com/avatar.jpg', $user->facebook_avatar_url);
        Notification::assertNotSentTo($user, SpotlightVerifyEmail::class);
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertRedirect(route('facebook.profile.edit'));
        $this->get(route('declarations.create'))->assertRedirect(route('facebook.profile.edit'));
        $this->get(route('profile.edit'))->assertOk();
        $this->get(route('facebook.profile.edit'))->assertOk()
            ->assertSee('Indiquez de préférence votre numéro WhatsApp.')
            ->assertDontSee('name="email"', false)
            ->assertDontSee('citoyen.facebook@gmail.com');

        $this->patch(route('facebook.profile.update'), ['telephone' => '+237 690 000 000'])
            ->assertRedirect(route('dashboard'));
        $this->assertSame('+237690000000', $user->fresh()->telephone);
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('facebook.profile.edit'))->assertRedirect(route('dashboard'));
    }

    public function test_facebook_without_email_requires_email_then_uses_verification(): void
    {
        Notification::fake();
        $this->mockFacebookUser('facebook-no-email', null);

        $this->get(route('facebook.callback'))->assertRedirect(route('facebook.profile.edit'));
        $user = User::where('facebook_id', 'facebook-no-email')->firstOrFail();
        $this->assertNull($user->email);
        $this->get(route('facebook.profile.edit'))->assertOk()->assertSee('Adresse e-mail');

        $this->patch(route('facebook.profile.update'), [
            'email' => 'nouveau@example.com',
            'telephone' => '+237690000000',
        ])->assertRedirect(route('verification.notice'));

        $this->assertSame('nouveau@example.com', $user->fresh()->email);
        $this->assertSame(User::EMAIL_SOURCE_MANUAL, $user->fresh()->email_source);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Notification::assertSentTo($user, SpotlightVerifyEmail::class);
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk();

        $user->markEmailAsVerified();
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
    }

    public function test_existing_phone_is_preserved_when_only_facebook_email_is_missing(): void
    {
        Notification::fake();
        $citizen = User::factory()->create([
            'facebook_id' => 'facebook-email-later',
            'email' => null,
            'email_verified_at' => null,
            'telephone' => '+237690000000',
        ]);
        $this->mockFacebookUser('facebook-email-later', null);

        $this->get(route('facebook.callback'))->assertRedirect(route('facebook.profile.edit'));
        $this->get(route('facebook.profile.edit'))->assertOk()
            ->assertSee('name="email"', false)
            ->assertDontSee('name="telephone"', false);
        $this->patch(route('facebook.profile.update'), [
            'email' => 'manuel@example.com',
            'telephone' => '+237699999999',
        ])->assertRedirect(route('verification.notice'));

        $this->assertSame('+237690000000', $citizen->fresh()->telephone);
        $this->assertSame(User::EMAIL_SOURCE_MANUAL, $citizen->fresh()->email_source);
        Notification::assertSentTo($citizen, SpotlightVerifyEmail::class);
    }

    public function test_legacy_facebook_account_is_verified_when_facebook_now_provides_its_email(): void
    {
        Notification::fake();
        $citizen = User::factory()->unverified()->create([
            'facebook_id' => 'facebook-legacy',
            'email' => 'legacy@example.com',
            'email_source' => null,
            'telephone' => null,
        ]);
        $this->mockFacebookUser('facebook-legacy', 'legacy@example.com');

        $this->get(route('facebook.callback'))->assertRedirect(route('facebook.profile.edit'));
        $this->assertTrue($citizen->fresh()->hasVerifiedEmail());
        $this->assertSame(User::EMAIL_SOURCE_FACEBOOK, $citizen->fresh()->email_source);
        $this->get(route('facebook.profile.edit'))->assertOk()->assertDontSee('name="email"', false);
        Notification::assertNotSentTo($citizen, SpotlightVerifyEmail::class);
    }

    public function test_existing_facebook_account_without_email_stores_it_when_facebook_supplies_it_later(): void
    {
        $citizen = User::factory()->unverified()->create([
            'facebook_id' => 'facebook-later',
            'email' => null,
            'telephone' => null,
        ]);
        $this->mockFacebookUser('facebook-later', 'later@example.com');

        $this->get(route('facebook.callback'))->assertRedirect(route('facebook.profile.edit'));
        $this->assertSame('later@example.com', $citizen->fresh()->email);
        $this->assertTrue($citizen->fresh()->hasVerifiedEmail());
        $this->assertSame(User::EMAIL_SOURCE_FACEBOOK, $citizen->fresh()->email_source);
    }

    public function test_manually_entered_email_is_not_verified_by_a_later_facebook_login(): void
    {
        $citizen = User::factory()->unverified()->create([
            'facebook_id' => 'facebook-manual',
            'email' => 'manual@example.com',
            'email_source' => User::EMAIL_SOURCE_MANUAL,
            'telephone' => '+237690000000',
        ]);
        $this->mockFacebookUser('facebook-manual', 'manual@example.com');

        $this->get(route('facebook.callback'))->assertRedirect(route('dashboard'));
        $this->assertFalse($citizen->fresh()->hasVerifiedEmail());
        $this->assertSame(User::EMAIL_SOURCE_MANUAL, $citizen->fresh()->email_source);
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_missing_email_cannot_use_an_existing_spotlight_address(): void
    {
        $existing = User::factory()->create(['email' => 'existant@example.com']);
        $this->mockFacebookUser('facebook-no-email', null);
        $this->get(route('facebook.callback'));

        $this->patch(route('facebook.profile.update'), [
            'email' => 'existant@example.com',
            'telephone' => '+237690000000',
        ])->assertSessionHasErrors('email');
        $this->assertNull(User::where('facebook_id', 'facebook-no-email')->firstOrFail()->email);
        $this->assertNull($existing->fresh()->facebook_id);
    }

    public function test_invalid_phone_is_rejected_without_losing_the_profile(): void
    {
        $this->mockFacebookUser('facebook-phone', 'phone@example.com');
        $this->get(route('facebook.callback'));

        $this->patch(route('facebook.profile.update'), ['telephone' => '690000000'])
            ->assertSessionHasErrors('telephone');
        $this->get(route('dashboard'))->assertRedirect(route('facebook.profile.edit'));
    }

    public function test_existing_linked_citizen_with_complete_profile_enters_normally(): void
    {
        $citizen = User::factory()->create([
            'email' => 'citoyen@gmail.com',
            'facebook_id' => 'facebook-2',
            'telephone' => '+237690000000',
        ]);
        $this->mockFacebookUser('facebook-2', $citizen->email);

        $this->get(route('facebook.callback'))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($citizen);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_an_existing_email_account_is_not_auto_linked(): void
    {
        $citizen = User::factory()->create(['email' => 'citoyen@gmail.com']);
        $this->mockFacebookUser('facebook-unlinked', $citizen->email);

        $this->get(route('facebook.callback'))->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull($citizen->fresh()->facebook_id);
        $this->assertSame(1, User::count());
    }

    public function test_facebook_cannot_link_to_an_internal_or_blocked_account(): void
    {
        foreach ([Role::Moderateur, Role::Administrateur] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->mockFacebookUser('facebook-'.$user->id, $user->email);
            $this->get(route('facebook.callback'))->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->assertNull($user->fresh()->facebook_id);
        }

        $blocked = User::factory()->create(['facebook_id' => 'facebook-blocked', 'is_blocked' => true]);
        $this->mockFacebookUser('facebook-blocked', $blocked->email);
        $this->get(route('facebook.callback'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_only_facebook_citizens_can_use_completion_page(): void
    {
        $ordinary = User::factory()->create(['telephone' => null]);
        $this->actingAs($ordinary)->get(route('facebook.profile.edit'))->assertForbidden();
        $this->actingAs($ordinary)->get(route('dashboard'))->assertOk();
        $moderator = User::factory()->create(['role' => Role::Moderateur]);
        $this->actingAs($moderator)->get(route('facebook.profile.edit'))->assertForbidden();
    }

    public function test_incomplete_facebook_citizen_can_log_out(): void
    {
        $citizen = User::factory()->create(['facebook_id' => 'facebook-logout', 'telephone' => null]);
        $this->actingAs($citizen)->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_existing_password_account_must_confirm_password_before_facebook_link(): void
    {
        $citizen = User::factory()->create();
        $this->actingAs($citizen)->get(route('facebook.link.redirect'))
            ->assertRedirect(route('password.confirm'));
        $this->assertNull($citizen->fresh()->facebook_id);
    }

    public function test_confirmed_password_account_can_link_facebook_without_changing_its_email(): void
    {
        config()->set('services.facebook.client_id', 'test-client-id');
        config()->set('services.facebook.client_secret', 'test-client-secret');
        $citizen = User::factory()->create(['email' => 'original@example.com']);

        $response = $this->actingAs($citizen)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('facebook.link.redirect'));
        $response->assertRedirect();
        $query = [];
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame(config('services.facebook.redirect'), $query['redirect_uri']);
        $this->assertNotEmpty($query['state']);
        $this->assertSame($citizen->id, session('facebook_link_user_id'));

        $this->mockFacebookUser('facebook-linked', 'autre@example.com');
        $this->get(route('facebook.callback'))->assertRedirect(route('profile.edit'));
        $this->assertSame('facebook-linked', $citizen->fresh()->facebook_id);
        $this->assertSame('original@example.com', $citizen->fresh()->email);
        $this->assertSame(1, User::count());
    }

    public function test_facebook_link_rejects_an_identity_already_owned_by_another_user(): void
    {
        $owner = User::factory()->create(['facebook_id' => 'facebook-owned']);
        $citizen = User::factory()->create();
        $this->actingAs($citizen)->withSession(['facebook_link_user_id' => $citizen->id]);
        $this->mockFacebookUser('facebook-owned', $owner->email);

        $this->get(route('facebook.callback'))->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('facebook');
        $this->assertNull($citizen->fresh()->facebook_id);
    }

    public function test_facebook_redirect_uses_only_identity_scopes_and_canonical_callback(): void
    {
        config()->set('services.facebook.client_id', 'test-client-id');
        config()->set('services.facebook.client_secret', 'test-client-secret');
        $url = config('services.facebook.redirect');

        $response = $this->get(route('facebook.redirect'));
        $response->assertRedirect();
        $query = [];
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame($url, $query['redirect_uri']);
        $this->assertStringNotContainsString('//auth/', $url);
        $this->assertStringContainsString('/'.config('services.meta.graph_version').'/dialog/oauth', $response->headers->get('Location'));
        $this->assertSame(['public_profile', 'email'], explode(',', $query['scope']));
        $this->assertNotEmpty($query['state']);
        $response->assertSessionHas('state');
    }

    public function test_callback_without_matching_state_is_rejected(): void
    {
        $this->get(route('facebook.callback', ['code' => 'fake-code', 'state' => 'wrong']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_temporary_mobile_diagnostic_tracks_departure_and_invalid_state_without_logging_oauth_values(): void
    {
        config()->set('services.facebook.client_id', 'test-client-id');
        config()->set('services.facebook.client_secret', 'test-client-secret');
        config()->set('services.facebook.mobile_diagnostic_enabled', true);
        Log::spy();

        $this->get(route('facebook.redirect'))->assertRedirect()->assertSessionHas('state');
        $trace = session('facebook_login_trace');
        $this->assertNotEmpty($trace);
        Log::shouldHaveReceived('info')->with('Diagnostic depart OAuth Facebook Spotlight', Mockery::on(
            fn (array $context) => $context['trace'] === $trace
                && $context['purpose'] === 'login'
                && $context['session_state_present'] === true
                && is_bool($context['request_secure'])
                && count($context) === 4
        ))->once();

        $this->get(route('facebook.callback', ['code' => 'fake-code', 'state' => 'wrong']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');
        Log::shouldHaveReceived('info')->with('Diagnostic retour OAuth Facebook Spotlight', Mockery::on(
            fn (array $context) => $context['trace'] === $trace
                && $context['session_state_present'] === true
                && is_bool($context['session_cookie_present'])
                && $context['state_parameter_present'] === true
                && $context['code_present'] === true
                && $context['error_present'] === false
                && is_bool($context['request_secure'])
                && count($context) === 7
        ))->once();
        $this->assertGuest();
    }

    public function test_facebook_callback_uses_the_configured_ca_bundle(): void
    {
        config()->set('services.meta.ca_bundle', 'C:/certificates/meta-ca.pem');
        $this->mockFacebookUser('facebook-tls', 'citoyen.tls@gmail.com');
        $this->get(route('facebook.callback'))->assertRedirect(route('facebook.profile.edit'));
    }

    public function test_temporary_email_diagnostic_uses_the_callback_user_token_and_logs_only_flags(): void
    {
        config()->set('services.facebook.email_diagnostic_enabled', true);
        Http::fake(['graph.facebook.com/*/me/permissions' => Http::response([
            'data' => [['permission' => 'email', 'status' => 'granted']],
        ])]);
        Log::spy();
        $this->mockFacebookUser('facebook-diagnostic', 'citoyen@example.com');

        $this->get(route('facebook.callback'))->assertRedirect(route('facebook.profile.edit'));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-user-token')
            && $request->url() === 'https://graph.facebook.com/'.config('services.meta.graph_version').'/me/permissions');
        Log::shouldHaveReceived('info')->with('Diagnostic email Facebook Spotlight', [
            'permission_email' => 'granted',
            'graph_email_present' => true,
            'graph_email_nonempty' => true,
            'socialite_email_usable' => true,
        ])->once();
    }

    public function test_temporary_email_diagnostic_distinguishes_declined_and_missing_graph_email(): void
    {
        config()->set('services.facebook.email_diagnostic_enabled', true);
        Http::fake(['graph.facebook.com/*/me/permissions' => Http::response([
            'data' => [['permission' => 'email', 'status' => 'declined']],
        ])]);
        Log::spy();
        $this->mockFacebookUser('facebook-diagnostic-declined', null, rawEmailPresent: false);

        $this->get(route('facebook.callback'))->assertRedirect(route('facebook.profile.edit'));

        Log::shouldHaveReceived('info')->with('Diagnostic email Facebook Spotlight', [
            'permission_email' => 'declined',
            'graph_email_present' => false,
            'graph_email_nonempty' => false,
            'socialite_email_usable' => false,
        ])->once();
    }

    public function test_temporary_email_diagnostic_distinguishes_graph_email_from_socialite_email(): void
    {
        config()->set('services.facebook.email_diagnostic_enabled', true);
        Http::fake(['graph.facebook.com/*/me/permissions' => Http::response(['data' => []])]);
        Log::spy();
        $this->mockFacebookUser('facebook-diagnostic-raw-only', null, rawEmailOverride: 'citoyen@example.com');

        $this->get(route('facebook.callback'))->assertRedirect(route('facebook.profile.edit'));

        Log::shouldHaveReceived('info')->with('Diagnostic email Facebook Spotlight', [
            'permission_email' => 'absent',
            'graph_email_present' => true,
            'graph_email_nonempty' => true,
            'socialite_email_usable' => false,
        ])->once();
    }

    public function test_temporary_email_diagnostic_does_not_block_login_when_permissions_request_fails(): void
    {
        config()->set('services.facebook.email_diagnostic_enabled', true);
        Http::fake(['graph.facebook.com/*/me/permissions' => Http::response(['error' => 'unavailable'], 503)]);
        Log::spy();
        $this->mockFacebookUser('facebook-diagnostic-error', null, rawEmailPresent: false);

        $this->get(route('facebook.callback'))->assertRedirect(route('facebook.profile.edit'));

        Log::shouldHaveReceived('info')->with('Diagnostic email Facebook Spotlight', [
            'permission_email' => 'unavailable',
            'graph_email_present' => false,
            'graph_email_nonempty' => false,
            'socialite_email_usable' => false,
        ])->once();
    }

    public function test_email_diagnostic_is_disabled_by_default(): void
    {
        Http::fake();
        Log::spy();
        $this->mockFacebookUser('facebook-diagnostic-disabled', null);

        $this->get(route('facebook.callback'))->assertRedirect(route('facebook.profile.edit'));

        Http::assertNothingSent();
        Log::shouldNotHaveReceived('info', ['Diagnostic email Facebook Spotlight']);
    }

    public function test_a_long_facebook_avatar_url_does_not_block_registration(): void
    {
        $this->mockFacebookUser('facebook-long-avatar', 'avatar@example.com',
            'https://example.com/'.str_repeat('a', 300));

        $this->get(route('facebook.callback'))->assertRedirect(route('facebook.profile.edit'));
        $this->assertGreaterThan(255, strlen(User::where('facebook_id', 'facebook-long-avatar')->firstOrFail()->facebook_avatar_url));
    }

    private function mockFacebookUser(string $id, ?string $email, string $avatar = 'https://example.com/avatar.jpg', bool $rawEmailPresent = true, ?string $rawEmailOverride = null): void
    {
        $facebookUser = Mockery::mock(SocialiteUser::class);
        $facebookUser->token = 'test-user-token';
        $facebookUser->shouldReceive('getId')->andReturn($id);
        $facebookUser->shouldReceive('getEmail')->andReturn($email);
        $facebookUser->shouldReceive('getRaw')->andReturn($rawEmailPresent ? ['email' => $rawEmailOverride ?? $email] : []);
        $facebookUser->shouldReceive('getName')->andReturn('Utilisateur Facebook');
        $facebookUser->shouldReceive('getNickname')->andReturnNull();
        $facebookUser->shouldReceive('getAvatar')->andReturn($avatar);

        $provider = Mockery::mock(FacebookProvider::class);
        $provider->shouldReceive('usingGraphVersion')->once()
            ->with(config('services.meta.graph_version', 'v26.0'))->andReturnSelf();
        $provider->shouldReceive('setHttpClient')->once()
            ->withArgs(fn (Client $client) => $client->getConfig('verify') === (config('services.meta.ca_bundle') ?: true))
            ->andReturnSelf();
        $provider->shouldReceive('fields')->once()
            ->with(['id', 'name', 'email', 'picture.type(large)'])->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($facebookUser);

        Socialite::shouldReceive('driver')->once()->with('facebook')->andReturn($provider);
    }
}
