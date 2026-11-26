<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Place extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = ['name', 'description', 'latitude', 'longitude', 'city_id'];

    protected static function booted(): void
    {
        // The database cascade removes its itinerary links, changing places_count.
        static::deleted(fn () => Itinerary::forgetWelcomeCache());
    }

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

    public function registerMediaConversions(?Media $media = null): void
    {
        // Fit::Max keeps the proportions and never enlarges small images.
        $this->addMediaConversion('thumbnail')
            ->performOnCollections('images')
            ->fit(Fit::Max, 300, 300)
            ->keepOriginalImageFormat()
            ->nonQueued();

        $this->addMediaConversion('details')
            ->performOnCollections('images')
            ->fit(Fit::Max, 600, 300)
            ->keepOriginalImageFormat()
            ->nonQueued();
    }

    public function imageUrl(?string $conversion = null): ?string
    {
        $image = $this->getFirstMedia('images');

        return $conversion === null
            ? $image?->getUrl()
            : $image?->getAvailableUrl([$conversion]);
    }
}
