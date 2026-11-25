<?php

namespace App\Http\Controllers\Userzone;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ItineraryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('userzone/itineraries/index', [
            'itineraries' => Itinerary::with(['city:id,name', 'user:id,name'])
                ->orderBy('title')
                ->get(['id', 'title', 'city_id', 'user_id']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('userzone/itineraries/create', $this->options());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $itinerary = $request->user()->itineraries()->create(Arr::except($validated, 'place_ids'));

        $itinerary->places()->sync($validated['place_ids'] ?? []);

        return redirect()->route('userzone.itineraries.index');
    }

    public function edit(Itinerary $itinerary): Response
    {
        return Inertia::render('userzone/itineraries/edit', [
            'itinerary' => [
                ...$itinerary->only(['id', 'title', 'city_id']),
                // A place can be moved to another city after it was selected.
                'place_ids' => $itinerary->places()
                    ->where('places.city_id', $itinerary->city_id)
                    ->orderBy('places.name')
                    ->pluck('places.id')
                    ->unique()
                    ->values(),
            ],
            ...$this->options(),
        ]);
    }

    public function update(Request $request, Itinerary $itinerary): RedirectResponse
    {
        $validated = $this->validated($request);

        $itinerary->update(Arr::except($validated, 'place_ids'));

        $itinerary->places()->sync($validated['place_ids'] ?? []);

        return redirect()->route('userzone.itineraries.index');
    }

    public function destroy(Itinerary $itinerary): RedirectResponse
    {
        $itinerary->places()->detach();
        $itinerary->delete();

        return redirect()->route('userzone.itineraries.index');
    }

    private function options(): array
    {
        return [
            'cities' => City::orderBy('name')->get(['id', 'name']),
            'places' => Place::orderBy('name')->get(['id', 'name', 'city_id']),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'city_id' => ['bail', 'required', 'integer', 'exists:cities,id'],
            'place_ids' => ['nullable', 'array'],
            'place_ids.*' => [
                'bail',
                'integer',
                'distinct',
                Rule::exists('places', 'id')->where('city_id', $request->integer('city_id')),
            ],
        ], [
            'place_ids.*.integer' => 'The selected places are invalid.',
            'place_ids.*.distinct' => 'Each place can only be selected once.',
            'place_ids.*.exists' => 'All selected places must belong to the selected city.',
        ]);
    }
}
