<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyAttachmentMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_public_attachment_is_moved_to_private_storage(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $citizen = User::factory()->create();
        $declaration = $citizen->declarations()->create([
            'type' => 'decouverte',
            'categorie' => 'objet',
            'description' => 'Objet trouvé.',
            'statut' => 'en_attente',
        ]);
        $path = 'declarations/ancien-document.pdf';
        Storage::disk('public')->put($path, 'document-prive');
        $id = DB::table('pieces_jointes')->insertGetId([
            'declaration_id' => $declaration->id,
            'type_document' => 'piece_jointe',
            'disque' => 'public',
            'chemin' => $path,
            'nom_original' => 'ancien-document.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_17_000000_secure_attachments_and_track_meta_posts.php');
        $migration->up();

        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseHas('pieces_jointes', ['id' => $id, 'disque' => 'local']);
    }
}
