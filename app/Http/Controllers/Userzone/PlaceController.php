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
        $validated = $request->validate($this->rules());

        Place::create($validated);

        return redirect()->route('userzone.places.index');
    }

    public function edit(Place $place): Response
    {
        return Inertia::render('userzone/places/edit', [
            'place' => $place->only(['id', 'name', 'description', 'city_id', 'latitude', 'longitude']),
            'cities' => City::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Place $place): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $place->update($validated);

        return redirect()->route('userzone.places.index');
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'latitude' => ['required', 'numeric', 'decimal:0,6', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'decimal:0,6', 'between:-180,180'],
        ];
    }
}
