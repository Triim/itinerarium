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
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('userzone/itineraries/index', [
            'itineraries' => Itinerary::with(['city:id,name', 'user:id,name'])
                ->unless($user->is_admin, fn ($query) => $query->where('user_id', $user->id))
                ->orderBy('title')
                ->get(['id', 'title', 'city_id', 'user_id', 'status']),
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

        $itinerary->syncStops($validated['place_ids'] ?? []);

        // sync() fires no model events, and the saved event ran before it changed places_count.
        Itinerary::forgetWelcomeCache();

        return redirect()->route('userzone.itineraries.index');
    }

    /**
     * The public page already shows a published itinerary, so the private resource route only checks access and sends the user there.
     */
    public function show(Request $request, Itinerary $itinerary): RedirectResponse
    {
        abort_unless($itinerary->canManage($request->user()), 403);

        // Drafts and pending itineraries have no public page yet.
        return $itinerary->status === Itinerary::PUBLISHED
            ? redirect()->route('itineraries.show', $itinerary)
            : redirect()->route('userzone.itineraries.edit', $itinerary);
    }

    public function edit(Request $request, Itinerary $itinerary): Response
    {
        abort_unless($itinerary->canManage($request->user()), 403);

        return Inertia::render('userzone/itineraries/edit', [
            'itinerary' => [
                ...$itinerary->only(['id', 'title', 'city_id']),
                // A place can be moved to another city after it was selected.
                'place_ids' => $itinerary->places()
                    ->where('places.city_id', $itinerary->city_id)
                    ->pluck('places.id')
                    ->unique()
                    ->values(),
            ],
            ...$this->options(),
        ]);
    }

    public function update(Request $request, Itinerary $itinerary): RedirectResponse
    {
        abort_unless($itinerary->canManage($request->user()), 403);

        $validated = $this->validated($request);

        $itinerary->fill(Arr::except($validated, 'place_ids'));

        // A pending review must cover the edited content, so the owner submits it again.
        // A published itinerary stays published: approval controls only the first publication.
        if ($itinerary->status === Itinerary::PENDING) {
            $itinerary->status = Itinerary::DRAFT;
        }

        $itinerary->save();

        $itinerary->syncStops($validated['place_ids'] ?? []);

        // sync() fires no model events, and the saved event ran before it changed places_count.
        Itinerary::forgetWelcomeCache();

        return redirect()->route('userzone.itineraries.index');
    }

    public function destroy(Request $request, Itinerary $itinerary): RedirectResponse
    {
        abort_unless($itinerary->canManage($request->user()), 403);

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
