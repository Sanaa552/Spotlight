<?php

namespace Tests\Feature;

use App\Models\Declaration;
use App\Models\Localisation;
use App\Models\PieceJointe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class DeclarationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_citizen_can_submit_a_declaration_with_a_valid_attachment(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $citizen = User::factory()->create();

        $response = $this->actingAs($citizen)->post(route('declarations.store'), [
            'type' => 'perte',
            'categorie' => 'objet',
            'type_perte' => 'Portefeuille perdu',
            'description' => 'Portefeuille noir perdu au marché central.',
            'lieu' => 'Marché central de Douala',
            'adresse' => 'Marché central, Akwa, Douala',
            'photo_publique' => UploadedFile::fake()->create('portefeuille.jpg', 1024, 'image/jpeg'),
            'declaration_perte' => UploadedFile::fake()->create('declaration-perte.pdf', 2048, 'application/pdf'),
            'pieces_jointes' => [UploadedFile::fake()->create('justificatif.pdf', 1024, 'application/pdf')],
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('declarations.index'));

        $this->assertDatabaseCount('declarations', 1);
        $this->assertDatabaseCount('localisations', 1);
        $this->assertDatabaseCount('pieces_jointes', 2);
        $this->assertDatabaseHas('pieces_jointes', [
            'type_document' => 'declaration_perte',
            'disque' => 'local',
        ]);
        $this->assertDatabaseHas('pieces_jointes', [
            'type_document' => 'piece_jointe',
            'disque' => 'local',
        ]);
        $this->assertNotNull(Declaration::firstOrFail()->photo_path);
        Storage::disk('local')->assertExists(Declaration::firstOrFail()->photo_path);
        Storage::disk('public')->assertMissing(Declaration::firstOrFail()->photo_path);
    }

    public function test_png_public_photo_is_saved_as_a_nonempty_private_jpeg(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('La conversion PNG necessite GD.');
        }

        Storage::fake('local');
        $citizen = User::factory()->create();

        $this->actingAs($citizen)->post(route('declarations.store'), [
            'type' => 'perte',
            'categorie' => 'objet',
            'type_perte' => 'Objet perdu',
            'description' => 'Un portefeuille noir perdu au centre-ville.',
            'adresse' => 'Douala',
            'photo_publique' => UploadedFile::fake()->image('portefeuille.png', 800, 600),
            'declaration_perte' => UploadedFile::fake()->create('signalement.pdf', 512, 'application/pdf'),
        ])->assertSessionHasNoErrors()->assertRedirect(route('declarations.index'));

        $path = Declaration::firstOrFail()->photo_path;
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('local')->assertExists($path);
        $jpeg = Storage::disk('local')->get($path);
        $this->assertNotEmpty($jpeg);
        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($jpeg)[2]);
    }

    public function test_empty_public_photo_is_rejected_with_a_clear_message(): void
    {
        $citizen = User::factory()->create();

        $this->actingAs($citizen)->from(route('declarations.create'))->post(route('declarations.store'), [
            'type' => 'perte',
            'categorie' => 'objet',
            'type_perte' => 'Objet perdu',
            'description' => 'Un portefeuille noir perdu au centre-ville.',
            'adresse' => 'Douala',
            'photo_publique' => UploadedFile::fake()->create('portefeuille.jpg', 0, 'image/jpeg'),
            'declaration_perte' => UploadedFile::fake()->create('signalement.pdf', 512, 'application/pdf'),
        ])->assertRedirect(route('declarations.create'))->assertSessionHasErrors([
            'photo_publique' => 'La photo reçue est vide. Actualisez la page et sélectionnez à nouveau le fichier original.',
        ]);

        $this->assertDatabaseCount('declarations', 0);
    }

    public function test_attachment_larger_than_ten_megabytes_is_rejected_in_french(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $citizen = User::factory()->create();

        $response = $this->actingAs($citizen)
            ->from(route('declarations.create'))
            ->post(route('declarations.store'), [
                'type' => 'perte',
                'categorie' => 'objet',
                'type_perte' => 'Portefeuille perdu',
                'description' => 'Portefeuille noir perdu au marché central.',
                'adresse' => 'Marché central, Akwa, Douala',
                'photo_publique' => UploadedFile::fake()->create('portefeuille.jpg', 1024, 'image/jpeg'),
                'declaration_perte' => UploadedFile::fake()->create('declaration-perte.pdf', 1024, 'application/pdf'),
                'pieces_jointes' => [UploadedFile::fake()->create('document.pdf', 11264, 'application/pdf')],
            ]);

        $response
            ->assertRedirect(route('declarations.create'))
            ->assertSessionHasErrors([
                'pieces_jointes.0' => 'Chaque pièce jointe doit peser au maximum 10 Mo.',
            ]);

        $this->assertDatabaseCount('declarations', 0);
        $this->assertDatabaseCount('pieces_jointes', 0);
    }

    public function test_loss_report_is_required_only_for_a_loss(): void
    {
        $citizen = User::factory()->create();

        $lossResponse = $this->actingAs($citizen)
            ->from(route('declarations.create'))
            ->post(route('declarations.store'), [
                'type' => 'perte',
                'categorie' => 'personne',
                'type_perte' => 'Personne disparue',
                'description' => 'Signalement suffisamment détaillé.',
                'adresse' => 'Douala',
                'photo_publique' => UploadedFile::fake()->create('personne.jpg', 1024, 'image/jpeg'),
            ]);

        $lossResponse->assertSessionHasErrors([
            'declaration_perte' => 'La preuve de signalement aux autorités est obligatoire pour soumettre une perte.',
        ]);

        $discoveryResponse = $this->actingAs($citizen)->post(route('declarations.store'), [
            'type' => 'decouverte',
            'categorie' => 'objet',
            'type_decouverte' => 'Téléphone trouvé',
            'description' => 'Téléphone trouvé près du marché.',
            'adresse' => 'Douala',
            'photo_publique' => UploadedFile::fake()->create('telephone.jpg', 1024, 'image/jpeg'),
            'preuve_decouverte' => UploadedFile::fake()->create('lieu.mp4', 1024, 'video/mp4'),
            'preuve_signalement' => UploadedFile::fake()->create('recepisse.pdf', 1024, 'application/pdf'),
        ]);

        $discoveryResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseCount('declarations', 1);
    }

    public function test_loss_report_is_private_and_access_controlled(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $owner = User::factory()->create();
        $otherCitizen = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);

        $this->actingAs($owner)->post(route('declarations.store'), [
            'type' => 'perte',
            'categorie' => 'personne',
            'type_perte' => 'Personne disparue',
            'description' => 'Signalement suffisamment détaillé.',
            'adresse' => 'Douala',
            'photo_publique' => UploadedFile::fake()->create('portrait.jpg', 1024, 'image/jpeg'),
            'declaration_perte' => UploadedFile::fake()->create('declaration-privee.pdf', 1024, 'application/pdf'),
            'pieces_jointes' => [UploadedFile::fake()->create('cni.jpg', 1024, 'image/jpeg')],
        ])->assertSessionHasNoErrors();

        $declaration = Declaration::firstOrFail();
        $this->actingAs($owner)
            ->get(route('declarations.photo-privee', $declaration))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($otherCitizen)
            ->get(route('declarations.photo-privee', $declaration))
            ->assertForbidden();
        $this->actingAs($moderator)
            ->get(route('declarations.photo-privee', $declaration))
            ->assertOk();

        $publicPhotoPath = 'photos-publiques/'.basename($declaration->photo_path);
        Storage::disk('public')->put($publicPhotoPath, Storage::disk('local')->get($declaration->photo_path));
        $declaration->update([
            'statut' => 'validee',
            'photo_path' => $publicPhotoPath,
            'facebook_post_id' => 'fb-test',
            'instagram_post_id' => 'ig-test',
        ]);
        $lossReport = PieceJointe::where('type_document', 'declaration_perte')->firstOrFail();
        $identityDocument = PieceJointe::where('nom_original', 'cni.jpg')->firstOrFail();

        $this->assertSame('local', $identityDocument->disque);
        Storage::disk('local')->assertExists($identityDocument->chemin);
        Storage::disk('public')->assertMissing($identityDocument->chemin);

        $this->actingAs($owner)
            ->get(route('pieces-jointes.telecharger', $lossReport))
            ->assertOk()
            ->assertDownload('declaration-privee.pdf');

        $this->actingAs($otherCitizen)
            ->get(route('pieces-jointes.telecharger', $lossReport))
            ->assertForbidden();
        $this->actingAs($otherCitizen)
            ->get(route('pieces-jointes.telecharger', $identityDocument))
            ->assertForbidden();

        $this->actingAs($moderator)
            ->get(route('pieces-jointes.telecharger', $lossReport))
            ->assertOk();
        $this->actingAs($moderator)
            ->get(route('pieces-jointes.telecharger', $identityDocument))
            ->assertDownload('cni.jpg');

        $this->actingAs($otherCitizen)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('declaration-privee.pdf')
            ->assertDontSee('cni.jpg');

        $this->get(route('public.declarations.index'))
            ->assertOk()
            ->assertDontSee('declaration-privee.pdf')
            ->assertDontSee('cni.jpg')
            ->assertSee('photos-publiques/', false);
    }

    public function test_private_image_preview_is_inline_and_restricted_to_owner_and_staff(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $admin = User::factory()->create(['role' => 'administrateur']);
        $declaration = $owner->declarations()->create([
            'type' => 'perte', 'categorie' => 'objet',
            'description' => 'Document privé', 'statut' => 'en_attente',
        ]);
        $path = 'declarations-privees/preuve.png';
        Storage::disk('local')->put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9LMqUAAAAASUVORK5CYII='
        ));
        $piece = PieceJointe::create([
            'declaration_id' => $declaration->id,
            'type_document' => 'piece_jointe',
            'disque' => 'local', 'chemin' => $path,
            'nom_original' => 'preuve.png', 'type_mime' => 'image/png',
        ]);

        $this->actingAs($owner)->get(route('pieces-jointes.apercu', $piece))
            ->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($moderator)->get(route('pieces-jointes.apercu', $piece))->assertOk();
        $this->actingAs($admin)->get(route('pieces-jointes.apercu', $piece))->assertOk();
        $this->actingAs($other)->get(route('pieces-jointes.apercu', $piece))->assertForbidden();

        $videoPath = 'declarations-privees/lieu.mp4';
        Storage::disk('local')->put($videoPath, "\x00\x00\x00\x18ftypisom\x00\x00\x00\x00isomiso2");
        $video = PieceJointe::create([
            'declaration_id' => $declaration->id,
            'type_document' => 'preuve_decouverte',
            'disque' => 'local', 'chemin' => $videoPath,
            'nom_original' => 'lieu.mp4', 'type_mime' => 'video/mp4',
        ]);
        $this->actingAs($owner)->get(route('pieces-jointes.apercu', $video))
            ->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->actingAs($owner)->withHeader('Range', 'bytes=0-7')
            ->get(route('pieces-jointes.apercu', $video))
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-7/24');
        $this->actingAs($other)->get(route('pieces-jointes.apercu', $video))->assertForbidden();
    }

    public function test_public_photo_is_required_and_private_attachment_cannot_replace_it(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $citizen = User::factory()->create();

        $this->actingAs($citizen)->post(route('declarations.store'), [
            'type' => 'decouverte',
            'categorie' => 'objet',
            'type_decouverte' => 'Téléphone trouvé',
            'description' => 'Téléphone trouvé près du marché.',
            'adresse' => 'Douala',
            'pieces_jointes' => [UploadedFile::fake()->create('preuve.jpg', 1024, 'image/jpeg')],
        ])->assertSessionHasErrors('photo_publique');

        $this->assertDatabaseCount('declarations', 0);
    }

    public function test_discovered_object_can_add_authority_receipt_after_private_submission(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $citizen = User::factory()->create();
        $data = [
            'type' => 'decouverte',
            'categorie' => 'objet',
            'type_decouverte' => 'Clés trouvées',
            'description' => 'Clés trouvées au marché.',
            'adresse' => 'Douala',
            'poste_prevu' => 'Poste de test (Douala)',
            'poste_latitude' => 4.06,
            'poste_longitude' => 9.71,
            'photo_publique' => UploadedFile::fake()->create('cles.jpg', 1024, 'image/jpeg'),
        ];

        $this->actingAs($citizen)->post(route('declarations.store'), $data)
            ->assertSessionHasErrors('preuve_decouverte');

        $this->actingAs($citizen)->post(route('declarations.store'), [
            ...$data,
            'preuve_decouverte' => UploadedFile::fake()->create('lieu.mp4', 1024, 'video/mp4'),
        ])->assertSessionHasNoErrors();

        $declaration = Declaration::firstOrFail();
        $this->assertSame('en_attente', $declaration->statut);
        $this->assertSame('Poste de test (Douala)', $declaration->localisation->poste_prevu);
        $this->assertEquals(4.06, $declaration->localisation->poste_latitude);
        $this->assertEquals(9.71, $declaration->localisation->poste_longitude);
        $this->actingAs($citizen)->get(route('declarations.show', $declaration))
            ->assertSee('Ajouter la preuve de remise ou de signalement')
            ->assertSee('Poste de test (Douala)');

        $otherCitizen = User::factory()->create();
        $this->actingAs($otherCitizen)->post(route('declarations.preuve-signalement.store', $declaration), [
            'preuve_signalement' => UploadedFile::fake()->create('intrus.pdf', 1024, 'application/pdf'),
        ])->assertForbidden();

        $this->actingAs($citizen)->post(route('declarations.preuve-signalement.store', $declaration), [
            'preuve_signalement' => UploadedFile::fake()->create('recepisse.pdf', 1024, 'application/pdf'),
        ])->assertSessionHas('success');

        foreach (['preuve_decouverte', 'preuve_signalement'] as $type) {
            $piece = $declaration->piecesJointes()->where('type_document', $type)->firstOrFail();
            $this->assertSame('local', $piece->disque);
            Storage::disk('local')->assertExists($piece->chemin);
        }

        $this->actingAs($citizen)->post(route('declarations.preuve-signalement.store', $declaration), [
            'preuve_signalement' => UploadedFile::fake()->create('doublon.pdf', 1024, 'application/pdf'),
        ])->assertSessionHas('warning');
        $this->assertSame(2, $declaration->piecesJointes()->count());
    }

    public function test_discovered_person_stays_private_without_a_public_photo(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $citizen = User::factory()->create();

        $this->actingAs($citizen)->post(route('declarations.store'), [
            'type' => 'decouverte',
            'categorie' => 'personne',
            'type_decouverte' => 'Personne signalée',
            'description' => 'Signalement confidentiel de test.',
            'adresse' => 'Douala',
            'preuve_signalement' => UploadedFile::fake()->create('signalement.pdf', 1024, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $declaration = Declaration::firstOrFail();
        $this->assertNull($declaration->photo_path);
        $this->assertSame('en_attente', $declaration->statut);
        $this->get(route('public.declarations.index', ['onglet' => 'decouvertes']))
            ->assertDontSee('Signalement confidentiel de test.');
    }

    public function test_selected_station_prefills_only_an_object_discovery_form(): void
    {
        $citizen = User::factory()->create();

        $this->actingAs($citizen)->get(route('declarations.create', ['poste' => 'Poste de test (Douala)']))
            ->assertOk()
            ->assertSee('value="Poste de test (Douala)"', false)
            ->assertSee("type: 'decouverte'", false)
            ->assertSee('Son choix ne remplace pas le récépissé des autorités');

        $this->actingAs($citizen)->get(route('declarations.create'))
            ->assertOk()
            ->assertSee("type: 'perte'", false);
    }

    public function test_planned_station_is_rejected_for_a_found_person(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $citizen = User::factory()->create();

        $this->actingAs($citizen)->post(route('declarations.store'), [
            'type' => 'decouverte',
            'categorie' => 'personne',
            'type_decouverte' => 'Personne signalée',
            'description' => 'Signalement confidentiel.',
            'adresse' => 'Douala',
            'poste_prevu' => 'Poste de test',
            'preuve_signalement' => UploadedFile::fake()->create('signalement.pdf', 1024, 'application/pdf'),
        ])->assertSessionHasErrors('poste_prevu');

        $this->assertDatabaseCount('declarations', 0);
    }

    public function test_only_the_owner_can_view_nearby_stations_for_a_discovery(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $declaration = $owner->declarations()->create([
            'type' => 'decouverte', 'categorie' => 'objet',
            'description' => 'Objet trouvé.', 'statut' => 'en_attente',
        ]);
        Localisation::create([
            'declaration_id' => $declaration->id,
            'adresse' => 'Douala',
            'latitude' => 4.05,
            'longitude' => 9.7,
        ]);
        Http::fake(['overpass-api.de/*' => Http::response(['elements' => []])]);

        $this->actingAs($other)->get(route('declarations.commissariats', $declaration))->assertForbidden();
        $this->actingAs($owner)->get(route('declarations.commissariats', $declaration))->assertOk();
    }

    public function test_citizen_can_search_stations_before_a_discovery_without_sharing_case_data(): void
    {
        $this->get(route('commissariats.rechercher'))->assertRedirect(route('login'));

        $citizen = User::factory()->create();
        Http::preventStrayRequests();
        Http::fake([
            'https://nominatim.openstreetmap.org/*' => Http::response([['lat' => '4.05', 'lon' => '9.70']]),
            'https://overpass-api.de/*' => Http::response(['elements' => [[
                'lat' => 4.06, 'lon' => 9.71, 'tags' => ['name' => 'Poste de test'],
            ]]]),
        ]);
        $this->actingAs($citizen)->get(route('commissariats.rechercher'))
            ->assertOk()->assertSee('Choisir une ville');
        Http::assertNothingSent();

        $this->actingAs($citizen)->get(route('commissariats.rechercher', ['ville' => 'Douala']))
            ->assertOk()->assertSee('Poste de test')->assertSee('1.57 km')->assertSee('Choisir ce poste');
        $this->actingAs($citizen)->get(route('commissariats.rechercher', ['ville' => 'Douala', 'format' => 'json']))
            ->assertOk()->assertJsonPath('postes.0.nom', 'Poste de test')
            ->assertJsonPath('postes.0.lat', 4.06);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'nominatim.openstreetmap.org')
            && str_contains(urldecode($request->url()), 'Douala, Cameroun'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'overpass-api.de'));

        $this->actingAs($citizen)->get(route('commissariats.rechercher', ['ville' => 'Rue privée 12']))
            ->assertSessionHasErrors('ville');
    }

    public function test_citizen_can_get_a_station_neighbourhood_without_exposing_arbitrary_coordinates(): void
    {
        $citizen = User::factory()->create();
        Cache::forget('osm:ville:'.sha1('Douala'));
        Cache::forget('osm:postes:v2:'.sha1('4.05,9.7'));
        Cache::forget('osm:adresse-poste:'.sha1('node/4537782550'));
        RateLimiter::clear('osm:nominatim:spotlight');
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/reverse')) {
                return Http::response(['address' => [
                    'suburb' => 'Bonanjo', 'road' => 'Rue French (N°1.082)', 'city' => 'Douala I',
                ]]);
            }

            if (str_contains($request->url(), '/search')) {
                return Http::response([['lat' => '4.05', 'lon' => '9.70']]);
            }

            return Http::response(['elements' => [[
                'type' => 'node', 'id' => 4537782550,
                'lat' => 4.0406646, 'lon' => 9.6847372,
                'tags' => ['name' => 'Groupement Mobile d’Intervention N°2'],
            ]]]);
        });

        $url = route('commissariats.adresse', ['ville' => 'Douala', 'osm_id' => 'node/4537782550']);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($citizen)->get(route('commissariats.rechercher', ['ville' => 'Douala', 'format' => 'json']))
            ->assertOk()->assertJsonPath('postes.0.osm_id', 'node/4537782550');
        RateLimiter::clear('osm:nominatim:spotlight');
        $this->get($url)->assertOk()->assertJsonPath('quartier', 'Bonanjo')
            ->assertJsonPath('rue', 'Rue French (N°1.082)');
        $this->get($url)->assertOk()->assertJsonPath('quartier', 'Bonanjo');
        $this->get(route('commissariats.adresse', ['ville' => 'Douala', 'osm_id' => 'node/999']))
            ->assertNotFound();
        Http::assertSentCount(3);
    }

    public function test_discovery_map_never_geocodes_the_private_address_or_person_location(): void
    {
        $owner = User::factory()->create();
        $declaration = $owner->declarations()->create([
            'type' => 'decouverte', 'categorie' => 'objet',
            'description' => 'Objet trouvé.', 'lieu' => 'Autre secteur', 'statut' => 'en_attente',
        ]);
        Localisation::create([
            'declaration_id' => $declaration->id,
            'adresse' => '12 rue privée, appartement 4',
            'latitude' => 4.001,
            'longitude' => 9.001,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://nominatim.openstreetmap.org/*' => Http::response([['lat' => '4.05', 'lon' => '9.70']]),
            'https://overpass-api.de/*' => Http::response(['elements' => []]),
        ]);

        $this->actingAs($owner)->get(route('declarations.commissariats', $declaration))
            ->assertOk()->assertSee('Choisir une ville');
        Http::assertNothingSent();

        $this->actingAs($owner)->get(route('declarations.commissariats', [
            'declaration' => $declaration, 'ville' => 'Douala',
        ]))->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'nominatim.openstreetmap.org')
            && ! str_contains(urldecode($request->url()), 'rue privée')
            && ! str_contains(urldecode($request->url()), 'Autre secteur'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'overpass-api.de')
            && ! str_contains($request->body(), '4.001')
            && ! str_contains($request->body(), '9.001'));
    }

    public function test_found_person_location_is_never_sent_to_mapping_services(): void
    {
        $owner = User::factory()->create();
        $declaration = $owner->declarations()->create([
            'type' => 'decouverte', 'categorie' => 'personne',
            'description' => 'Signalement confidentiel.',
            'statut' => 'en_attente',
        ]);
        Localisation::create(['declaration_id' => $declaration->id, 'adresse' => 'Adresse privée']);
        Http::fake();

        $this->actingAs($owner)->get(route('declarations.commissariats', $declaration))
            ->assertForbidden();
        Http::assertNothingSent();
    }
}
