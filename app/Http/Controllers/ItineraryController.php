<?php

namespace App\Http\Controllers;

use App\Models\Itinerary;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItineraryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('itineraries/index', [
            'itineraries' => Itinerary::select(['id', 'title', 'slug', 'city_id', 'user_id'])
                ->published()
                ->with(['city:id,name', 'user:id,name', ...Itinerary::COVER_RELATIONS])
                ->withCount('places')
                ->orderBy('title')
                ->get()
                ->map(fn (Itinerary $itinerary) => [
                    ...$itinerary->only(['id', 'title', 'slug', 'places_count']),
                    'city' => $itinerary->city->only(['id', 'name']),
                    'user' => $itinerary->user->only(['id', 'name']),
                    'cover' => $itinerary->coverImage(),
                ]),
        ]);
    }

    public function show(Request $request, Itinerary $itinerary): Response|RedirectResponse
    {
        // Drafts and pending itineraries are managed in the Userzone, even by their owner.
        // Checked first, so an old numeric link never redirects to an unpublished itinerary.
        abort_unless($itinerary->status === Itinerary::PUBLISHED, 404);

        // Old numeric links (/itineraries/17) move permanently to the slug URL.
        if ($request->route()->originalParameter('itinerary') !== $itinerary->slug) {
            return redirect()->route('itineraries.show', $itinerary, 301);
        }

        $itinerary->load([
            'city:id,name',
            'user:id,name',
            // The relation returns places in stop order.
            'places' => fn ($query) => $query
                ->select(['places.id', 'places.name', 'places.description', 'places.themes', 'places.films'])
                ->with('media'),
            ...Itinerary::COVER_RELATIONS,
        ]);

        return Inertia::render('itineraries/show', [
            'itinerary' => [
                ...$itinerary->only(['id', 'title']),
                'city' => $itinerary->city->only(['id', 'name']),
                'user' => $itinerary->user->only(['id', 'name']),
                'cover' => $itinerary->coverImage(),
                'places' => $itinerary->places->map(fn (Place $place) => [
                    ...$place->only(['id', 'name', 'description']),
                    'image_url' => $place->imageUrl('thumbnail'),
                    'themes' => $place->themeLabels(),
                    'films' => $place->films ?? [],
                ]),
            ],
        ]);
    }
}
