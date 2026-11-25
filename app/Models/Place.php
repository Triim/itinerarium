<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Place extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = ['name', 'description', 'latitude', 'longitude', 'city_id', 'image_path'];

    protected $attributes = ['image_path' => null];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function itineraries(): BelongsToMany
    {
        return $this->belongsToMany(Itinerary::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useDisk('public')
            ->singleFile();
    }

    public function imageUrl(): ?string
    {
        $image = $this->getFirstMedia('images');

        if ($image !== null) {
            return $image->getUrl();
        }

        // Images uploaded before MediaLibrary are still referenced by their path.
        return $this->image_path === null
            ? null
            : Storage::disk('public')->url($this->image_path);
    }
}
