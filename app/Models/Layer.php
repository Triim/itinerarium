<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A map layer: tourist sights, filming locations, informal sights,
 * worth to try (local food), unique experience. New layers are just new rows.
 */
class Layer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'color', 'sort_order'];

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
