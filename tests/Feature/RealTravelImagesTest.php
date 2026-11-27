<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Place;
use App\Models\User;
use Database\Seeders\RealTravelImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PharData;
use RecursiveIteratorIterator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class RealTravelImagesTest extends TestCase
{
    use RefreshDatabase;

    private const IMAGE_FIELDS = ['place', 'file', 'sha256', 'package_id', 'title', 'author', 'source_url', 'license', 'license_url', 'attribution', 'modifications', 'match'];

    private RealTravelImages $bundle;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->bundle = new RealTravelImages;
        $this->author = User::factory()->create(['email' => 'test@example.com']);
    }

    private function place(string $city, string $name): Place
    {
        $city = City::firstOrCreate(['name' => $city, 'user_id' => $this->author->id]);

        return Place::factory()->for($city)->create(['name' => $name]);
    }

    /** An image as the previous seeder attached it: the retired file name and bundled size. */
    private function addRetiredImage(Place $place, string $key): Media
    {
        $retired = $this->bundle->retired()[$key];
        $media = $place->addMedia(UploadedFile::fake()->image('old.jpg', 40, 30))
            ->usingFileName($retired['file_name'])
            ->toMediaCollection('images');
        $media->forceFill(['size' => $retired['size']])->save();

        return $media;
    }

    private function addUpload(Place $place): Media
    {
        return $place->addMedia(UploadedFile::fake()->image('upload.jpg', 40, 30))
            ->usingFileName(Str::random(40).'.jpg')
            ->toMediaCollection('images');
    }

    public function test_every_manifest_image_is_a_bundled_commons_photo_of_one_real_place_with_its_licence()
    {
        $images = RealTravelImages::readJson('images.json')['images'];
        $placeKeys = array_column(RealTravelImages::readJson('places.json')['places'], 'key');

        $this->assertCount(97, $images);
        $this->assertSame(count($images), count(array_unique(array_column($images, 'place'))));
        $this->assertSame(count($images), count(array_unique(array_column($images, 'file'))));

        foreach ($images as $image) {
            $key = $image['place'];

            // Only photo metadata: no addresses or notes from the source documents.
            $this->assertSame(self::IMAGE_FIELDS, array_keys($image), $key);
            $this->assertContains($key, $placeKeys);
            $this->assertStringStartsWith(explode('-', $key)[0].'/', $image['file'], $key);

            $path = 'phar://'.database_path("data/real-travel/images.tar/{$image['file']}");
            $this->assertSame($image['sha256'], hash_file('sha256', $path), $key);
            $this->assertSame(IMAGETYPE_JPEG, getimagesize($path)[2], $key);

            foreach (['title', 'author', 'modifications'] as $field) {
                $this->assertNotSame('', trim($image[$field]), "{$key} {$field}");
            }
            $this->assertStringStartsWith('https://commons.wikimedia.org/wiki/File:', $image['source_url'], $key);
            if ($image['license'] === 'Public domain') {
                $this->assertNull($image['license_url'], $key);
            } else {
                $this->assertMatchesRegularExpression('/^CC(0| BY(-SA)? \d\.\d( de)?)$/', $image['license'], $key);
                $this->assertStringStartsWith('https://creativecommons.org/', $image['license_url'], $key);
                $this->assertStringContainsString($image['license_url'], $image['attribution'], $key);
            }
            foreach (['title', 'author', 'license', 'modifications'] as $field) {
                $this->assertStringContainsString($image[$field], $image['attribution'], "{$key} {$field}");
            }
        }
    }

    public function test_the_archive_holds_exactly_the_manifest_photos_and_none_of_the_retired_files()
    {
        $archive = 'phar://'.database_path('data/real-travel/images.tar').'/';
        $files = [];
        foreach (new RecursiveIteratorIterator(new PharData(database_path('data/real-travel/images.tar'))) as $file) {
            $files[] = Str::after($file->getPathname(), $archive);
        }
        sort($files);

        $manifest = array_column($this->bundle->images(), 'file');
        sort($manifest);
        $this->assertSame($manifest, $files);

        $retired = array_column($this->bundle->retired(), 'file_name');
        $this->assertCount(30, $retired);
        $this->assertSame([], array_intersect($retired, array_map('basename', $files)));
    }

    public function test_the_dry_run_lists_the_changes_without_making_them()
    {
        $louvre = $this->place('Paris', 'Louvre Museum');
        $old = $this->addRetiredImage($louvre, 'paris-louvre');

        $this->artisan('real-travel:sync-images', ['--dry-run' => true])
            ->expectsOutputToContain('replace')
            ->expectsOutputToContain('1 to change')
            ->assertSuccessful();

        $this->assertSame([$old->id], $louvre->fresh()->getMedia('images')->pluck('id')->all());
    }

    public function test_it_replaces_or_removes_bundled_images_and_keeps_uploads()
    {
        $louvre = $this->place('Paris', 'Louvre Museum');
        $roseGarden = $this->place('Paris', 'Residence Rose Garden');
        $pontNeuf = $this->place('Paris', 'Pont Neuf');
        $elbphilharmonie = $this->place('Hamburg', 'Elbphilharmonie');
        $sample = $this->place('Barcelona', 'Sagrada Família');

        $retiredLouvre = $this->addRetiredImage($louvre, 'paris-louvre');
        $retiredRoseGarden = $this->addRetiredImage($roseGarden, 'paris-residence-rose-garden');
        $upload = $this->addUpload($pontNeuf);
        $sampleUpload = $this->addUpload($sample);

        $this->artisan('real-travel:sync-images', ['--force' => true])
            ->expectsOutputToContain('3 to change')
            ->expectsOutputToContain('keep: not a bundled image')
            ->assertSuccessful();

        $images = $this->bundle->images();
        $new = $louvre->fresh()->getFirstMedia('images');
        $this->assertTrue($this->bundle->isCurrent($new, $images['paris-louvre']));
        $this->assertSame('Ali Sabbagh', $new->getCustomProperty('credit')['author']);
        $this->assertModelMissing($retiredLouvre);
        Storage::disk('public')->assertMissing($retiredLouvre->getPathRelativeToRoot());

        // No verified replacement: the unlicensed image is removed and the Place has none.
        $this->assertModelMissing($retiredRoseGarden);
        $this->assertNull($roseGarden->fresh()->getFirstMedia('images'));

        $this->assertTrue($this->bundle->isCurrent($elbphilharmonie->fresh()->getFirstMedia('images'), $images['hamburg-elbphilharmonie']));

        foreach ([$upload, $sampleUpload] as $media) {
            $this->assertModelExists($media);
            Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
        }
        $this->assertSame($upload->id, $pontNeuf->fresh()->getFirstMedia('images')->id);
    }

    public function test_running_it_again_changes_nothing()
    {
        $louvre = $this->place('Paris', 'Louvre Museum');
        $this->addRetiredImage($louvre, 'paris-louvre');
        $this->artisan('real-travel:sync-images', ['--force' => true])->assertSuccessful();
        $ids = Media::pluck('id')->all();

        $this->artisan('real-travel:sync-images', ['--force' => true])
            ->expectsOutputToContain('0 to change, 1 already up to date')
            ->assertSuccessful();

        $this->assertSame($ids, Media::pluck('id')->all());
    }

    public function test_an_image_with_a_retired_name_but_another_size_is_not_replaced()
    {
        $louvre = $this->place('Paris', 'Louvre Museum');
        $media = $this->addRetiredImage($louvre, 'paris-louvre');
        $media->forceFill(['size' => $media->size + 1])->save();

        $this->artisan('real-travel:sync-images', ['--force' => true])
            ->expectsOutputToContain('keep: not a bundled image')
            ->expectsOutputToContain('0 to change')
            ->assertSuccessful();

        $this->assertModelExists($media);
    }

    public function test_nothing_changes_when_the_confirmation_is_declined()
    {
        $louvre = $this->place('Paris', 'Louvre Museum');
        $old = $this->addRetiredImage($louvre, 'paris-louvre');

        $this->artisan('real-travel:sync-images')
            ->expectsConfirmation('Apply these changes?', 'no')
            ->expectsOutputToContain('Nothing was changed.')
            ->assertSuccessful();

        $this->assertModelExists($old);
    }

    public function test_it_fails_without_the_real_travel_author()
    {
        $this->author->delete();

        $this->artisan('real-travel:sync-images', ['--dry-run' => true])->assertFailed();
    }

    public function test_the_place_page_credits_the_attached_photograph_only()
    {
        $louvre = $this->place('Paris', 'Louvre Museum');
        $image = $this->bundle->images()['paris-louvre'];
        $this->bundle->attach($louvre, $image);
        $uploaded = $this->place('Paris', 'Pont Neuf');
        $this->addUpload($uploaded);
        $missing = $this->place('Paris', 'Residence Rose Garden');

        $this->get(route('places.show', $louvre))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('place.image_credit', [
                    'title' => $image['title'],
                    'author' => $image['author'],
                    'source_url' => $image['source_url'],
                    'license' => 'CC0',
                    'license_url' => 'https://creativecommons.org/publicdomain/zero/1.0/',
                    'modifications' => $image['modifications'],
                ]));

        $this->get(route('places.show', $uploaded))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->whereNot('place.image_url', null)->where('place.image_credit', null));

        $this->get(route('places.show', $missing))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('place.image_url', null)->where('place.image_credit', null));
    }
}
