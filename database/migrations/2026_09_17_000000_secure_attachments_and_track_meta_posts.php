<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('declarations', 'facebook_post_id')) {
            Schema::table('declarations', function (Blueprint $table) {
                $table->string('facebook_post_id')->nullable();
                $table->string('instagram_post_id')->nullable();
            });
        }

        DB::table('pieces_jointes')->where('disque', 'public')->orderBy('id')->chunkById(100, function ($pieces) {
            foreach ($pieces as $piece) {
                $path = $piece->chemin;

                if (Storage::disk('public')->exists($path)) {
                    $stream = Storage::disk('public')->readStream($path);
                    if (! $stream) {
                        throw new RuntimeException("Lecture impossible de la piece jointe {$piece->id}.");
                    }

                    try {
                        if (! Storage::disk('local')->put($path, $stream)) {
                            throw new RuntimeException("Copie privee impossible de la piece jointe {$piece->id}.");
                        }
                    } finally {
                        fclose($stream);
                    }

                    if (Storage::disk('local')->size($path) !== Storage::disk('public')->size($path)) {
                        throw new RuntimeException("Verification impossible de la piece jointe {$piece->id}.");
                    }

                    if (! Storage::disk('public')->delete($path)) {
                        throw new RuntimeException("Suppression de la copie publique impossible pour la piece jointe {$piece->id}.");
                    }
                } elseif (! Storage::disk('local')->exists($path)) {
                    Log::warning('Ancienne piece jointe introuvable Spotlight', ['piece_jointe_id' => $piece->id]);
                }

                DB::table('pieces_jointes')->where('id', $piece->id)->update(['disque' => 'local']);
                Log::info('Piece jointe securisee Spotlight', ['piece_jointe_id' => $piece->id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->dropColumn(['facebook_post_id', 'instagram_post_id']);
        });
    }
};
