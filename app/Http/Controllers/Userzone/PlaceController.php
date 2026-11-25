<?php

namespace App\Http\Controllers\Userzone;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

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

        // A failed upload must not leave a half-created place behind.
        DB::transaction(function () use ($validated) {
            $place = Place::create(Arr::except($validated, 'image'));

            if (isset($validated['image'])) {
                $place->update(['image_path' => $this->storeImage($validated['image'])]);
            }
        });

        return redirect()->route('userzone.places.index');
    }

    public function edit(Place $place): Response
    {
        return Inertia::render('userzone/places/edit', [
            'place' => $place->only(['id', 'name', 'description', 'city_id', 'latitude', 'longitude']),
            'image_url' => $place->imageUrl(),
            'cities' => City::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Place $place): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $attributes = Arr::except($validated, 'image');
        $previousPath = $place->image_path;

        if (isset($validated['image'])) {
            $attributes['image_path'] = $this->storeImage($validated['image']);
        }

        $place->update($attributes);

        // Only remove the old file once the place points to the new one.
        if (isset($validated['image']) && $previousPath !== null) {
            Storage::disk('public')->delete($previousPath);
        }

        return redirect()->route('userzone.places.index');
    }

    public function destroy(Place $place): RedirectResponse
    {
        $place->delete();

        if ($place->image_path !== null) {
            Storage::disk('public')->delete($place->image_path);
        }

        return redirect()->route('userzone.places.index');
    }

    private function storeImage(UploadedFile $image): string
    {
        $path = $image->store('places', 'public');

        // The public disk reports a failed write by returning false.
        if ($path === false) {
            throw new RuntimeException('The place image could not be stored.');
        }

        return $path;
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'latitude' => ['required', 'numeric', 'decimal:0,6', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'decimal:0,6', 'between:-180,180'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:8192'],
        ];
    }
}
