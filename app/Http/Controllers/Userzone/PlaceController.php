<?php

namespace App\Http\Controllers\Userzone;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlaceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('userzone/places/index', [
            'places' => Place::with('city:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'city_id']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('userzone/places/create', [
            'cities' => City::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'latitude' => ['required', 'numeric', 'decimal:0,6', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'decimal:0,6', 'between:-180,180'],
        ]);

        Place::create($validated);

        return redirect()->route('userzone.places.index');
    }
}
