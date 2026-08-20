<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commentaire extends Model
{
    protected $fillable = [
        'declaration_id',
        'user_id',
        'contenu',
    ];

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(Declaration::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}