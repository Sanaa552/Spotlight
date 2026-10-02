<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('facebook_avatar_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        foreach (DB::table('users')->whereNotNull('facebook_avatar_url')->select('facebook_avatar_url')->cursor() as $user) {
            if (strlen($user->facebook_avatar_url) > 255) {
                throw new RuntimeException('Une URL avatar depasse 255 caracteres ; annulation impossible sans perte de donnees.');
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('facebook_avatar_url')->nullable()->change();
        });
    }
};
