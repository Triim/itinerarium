<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An author's route through a city: an ordered list of places.
 * Named Itinerary to avoid clashing with Laravel's Route facade.
 */
class Itinerary extends Model
{
    use HasFactory;

    protected $fillable = ['city_id', 'title', 'description', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function places(): BelongsToMany
    {
        return $this->belongsToMany(Place::class)
            ->withPivot(['position', 'note'])
            ->orderByPivot('position');
    }

    public function selections(): BelongsToMany
    {
        return $this->belongsToMany(Selection::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** Cities and layers act as the filters instead of keywords. */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['city'] ?? null, fn (Builder $q, string $slug) => $q->whereHas('city', fn (Builder $c) => $c->where('slug', $slug)))
            ->when($filters['layers'] ?? null, fn (Builder $q, array $layers) => $q->whereHas('places.layer', fn (Builder $l) => $l->whereIn('slug', $layers)));
    }

    /**
     * Replace the stops with the given ordered list of place ids.
     *
     * @param  list<int>  $placeIds
     */
    public function syncStops(array $placeIds): void
    {
        $this->places()->sync(
            collect(array_values($placeIds))->mapWithKeys(fn (int $id, int $i) => [$id => ['position' => $i + 1]])->all()
        );
    }
}
