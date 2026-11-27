<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Itinerary;
use Inertia\Inertia;
use Inertia\Response;

class CityController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('cities/index', [
            'cities' => City::select(['id', 'name'])
                ->with('coverPlace')
                ->withCount(['itineraries' => fn ($query) => $query->published(), 'places'])
                ->orderBy('name')
                ->get()
                ->map(fn (City $city) => [
                    ...$city->only(['id', 'name', 'itineraries_count', 'places_count']),
                    'cover' => $city->coverImage(),
                ]),
        ]);
    }

    public function show(City $city): Response
    {
        $city->loadCount(['itineraries' => fn ($query) => $query->published(), 'places']);

        return Inertia::render('cities/show', [
            'city' => [
                ...$city->only(['id', 'name', 'itineraries_count', 'places_count']),
                'cover' => $city->coverImage(),
            ],
            'itineraries' => $city->itineraries()
                ->published()
                ->select(['id', 'title', 'slug', 'city_id', 'user_id'])
                ->with(['user:id,name', 'coverPlace'])
                ->withCount('places')
                ->orderBy('title')
                ->get()
                // Every itinerary here falls back to this city's cover, which is already loaded.
                ->each(fn (Itinerary $itinerary) => $itinerary->setRelation('city', $city))
                ->map(fn (Itinerary $itinerary) => [
                    ...$itinerary->only(['id', 'title', 'slug', 'places_count']),
                    'user' => $itinerary->user->only(['id', 'name']),
                    'cover' => $itinerary->coverImage(),
                ]),
        ]);
    }
}
