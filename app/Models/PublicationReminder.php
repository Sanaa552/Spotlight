<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationReminder extends Model
{
    protected $fillable = [
        'declaration_id',
        'user_id',
        'channel',
        'status',
        'post_id',
        'post_url',
        'error',
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
