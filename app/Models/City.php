<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'country', 'description', 'lat', 'lng', 'zoom'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'zoom' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    public function itineraries(): HasMany
    {
        return $this->hasMany(Itinerary::class);
    }
}
