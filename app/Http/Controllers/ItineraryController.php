<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItineraryRequest;
use App\Models\City;
use App\Models\Itinerary;
use App\Models\Layer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ItineraryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'city' => $request->string('city')->toString() ?: null,
            'layers' => array_values(array_filter((array) $request->input('layers', []), 'is_string')),
        ];

        return Inertia::render('itineraries/index', [
            'itineraries' => Itinerary::published()
                ->filter($filters)
                ->with(['author:id,name', 'city:id,name,slug', 'places.layer'])
                ->latest()
                ->paginate(12)
                ->withQueryString(),
            'cities' => City::orderBy('name')->get(['id', 'name', 'slug']),
            'layers' => Layer::ordered()->get(),
            'filters' => $filters,
        ]);
    }

    public function show(Itinerary $itinerary): Response
    {
        $this->authorize('view', $itinerary);

        return Inertia::render('itineraries/show', [
            'itinerary' => $itinerary->load(['author:id,name', 'city', 'places', 'selections:id,title,slug']),
            'layers' => Layer::ordered()->get(),
            'can' => ['update' => request()->user()?->can('update', $itinerary) ?? false],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('itineraries/form', [
            'itinerary' => null,
            'initialCityId' => City::where('slug', $request->string('city'))->value('id'),
            ...$this->formOptions(),
        ]);
    }

    public function store(ItineraryRequest $request): RedirectResponse
    {
        $itinerary = DB::transaction(function () use ($request) {
            $itinerary = $request->user()->itineraries()->create($request->safe()->except('place_ids'));
            $itinerary->syncStops($request->validated('place_ids'));

            return $itinerary;
        });

        return to_route('itineraries.show', $itinerary)->with('success', 'Маршрут создан.');
    }

    public function edit(Itinerary $itinerary): Response
    {
        $this->authorize('update', $itinerary);

        return Inertia::render('itineraries/form', [
            'itinerary' => $itinerary->load('places:id'),
            'initialCityId' => $itinerary->city_id,
            ...$this->formOptions(),
        ]);
    }

    public function update(ItineraryRequest $request, Itinerary $itinerary): RedirectResponse
    {
        $this->authorize('update', $itinerary);

        DB::transaction(function () use ($request, $itinerary) {
            $itinerary->update($request->safe()->except('place_ids'));
            $itinerary->syncStops($request->validated('place_ids'));
        });

        return to_route('itineraries.show', $itinerary)->with('success', 'Маршрут сохранён.');
    }

    public function destroy(Itinerary $itinerary): RedirectResponse
    {
        $this->authorize('delete', $itinerary);

        $itinerary->delete();

        return to_route('dashboard')->with('success', 'Маршрут удалён.');
    }

    /** Cities with their editorial places, so the editor can switch city without a round trip. */
    private function formOptions(): array
    {
        return [
            'cities' => City::with('places')->orderBy('name')->get(),
            'layers' => Layer::ordered()->get(),
        ];
    }
}
