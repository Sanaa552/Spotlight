<?php

namespace Tests\Feature;

use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame(User::EMAIL_SOURCE_MANUAL, $user->email_source);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_citizen_can_add_phone_and_disable_new_declaration_emails(): void
    {
        $user = User::factory()->create(['facebook_id' => 'facebook-profile']);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'telephone' => '+237 690 000 000',
            'new_declaration_email' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertSame('+237 690 000 000', $user->refresh()->telephone);
        $this->assertFalse($user->new_declaration_email);
    }

    public function test_changing_facebook_email_in_profile_requires_verification_again(): void
    {
        $user = User::factory()->create([
            'facebook_id' => 'facebook-profile-email',
            'email_source' => User::EMAIL_SOURCE_FACEBOOK,
            'telephone' => '+237690000000',
        ]);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'manuelle@example.com',
            'telephone' => $user->telephone,
        ])->assertRedirect('/profile');

        $this->assertSame(User::EMAIL_SOURCE_MANUAL, $user->fresh()->email_source);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user->fresh())->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_facebook_only_account_must_reauthenticate_and_explicitly_confirm_deletion(): void
    {
        $user = User::factory()->create([
            'facebook_id' => 'facebook-delete-owner',
            'password_is_local' => false,
        ]);

        $this->actingAs($user)->delete(route('profile.destroy'), [
            'confirmation_method' => 'facebook', 'confirm_delete' => '1',
        ])->assertSessionHasErrorsIn('userDeletion', 'facebook');
        $this->assertNotNull($user->fresh());

        $this->withSession([
            'facebook_delete_user_id' => $user->id,
            'facebook_delete_started_at' => time(),
        ]);
        $this->mockDeletionFacebookUser('facebook-delete-owner');
        $this->get(route('facebook.callback'))->assertRedirect(route('profile.edit'));
        $this->assertNotNull($user->fresh());

        $this->delete(route('profile.destroy'), ['confirmation_method' => 'facebook'])
            ->assertSessionHasErrorsIn('userDeletion', 'confirm_delete');
        $this->assertNotNull($user->fresh());

        $this->delete(route('profile.destroy'), [
            'confirmation_method' => 'facebook', 'confirm_delete' => '1',
        ])->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_different_facebook_identity_cannot_confirm_deletion(): void
    {
        $user = User::factory()->create(['facebook_id' => 'facebook-owner', 'password_is_local' => false]);
        $this->actingAs($user)->withSession([
            'facebook_delete_user_id' => $user->id,
            'facebook_delete_started_at' => time(),
        ]);
        $this->mockDeletionFacebookUser('facebook-other');

        $this->get(route('facebook.callback'))->assertSessionHasErrorsIn('userDeletion', 'facebook');
        $this->delete(route('profile.destroy'), [
            'confirmation_method' => 'facebook', 'confirm_delete' => '1',
        ])->assertSessionHasErrorsIn('userDeletion', 'facebook');
        $this->assertNotNull($user->fresh());
    }

    public function test_cancelled_facebook_confirmation_keeps_the_account(): void
    {
        $user = User::factory()->create(['facebook_id' => 'facebook-cancel', 'password_is_local' => false]);
        $this->actingAs($user)->withSession([
            'facebook_delete_user_id' => $user->id,
            'facebook_delete_started_at' => time(),
        ]);

        $this->get(route('facebook.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrorsIn('userDeletion', 'facebook');
        $this->assertNotNull($user->fresh());
        $this->delete(route('profile.destroy'), [
            'confirmation_method' => 'facebook', 'confirm_delete' => '1',
        ])->assertSessionHasErrorsIn('userDeletion', 'facebook');
    }

    public function test_expired_facebook_confirmation_cannot_delete_account(): void
    {
        $user = User::factory()->create(['facebook_id' => 'facebook-expired', 'password_is_local' => false]);
        $this->actingAs($user)->withSession([
            'facebook_delete_verified_user_id' => $user->id,
            'facebook_delete_verified_at' => time() - 301,
        ]);

        $this->delete(route('profile.destroy'), [
            'confirmation_method' => 'facebook', 'confirm_delete' => '1',
        ])->assertSessionHasErrorsIn('userDeletion', 'facebook');
        $this->assertNotNull($user->fresh());
    }

    public function test_expired_facebook_reauthentication_callback_does_not_issue_deletion_proof(): void
    {
        $user = User::factory()->create(['facebook_id' => 'facebook-expired-callback', 'password_is_local' => false]);
        $this->actingAs($user)->withSession([
            'facebook_delete_user_id' => $user->id,
            'facebook_delete_started_at' => time() - 301,
        ]);

        $this->get(route('facebook.callback'))->assertSessionHasErrorsIn('userDeletion', 'facebook');
        $this->assertNull(session('facebook_delete_verified_at'));
        $this->assertNotNull($user->fresh());
    }

    public function test_invalid_oauth_state_cannot_issue_facebook_deletion_proof(): void
    {
        config()->set('services.facebook.client_id', 'test-client-id');
        config()->set('services.facebook.client_secret', 'test-client-secret');
        $user = User::factory()->create(['facebook_id' => 'facebook-invalid-state', 'password_is_local' => false]);
        $this->actingAs($user)->withSession([
            'facebook_delete_user_id' => $user->id,
            'facebook_delete_started_at' => time(),
            'state' => 'expected-state',
        ]);

        $this->get(route('facebook.callback', ['code' => 'fake-code', 'state' => 'wrong-state']))
            ->assertSessionHasErrorsIn('userDeletion', 'facebook');
        $this->assertNull(session('facebook_delete_verified_at'));
        $this->assertNotNull($user->fresh());
    }

    public function test_legacy_facebook_account_can_choose_either_confirmation_until_password_origin_is_known(): void
    {
        $user = User::factory()->create(['facebook_id' => 'facebook-legacy-delete', 'password_is_local' => null]);

        $this->assertTrue($user->canConfirmDeletionWithPassword());
        $this->assertTrue($user->canConfirmDeletionWithFacebook());
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertSee('Mot de passe actuel')
            ->assertSee('Confirmer mon identité avec Facebook');
    }

    public function test_password_account_cannot_switch_to_facebook_deletion(): void
    {
        $user = User::factory()->create(['facebook_id' => 'facebook-linked', 'password_is_local' => true]);

        $this->actingAs($user)->post(route('profile.facebook.delete.redirect'))->assertForbidden();
        $this->delete(route('profile.destroy'), [
            'confirmation_method' => 'facebook', 'confirm_delete' => '1',
        ])->assertSessionHasErrorsIn('userDeletion', 'user');
        $this->delete(route('profile.destroy'), [
            'confirmation_method' => 'password', 'password' => 'password',
        ])->assertRedirect('/');
        $this->assertNull($user->fresh());
    }

    public function test_account_with_declaration_and_justificatif_is_not_deleted_automatically(): void
    {
        $user = User::factory()->create();
        $declaration = $user->declarations()->create([
            'type' => 'perte', 'categorie' => 'objet', 'description' => 'Dossier à conserver.',
        ]);
        $piece = $declaration->piecesJointes()->create([
            'type_document' => 'declaration_perte', 'disque' => 'local',
            'chemin' => 'declarations-privees/preuve.pdf', 'nom_original' => 'preuve.pdf',
        ]);

        $this->actingAs($user)->from(route('profile.edit'))->delete(route('profile.destroy'), [
            'confirmation_method' => 'password', 'password' => 'password',
        ])->assertSessionHasErrorsIn('userDeletion', 'user')
            ->assertRedirect(route('profile.edit'));
        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Contactez l’administration : leur conservation et les publications Meta doivent être traitées avant toute suppression.');
        $this->assertNotNull($user->fresh());
        $this->assertNotNull($declaration->fresh());
        $this->assertNotNull($piece->fresh());
    }

    public function test_facebook_deletion_redirect_keeps_state_and_requests_reauthentication(): void
    {
        config()->set('services.facebook.client_id', 'test-client-id');
        config()->set('services.facebook.client_secret', 'test-client-secret');
        $user = User::factory()->create(['facebook_id' => 'facebook-start', 'password_is_local' => false]);

        $response = $this->actingAs($user)->post(route('profile.facebook.delete.redirect'));
        $response->assertRedirect();
        $query = [];
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('reauthenticate', $query['auth_type']);
        $this->assertSame(['public_profile'], explode(',', $query['scope']));
        $this->assertNotEmpty($query['state']);
        $response->assertSessionHas('state');
        $this->assertNotNull($user->fresh());
    }

    private function mockDeletionFacebookUser(string $id): void
    {
        $facebookUser = SocialiteUser::fake(['id' => $id]);
        $provider = Mockery::mock(FacebookProvider::class);
        $provider->shouldReceive('usingGraphVersion')->once()->andReturnSelf();
        $provider->shouldReceive('setHttpClient')->once()->withArgs(fn ($client) => $client instanceof Client)->andReturnSelf();
        $provider->shouldReceive('fields')->once()->with(['id'])->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($facebookUser);
        Socialite::shouldReceive('driver')->once()->with('facebook')->andReturn($provider);
    }
}
