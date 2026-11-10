<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Layer;
use Inertia\Inertia;
use Inertia\Response;

class CityController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('cities/index', [
            'cities' => City::withCount(['places', 'itineraries' => fn ($q) => $q->published()])
                ->orderBy('name')
                ->get(),
        ]);
    }

    /** The core screen: a city map with toggleable layers. */
    public function show(City $city): Response
    {
        return Inertia::render('cities/show', [
            'city' => $city,
            'layers' => Layer::ordered()->get(),
            'places' => $city->places()->get(),
            'itineraries' => $city->itineraries()
                ->published()
                ->with('author:id,name')
                ->withCount('places')
                ->latest()
                ->get(),
        ]);
    }
}
