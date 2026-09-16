<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use App\Notifications\SpotlightResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_only_the_super_administrator(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'email' => config('spotlight.super_admin.email'),
            'role' => Role::Administrateur->value,
            'is_blocked' => false,
        ]);
    }

    public function test_super_administrator_can_create_a_moderator(): void
    {
        $admin = User::factory()->create(['role' => Role::Administrateur]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Modérateur Test',
            'email' => 'moderateur@spotlight.cm',
            'role' => Role::Moderateur->value,
            'password' => 'mot-de-passe',
            'password_confirmation' => 'mot-de-passe',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'moderateur@spotlight.cm',
            'role' => Role::Moderateur->value,
        ]);
    }

    public function test_another_administrator_cannot_be_created_from_the_interface(): void
    {
        $admin = User::factory()->create(['role' => Role::Administrateur]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Autre Admin',
            'email' => 'autre.admin@spotlight.cm',
            'role' => Role::Administrateur->value,
            'password' => 'mot-de-passe',
            'password_confirmation' => 'mot-de-passe',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'autre.admin@spotlight.cm']);
    }

    public function test_super_administrator_can_resend_a_password_creation_link(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => Role::Administrateur]);
        $moderator = User::factory()->create(['role' => Role::Moderateur]);

        $this->actingAs($admin)
            ->post(route('admin.users.send-reset', $moderator))
            ->assertSessionHas('success');

        Notification::assertSentTo($moderator, SpotlightResetPassword::class);
    }

    public function test_super_administrator_cannot_be_modified_or_deleted_from_the_interface(): void
    {
        $admin = User::factory()->create(['role' => Role::Administrateur]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'role' => Role::Citoyen->value,
                'is_blocked' => true,
            ])
            ->assertSessionHasErrors('user');

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => Role::Administrateur->value,
            'is_blocked' => false,
        ]);
    }

    public function test_super_administrator_cannot_reset_or_delete_its_account_from_profile(): void
    {
        $admin = User::factory()->create(['role' => Role::Administrateur]);

        $this->actingAs($admin)
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'nouveau-mot-de-passe',
                'password_confirmation' => 'nouveau-mot-de-passe',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->actingAs($admin)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'user');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
