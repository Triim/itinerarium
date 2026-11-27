<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Itinerary extends Model
{
    use HasFactory;

    /** Renamed from welcome.recent_itineraries when the value changed shape, so no old entry is read. */
    public const WELCOME_CACHE_KEY = 'welcome.itineraries';

    /** Seconds the cached list is fresh, and the most it may be served stale while it refreshes. */
    public const WELCOME_CACHE_TTL = [60, 300];

    /** Publication states: the owner submits a draft, an administrator approves it. */
    public const DRAFT = 'draft';

    public const PENDING = 'pending';

    public const PUBLISHED = 'published';

    /** Eager loaded by every list of itinerary cards, so coverImage() runs no queries per card. */
    public const COVER_RELATIONS = ['coverPlace', 'city.coverPlace'];

    protected $fillable = ['title', 'user_id', 'city_id', 'status'];

    /** Matches the database default, so a new itinerary is a draft before it is saved too. */
    protected $attributes = ['status' => self::DRAFT];

    protected static function booted(): void
    {
        // The slug needs the id, so it is set right after the insert. It is never
        // regenerated, so editing the title keeps shared links working.
        static::created(function (self $itinerary) {
            $itinerary->slug ??= static::slugFor($itinerary->title, $itinerary->id);
            $itinerary->saveQuietly();
        });
        static::saved(fn () => static::forgetWelcomeCache());
        static::deleted(fn () => static::forgetWelcomeCache());
    }

    /**
     * A readable, unique slug: the title plus the id suffix, e.g. a-weekend-in-madrid-17.
     */
    public static function slugFor(string $title, int $id): string
    {
        return (Str::slug(Str::limit($title, 80, '')) ?: 'itinerary').'-'.$id;
    }

    /**
     * The public route binds {itinerary:slug}. An old numeric link (/itineraries/17) still
     * resolves by id there, and the controller redirects it to the slug URL. Bindings
     * without a field (the Userzone routes) are unchanged.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        if ($field === 'slug' && ctype_digit((string) $value)) {
            return static::find($value);
        }

        return parent::resolveRouteBinding($value, $field);
    }

    /**
     * Only published itineraries are shown on public pages.
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('itineraries.status', self::PUBLISHED);
    }

    /**
     * The three most saved published itineraries as plain public card data, cached. Ties, and
     * itineraries nobody has saved, follow newest first, so with no saves at all this is the
     * three most recent. 'popular' says whether any card has a save, for the section heading.
     *
     * @return array{popular: bool, itineraries: list<array<string, mixed>>}
     */
    public static function popularForWelcome(): array
    {
        return Cache::flexible(self::WELCOME_CACHE_KEY, self::WELCOME_CACHE_TTL, function () {
            $itineraries = static::select(['id', 'title', 'slug', 'city_id', 'user_id'])
                ->published()
                ->with(['city:id,name', 'user:id,name', ...self::COVER_RELATIONS])
                // One subquery per count in the same select; the pivot is unique, so rows are distinct users.
                ->withCount(['places', 'savedByUsers'])
                ->orderByDesc('saved_by_users_count')
                ->latest()
                ->orderByDesc('id')
                ->take(3)
                ->get();

            return [
                // Sorted by saves, so the first card has one whenever any published itinerary does.
                'popular' => $itineraries->first()?->saved_by_users_count > 0,
                'itineraries' => $itineraries->map(fn (self $itinerary) => [
                    'id' => $itinerary->id,
                    'title' => $itinerary->title,
                    'slug' => $itinerary->slug,
                    'places_count' => $itinerary->places_count,
                    'city' => $itinerary->city->only(['id', 'name']),
                    'user' => $itinerary->user->only(['id', 'name']),
                    'cover' => $itinerary->coverImage(),
                ])->all(),
            ];
        });
    }

    /**
     * Without the cached value, the next Welcome visit rebuilds the list at once.
     */
    public static function forgetWelcomeCache(): void
    {
        Cache::forget(self::WELCOME_CACHE_KEY);
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
        // Links attached without a position (default 0) fall back to alphabetical order.
        return $this->belongsToMany(Place::class)
            ->withPivot('position')
            ->orderByPivot('position')
            ->orderBy('places.name')
            ->orderBy('places.id');
    }

    /**
     * Users who saved this itinerary. Each user counts once (unique pivot), which makes it
     * the basis for popularity. The author is user(), not part of this relationship.
     */
    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * The first stop with a photo, in stop order. Limited, so it can be eager loaded for a list.
     */
    public function coverPlace(): BelongsToMany
    {
        return $this->places()->has('media')->with('media')->limit(1);
    }

    /**
     * The photo of the first photographed stop, otherwise the city's cover, otherwise null.
     * Lists eager load coverPlace and city.coverPlace, so this runs no queries for them.
     */
    public function coverImage(): ?array
    {
        return $this->coverPlace->first()?->coverImage() ?? $this->city->coverImage();
    }

    /**
     * Sync the places as stops in the given order, numbered from 1.
     */
    public function syncStops(array $placeIds): void
    {
        $this->places()->sync(collect($placeIds)
            ->values()
            ->mapWithKeys(fn ($id, int $index) => [(int) $id => ['position' => $index + 1]])
            ->all());
    }

    /**
     * draft → pending. Returns false, changing nothing, from any other state.
     */
    public function submitForReview(): bool
    {
        return $this->moveTo(self::DRAFT, self::PENDING);
    }

    /**
     * pending → published. Returns false, changing nothing, from any other state.
     */
    public function approve(): bool
    {
        return $this->moveTo(self::PENDING, self::PUBLISHED);
    }

    private function moveTo(string $from, string $to): bool
    {
        if ($this->status !== $from) {
            return false;
        }

        // Saving fires the saved event, which clears the Welcome cache.
        return $this->update(['status' => $to]);
    }

    public function canManage(User $user): bool
    {
        return $user->is_admin || $this->user_id == $user->id;
    }
}
