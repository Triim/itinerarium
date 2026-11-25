<?php

namespace App\Http\Controllers\Userzone;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CityController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('userzone/cities/index', [
            'cities' => City::select(['id', 'name'])
                ->withCount(['places', 'itineraries'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('userzone/cities/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        City::create([...$validated, 'user_id' => $request->user()->id]);

        return redirect()->route('userzone.cities.index');
    }

    public function edit(City $city): Response
    {
        return Inertia::render('userzone/cities/edit', [
            'city' => $city->only(['id', 'name']),
        ]);
    }

    public function update(Request $request, City $city): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $city->update($validated);

        return redirect()->route('userzone.cities.index');
    }

    public function destroy(City $city): RedirectResponse
    {
        // Deleting a city cascades to its places and itineraries, so only an
        // empty city may be deleted. Checking and deleting in one query leaves
        // no moment in between for related records to be added.
        $deleted = City::whereKey($city->id)
            ->doesntHave('places')
            ->doesntHave('itineraries')
            ->delete();

        if (! $deleted) {
            return redirect()->route('userzone.cities.index')->withErrors([
                'city' => "\"{$city->name}\" cannot be deleted because it still has places or itineraries.",
            ]);
        }

        return redirect()->route('userzone.cities.index');
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
