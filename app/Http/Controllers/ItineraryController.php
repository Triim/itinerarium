<?php

namespace App\Http\Controllers;

use App\Models\Itinerary;
use App\Models\Place;
use Inertia\Inertia;
use Inertia\Response;

class ItineraryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('itineraries/index', [
            'itineraries' => Itinerary::select(['id', 'title', 'city_id', 'user_id'])
                ->with(['city:id,name', 'user:id,name'])
                ->withCount('places')
                ->orderBy('title')
                ->get()
                ->map(fn (Itinerary $itinerary) => [
                    ...$itinerary->only(['id', 'title', 'places_count']),
                    'city' => $itinerary->city->only(['id', 'name']),
                    'user' => $itinerary->user->only(['id', 'name']),
                ]),
        ]);
    }

    public function show(Itinerary $itinerary): Response
    {
        $itinerary->load([
            'city:id,name',
            'user:id,name',
            // The relation returns places in stop order.
            'places' => fn ($query) => $query
                ->select(['places.id', 'places.name', 'places.description', 'places.themes', 'places.films'])
                ->with('media'),
        ]);

        return Inertia::render('itineraries/show', [
            'itinerary' => [
                ...$itinerary->only(['id', 'title']),
                'city' => $itinerary->city->only(['id', 'name']),
                'user' => $itinerary->user->only(['id', 'name']),
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
