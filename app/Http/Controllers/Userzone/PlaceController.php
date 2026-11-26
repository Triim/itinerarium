<?php

namespace App\Http\Controllers\Userzone;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Place;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Image\Exceptions\CouldNotLoadImage;
use Spatie\Image\Image;

class PlaceController extends Controller
{
    private const IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    private const MAX_IMAGE_SIDE = 5000;

    private const MAX_IMAGE_PIXELS = 8_000_000;

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
            'theme_options' => Place::themeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules(), $this->messages());

        // A failed upload must not leave a half-created place behind.
        DB::transaction(function () use ($validated) {
            $place = Place::create($this->attributes($validated));

            if (isset($validated['image'])) {
                $this->addImage($place, $validated['image']);
            }
        });

        return redirect()->route('userzone.places.index');
    }

    public function edit(Place $place): Response
    {
        return Inertia::render('userzone/places/edit', [
            'place' => [
                ...$place->only(['id', 'name', 'description', 'city_id', 'latitude', 'longitude']),
                'themes' => $place->themes ?? [],
                'films' => $place->films ?? [],
            ],
            'image_url' => $place->imageUrl(),
            'cities' => City::orderBy('name')->get(['id', 'name']),
            'theme_options' => Place::themeOptions(),
        ]);
    }

    public function update(Request $request, Place $place): RedirectResponse
    {
        $validated = $request->validate($this->rules(), $this->messages());

        // A failed upload must leave the place and its previous image untouched.
        DB::transaction(function () use ($place, $validated) {
            $place->update($this->attributes($validated));

            if (isset($validated['image'])) {
                $this->addImage($place, $validated['image']);
            }
        });

        return redirect()->route('userzone.places.index');
    }

    public function destroy(Place $place): RedirectResponse
    {
        $place->delete();

        return redirect()->route('userzone.places.index');
    }

    /**
     * The form always sends both lists; one left out (an empty list in a multipart request) means none.
     */
    private function attributes(array $validated): array
    {
        return [
            ...Arr::except($validated, 'image'),
            'themes' => Place::normalizeThemes($validated['themes'] ?? []),
            'films' => $validated['films'] ?? [],
        ];
    }

    private function addImage(Place $place, UploadedFile $image): void
    {
        // The name is random and the extension follows the inspected content, never the client's file name.
        $extension = self::IMAGE_EXTENSIONS[$image->getMimeType()];

        $place->addMedia($image)
            ->usingFileName(Str::random(40).'.'.$extension)
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
            'themes' => ['nullable', 'array', 'list'],
            'themes.*' => ['bail', 'string', Rule::in(array_keys(Place::THEMES)), 'distinct'],
            'films' => ['nullable', 'array', 'list', 'max:20'],
            'films.*' => ['bail', 'required', 'string', 'max:255', 'distinct:ignore_case'],
            'image' => [
                'bail',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png',
                'mimetypes:'.implode(',', array_keys(self::IMAGE_EXTENSIONS)),
                'max:8192',
                $this->imageWithinLimits(...),
                $this->readableImage(...),
            ],
        ];
    }

    private function messages(): array
    {
        return [
            'themes.*.string' => 'The selected themes are invalid.',
            'themes.*.in' => 'The selected themes are invalid.',
            'themes.*.distinct' => 'Each theme can only be selected once.',
            'films.max' => 'A place can have at most 20 film titles.',
            'films.*.required' => 'Film titles cannot be empty.',
            'films.*.string' => 'Film titles must be text.',
            'films.*.max' => 'Film titles may not be longer than 255 characters.',
            'films.*.distinct' => 'Each film title can only be added once.',
        ];
    }

    private function imageWithinLimits(string $attribute, UploadedFile $value, Closure $fail): void
    {
        // Only reads the file header: decoding a huge image could exhaust the memory.
        $size = @getimagesize($value->getPathname());

        if ($size === false) {
            $fail('validation.image')->translate();

            return;
        }

        [$width, $height] = $size;

        if (max($width, $height) > self::MAX_IMAGE_SIDE || $width * $height > self::MAX_IMAGE_PIXELS) {
            $fail('The :attribute must not exceed 5000 pixels on either side or 8 megapixels in total.');
        }
    }

    private function readableImage(string $attribute, UploadedFile $value, Closure $fail): void
    {
        // Damaged files can pass the type checks, but would break the image conversions.
        try {
            Image::useImageDriver(config('media-library.image_driver'))->loadFile($value->getPathname());
        } catch (CouldNotLoadImage) {
            $fail('validation.image')->translate();
        }
    }
}
