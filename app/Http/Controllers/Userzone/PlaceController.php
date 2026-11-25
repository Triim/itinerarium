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
                $this->addImage($place, $validated['image']);
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
        $legacyPath = null;

        if (isset($validated['image'])) {
            $legacyPath = $place->image_path;
            $attributes['image_path'] = null;
        }

        // A failed upload must leave the place and its previous image untouched.
        DB::transaction(function () use ($place, $validated, $attributes) {
            $place->update($attributes);

            if (isset($validated['image'])) {
                $this->addImage($place, $validated['image']);
            }
        });

        // Only remove the legacy file once nothing refers to it anymore.
        if ($legacyPath !== null) {
            Storage::disk('public')->delete($legacyPath);
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

    private function addImage(Place $place, UploadedFile $image): void
    {
        // A generated name keeps the client's file name and extension out of public storage.
        $place->addMedia($image)
            ->usingFileName($image->hashName())
            ->toMediaCollection('images');
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
