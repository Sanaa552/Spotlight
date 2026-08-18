<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Statistique extends Model
{
    protected $fillable = [
        'administrateur_id',
        'type',
        'date_generation',
        'donnees',
    ];

    protected function casts(): array
    {
        return [
            'date_generation' => 'datetime',
            'donnees' => 'array',
        ];
    }

    public function administrateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrateur_id');
    }
}