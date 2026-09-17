<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\SpotlightVerifyEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
        $response->assertSee('Corriger et renvoyer');
    }

    public function test_unverified_citizen_can_correct_email_without_registering_again(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'erreur@example.com']);
        $oldVerificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)
            ->patch(route('verification.address.update'), ['email' => ' Correcte@Example.com '])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'verification-address-updated');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'correcte@example.com',
            'email_verified_at' => null,
        ]);
        Notification::assertSentTo($user, SpotlightVerifyEmail::class);
        $this->actingAs($user)->get($oldVerificationUrl)->assertForbidden();
    }

    public function test_invalid_duplicate_and_unchanged_email_are_refused(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'actuelle@example.com']);
        User::factory()->create(['email' => 'prise@example.com']);

        foreach (['pas-un-email', 'prise@example.com', 'actuelle@example.com'] as $email) {
            $this->actingAs($user)
                ->patch(route('verification.address.update'), ['email' => $email])
                ->assertSessionHasErrors('email');
        }

        $this->assertSame('actuelle@example.com', $user->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_internal_or_already_verified_account_cannot_use_email_correction(): void
    {
        $moderator = User::factory()->unverified()->create(['role' => 'moderateur']);
        $admin = User::factory()->unverified()->create(['role' => 'administrateur']);
        $verifiedCitizen = User::factory()->create();

        foreach ([$moderator, $admin, $verifiedCitizen] as $user) {
            $this->actingAs($user)
                ->patch(route('verification.address.update'), ['email' => 'nouvelle@example.com'])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'nouvelle@example.com']);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
