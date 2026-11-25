<?php

namespace App\Http\Controllers;

use App\Models\City;
use Inertia\Inertia;
use Inertia\Response;

class CityController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('cities/index', [
            'cities' => City::select(['id', 'name'])
                ->withCount('itineraries')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(City $city): Response
    {
        return Inertia::render('cities/show', [
            'city' => $city->only(['id', 'name']),
            'itineraries' => $city->itineraries()
                ->with('user:id,name')
                ->orderBy('title')
                ->get(['id', 'title', 'user_id']),
        ]);
    }
}
