<?php

use App\Models\Place;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

return new class extends Migration
{
    // The limits that applied to uploads when this migration was written.
    private const IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    private const MAX_IMAGE_BYTES = 8192 * 1024;

    private const MAX_IMAGE_SIDE = 5000;

    private const MAX_IMAGE_PIXELS = 8_000_000;

    // Every image is committed on its own, so a retry continues after the last successful transfer.
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $paths = DB::table('places')->whereNotNull('image_path')->orderBy('id')->pluck('image_path', 'id')->all();

        if ($paths !== []) {
            $this->transferImages($paths);
        }

        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }

    /**
     * Reverse the migrations.
     *
     * This only restores an empty column: the images stay in MediaLibrary and the former paths are not recovered.
     */
    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->string('image_path')->nullable();
        });
    }

    private function transferImages(array $paths): void
    {
        $expected = $this->mediaIdsPerPlace();
        $problems = [];
        $extensions = [];

        foreach ($paths as $placeId => $path) {
            try {
                if (isset($expected[$placeId])) {
                    $this->ensureOriginalExists(Place::findOrFail($placeId));
                } else {
                    $extensions[$placeId] = $this->inspect($path);
                }
            } catch (Throwable $exception) {
                $problems[] = "Place {$placeId} ({$path}): {$exception->getMessage()}";
            }
        }

        $this->abortOn($problems);

        foreach ($extensions as $placeId => $extension) {
            try {
                $expected[$placeId] = [$this->transfer($placeId, $paths[$placeId], $extension)];
            } catch (Throwable $exception) {
                $this->abortOn(["Place {$placeId} ({$paths[$placeId]}): {$exception->getMessage()}"], $exception);
            }
        }

        $this->verify($paths, $expected);
    }

    /**
     * Returns the extension that belongs to the content of a legacy image, or throws when it cannot be transferred.
     */
    private function inspect(string $path): string
    {
        $disk = Storage::disk('public');

        $this->ensure(
            preg_match('#\Aplaces/[^/\\\\\x00-\x1F]+\z#', $path) === 1 && ! in_array(basename($path), ['.', '..'], true),
            'the path is not inside the places directory.',
        );

        $file = $disk->path($path);

        $this->ensure(is_file($file), 'the file is missing.');
        // A symbolic link could still point to any other file.
        $this->ensure(
            dirname(realpath($file)) === realpath($disk->path('places')),
            'the file is not inside the places directory.',
        );
        $this->ensure(is_readable($file), 'the file is not readable.');
        $this->ensure(filesize($file) <= self::MAX_IMAGE_BYTES, 'the file is larger than 8 MB.');

        $extension = self::IMAGE_EXTENSIONS[mime_content_type($file)] ?? null;
        $this->ensure($extension !== null, 'the file is not a JPG or PNG image.');

        // Only reads the file header: decoding a huge image could exhaust the memory.
        $size = @getimagesize($file);
        $this->ensure($size !== false, 'the image is damaged.');

        [$width, $height] = $size;
        $this->ensure(
            max($width, $height) <= self::MAX_IMAGE_SIDE && $width * $height <= self::MAX_IMAGE_PIXELS,
            'the image exceeds 5000 pixels on a side or 8 megapixels in total.',
        );

        Image::useImageDriver(config('media-library.image_driver'))->loadFile($file);

        return $extension;
    }

    private function transfer(int $placeId, string $path, string $extension): int
    {
        return DB::transaction(function () use ($placeId, $path, $extension) {
            $place = Place::findOrFail($placeId);

            // The single-file collection would silently replace an image that was uploaded in the meantime.
            $this->ensure($place->getMedia('images')->isEmpty(), 'an image was uploaded during the migration.');

            try {
                $media = $place->addMediaFromDisk($path, 'public')
                    ->preservingOriginal()
                    ->usingFileName(Str::random(40).'.'.$extension)
                    ->toMediaCollection('images');

                $this->verifyTransfer($media->fresh(), $path);

                return $media->id;
            } catch (Throwable $exception) {
                // The rollback removes the record, but not the files that were already written.
                $place->media()->where('collection_name', 'images')->get()->each->delete();

                throw $exception;
            }
        });
    }

    private function verifyTransfer(Media $media, string $path): void
    {
        $disk = Storage::disk($media->disk);
        $original = $media->getPathRelativeToRoot();

        $this->ensure(
            $disk->exists($original) && hash('sha256', $disk->get($original)) === hash('sha256', Storage::disk('public')->get($path)),
            'the image was not copied completely.',
        );

        foreach (['thumbnail', 'details'] as $conversion) {
            $this->ensure(
                $media->hasGeneratedConversion($conversion)
                    && Storage::disk($media->conversions_disk)->exists($media->getPathRelativeToRoot($conversion)),
                "the {$conversion} conversion was not generated.",
            );
        }
    }

    private function verify(array $paths, array $expected): void
    {
        $this->ensure(
            $this->mediaIdsPerPlace() == $expected,
            'The MediaLibrary images changed unexpectedly, the image_path column was kept.',
        );

        $problems = [];

        foreach ($paths as $placeId => $path) {
            try {
                $this->ensureOriginalExists(Place::findOrFail($placeId));
            } catch (Throwable $exception) {
                $problems[] = "Place {$placeId} ({$path}): {$exception->getMessage()}";
            }
        }

        $this->abortOn($problems);
    }

    private function mediaIdsPerPlace(): array
    {
        return Media::query()
            ->where('model_type', (new Place)->getMorphClass())
            ->where('collection_name', 'images')
            ->orderBy('id')
            ->get(['id', 'model_id'])
            ->groupBy('model_id')
            ->map(fn ($media) => $media->pluck('id')->all())
            ->all();
    }

    private function ensureOriginalExists(Place $place): void
    {
        $media = $place->getFirstMedia('images');

        $this->ensure(
            $media !== null && Storage::disk($media->disk)->exists($media->getPathRelativeToRoot()),
            'the file of its MediaLibrary image is missing.',
        );
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }

    private function abortOn(array $problems, ?Throwable $previous = null): void
    {
        if ($problems !== []) {
            throw new RuntimeException(
                "The image_path column was kept because these Place images cannot be moved to MediaLibrary:\n- ".implode("\n- ", $problems),
                previous: $previous,
            );
        }
    }
};
