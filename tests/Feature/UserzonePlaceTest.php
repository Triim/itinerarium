<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Place management in the Userzone, administrators only, including the uploaded image.
 */
class UserzonePlaceTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private const THEME_OPTIONS = [
        ['id' => 'landmark', 'label' => 'Landmark'],
        ['id' => 'film', 'label' => 'Film & TV'],
        ['id' => 'architecture', 'label' => 'Architecture'],
        ['id' => 'history', 'label' => 'History'],
        ['id' => 'unusual', 'label' => 'Unusual places'],
        ['id' => 'local_food', 'label' => 'Local food'],
        ['id' => 'experience', 'label' => 'Experiences'],
    ];

    public function test_guests_cannot_access_place_management()
    {
        $place = $this->placeWithMedia();
        $files = $this->storedFiles();

        $this->get('/user/places')->assertRedirect('/login');
        $this->get('/user/places/create')->assertRedirect('/login');
        $this->get("/user/places/{$place->id}/edit")->assertRedirect('/login');
        $this->post('/user/places', $this->validData(['image' => UploadedFile::fake()->image('new.png')]))->assertRedirect('/login');
        $this->post("/user/places/{$place->id}", $this->validData(['_method' => 'PUT', 'image' => UploadedFile::fake()->image('new.png')]))->assertRedirect('/login');
        $this->delete("/user/places/{$place->id}")->assertRedirect('/login');

        $this->assertPlaceUnchanged($place, $files);
    }

    public function test_regular_users_cannot_open_the_place_management_pages()
    {
        $place = Place::factory()->create();

        $this->actingAs(User::factory()->create());

        $this->get('/user/places')->assertForbidden();
        $this->get('/user/places/create')->assertForbidden();
        $this->get("/user/places/{$place->id}/edit")->assertForbidden();
    }

    public function test_regular_users_cannot_create_update_or_delete_places_even_in_their_own_itinerary()
    {
        $user = User::factory()->create();
        $place = $this->placeWithMedia();
        $place->update(['themes' => ['film'], 'films' => ['Amélie']]);
        $place = $place->fresh();
        Itinerary::factory()->for($user)->for($place->city)->hasAttached($place)->create();
        $files = $this->storedFiles();
        $links = DB::table('itinerary_place')->get();

        $this->actingAs($user);

        // Rejected before validation, so the response reveals no validation errors.
        $this->post('/user/places', [])->assertForbidden()->assertSessionHasNoErrors();
        $this->put("/user/places/{$place->id}", [])->assertForbidden()->assertSessionHasNoErrors();

        $this->post('/user/places', $this->validData(['image' => UploadedFile::fake()->image('new.png'), 'themes' => ['history']]))->assertForbidden();
        $this->put("/user/places/{$place->id}", $this->validData(['themes' => [], 'films' => []]))->assertForbidden();
        $this->patch("/user/places/{$place->id}", $this->validData())->assertForbidden();
        $this->post("/user/places/{$place->id}", $this->validData(['_method' => 'PUT', 'image' => UploadedFile::fake()->image('new.png')]))->assertForbidden();
        $this->delete("/user/places/{$place->id}")->assertForbidden();

        $this->assertPlaceUnchanged($place, $files);
        $this->assertEquals($links, DB::table('itinerary_place')->get());
    }

    public function test_administrators_can_see_all_places_with_their_city()
    {
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $bangkok = City::factory()->create(['name' => 'Bangkok']);
        Place::factory()->for($bangkok)->create(['name' => 'Wat Pho']);
        Place::factory()->for($barcelona)->create(['name' => 'Casa Batlló']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/user/places')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/places/index')
                ->has('places', 2)
                ->where('places.0.name', 'Casa Batlló')
                ->where('places.0.city.name', 'Barcelona')
                ->where('places.1.name', 'Wat Pho')
                ->where('places.1.city.name', 'Bangkok'));
    }

    public function test_create_and_edit_pages_receive_the_place_cities_and_theme_vocabulary()
    {
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $bangkok = City::factory()->create(['name' => 'Bangkok']);
        $place = Place::factory()->for($barcelona)->create([
            'name' => 'Casa Batlló',
            'description' => 'A house by Gaudí.',
            'latitude' => 41.39164,
            'longitude' => 2.16489,
            'themes' => ['film', 'local_food'],
            'films' => ['Ratatouille'],
        ]);
        $place->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('images');
        $cities = [['id' => $bangkok->id, 'name' => 'Bangkok'], ['id' => $barcelona->id, 'name' => 'Barcelona']];

        $this->actingAs(User::factory()->admin()->create())
            ->get('/user/places/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/places/create')
                ->where('cities', $cities)
                ->where('theme_options', self::THEME_OPTIONS));

        // The edit form shows the original image, not a conversion.
        $this->get("/user/places/{$place->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/places/edit')
                ->where('place', [
                    'id' => $place->id,
                    'name' => 'Casa Batlló',
                    'description' => 'A house by Gaudí.',
                    'city_id' => $barcelona->id,
                    'latitude' => 41.39164,
                    'longitude' => 2.16489,
                    'themes' => ['film', 'local_food'],
                    'films' => ['Ratatouille'],
                ])
                ->where('image_url', $place->getFirstMedia('images')->getUrl())
                ->where('cities', $cities)
                ->where('theme_options', self::THEME_OPTIONS));
    }

    public function test_administrators_can_create_a_place_without_an_image_themes_or_films()
    {
        $data = $this->validData();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/places', $data)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.places.index'));

        $this->assertDatabaseHas('places', $data);
        $place = Place::sole();
        $this->assertSame([], $place->themes);
        $this->assertSame([], $place->films);
        $this->assertNull($place->imageUrl());
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->get('/user/places')->assertInertia(fn (Assert $page) => $page->where('places.0.name', 'Sagrada Família'));
        $this->get("/places/{$place->id}")->assertOk();
    }

    public function test_a_place_can_be_created_with_several_themes_and_films()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/places', $this->validData([
                'themes' => ['history', 'film', 'landmark'],
                'films' => ['The Dreamers', 'Funny Face', str_repeat('a', 255)],
            ]))
            ->assertSessionHasNoErrors();

        $place = Place::sole();
        // Themes are stored in vocabulary order; films keep the entered order.
        $this->assertSame(['landmark', 'film', 'history'], $place->themes);
        $this->assertSame(['The Dreamers', 'Funny Face', str_repeat('a', 255)], $place->films);
    }

    #[DataProvider('boundaryCoordinates')]
    public function test_boundary_coordinates_are_accepted(int $latitude, int $longitude)
    {
        $data = $this->validData(['latitude' => $latitude, 'longitude' => $longitude]);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/places', $data)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('places', $data);
    }

    public static function boundaryCoordinates(): array
    {
        return [
            'minimum' => [-90, -180],
            'zero' => [0, 0],
            'maximum' => [90, 180],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_rejected(string $field, mixed $value)
    {
        $this->actingAs(User::factory()->admin()->create())
            ->from('/user/places/create')
            ->post('/user/places', $this->validData([$field => $value]))
            ->assertRedirect('/user/places/create')
            ->assertSessionHasErrors([$field]);

        $this->assertDatabaseCount('places', 0);
    }

    public static function invalidValues(): array
    {
        return [
            'missing name' => ['name', null],
            'short name' => ['name', 'ab'],
            'long name' => ['name', str_repeat('a', 256)],
            'non-string name' => ['name', ['abc']],
            'missing description' => ['description', null],
            'missing city' => ['city_id', null],
            'unknown city' => ['city_id', 9999],
            'non-numeric city' => ['city_id', 'abc'],
            'missing latitude' => ['latitude', null],
            'non-numeric latitude' => ['latitude', 'abc'],
            'latitude too low' => ['latitude', -90.1],
            'latitude too high' => ['latitude', 90.1],
            'latitude too precise' => ['latitude', '41.4036299'],
            'longitude too low' => ['longitude', -180.1],
            'longitude too high' => ['longitude', 180.1],
            'longitude too precise' => ['longitude', '2.1743558'],
            'image that is not a file' => ['image', 'photo.jpg'],
        ];
    }

    public function test_an_invalid_update_changes_nothing()
    {
        $place = Place::factory()->create()->fresh();

        $this->actingAs(User::factory()->admin()->create())
            ->from("/user/places/{$place->id}/edit")
            ->put("/user/places/{$place->id}", $this->validData(['name' => 'ab', 'latitude' => 90.1]))
            ->assertRedirect("/user/places/{$place->id}/edit")
            ->assertSessionHasErrors(['name', 'latitude']);

        $this->assertEquals($place->getAttributes(), $place->fresh()->getAttributes());
    }

    public function test_administrators_can_update_a_place_and_its_metadata_without_touching_its_links()
    {
        $other = Place::factory()->create()->fresh();
        $place = Place::factory()->create(['themes' => ['film', 'history'], 'films' => ['Amélie', 'La Haine']]);
        Itinerary::factory()->for($place->city)->create()->syncStops([$place->id]);
        $links = DB::table('itinerary_place')->get();
        $data = $this->validData(['city_id' => $place->city_id]);

        $this->actingAs(User::factory()->admin()->create())
            ->put("/user/places/{$place->id}", [...$data, 'themes' => ['film', 'experience'], 'films' => ['Amélie (2001)', 'Before Sunset']])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.places.index'));

        $this->assertDatabaseHas('places', ['id' => $place->id, ...$data]);
        $place->refresh();
        $this->assertSame(['film', 'experience'], $place->themes);
        $this->assertSame(['Amélie (2001)', 'Before Sunset'], $place->films);
        $this->assertEquals($other->getAttributes(), $other->fresh()->getAttributes());
        $this->assertEquals($links, DB::table('itinerary_place')->get());
    }

    #[DataProvider('emptyMetadata')]
    public function test_updating_can_clear_all_themes_and_films(array $metadata)
    {
        $place = Place::factory()->create(['themes' => ['film'], 'films' => ['Amélie']]);

        $this->actingAs(User::factory()->admin()->create())
            ->put("/user/places/{$place->id}", $this->validData($metadata))
            ->assertSessionHasNoErrors();

        $place->refresh();
        $this->assertSame([], $place->themes);
        $this->assertSame([], $place->films);
    }

    public static function emptyMetadata(): array
    {
        return [
            'omitted' => [[]],
            'null' => [['themes' => null, 'films' => null]],
            'empty lists' => [['themes' => [], 'films' => []]],
        ];
    }

    #[DataProvider('invalidMetadata')]
    public function test_invalid_themes_and_films_are_rejected(string $field, mixed $value, string $error)
    {
        $place = Place::factory()->create(['themes' => ['film'], 'films' => ['Amélie']]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/user/places', $this->validData([$field => $value]))
            ->assertSessionHasErrors([$error]);
        $this->assertDatabaseCount('places', 1);

        $this->put("/user/places/{$place->id}", $this->validData([$field => $value]))
            ->assertSessionHasErrors([$error]);
        $this->assertEquals($place->getAttributes(), $place->fresh()->getAttributes());
    }

    public static function invalidMetadata(): array
    {
        return [
            'unknown theme' => ['themes', ['food'], 'themes.0'],
            'theme label instead of identifier' => ['themes', ['Film & TV'], 'themes.0'],
            'duplicate theme' => ['themes', ['film', 'film'], 'themes.0'],
            'nested theme' => ['themes', [['film']], 'themes.0'],
            'themes not a list' => ['themes', ['a' => 'film'], 'themes'],
            'themes not an array' => ['themes', 'film', 'themes'],
            'empty film title' => ['films', ['Amélie', ''], 'films.1'],
            'blank film title' => ['films', ['   '], 'films.0'],
            'too long film title' => ['films', [str_repeat('a', 256)], 'films.0'],
            'nested film title' => ['films', [['Amélie']], 'films.0'],
            'duplicate film title' => ['films', ['Amélie', 'amélie'], 'films.0'],
            'films not a list' => ['films', ['a' => 'Amélie'], 'films'],
            'films not an array' => ['films', 'Amélie', 'films'],
            'too many films' => ['films', array_map(fn ($i) => "Film {$i}", range(1, 21)), 'films'],
        ];
    }

    public function test_a_missing_place_returns_not_found()
    {
        $place = Place::factory()->create();

        $this->actingAs(User::factory()->admin()->create());

        $this->get('/user/places/9999/edit')->assertNotFound();
        $this->put('/user/places/9999', $this->validData())->assertNotFound();
        $this->delete('/user/places/9999')->assertNotFound();

        $this->assertDatabaseCount('places', 1);
        $this->assertModelExists($place);
    }

    public function test_deleting_a_place_removes_only_the_place_its_links_and_its_image()
    {
        $place = $this->placeWithMedia();
        $other = Place::factory()->for($place->city)->create();
        $other->addMedia(UploadedFile::fake()->image('other.jpg'))->toMediaCollection('images');
        $itineraries = Itinerary::factory()->count(2)->for($place->city)->create();
        $itineraries->each(fn (Itinerary $itinerary) => $itinerary->places()->attach([$place->id, $other->id]));
        $itineraryRows = DB::table('itineraries')->get();

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/user/places/{$place->id}")
            ->assertRedirect(route('userzone.places.index'));

        $this->assertModelMissing($place);
        $this->assertModelExists($place->city);
        $this->assertEquals($itineraryRows, DB::table('itineraries')->get());
        $this->assertSame([$other->id, $other->id], DB::table('itinerary_place')->pluck('place_id')->all());
        $this->assertTrue(Media::sole()->model->is($other));
        $this->assertEqualsCanonicalizing($this->mediaFiles(Media::sole()), Storage::disk('public')->allFiles());
        $this->get("/places/{$place->id}")->assertNotFound();
    }

    #[DataProvider('uploads')]
    public function test_uploads_are_stored_under_a_random_name_with_proportional_conversions(string $filename, string $type, int $width, int $height, array $thumbnail, array $details)
    {
        $image = UploadedFile::fake()->image($filename, $width, $height);
        $contents = $image->getContent();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/places', $this->validData(['image' => $image]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.places.index'));

        $place = Place::sole();
        $media = Media::sole();
        $disk = Storage::disk('public');
        $extension = $type === 'image/png' ? 'png' : 'jpg';

        $this->assertTrue($media->model->is($place));
        $this->assertSame(['images', 'public'], [$media->collection_name, $media->disk]);
        $this->assertMatchesRegularExpression("#^[A-Za-z0-9]{40}\\.{$extension}$#", $media->file_name);
        $this->assertEqualsCanonicalizing($this->mediaFiles($media), $disk->allFiles());
        $this->assertSame($contents, $disk->get($media->getPathRelativeToRoot()));

        // Fit::Max keeps the proportions and never enlarges a small image.
        foreach (['thumbnail' => $thumbnail, 'details' => $details] as $conversion => $size) {
            $this->assertTrue($media->hasGeneratedConversion($conversion));
            $info = getimagesizefromstring($disk->get($media->getPathRelativeToRoot($conversion)));
            $this->assertSame([...$size, $type], [$info[0], $info[1], $info['mime']]);
        }

        $this->assertSame($media->getUrl(), $place->imageUrl());
        $this->assertSame($media->getUrl('thumbnail'), $place->imageUrl('thumbnail'));
        $this->assertSame($media->getUrl('details'), $place->imageUrl('details'));
    }

    public static function uploads(): array
    {
        return [
            'landscape jpg' => ['photo.jpg', 'image/jpeg', 1200, 800, [300, 200], [450, 300]],
            'portrait png' => ['photo.png', 'image/png', 800, 1200, [200, 300], [200, 300]],
            'panorama jpeg' => ['photo.jpeg', 'image/jpeg', 1200, 300, [300, 75], [600, 150]],
            'small png is not enlarged' => ['photo.png', 'image/png', 100, 50, [100, 50], [100, 50]],
        ];
    }

    #[DataProvider('misleadingFilenames')]
    public function test_the_stored_extension_follows_the_image_content_not_the_client_file_name(string $encoder, string $filename, string $extension)
    {
        $place = $this->placeWithMedia();
        $contents = $this->encode($encoder, imagecreatetruecolor(40, 20));

        $this->actingAs(User::factory()->admin()->create());

        $this->post('/user/places', $this->validData(['image' => $this->upload($filename, $contents)]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.places.index'));
        $this->post("/user/places/{$place->id}", $this->validData(['_method' => 'PUT', 'image' => $this->upload($filename, $contents)]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.places.index'));

        $media = Media::all();

        $this->assertCount(2, $media);
        $this->assertCount(2, $media->pluck('file_name')->unique());
        $media->each(fn (Media $item) => $this->assertMatchesRegularExpression("#^[A-Za-z0-9]{40}\\.{$extension}$#", $item->file_name));
        $this->assertEqualsCanonicalizing(
            $media->flatMap(fn (Media $item) => $this->mediaFiles($item))->all(),
            Storage::disk('public')->allFiles(),
        );
    }

    public static function misleadingFilenames(): array
    {
        return [
            'png with php segment' => ['imagepng', 'shell.php.png', 'png'],
            'png as html' => ['imagepng', 'page.html', 'png'],
            'png as jpg' => ['imagepng', 'photo.jpg', 'png'],
            'png with a path' => ['imagepng', '../../evil.png', 'png'],
            'jpeg as htm' => ['imagejpeg', 'weird name #1.phtml.HTM', 'jpg'],
            'jpeg as png' => ['imagejpeg', 'photo.png', 'jpg'],
            'jpeg without extension' => ['imagejpeg', 'photo', 'jpg'],
        ];
    }

    #[DataProvider('imagesWithinLimits')]
    #[RunInSeparateProcess]
    public function test_images_within_the_size_limits_are_accepted(string $filename, int $width, int $height, ?int $kilobytes = null)
    {
        $image = UploadedFile::fake()->image($filename, $width, $height);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/places', $this->validData(['image' => $kilobytes ? $image->size($kilobytes) : $image]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.places.index'));

        $this->assertEqualsCanonicalizing($this->mediaFiles(Media::sole()), Storage::disk('public')->allFiles());
    }

    public static function imagesWithinLimits(): array
    {
        return [
            'exactly 8 MB' => ['photo.jpg', 10, 10, 8192],
            'exactly 8 megapixels' => ['photo.jpg', 3200, 2500],
            'maximum width' => ['photo.png', 5000, 100],
            'maximum height' => ['photo.jpg', 100, 5000],
        ];
    }

    #[DataProvider('invalidImages')]
    public function test_invalid_images_are_rejected_and_the_existing_image_is_kept(string $filename, string $contents, ?int $kilobytes = null)
    {
        $place = $this->placeWithMedia();
        $files = $this->storedFiles();

        $this->actingAs(User::factory()->admin()->create());

        $this->from('/user/places/create')
            ->post('/user/places', $this->validData(['image' => $this->upload($filename, $contents, $kilobytes)]))
            ->assertRedirect('/user/places/create')
            ->assertSessionHasErrors(['image']);
        $this->post("/user/places/{$place->id}", $this->validData(['_method' => 'PUT', 'image' => $this->upload($filename, $contents, $kilobytes)]))
            ->assertSessionHasErrors(['image']);

        $this->assertPlaceUnchanged($place, $files);
    }

    public static function invalidImages(): array
    {
        return [
            'text file' => ['notes.txt', 'Just text'],
            'text disguised as an image' => ['photo.jpg', 'Just text'],
            'pdf' => ['document.pdf', '%PDF-1.4'],
            'svg' => ['drawing.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'],
            'gif' => ['animation.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')],
            'php file with image content' => ['photo.php', base64_decode(self::PNG)],
            'truncated png' => ['photo.png', substr(base64_decode(self::PNG), 0, 45)],
            'html disguised as an image' => ['photo.png', '<html><script>alert(1)</script></html>'],
            'file over 8 MB' => ['photo.png', base64_decode(self::PNG), 8193],
        ];
    }

    #[DataProvider('imagesOverLimits')]
    public function test_images_over_the_dimension_limits_are_rejected_and_the_existing_image_is_kept(string $type, int $width, int $height)
    {
        $place = $this->placeWithMedia();
        $files = $this->storedFiles();
        $error = ['image' => 'The image must not exceed 5000 pixels on either side or 8 megapixels in total.'];

        $this->actingAs(User::factory()->admin()->create());

        $this->post('/user/places', $this->validData(['image' => $this->imageClaiming($type, $width, $height)]))
            ->assertSessionHasErrors($error);
        $this->post("/user/places/{$place->id}", $this->validData(['_method' => 'PUT', 'image' => $this->imageClaiming($type, $width, $height)]))
            ->assertSessionHasErrors($error);

        $this->assertPlaceUnchanged($place, $files);
    }

    public static function imagesOverLimits(): array
    {
        return [
            'jpeg just over 8 megapixels' => ['jpeg', 4000, 2001],
            'png just over 8 megapixels' => ['png', 4000, 2001],
            'too wide' => ['jpeg', 5001, 10],
            'too high' => ['png', 10, 5001],
        ];
    }

    public function test_an_image_is_not_stored_when_another_field_is_invalid()
    {
        $place = $this->placeWithMedia();
        $files = $this->storedFiles();

        $this->actingAs(User::factory()->admin()->create());

        $this->post('/user/places', $this->validData(['name' => 'ab', 'image' => UploadedFile::fake()->image('new.png')]))
            ->assertSessionHasErrors(['name']);
        $this->post("/user/places/{$place->id}", $this->validData(['_method' => 'PUT', 'name' => 'ab', 'image' => UploadedFile::fake()->image('new.png')]))
            ->assertSessionHasErrors(['name']);

        $this->assertPlaceUnchanged($place, $files);
    }

    public function test_administrators_can_replace_an_image()
    {
        $place = $this->placeWithMedia();
        $previous = $place->getFirstMedia('images');
        $other = Place::factory()->create();
        $other->addMedia(UploadedFile::fake()->image('other.jpg'))->toMediaCollection('images');
        $image = UploadedFile::fake()->image('new.png', 20, 20);
        $contents = $image->getContent();

        $this->actingAs(User::factory()->admin()->create())
            ->post("/user/places/{$place->id}", $this->validData(['_method' => 'PUT', 'image' => $image]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.places.index'));

        $media = $place->fresh()->media()->sole();

        $this->assertModelMissing($previous);
        $this->assertSame($contents, Storage::disk('public')->get($media->getPathRelativeToRoot()));
        $this->assertSame($media->getUrl(), $place->fresh()->imageUrl());
        $this->assertEqualsCanonicalizing([
            ...$this->mediaFiles($media),
            ...$this->mediaFiles($other->getFirstMedia('images')),
        ], Storage::disk('public')->allFiles());
    }

    #[DataProvider('updatesWithoutAnImage')]
    public function test_editing_a_place_without_a_new_image_keeps_its_image(array $overrides)
    {
        $place = $this->placeWithMedia();
        $files = $this->storedFiles();

        $this->actingAs(User::factory()->admin()->create())
            ->put("/user/places/{$place->id}", $this->validData(['name' => 'Renamed place', ...$overrides]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed place', $place->fresh()->name);
        $this->assertSame($place->imageUrl(), $place->fresh()->imageUrl());
        $this->assertSame($files, $this->storedFiles());
    }

    public static function updatesWithoutAnImage(): array
    {
        return [
            'image field absent' => [[]],
            'image field empty' => [['image' => null]],
        ];
    }

    public function test_an_image_upload_saves_themes_and_films_in_the_same_request()
    {
        $place = Place::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/places', $this->validData([
                'image' => UploadedFile::fake()->image('photo.jpg'),
                'themes' => ['film'],
                'films' => ['Amélie'],
            ]))
            ->assertSessionHasNoErrors();

        $created = Place::latest('id')->first();
        $this->assertSame(['film'], $created->themes);
        $this->assertSame(['Amélie'], $created->films);
        $this->assertNotNull($created->imageUrl());

        // The edit form sends an image as a POST with a spoofed PUT method.
        $this->post("/user/places/{$place->id}", $this->validData([
            '_method' => 'PUT',
            'image' => UploadedFile::fake()->image('photo.png'),
            'themes' => ['history', 'unusual'],
            'films' => ['La Haine'],
        ]))->assertSessionHasNoErrors();

        $place->refresh();
        $this->assertSame(['history', 'unusual'], $place->themes);
        $this->assertSame(['La Haine'], $place->films);
        $this->assertNotNull($place->imageUrl());
        $this->assertDatabaseCount('media', 2);
    }

    public function test_a_failed_upload_does_not_create_a_place()
    {
        $this->failUploads();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/places', $this->validData(['image' => UploadedFile::fake()->image('new.png')]))
            ->assertServerError();

        $this->assertDatabaseCount('places', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_a_failed_upload_keeps_the_place_and_its_image()
    {
        $place = $this->placeWithMedia();
        $files = $this->storedFiles();
        $this->failUploads();

        $this->actingAs(User::factory()->admin()->create())
            ->post("/user/places/{$place->id}", $this->validData([
                '_method' => 'PUT',
                'image' => UploadedFile::fake()->image('new.png'),
            ]))
            ->assertServerError();

        $this->assertPlaceUnchanged($place, $files);
    }

    private function placeWithMedia(): Place
    {
        $place = Place::factory()->create()->fresh();
        $place->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('images');

        return $place;
    }

    /** The place row, its one image and every stored file are exactly as before. */
    private function assertPlaceUnchanged(Place $place, array $files): void
    {
        $this->assertDatabaseCount('places', 1);
        $this->assertEquals($place->getAttributes(), $place->fresh()->getAttributes());
        $this->assertSame([$place->getFirstMedia('images')->id], Media::pluck('id')->all());
        $this->assertSame($files, $this->storedFiles());
    }

    /** Every file on the public disk with its contents. */
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
            "{$media->id}/conversions/{$name}-cover.{$extension}",
        ];
    }

    private function failUploads(): void
    {
        $this->mock(Filesystem::class, function ($mock) {
            $mock->shouldReceive('add')->once()->andReturnFalse();
            $mock->shouldReceive('removeAllFiles');
        });
    }

    private function upload(string $filename, string $contents, ?int $kilobytes = null): UploadedFile
    {
        // A plain test upload, unlike a fake one, reports the type of its real content.
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $contents);

        if ($kilobytes !== null) {
            file_put_contents($path, str_repeat("\0", $kilobytes * 1024 - strlen($contents)), FILE_APPEND);
        }

        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return new UploadedFile($path, $filename, null, null, true);
    }

    private function imageClaiming(string $type, int $width, int $height): UploadedFile
    {
        // A small image whose header states other dimensions, so no large canvas is allocated.
        $contents = $this->encode("image{$type}", imagecreatetruecolor(16, 16));

        if ($type === 'jpeg') {
            $offset = strpos($contents, "\xFF\xC0") + 5;
            $contents = substr_replace($contents, pack('nn', $height, $width), $offset, 4);
        } else {
            $header = 'IHDR'.pack('NN', $width, $height).substr($contents, 24, 5);
            $contents = substr_replace($contents, $header.pack('N', crc32($header)), 12, 21);
        }

        $image = $this->upload("photo.{$type}", $contents);
        $this->assertSame([$width, $height], array_slice(getimagesize($image->getPathname()), 0, 2));

        return $image;
    }

    private function encode(string $encoder, \GdImage $image): string
    {
        ob_start();
        $encoder($image);

        return ob_get_clean();
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sagrada Família',
            'description' => 'A basilica by Gaudí.',
            'city_id' => City::factory()->create()->id,
            'latitude' => 41.40363,
            'longitude' => 2.174356,
        ], $overrides);
    }
}
