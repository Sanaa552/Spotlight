<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_alone_are_invited_to_create_an_account(): void
    {
        $this->get(route('public.declarations.index', ['onglet' => 'decouvertes']))
            ->assertOk()->assertSee('Créer un compte pour agir');
    }

    public function test_ready_citizen_sees_an_action_and_mon_espace_in_desktop_and_mobile_navigation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('public.declarations.index', ['onglet' => 'restitutions']))
            ->assertOk()->assertSee('Faire une déclaration')->assertSee('Mon espace')
            ->assertDontSee('Créer un compte pour agir')->assertDontSee('Tableau de bord');
        $this->get(route('dashboard'))->assertOk()->assertSee('Mon espace');
    }

    public function test_incomplete_or_unverified_citizen_is_sent_to_the_right_step(): void
    {
        $incomplete = User::factory()->create(['facebook_id' => 'facebook-incomplete', 'telephone' => null]);
        $this->actingAs($incomplete)->get(route('public.declarations.index'))
            ->assertOk()->assertSee('Compléter mon profil')->assertDontSee('Créer un compte pour agir');

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->get(route('public.declarations.index'))
            ->assertOk()->assertSee('Vérifier mon e-mail')->assertDontSee('Créer un compte pour agir');
    }

    public function test_moderator_and_administrator_keep_staff_actions(): void
    {
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $this->actingAs($moderator)->get(route('public.declarations.index'))
            ->assertOk()->assertSee('Ouvrir la modération')->assertDontSee('Créer un compte pour agir');

        $admin = User::factory()->create(['role' => 'administrateur']);
        $this->actingAs($admin)->get(route('public.declarations.index'))
            ->assertOk()->assertSee('Dashboard')->assertDontSee('Mon espace');
        $this->get(route('dashboard'))->assertOk()->assertSee('Dashboard');
    }

    public function test_public_detail_shows_only_actions_the_viewer_can_use(): void
    {
        $owner = User::factory()->create();
        $declaration = $owner->declarations()->create([
            'type' => 'perte', 'categorie' => 'objet', 'type_perte' => 'Sac bleu',
            'description' => 'Sac perdu.', 'statut' => 'validee',
            'photo_path' => 'photos-publiques/sac-bleu.jpg',
            'facebook_post_id' => 'fb-test', 'instagram_post_id' => 'ig-test',
        ]);
        $declaration->piecesJointes()->create([
            'type_document' => 'declaration_perte', 'disque' => 'local',
            'chemin' => 'declarations-privees/declaration.pdf', 'nom_original' => 'declaration.pdf',
        ]);

        $this->get(route('public.declarations.show', $declaration))->assertOk()
            ->assertSee('Se connecter pour signaler une découverte');

        $incomplete = User::factory()->create(['facebook_id' => 'facebook-detail', 'telephone' => null]);
        $this->actingAs($incomplete)->get(route('public.declarations.show', $declaration))->assertOk()
            ->assertSee('Compléter mon profil pour agir')
            ->assertDontSee('name="contenu"', false);

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->get(route('public.declarations.show', $declaration))->assertOk()
            ->assertSee('Vérifier mon e-mail pour agir');

        $ready = User::factory()->create();
        $this->actingAs($ready)->get(route('public.declarations.show', $declaration))->assertOk()
            ->assertSee('J’ai retrouvé cet objet')
            ->assertSee('name="contenu"', false);
    }
}
