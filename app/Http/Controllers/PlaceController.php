<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Place;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlaceController extends Controller
{
    public function index(Request $request): Response
    {
        $cities = City::select(['id', 'name'])
            ->whereHas('places')
            ->with(['places' => fn ($query) => $query
                ->select(['id', 'name', 'city_id', 'themes'])
                ->with('media')
                ->withCount(['itineraries' => fn ($query) => $query->published()])
                ->orderBy('name')])
            ->orderBy('name')
            ->get();

        // ?city=<id> preselects a city; anything else falls back to all cities.
        $cityId = filter_var($request->query('city'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return Inertia::render('places/index', [
            'cities' => $cities->map(fn (City $city) => [
                ...$city->only(['id', 'name']),
                'places' => $city->places->map(fn (Place $place) => [
                    ...$place->only(['id', 'name', 'itineraries_count']),
                    'image_url' => $place->imageUrl('thumbnail'),
                    'themes' => Place::normalizeThemes($place->themes ?? []),
                ]),
            ]),
            'themes' => Place::themeOptions(),
            'selected_city' => $cities->contains('id', $cityId) ? $cityId : null,
        ]);
    }

    public function show(Place $place): Response
    {
        return Inertia::render('places/show', [
            'place' => [
                ...$place->only(['id', 'name', 'description']),
                'image_url' => $place->imageUrl('details'),
                'themes' => $place->themeLabels(),
                'films' => $place->films ?? [],
            ],
            'city' => $place->city->only(['id', 'name']),
            'itineraries' => $place->itineraries()
                ->published()
                ->with('user:id,name')
                ->orderBy('title')
                ->get(['itineraries.id', 'title', 'user_id']),
        ]);
    }
}
