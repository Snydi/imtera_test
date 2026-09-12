<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'url',
        'rating',
        'rating_updated_at',
        'rating_count',
        'review_count',
        'parsing_status',
        'parsing_progress',
        'parsing_error',
        'data_updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'rating_updated_at' => 'datetime',
            'rating_count' => 'integer',
            'review_count' => 'integer',
            'parsing_progress' => 'integer',
            'data_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
