<?php

namespace App\Http\Controllers\Userzone;

use App\Http\Controllers\Controller;
use App\Models\Itinerary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's saved itineraries (itinerary_user). Every action works on the current
 * user's own saves only, so no one can add or remove a save for someone else.
 */
class SavedItineraryController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('userzone/saved-itineraries/index', [
            // The pivot table has user_id and created_at too, so the columns are qualified.
            'itineraries' => $request->user()->savedItineraries()
                ->select(['itineraries.id', 'itineraries.title', 'itineraries.slug', 'itineraries.city_id', 'itineraries.user_id'])
                ->published()
                ->with(['city:id,name', 'user:id,name', ...Itinerary::COVER_RELATIONS])
                ->withCount('places')
                // Most recently saved first.
                ->orderByPivot('created_at', 'desc')
                ->orderByDesc('itineraries.id')
                ->get()
                ->map(fn (Itinerary $itinerary) => [
                    ...$itinerary->only(['id', 'title', 'slug', 'places_count']),
                    'city' => $itinerary->city->only(['id', 'name']),
                    'user' => $itinerary->user->only(['id', 'name']),
                    'cover' => $itinerary->coverImage(),
                ]),
        ]);
    }

    /**
     * Saving twice keeps the single row. Unpublished itineraries have no public page, and
     * authors cannot save their own, so saves count only other users' interest. Saves rank the
     * Welcome list, so its cache is cleared, but only when a row was actually added.
     */
    public function store(Request $request, Itinerary $itinerary): RedirectResponse
    {
        abort_unless($itinerary->status === Itinerary::PUBLISHED, 404);
        abort_if($itinerary->user()->is($request->user()), 403);

        $changes = $request->user()->savedItineraries()->syncWithoutDetaching([$itinerary->id]);

        if ($changes['attached'] !== []) {
            Itinerary::forgetWelcomeCache();
        }

        return back();
    }

    /**
     * Removes only the current user's save; the itinerary and other users' saves stay.
     * Removing an itinerary that is not saved changes nothing, the Welcome cache included.
     */
    public function destroy(Request $request, Itinerary $itinerary): RedirectResponse
    {
        if ($request->user()->savedItineraries()->detach($itinerary->id) > 0) {
            Itinerary::forgetWelcomeCache();
        }

        return back();
    }
}
