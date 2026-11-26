<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

class Itinerary extends Model
{
    use HasFactory;

    public const WELCOME_CACHE_KEY = 'welcome.recent_itineraries';

    /** Seconds the cached list is fresh, and the most it may be served stale while it refreshes. */
    public const WELCOME_CACHE_TTL = [60, 300];

    protected $fillable = ['title', 'user_id', 'city_id'];

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetWelcomeCache());
        static::deleted(fn () => static::forgetWelcomeCache());
    }

    /**
     * The three most recent itineraries as plain public card data, cached.
     */
    public static function recentForWelcome(): array
    {
        return Cache::flexible(self::WELCOME_CACHE_KEY, self::WELCOME_CACHE_TTL, fn () => static::select(['id', 'title', 'city_id', 'user_id'])
            ->with(['city:id,name', 'user:id,name'])
            ->withCount('places')
            ->latest()
            ->orderByDesc('id')
            ->take(3)
            ->get()
            ->map(fn (self $itinerary) => [
                'id' => $itinerary->id,
                'title' => $itinerary->title,
                'places_count' => $itinerary->places_count,
                'city' => $itinerary->city->only(['id', 'name']),
                'user' => $itinerary->user->only(['id', 'name']),
            ])
            ->all());
    }

    /**
     * Cache::flexible() also stores when the value was created. Forgetting that
     * timestamp too makes any refresh already deferred for the old value skip.
     */
    public static function forgetWelcomeCache(): void
    {
        Cache::forget(self::WELCOME_CACHE_KEY);
        Cache::forget('illuminate:cache:flexible:created:'.self::WELCOME_CACHE_KEY);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function places(): BelongsToMany
    {
        return $this->belongsToMany(Place::class);
    }

    public function canManage(User $user): bool
    {
        return $user->is_admin || $this->user_id == $user->id;
    }
}
