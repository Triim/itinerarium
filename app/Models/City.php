<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'user_id'];

    protected static function booted(): void
    {
        static::updated(function (self $city) {
            if ($city->wasChanged('name')) {
                Itinerary::forgetWelcomeCache();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function itineraries(): HasMany
    {
        return $this->hasMany(Itinerary::class);
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    /**
     * The place whose photo is the city's cover: the first photographed landmark in the
     * alphabetical place list, otherwise the first photographed place. A has-many with a
     * limit, so it can be eager loaded for a whole list of cities.
     */
    public function coverPlace(): HasMany
    {
        return $this->places()
            ->has('media')
            ->with('media')
            // Themes are stored as a JSON list of identifiers, e.g. ["landmark","history"].
            ->orderByRaw('themes LIKE ? DESC', ['%"landmark"%'])
            ->orderBy('name')
            ->orderBy('id')
            ->limit(1);
    }

    /**
     * The cover photo, or null when none of the city's places has one.
     */
    public function coverImage(): ?array
    {
        return $this->coverPlace->first()?->coverImage();
    }
}
