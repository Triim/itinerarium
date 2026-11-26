<?php

namespace Tests\Feature;

use App\Http\Middleware\IsAdmin;
use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class UserzonePlaceTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/user/places')->assertRedirect('/login');
    }

    public function test_regular_users_cannot_open_the_place_management_pages()
    {
        $place = Place::factory()->create();

        $this->actingAs(User::factory()->create());

        $this->get('/user/places')->assertForbidden();
        $this->get('/user/places/create')->assertForbidden();
        $this->get("/user/places/{$place->id}/edit")->assertForbidden();
    }

    public function test_regular_users_cannot_store_a_place()
    {
        $this->actingAs(User::factory()->create())
            ->post('/user/places', $this->validData())
            ->assertForbidden();

        $this->assertDatabaseCount('places', 0);
    }

    public function test_regular_users_are_rejected_before_validation()
    {
        $place = Place::factory()->create();

        $this->actingAs(User::factory()->create());

        $this->post('/user/places', [])->assertForbidden()->assertSessionHasNoErrors();
        $this->put("/user/places/{$place->id}", [])->assertForbidden()->assertSessionHasNoErrors();
    }

    public function test_regular_users_cannot_update_a_place()
    {
        $place = Place::factory()->create();

        $this->actingAs(User::factory()->create());

        $this->put("/user/places/{$place->id}", $this->validData())->assertForbidden();
        $this->patch("/user/places/{$place->id}", $this->validData())->assertForbidden();

        $this->assertEquals($place->getAttributes(), $place->fresh()->getAttributes());
    }

    public function test_regular_users_cannot_delete_a_place_even_if_it_is_in_their_itinerary()
    {
        $user = User::factory()->create();
        $place = Place::factory()->create();
        $itineraries = [Itinerary::factory()->for($user)->create(), Itinerary::factory()->create()];

        foreach ($itineraries as $itinerary) {
            $itinerary->places()->attach($place);
        }

        $this->actingAs($user)
            ->delete("/user/places/{$place->id}")
            ->assertForbidden();

        $this->assertEquals($place->getAttributes(), $place->fresh()->getAttributes());
        $this->assertDatabaseCount('itinerary_place', 2);

        foreach ($itineraries as $itinerary) {
            $this->assertDatabaseHas('itinerary_place', [
                'itinerary_id' => $itinerary->id,
                'place_id' => $place->id,
            ]);
        }
    }

    public function test_public_place_pages_remain_accessible_to_guests_and_regular_users()
    {
        $place = Place::factory()->create();

        $this->get('/places')->assertOk();
        $this->get("/places/{$place->id}")->assertOk();

        $this->actingAs(User::factory()->create());

        $this->get('/places')->assertOk();
        $this->get("/places/{$place->id}")->assertOk();
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

    public function test_overview_renders_when_there_are_no_places()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/user/places')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/places/index')
                ->has('places', 0));
    }

    public function test_guests_cannot_open_the_create_page()
    {
        $this->get('/user/places/create')->assertRedirect('/login');
    }

    public function test_guests_cannot_store_a_place()
    {
        $this->post('/user/places', $this->validData())->assertRedirect('/login');

        $this->assertDatabaseCount('places', 0);
    }

    public function test_create_page_receives_the_available_cities()
    {
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $bangkok = City::factory()->create(['name' => 'Bangkok']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/user/places/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/places/create')
                ->has('cities', 2)
                ->where('cities.0', ['id' => $bangkok->id, 'name' => 'Bangkok'])
                ->where('cities.1', ['id' => $barcelona->id, 'name' => 'Barcelona']));
    }

    public function test_administrators_can_create_a_place()
    {
        $data = $this->validData();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/places', $data)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.places.index'));

        $this->assertDatabaseCount('places', 1);
        $this->assertDatabaseHas('places', $data);

        $this->get('/user/places')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('places', 1)
                ->where('places.0.name', 'Sagrada Família'));

        $this->get('/places')->assertOk();
        $this->get('/places/'.Place::sole()->id)->assertOk();
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
            'non-string description' => ['description', ['abc']],
            'missing city' => ['city_id', null],
            'unknown city' => ['city_id', 9999],
            'non-numeric city' => ['city_id', 'abc'],
            'missing latitude' => ['latitude', null],
            'non-numeric latitude' => ['latitude', 'abc'],
            'latitude too low' => ['latitude', -90.1],
            'latitude too high' => ['latitude', 90.1],
            'latitude too precise' => ['latitude', '41.4036299'],
            'missing longitude' => ['longitude', null],
            'non-numeric longitude' => ['longitude', 'abc'],
            'longitude too low' => ['longitude', -180.1],
            'longitude too high' => ['longitude', 180.1],
            'longitude too precise' => ['longitude', '2.1743558'],
        ];
    }

    public function test_guests_cannot_open_the_edit_page()
    {
        $place = Place::factory()->create();

        $this->get("/user/places/{$place->id}/edit")->assertRedirect('/login');
    }

    public function test_guests_cannot_update_a_place()
    {
        $place = Place::factory()->create();

        $this->put("/user/places/{$place->id}", $this->validData())->assertRedirect('/login');

        $this->assertEquals($place->getAttributes(), $place->fresh()->getAttributes());
    }

    public function test_edit_page_receives_the_place_and_the_available_cities()
    {
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $bangkok = City::factory()->create(['name' => 'Bangkok']);
        Place::factory()->for($bangkok)->create();
        $place = Place::factory()->for($barcelona)->create([
            'name' => 'Casa Batlló',
            'description' => 'A house by Gaudí.',
            'latitude' => 41.39164,
            'longitude' => 2.16489,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get("/user/places/{$place->id}/edit")
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
                ])
                ->has('cities', 2)
                ->where('cities.0', ['id' => $bangkok->id, 'name' => 'Bangkok'])
                ->where('cities.1', ['id' => $barcelona->id, 'name' => 'Barcelona']));
    }

    public function test_administrators_can_update_a_place()
    {
        $other = Place::factory()->create();
        $place = Place::factory()->create();
        $data = $this->validData();

        $this->actingAs(User::factory()->admin()->create())
            ->put("/user/places/{$place->id}", $data)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.places.index'));

        $this->assertDatabaseCount('places', 2);
        $this->assertDatabaseHas('places', ['id' => $place->id, ...$data]);
        $this->assertEquals($other->getAttributes(), $other->fresh()->getAttributes());

        $this->get('/places')->assertOk();
        $this->get("/places/{$place->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('place.name', 'Sagrada Família'));
    }

    #[DataProvider('boundaryCoordinates')]
    public function test_boundary_coordinates_are_accepted_when_updating(int $latitude, int $longitude)
    {
        $place = Place::factory()->create();
        $data = $this->validData(['latitude' => $latitude, 'longitude' => $longitude]);

        $this->actingAs(User::factory()->admin()->create())
            ->put("/user/places/{$place->id}", $data)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('places', ['id' => $place->id, ...$data]);
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_rejected_when_updating(string $field, mixed $value)
    {
        $place = Place::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->from("/user/places/{$place->id}/edit")
            ->put("/user/places/{$place->id}", $this->validData([$field => $value]))
            ->assertRedirect("/user/places/{$place->id}/edit")
            ->assertSessionHasErrors([$field]);

        $this->assertDatabaseCount('places', 1);
        $this->assertEquals($place->getAttributes(), $place->fresh()->getAttributes());
    }

    public function test_editing_or_updating_a_nonexistent_place_returns_not_found()
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/user/places/9999/edit')->assertNotFound();
        $this->put('/user/places/9999', $this->validData())->assertNotFound();

        $this->assertDatabaseCount('places', 0);
    }

    public function test_guests_cannot_delete_a_place()
    {
        $place = Place::factory()->create();

        $this->delete("/user/places/{$place->id}")->assertRedirect('/login');

        $this->assertModelExists($place);
    }

    public function test_administrators_can_delete_a_place()
    {
        $other = Place::factory()->create();
        $place = Place::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/user/places/{$place->id}")
            ->assertRedirect(route('userzone.places.index'));

        $this->assertModelMissing($place);
        $this->assertModelExists($other);

        $this->get('/places')->assertOk();
        $this->get("/places/{$place->id}")->assertNotFound();
        $this->get("/places/{$other->id}")->assertOk();
    }

    public function test_deleting_a_place_only_removes_its_own_itinerary_links()
    {
        $city = City::factory()->create();
        $place = Place::factory()->for($city)->create();
        $other = Place::factory()->for($city)->create();
        $itineraries = Itinerary::factory()->count(2)->create();
        $itineraries->each(fn (Itinerary $itinerary) => $itinerary->places()->attach([$place->id, $other->id]));

        $cityAttributes = $city->fresh()->getAttributes();
        $itineraryAttributes = $itineraries->map(fn (Itinerary $itinerary) => $itinerary->fresh()->getAttributes());

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/user/places/{$place->id}")
            ->assertRedirect(route('userzone.places.index'));

        $this->assertModelMissing($place);
        $this->assertDatabaseMissing('itinerary_place', ['place_id' => $place->id]);
        $this->assertDatabaseCount('itinerary_place', 2);
        $this->assertEquals($cityAttributes, $city->fresh()->getAttributes());
        $this->assertEquals(
            $itineraryAttributes,
            $itineraries->map(fn (Itinerary $itinerary) => $itinerary->fresh()->getAttributes()),
        );

        foreach ($itineraries as $itinerary) {
            $this->assertDatabaseHas('itinerary_place', [
                'itinerary_id' => $itinerary->id,
                'place_id' => $other->id,
            ]);
        }
    }

    public function test_deleting_a_nonexistent_place_returns_not_found()
    {
        $place = Place::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete('/user/places/9999')
            ->assertNotFound();

        $this->assertModelExists($place);
    }

    #[DataProvider('resourceRoutes')]
    public function test_resource_routes_have_the_expected_uris_and_methods(string $action, string $uri, array $methods)
    {
        $route = Route::getRoutes()->getByName("userzone.places.{$action}");

        $this->assertNotNull($route);
        $this->assertSame($uri, $route->uri());
        $this->assertSame($methods, $route->methods());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains(IsAdmin::class, $route->gatherMiddleware());
    }

    public static function resourceRoutes(): array
    {
        return [
            'index' => ['index', 'user/places', ['GET', 'HEAD']],
            'create' => ['create', 'user/places/create', ['GET', 'HEAD']],
            'store' => ['store', 'user/places', ['POST']],
            'edit' => ['edit', 'user/places/{place}/edit', ['GET', 'HEAD']],
            'update' => ['update', 'user/places/{place}', ['PUT', 'PATCH']],
            'destroy' => ['destroy', 'user/places/{place}', ['DELETE']],
        ];
    }

    public function test_there_is_no_private_show_route()
    {
        $this->assertFalse(Route::has('userzone.places.show'));
    }

    public function test_administrators_can_create_a_place_without_an_image()
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/places', $this->validData(['image' => null]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.places.index'));

        $this->assertNull(Place::sole()->imageUrl());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    #[DataProvider('validImages')]
    public function test_administrators_can_create_a_place_with_an_image(string $filename, string $extension)
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image($filename);
        $contents = $image->getContent();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/places', $this->validData(['image' => $image]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.places.index'));

        $place = Place::sole();
        $media = Media::sole();
        $path = $media->getPathRelativeToRoot();

        $this->assertTrue($media->model->is($place));
        $this->assertSame('images', $media->collection_name);
        $this->assertSame('public', $media->disk);
        $this->assertMatchesRegularExpression("#^{$media->id}/[A-Za-z0-9]{40}\\.{$extension}$#", $path);
        $this->assertEqualsCanonicalizing($this->mediaFiles($media), Storage::disk('public')->allFiles());
        $this->assertSame($contents, Storage::disk('public')->get($path));
        $this->assertSame(Storage::disk('public')->url($path), $place->imageUrl());
    }

    public static function validImages(): array
    {
        return [
            'jpg' => ['photo.jpg', 'jpg'],
            'jpeg' => ['photo.jpeg', 'jpg'],
            'png' => ['photo.png', 'png'],
        ];
    }

    public function test_an_image_of_exactly_the_maximum_size_is_accepted()
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/places', $this->validData(['image' => UploadedFile::fake()->image('photo.jpg')->size(8192)]))
            ->assertSessionHasNoErrors();

        Storage::disk('public')->assertExists(Media::sole()->getPathRelativeToRoot());
    }

    public function test_the_storage_path_cannot_be_submitted_directly()
    {
        Storage::fake('public');
        Storage::disk('public')->put('places/other.jpg', 'other');
        $place = Place::factory()->create();

        $this->actingAs(User::factory()->admin()->create());

        $this->post('/user/places', $this->validData(['image_path' => 'places/other.jpg']))->assertSessionHasNoErrors();
        $this->put("/user/places/{$place->id}", $this->validData(['image_path' => 'places/other.jpg']))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('places', 2);
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([null, null], Place::all()->map->imageUrl()->all());
        $this->assertSame(['places/other.jpg'], Storage::disk('public')->allFiles());
    }

    #[DataProvider('invalidImages')]
    public function test_invalid_images_are_rejected(string $filename, string $contents, ?int $kilobytes = null)
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->admin()->create())
            ->from('/user/places/create')
            ->post('/user/places', $this->validData(['image' => $this->upload($filename, $contents, $kilobytes)]))
            ->assertRedirect('/user/places/create')
            ->assertSessionHasErrors(['image']);

        $this->assertDatabaseCount('places', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
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
            'oversized image' => ['photo.png', base64_decode(self::PNG), 8193],
        ];
    }

    public function test_an_image_must_be_a_file()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/places', $this->validData(['image' => 'photo.jpg']))
            ->assertSessionHasErrors(['image']);

        $this->assertDatabaseCount('places', 0);
    }

    public function test_an_image_is_not_stored_when_another_field_is_invalid()
    {
        $place = $this->placeWithMedia();
        $files = Storage::disk('public')->allFiles();
        $image = UploadedFile::fake()->image('new.png');

        $this->actingAs(User::factory()->admin()->create());

        $this->post('/user/places', $this->validData(['name' => 'ab', 'image' => $image]))
            ->assertSessionHasErrors(['name']);
        $this->post("/user/places/{$place->id}", $this->validData(['_method' => 'PUT', 'name' => 'ab', 'image' => $image]))
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseCount('places', 1);
        $this->assertEquals($place->getAttributes(), $place->fresh()->getAttributes());
        $this->assertModelExists($place->getFirstMedia('images'));
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_regular_users_cannot_upload_replace_or_delete_an_image()
    {
        $place = $this->placeWithMedia();
        $files = Storage::disk('public')->allFiles();
        $image = UploadedFile::fake()->image('new.png');

        $this->actingAs(User::factory()->create());

        $this->post('/user/places', $this->validData(['image' => $image]))->assertForbidden();
        $this->post("/user/places/{$place->id}", $this->validData(['_method' => 'PUT', 'image' => $image]))->assertForbidden();
        $this->delete("/user/places/{$place->id}")->assertForbidden();

        $this->assertDatabaseCount('places', 1);
        $this->assertEquals($place->getAttributes(), $place->fresh()->getAttributes());
        $this->assertModelExists($place->getFirstMedia('images'));
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_guests_cannot_upload_replace_or_delete_an_image()
    {
        $place = $this->placeWithMedia();
        $files = Storage::disk('public')->allFiles();
        $image = UploadedFile::fake()->image('new.png');

        $this->post('/user/places', $this->validData(['image' => $image]))->assertRedirect('/login');
        $this->post("/user/places/{$place->id}", $this->validData(['_method' => 'PUT', 'image' => $image]))->assertRedirect('/login');
        $this->delete("/user/places/{$place->id}")->assertRedirect('/login');

        $this->assertDatabaseCount('places', 1);
        $this->assertEquals($place->getAttributes(), $place->fresh()->getAttributes());
        $this->assertModelExists($place->getFirstMedia('images'));
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public static function updatesWithoutAnImage(): array
    {
        return [
            'image field absent' => [[]],
            'image field empty' => [['image' => null]],
        ];
    }

    public function test_administrators_can_add_an_image_to_an_existing_place()
    {
        Storage::fake('public');
        $place = Place::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post("/user/places/{$place->id}", $this->validData([
                '_method' => 'PUT',
                'image' => UploadedFile::fake()->image('new.png'),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing($this->mediaFiles(Media::sole()), Storage::disk('public')->allFiles());
        $this->assertTrue(Media::sole()->model->is($place));
    }

    public function test_places_without_an_image_remain_functional()
    {
        Storage::fake('public');
        $place = Place::factory()->create();

        $this->get('/places')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('cities.0.places.0.image_url', null));
        $this->get("/places/{$place->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('place.image_url', null));

        $this->actingAs(User::factory()->admin()->create());

        $this->get("/user/places/{$place->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('image_url', null));
        $this->put("/user/places/{$place->id}", $this->validData())->assertSessionHasNoErrors();
        $this->delete("/user/places/{$place->id}")->assertRedirect(route('userzone.places.index'));
        $this->assertModelMissing($place);
    }

    public function test_the_media_table_is_created_by_its_migration()
    {
        $this->assertTrue(Schema::hasColumns('media', [
            'id', 'model_type', 'model_id', 'uuid', 'collection_name', 'name', 'file_name', 'mime_type', 'disk', 'size',
        ]));
        $this->assertFalse(Schema::hasColumn('places', 'image_path'));
    }

    public function test_the_stored_file_name_does_not_depend_on_the_client_file_name()
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->admin()->create());

        foreach (['shell.php.png', 'page.html', '../../evil.png'] as $filename) {
            $this->post('/user/places', $this->validData(['image' => $this->upload($filename, base64_decode(self::PNG), null)]))
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('userzone.places.index'));
        }

        $this->assertDatabaseCount('media', 3);

        foreach (Storage::disk('public')->allFiles() as $path) {
            $this->assertMatchesRegularExpression('#^\d+/(conversions/)?[A-Za-z0-9]{40}(-thumbnail|-details)?\.png$#', $path);
        }
    }

    public function test_administrators_can_replace_a_media_library_image()
    {
        $place = $this->placeWithMedia();
        $other = Place::factory()->create();
        $other->addMedia(UploadedFile::fake()->image('other.jpg'))->toMediaCollection('images');
        $previous = $place->getFirstMedia('images');
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
        $this->assertNotNull($other->fresh()->imageUrl());
        $this->assertEqualsCanonicalizing([
            ...$this->mediaFiles($media),
            ...$this->mediaFiles($other->getFirstMedia('images')),
        ], Storage::disk('public')->allFiles());
    }

    public function test_the_images_collection_holds_at_most_one_file()
    {
        $place = $this->placeWithMedia();

        $place->addMedia(UploadedFile::fake()->image('second.jpg'))->toMediaCollection('images');
        $place->addMedia(UploadedFile::fake()->image('third.jpg'))->toMediaCollection('images');

        $this->assertSame('third.jpg', $place->media()->sole()->file_name);
        $this->assertEqualsCanonicalizing($this->mediaFiles($place->media()->sole()), Storage::disk('public')->allFiles());
    }

    #[DataProvider('updatesWithoutAnImage')]
    public function test_administrators_can_edit_a_place_without_changing_its_media_library_image(array $overrides)
    {
        $place = $this->placeWithMedia();
        $files = Storage::disk('public')->allFiles();

        $this->actingAs(User::factory()->admin()->create())
            ->put("/user/places/{$place->id}", $this->validData(['name' => 'Renamed place', ...$overrides]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed place', $place->fresh()->name);
        $this->assertSame($place->imageUrl(), $place->fresh()->imageUrl());
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    #[DataProvider('invalidImages')]
    public function test_an_invalid_replacement_preserves_the_media_library_image(string $filename, string $contents, ?int $kilobytes = null)
    {
        $place = $this->placeWithMedia();
        $files = Storage::disk('public')->allFiles();

        $this->actingAs(User::factory()->admin()->create())
            ->post("/user/places/{$place->id}", $this->validData([
                '_method' => 'PUT',
                'image' => $this->upload($filename, $contents, $kilobytes),
            ]))
            ->assertSessionHasErrors(['image']);

        $this->assertEquals($place->getAttributes(), $place->fresh()->getAttributes());
        $this->assertModelExists($place->getFirstMedia('images'));
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_a_failed_upload_does_not_create_a_place()
    {
        Storage::fake('public');
        $this->failUploads();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/places', $this->validData(['image' => UploadedFile::fake()->image('new.png')]))
            ->assertServerError();

        $this->assertDatabaseCount('places', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_a_failed_upload_preserves_the_place_and_its_image()
    {
        $place = $this->placeWithMedia();
        $files = Storage::disk('public')->allFiles();
        $mediaIds = Media::pluck('id')->all();
        $this->failUploads();

        $this->actingAs(User::factory()->admin()->create())
            ->post("/user/places/{$place->id}", $this->validData([
                '_method' => 'PUT',
                'image' => UploadedFile::fake()->image('new.png'),
            ]))
            ->assertServerError();

        $this->assertEquals($place->getAttributes(), $place->fresh()->getAttributes());
        $this->assertSame($place->imageUrl(), $place->fresh()->imageUrl());
        $this->assertSame($mediaIds, Media::pluck('id')->all());
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_unauthorized_users_cannot_replace_or_delete_a_media_library_image()
    {
        $place = $this->placeWithMedia();
        $files = Storage::disk('public')->allFiles();
        $data = $this->validData(['_method' => 'PUT', 'image' => UploadedFile::fake()->image('new.png')]);

        $this->post("/user/places/{$place->id}", $data)->assertRedirect('/login');
        $this->delete("/user/places/{$place->id}")->assertRedirect('/login');

        $this->actingAs(User::factory()->create());

        $this->post("/user/places/{$place->id}", $data)->assertForbidden();
        $this->delete("/user/places/{$place->id}")->assertForbidden();

        $this->assertEquals($place->getAttributes(), $place->fresh()->getAttributes());
        $this->assertModelExists($place->getFirstMedia('images'));
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_deleting_a_place_removes_its_media_record_and_file()
    {
        $place = $this->placeWithMedia();
        $other = Place::factory()->for($place->city)->create();
        $other->addMedia(UploadedFile::fake()->image('other.jpg'))->toMediaCollection('images');
        $itinerary = Itinerary::factory()->create();
        $itinerary->places()->attach([$place->id, $other->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/user/places/{$place->id}")
            ->assertRedirect(route('userzone.places.index'));

        $this->assertModelMissing($place);
        $this->assertTrue(Media::sole()->model->is($other));
        $this->assertEqualsCanonicalizing($this->mediaFiles(Media::sole()), Storage::disk('public')->allFiles());
        $this->assertSame([$other->id], $itinerary->places()->pluck('places.id')->all());
        $this->assertModelExists($place->city);
    }

    public function test_pages_receive_the_media_library_image_url()
    {
        $place = $this->placeWithMedia();
        $media = $place->getFirstMedia('images');
        $url = Storage::disk('public')->url("{$media->id}/{$media->file_name}");
        $thumbnailUrl = Storage::disk('public')->url("{$media->id}/conversions/old-thumbnail.jpg");
        $detailsUrl = Storage::disk('public')->url("{$media->id}/conversions/old-details.jpg");

        $this->get('/places')->assertInertia(fn (Assert $page) => $page
            ->where('cities.0.places.0.image_url', $thumbnailUrl)
            ->missing('cities.0.places.0.media'));
        $this->get("/places/{$place->id}")->assertInertia(fn (Assert $page) => $page
            ->where('place.image_url', $detailsUrl)
            ->missing('place.media'));
        $this->actingAs(User::factory()->admin()->create())
            ->get("/user/places/{$place->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('image_url', $url)->missing('place.media'));
    }

    public function test_the_public_overview_loads_all_media_in_one_query()
    {
        Storage::fake('public');
        Place::factory()->for(City::factory())->count(3)->create()
            ->each(fn (Place $place) => $place->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('images'));

        DB::enableQueryLog();
        $this->get('/places')->assertOk();

        $this->assertCount(1, array_filter(
            DB::getQueryLog(),
            fn (array $query) => str_contains($query['query'], 'from "media"'),
        ));
    }

    #[DataProvider('conversionImages')]
    public function test_uploads_generate_proportional_conversions(string $filename, string $type, int $width, int $height, array $thumbnail, array $details)
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image($filename, $width, $height);
        $contents = $image->getContent();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/places', $this->validData(['image' => $image]))
            ->assertSessionHasNoErrors();

        $media = Media::sole();
        $disk = Storage::disk('public');

        $this->assertEqualsCanonicalizing($this->mediaFiles($media), $disk->allFiles());
        $this->assertSame($contents, $disk->get($media->getPathRelativeToRoot()));

        foreach (['thumbnail' => $thumbnail, 'details' => $details] as $conversion => $size) {
            $this->assertTrue($media->hasGeneratedConversion($conversion));
            $info = getimagesizefromstring($disk->get($media->getPathRelativeToRoot($conversion)));
            $this->assertSame($size, [$info[0], $info[1]]);
            $this->assertSame($type, $info['mime']);
        }

        $place = Place::sole();
        $this->assertSame($media->getUrl(), $place->imageUrl());
        $this->assertSame($media->getUrl('thumbnail'), $place->imageUrl('thumbnail'));
        $this->assertSame($media->getUrl('details'), $place->imageUrl('details'));
    }

    public static function conversionImages(): array
    {
        return [
            'landscape jpeg' => ['photo.jpg', 'image/jpeg', 1200, 800, [300, 200], [450, 300]],
            'portrait png' => ['photo.png', 'image/png', 800, 1200, [200, 300], [200, 300]],
            'panorama jpeg' => ['photo.jpeg', 'image/jpeg', 1200, 300, [300, 75], [600, 150]],
            'small png is not enlarged' => ['photo.png', 'image/png', 100, 50, [100, 50], [100, 50]],
        ];
    }

    public function test_png_conversions_keep_their_transparency()
    {
        Storage::fake('public');
        $canvas = imagecreatetruecolor(800, 400);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/places', $this->validData(['image' => $this->upload('photo.png', $this->encode('imagepng', $canvas), null)]))
            ->assertSessionHasNoErrors();

        foreach (['thumbnail', 'details'] as $conversion) {
            $converted = imagecreatefromstring(Storage::disk('public')->get(Media::sole()->getPathRelativeToRoot($conversion)));
            $this->assertSame(127, imagecolorsforindex($converted, imagecolorat($converted, 10, 10))['alpha']);
        }
    }

    public function test_conversions_only_apply_to_the_images_collection()
    {
        $place = $this->placeWithMedia();
        $media = $place->addMedia(UploadedFile::fake()->image('other.jpg'))->toMediaCollection('other', 'public');

        $this->assertSame([], $media->fresh()->generated_conversions);
    }

    public function test_media_without_conversions_falls_back_to_the_original()
    {
        $place = $this->placeWithMedia();
        $media = Media::sole();
        $media->update(['generated_conversions' => []]);
        Storage::disk('public')->deleteDirectory("{$media->id}/conversions");
        $url = Storage::disk('public')->url("{$media->id}/old.jpg");

        $this->assertSame($url, $place->fresh()->imageUrl());
        $this->get('/places')->assertInertia(fn (Assert $page) => $page->where('cities.0.places.0.image_url', $url));
        $this->get("/places/{$place->id}")->assertInertia(fn (Assert $page) => $page->where('place.image_url', $url));
    }

    public function test_places_without_an_image_have_no_conversion_urls()
    {
        $place = Place::factory()->create();

        foreach (['thumbnail', 'details'] as $conversion) {
            $this->assertNull($place->imageUrl($conversion));
        }
    }

    #[DataProvider('misleadingFilenames')]
    public function test_stored_extensions_follow_the_image_content(string $encoder, string $filename, string $extension)
    {
        $place = $this->placeWithMedia();
        $contents = $this->encode($encoder, imagecreatetruecolor(40, 20));

        $this->actingAs(User::factory()->admin()->create());

        $this->post('/user/places', $this->validData(['image' => $this->upload($filename, $contents, null)]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.places.index'));
        $this->post("/user/places/{$place->id}", $this->validData(['_method' => 'PUT', 'image' => $this->upload($filename, $contents, null)]))
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
            'jpeg as htm' => ['imagejpeg', 'weird name #1.phtml.HTM', 'jpg'],
            'jpeg as png' => ['imagejpeg', 'photo.png', 'jpg'],
            'jpeg without extension' => ['imagejpeg', 'photo', 'jpg'],
        ];
    }

    #[DataProvider('imagesWithinLimits')]
    public function test_images_within_the_dimension_limits_are_accepted(string $filename, int $width, int $height)
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image($filename, $width, $height);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/places', $this->validData(['image' => $image]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.places.index'));

        $this->assertEqualsCanonicalizing($this->mediaFiles(Media::sole()), Storage::disk('public')->allFiles());
    }

    public static function imagesWithinLimits(): array
    {
        return [
            'exactly 8 megapixels' => ['photo.jpg', 3200, 2500],
            'maximum width' => ['photo.png', 5000, 100],
            'maximum height' => ['photo.jpg', 100, 5000],
        ];
    }

    #[DataProvider('imagesOverLimits')]
    public function test_images_over_the_dimension_limits_are_rejected(string $type, int $width, int $height)
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->admin()->create())
            ->from('/user/places/create')
            ->post('/user/places', $this->validData(['image' => $this->imageClaiming($type, $width, $height)]))
            ->assertRedirect('/user/places/create')
            ->assertSessionHasErrors(['image' => 'The image must not exceed 5000 pixels on either side or 8 megapixels in total.']);

        $this->assertDatabaseCount('places', 0);
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    #[DataProvider('imagesOverLimits')]
    public function test_a_replacement_over_the_dimension_limits_preserves_the_image_and_its_conversions(string $type, int $width, int $height)
    {
        $place = $this->placeWithMedia();
        $media = Media::sole();
        $contents = collect($this->mediaFiles($media))->map(fn (string $path) => Storage::disk('public')->get($path));

        $this->actingAs(User::factory()->admin()->create())
            ->from("/user/places/{$place->id}/edit")
            ->post("/user/places/{$place->id}", $this->validData([
                '_method' => 'PUT',
                'image' => $this->imageClaiming($type, $width, $height),
            ]))
            ->assertRedirect("/user/places/{$place->id}/edit")
            ->assertSessionHasErrors(['image' => 'The image must not exceed 5000 pixels on either side or 8 megapixels in total.']);

        $this->assertEquals($place->getAttributes(), $place->fresh()->getAttributes());
        $this->assertEquals($media->getAttributes(), Media::sole()->getAttributes());
        $this->assertEqualsCanonicalizing($this->mediaFiles($media), Storage::disk('public')->allFiles());
        $this->assertEquals($contents, collect($this->mediaFiles($media))->map(fn (string $path) => Storage::disk('public')->get($path)));
    }

    public static function imagesOverLimits(): array
    {
        return [
            'jpeg just over 8 megapixels' => ['jpeg', 4000, 2001],
            'png just over 8 megapixels' => ['png', 4000, 2001],
            'too wide' => ['jpeg', 5001, 10],
            'too high' => ['png', 10, 5001],
            '48 megapixel jpeg' => ['jpeg', 8000, 6000],
            '48 megapixel png' => ['png', 8000, 6000],
        ];
    }

    private function imageClaiming(string $type, int $width, int $height): UploadedFile
    {
        // A small image whose header states other dimensions, as a real 48 MP canvas would not fit in memory.
        $contents = $this->encode("image{$type}", imagecreatetruecolor(16, 16));

        if ($type === 'jpeg') {
            $offset = strpos($contents, "\xFF\xC0") + 5;
            $contents = substr_replace($contents, pack('nn', $height, $width), $offset, 4);
        } else {
            $header = 'IHDR'.pack('NN', $width, $height).substr($contents, 24, 5);
            $contents = substr_replace($contents, $header.pack('N', crc32($header)), 12, 21);
        }

        $image = $this->upload("photo.{$type}", $contents, null);
        $this->assertSame([$width, $height], array_slice(getimagesize($image->getPathname()), 0, 2));

        return $image;
    }

    private function encode(string $encoder, \GdImage $image): string
    {
        ob_start();
        $encoder($image);

        return ob_get_clean();
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

    private function failUploads(): void
    {
        $this->mock(Filesystem::class, function ($mock) {
            $mock->shouldReceive('add')->once()->andReturnFalse();
            $mock->shouldReceive('removeAllFiles');
        });
    }

    private function placeWithMedia(): Place
    {
        Storage::fake('public');
        $place = Place::factory()->create()->fresh();
        $place->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('images');

        return $place;
    }

    private function upload(string $filename, string $contents, ?int $kilobytes): UploadedFile
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
