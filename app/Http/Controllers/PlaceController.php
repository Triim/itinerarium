<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Place;
use Inertia\Inertia;
use Inertia\Response;

class PlaceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('places/index', [
            'cities' => City::select(['id', 'name'])
                ->whereHas('places')
                ->with(['places' => fn ($query) => $query
                    ->select(['id', 'name', 'city_id'])
                    ->withCount('itineraries')
                    ->orderBy('name')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(Place $place): Response
    {
        return Inertia::render('places/show', [
            'place' => $place->only(['id', 'name', 'description']),
            'city' => $place->city->only(['id', 'name']),
            'itineraries' => $place->itineraries()
                ->with('user:id,name')
                ->orderBy('title')
                ->get(['itineraries.id', 'title', 'user_id']),
        ]);
    }
}
