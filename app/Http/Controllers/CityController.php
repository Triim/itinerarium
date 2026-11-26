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
                ->withCount(['itineraries' => fn ($query) => $query->published(), 'places'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(City $city): Response
    {
        $city->loadCount(['itineraries' => fn ($query) => $query->published(), 'places']);

        return Inertia::render('cities/show', [
            'city' => $city->only(['id', 'name', 'itineraries_count', 'places_count']),
            'itineraries' => $city->itineraries()
                ->published()
                ->select(['id', 'title', 'user_id'])
                ->with('user:id,name')
                ->withCount('places')
                ->orderBy('title')
                ->get()
                ->map(fn (Itinerary $itinerary) => [
                    ...$itinerary->only(['id', 'title', 'places_count']),
                    'user' => $itinerary->user->only(['id', 'name']),
                ]),
        ]);
    }
}
