<?php

namespace Tests\Feature;

use App\Models\Itinerary;
use App\Models\Place;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;
use Spatie\MediaLibrary\Conversions\Events\ConversionWillStartEvent;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * The one-off migration that moved places.image_path files into MediaLibrary. It must stay safe for an
 * installation that still has legacy images: every file is checked before anything moves, unsafe paths and
 * files block the column removal, and an interrupted transfer can be retried without losing images.
 */
class PlaceImageMigrationTest extends TestCase
{
    private const MIGRATION = '2026_11_25_230000_move_place_images_to_media_library';

    protected function setUp(): void
    {
        parent::setUp();

        // Every test gets its own database file in the state before the migration.
        $database = tempnam(sys_get_temp_dir(), 'itinerarium');
        config([
            'database.connections.legacy' => [...config('database.connections.sqlite'), 'url' => null, 'database' => $database],
            'database.default' => 'legacy',
        ]);
        $this->beforeApplicationDestroyed(function () use ($database) {
            DB::purge('legacy');
            @unlink($database);
        });

        $this->artisan('migrate', [
            '--path' => array_values(array_filter(
                glob(database_path('migrations/*.php')),
                fn (string $path) => basename($path, '.php') < self::MIGRATION,
            )),
            '--realpath' => true,
        ])->assertSuccessful();

        $this->assertSame($database, DB::connection()->getDatabaseName());
        $this->assertTrue(Schema::hasColumn('places', 'image_path'));
    }

    #[DataProvider('legacyImages')]
    public function test_a_legacy_image_is_moved_to_the_media_library(string $encoder, string $path, string $extension, string $type)
    {
        $contents = $this->encode($encoder, 1200, 800);
        $place = $this->legacyPlace($path, $contents);

        $this->runMigration();

        $media = Media::sole();
        $disk = Storage::disk('public');

        $this->assertFalse(Schema::hasColumn('places', 'image_path'));
        $this->assertTrue($media->model->is($place));
        $this->assertSame('images', $media->collection_name);
        $this->assertSame('public', $media->disk);
        $this->assertSame($type, $media->mime_type);
        $this->assertMatchesRegularExpression("#^[A-Za-z0-9]{40}\\.{$extension}$#", $media->file_name);
        $this->assertSame($contents, $disk->get($media->getPathRelativeToRoot()));
        $this->assertSame($contents, $disk->get($path));
        $this->assertEqualsCanonicalizing([$path, ...$this->mediaFiles($media)], $disk->allFiles());

        foreach (['thumbnail' => [300, 200], 'details' => [450, 300]] as $conversion => $size) {
            $info = getimagesizefromstring($disk->get($media->getPathRelativeToRoot($conversion)));
            $this->assertSame([...$size, $type], [$info[0], $info[1], $info['mime']]);
        }
    }

    public static function legacyImages(): array
    {
        return [
            'jpeg' => ['imagejpeg', 'places/old.jpg', 'jpg', 'image/jpeg'],
            'png stored as jpg' => ['imagepng', 'places/old.jpg', 'png', 'image/png'],
            'jpeg with an unsafe name' => ['imagejpeg', 'places/shell.php.html', 'jpg', 'image/jpeg'],
        ];
    }

    public function test_places_without_an_image_and_other_data_remain_unchanged()
    {
        $this->legacyPlace();
        $place = Place::factory()->create();
        // Inserted directly: the legacy schema has no itinerary status column yet.
        $itineraryId = DB::table('itineraries')->insertGetId([...Arr::except(Itinerary::factory()->raw(), 'status'), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('itinerary_place')->insert(['itinerary_id' => $itineraryId, 'place_id' => $place->id]);
        $columns = Schema::getColumns('places');
        $foreignKeys = Schema::getForeignKeys('places');
        $rows = $this->placeRows();

        $this->runMigration();

        $this->assertEquals(array_values(array_filter($columns, fn ($column) => $column['name'] !== 'image_path')), Schema::getColumns('places'));
        $this->assertEquals($foreignKeys, Schema::getForeignKeys('places'));
        $this->assertEquals($rows, $this->placeRows());
        $this->assertNull($place->fresh()->imageUrl());
        $this->assertSame([$place->id], DB::table('itinerary_place')->where('itinerary_id', $itineraryId)->pluck('place_id')->all());
        $this->assertDatabaseCount('media', 1);
    }

    #[DataProvider('supersededLegacyImages')]
    public function test_an_existing_media_library_image_is_not_overwritten(?string $contents)
    {
        $place = $this->legacyPlace('places/old.jpg', $contents);
        $place->addMedia(UploadedFile::fake()->image('new.png'))->toMediaCollection('images');
        // An image from before the conversions existed must stay as it is too.
        Media::sole()->update(['generated_conversions' => []]);
        $attributes = Media::sole()->getAttributes();
        $files = $this->storedFiles();

        $this->runMigration();

        $this->assertFalse(Schema::hasColumn('places', 'image_path'));
        $this->assertSame($attributes, Media::sole()->getAttributes());
        $this->assertSame($files, $this->storedFiles());
        $this->assertSame(Media::sole()->getUrl(), $place->fresh()->imageUrl('thumbnail'));
    }

    public static function supersededLegacyImages(): array
    {
        return [
            'legacy file is present' => ['old'],
            'legacy file is missing' => [null],
        ];
    }

    public function test_a_media_library_image_without_its_file_blocks_the_column_removal()
    {
        $place = $this->legacyPlace();
        $place->addMedia(UploadedFile::fake()->image('new.png'))->toMediaCollection('images');
        Storage::disk('public')->delete(Media::sole()->getPathRelativeToRoot());

        $this->assertMigrationIsBlocked($place, 'the file of its MediaLibrary image is missing');
    }

    public function test_places_can_share_a_legacy_file()
    {
        $this->legacyPlace();
        $this->legacyPlace();

        $this->runMigration();

        $this->assertDatabaseCount('media', 2);
        $this->assertCount(2, Media::pluck('file_name')->unique());
        Storage::disk('public')->assertExists('places/old.jpg');
    }

    #[DataProvider('blockingImages')]
    public function test_an_image_that_cannot_be_moved_blocks_the_column_removal(string $path, ?string $contents, string $reason, ?int $paddedBytes = null)
    {
        // Padded here rather than in the provider, so the 8 MB string is not kept for the whole run.
        if ($paddedBytes !== null) {
            $contents = str_pad($contents, $paddedBytes, "\0");
        }

        // The valid image comes first, but nothing may be moved before everything is checked.
        $this->legacyPlace('places/valid.jpg');

        $this->assertMigrationIsBlocked($this->legacyPlace($path, $contents), $reason);
        $this->assertDatabaseCount('media', 0);
    }

    public static function blockingImages(): array
    {
        $outside = 'the path is not inside the places directory';
        $type = 'the file is not a JPG or PNG image';
        $size = 'the image exceeds 5000 pixels on a side or 8 megapixels in total';
        $png = self::encode('imagepng');

        return [
            'missing file' => ['places/missing.jpg', null, 'the file is missing'],
            'empty path' => ['', null, $outside],
            'other directory' => ['other/old.jpg', self::encode('imagejpeg'), $outside],
            'parent directory' => ['places/../old.jpg', null, $outside],
            'nested directory' => ['places/nested/old.jpg', self::encode('imagejpeg'), $outside],
            'absolute path' => ['/etc/hosts', null, $outside],
            'directory itself' => ['places/..', null, $outside],
            'text disguised as an image' => ['places/old.jpg', 'Just text', $type],
            'empty file' => ['places/old.jpg', '', $type],
            'svg' => ['places/old.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>', $type],
            'gif' => ['places/old.gif', self::encode('imagegif'), $type],
            'truncated png' => ['places/old.png', substr($png, 0, 45), ''],
            'file over 8 MB' => ['places/old.png', $png, 'the file is larger than 8 MB', 8192 * 1024 + 1],
            'png over 8 megapixels' => ['places/old.png', self::pngClaiming(4000, 2001), $size],
            'too wide' => ['places/old.png', self::pngClaiming(5001, 10), $size],
            'too high' => ['places/old.png', self::pngClaiming(10, 5001), $size],
        ];
    }

    public function test_every_problem_is_reported_at_once()
    {
        $first = $this->legacyPlace('places/first.jpg', null);
        $second = $this->legacyPlace('places/second.jpg', 'Just text');

        $message = $this->migrationFailure();

        $this->assertStringContainsString("Place {$first->id} (places/first.jpg): the file is missing.", $message);
        $this->assertStringContainsString("Place {$second->id} (places/second.jpg): the file is not a JPG or PNG image.", $message);
    }

    public function test_a_symbolic_link_out_of_the_places_directory_blocks_the_column_removal()
    {
        Storage::disk('public')->put('secret.jpg', $this->encode('imagejpeg'));
        $place = $this->legacyPlace('places/link.jpg', null);
        Storage::disk('public')->makeDirectory('places');
        symlink(Storage::disk('public')->path('secret.jpg'), Storage::disk('public')->path('places/link.jpg'));

        $message = $this->migrationFailure();

        $this->assertStringContainsString("Place {$place->id} (places/link.jpg): the file is not inside the places directory.", $message);
        $this->assertTrue(Schema::hasColumn('places', 'image_path'));
        $this->assertDatabaseCount('media', 0);
    }

    public function test_the_migration_can_be_retried_after_the_image_is_restored()
    {
        $place = $this->legacyPlace('places/old.jpg', null);
        $this->migrationFailure();
        Storage::disk('public')->put('places/old.jpg', $this->encode('imagejpeg'));

        $this->runMigration();

        $this->assertFalse(Schema::hasColumn('places', 'image_path'));
        $this->assertTrue(Media::sole()->model->is($place));
    }

    #[DataProvider('interruptions')]
    public function test_a_partial_transfer_can_be_retried(string $event, bool $throws)
    {
        $first = $this->legacyPlace('places/first.jpg', $this->encode('imagejpeg', 30, 30));
        $second = $this->legacyPlace('places/second.jpg', $this->encode('imagejpeg', 40, 40));
        $third = $this->legacyPlace('places/third.jpg', $this->encode('imagejpeg', 50, 50));
        $legacyFiles = $this->storedFiles();
        $fail = true;

        Event::listen($event, function ($event) use (&$fail, $second, $throws) {
            if (! $fail || $event->media->model_id !== $second->id) {
                return;
            }

            if ($throws) {
                throw new RuntimeException('Interrupted.');
            }

            // Not noticed by MediaLibrary itself, only by the verification.
            Storage::disk('public')->deleteDirectory("{$event->media->id}/conversions");
        });

        $message = $this->migrationFailure();

        $this->assertStringContainsString("Place {$second->id} (places/second.jpg)", $message);
        $this->assertTrue(Schema::hasColumn('places', 'image_path'));
        $this->assertSame(3, DB::table('places')->whereNotNull('image_path')->count());
        $transferred = Media::sole();
        $this->assertTrue($transferred->model->is($first));
        $this->assertEqualsCanonicalizing(
            [...array_keys($legacyFiles), ...$this->mediaFiles($transferred)],
            array_keys($this->storedFiles()),
        );

        $fail = false;
        $this->runMigration();

        $this->assertFalse(Schema::hasColumn('places', 'image_path'));
        $this->assertDatabaseCount('media', 3);
        $this->assertSame($transferred->getAttributes(), $first->media()->sole()->getAttributes());

        $expectedFiles = array_keys($legacyFiles);
        foreach ([[$first, 'first'], [$second, 'second'], [$third, 'third']] as [$place, $name]) {
            $media = $place->media()->sole();
            array_push($expectedFiles, ...$this->mediaFiles($media));
            $this->assertSame($legacyFiles["places/{$name}.jpg"], Storage::disk('public')->get($media->getPathRelativeToRoot()));
        }
        $this->assertEqualsCanonicalizing($expectedFiles, array_keys($this->storedFiles()));
        $this->assertSame($legacyFiles, array_intersect_key($this->storedFiles(), $legacyFiles));
    }

    public static function interruptions(): array
    {
        return [
            'after copying the original' => [MediaHasBeenAddedEvent::class, true],
            'during the conversions' => [ConversionWillStartEvent::class, true],
            'missing conversions' => [ConversionHasBeenCompletedEvent::class, false],
        ];
    }

    public function test_a_clean_database_does_not_contain_the_column()
    {
        $this->artisan('migrate:fresh')->assertSuccessful();

        $this->assertFalse(Schema::hasColumn('places', 'image_path'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_rolling_back_only_restores_an_empty_column()
    {
        $place = $this->legacyPlace();
        $this->runMigration();
        $files = $this->storedFiles();

        // Later migrations run in the same batch; roll back only this one.
        $this->artisan('migrate:rollback', [
            '--path' => [database_path('migrations/'.self::MIGRATION.'.php')],
            '--realpath' => true,
        ])->assertSuccessful();

        $this->assertTrue(Schema::hasColumn('places', 'image_path'));
        $this->assertSame([null], DB::table('places')->pluck('image_path')->all());
        $this->assertSame($files, $this->storedFiles());
        $this->assertSame(Media::sole()->getUrl(), $place->fresh()->imageUrl());

        $this->runMigration();

        $this->assertFalse(Schema::hasColumn('places', 'image_path'));
        $this->assertDatabaseCount('media', 1);
        $this->assertSame($files, $this->storedFiles());
    }

    private function runMigration(): void
    {
        // Only this migration: later ones change the places table and the itinerary links too.
        $this->artisan('migrate', [
            '--path' => [database_path('migrations/'.self::MIGRATION.'.php')],
            '--realpath' => true,
        ])->assertSuccessful();
    }

    private function migrationFailure(): string
    {
        try {
            $this->runMigration();
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }

        $this->fail('The migration was expected to fail.');
    }

    private function assertMigrationIsBlocked(Place $place, string $reason): void
    {
        $path = DB::table('places')->where('id', $place->id)->value('image_path');
        $files = $this->storedFiles();
        $media = Media::all()->toArray();

        $message = $this->migrationFailure();

        $this->assertStringContainsString("Place {$place->id} ({$path}): {$reason}", $message);
        $this->assertSame($path, DB::table('places')->where('id', $place->id)->value('image_path'));
        $this->assertDatabaseMissing('migrations', ['migration' => self::MIGRATION]);
        $this->assertSame($files, $this->storedFiles());
        $this->assertSame($media, Media::all()->toArray());
    }

    private function legacyPlace(string $path = 'places/old.jpg', ?string $contents = ''): Place
    {
        // Without explicit contents the file is a valid image.
        if (func_num_args() < 2) {
            $contents = $this->encode('imagejpeg');
        }

        if ($contents !== null) {
            Storage::disk('public')->put($path, $contents);
        }

        $place = Place::factory()->create();
        DB::table('places')->where('id', $place->id)->update(['image_path' => $path]);

        return $place;
    }

    private function placeRows(): array
    {
        return DB::table('places')->orderBy('id')->get()
            ->map(fn (object $row) => collect((array) $row)->except('image_path')->all())
            ->all();
    }

    private function storedFiles(): array
    {
        return collect(Storage::disk('public')->allFiles())
            ->mapWithKeys(fn (string $path) => [$path => Storage::disk('public')->get($path)])
            ->all();
    }

    private function mediaFiles(Media $media): array
    {
        ['filename' => $name, 'extension' => $extension] = pathinfo($media->file_name);

        return [
            "{$media->id}/{$media->file_name}",
            "{$media->id}/conversions/{$name}-thumbnail.{$extension}",
            "{$media->id}/conversions/{$name}-details.{$extension}",
        ];
    }

    private static function encode(string $encoder, int $width = 16, int $height = 16): string
    {
        ob_start();
        $encoder(imagecreatetruecolor($width, $height));

        return ob_get_clean();
    }

    private static function pngClaiming(int $width, int $height): string
    {
        // A small image whose header states other dimensions, as a real 48 MP canvas would not fit in memory.
        $contents = self::encode('imagepng');
        $header = 'IHDR'.pack('NN', $width, $height).substr($contents, 24, 5);

        return substr_replace($contents, $header.pack('N', crc32($header)), 12, 21);
    }
}
