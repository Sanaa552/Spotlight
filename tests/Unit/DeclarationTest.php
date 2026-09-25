<?php

namespace Tests\Unit;

use App\Models\Declaration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeclarationTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_declaration_soumise_passe_en_attente(): void
    {
        $user = User::factory()->create();

        $declaration = Declaration::create([
            'user_id' => $user->id,
            'type' => 'perte',
            'categorie' => 'objet',
            'description' => 'Téléphone perdu',
            'lieu' => 'Douala',
            'statut' => 'validee',
        ]);

        $declaration->soumettre();

        $this->assertSame('en_attente', $declaration->fresh()->statut);
    }

    //Publication sur les réseaux
    public function test_une_declaration_peut_etre_publiee_si_les_publications_sociales_sont_confirmees(): void
{
    $user = User::factory()->create();

    $declaration = Declaration::create([
        'user_id' => $user->id,
        'type' => 'perte',
        'categorie' => 'objet',
        'description' => 'Téléphone perdu',
        'lieu' => 'Douala',
        'statut' => 'en_attente',
        'facebook_post_id' => 'facebook_123',
        'instagram_post_id' => 'instagram_123',
    ]);

    $declaration->publier();

    $this->assertSame('validee', $declaration->fresh()->statut);
    }

    //Déclaration incomplète
    public function test_une_declaration_ne_peut_pas_etre_publiee_si_une_publication_sociale_manque(): void
{
    $user = User::factory()->create();

    $declaration = Declaration::create([
        'user_id' => $user->id,
        'type' => 'perte',
        'categorie' => 'objet',
        'description' => 'Téléphone perdu',
        'lieu' => 'Douala',
        'statut' => 'en_attente',
        'facebook_post_id' => 'facebook_123',
        'instagram_post_id' => null,
    ]);

    $this->expectException(\LogicException::class);

    $declaration->publier();
}

}

