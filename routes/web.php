<?php

use App\Http\Controllers\CityController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\Userzone\PlaceController as UserzonePlaceController;
use App\Models\Itinerary;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'itinerary' => Itinerary::with(['city', 'user'])->first(),
    ]);
})->name('home');

Route::get('itineraries', [ItineraryController::class, 'index'])
    ->name('itineraries.index');
Route::get('itineraries/{itinerary}', [ItineraryController::class, 'show'])
    ->name('itineraries.show');

Route::get('cities', [CityController::class, 'index'])
    ->name('cities.index');
Route::get('cities/{city}', [CityController::class, 'show'])
    ->name('cities.show');

Route::get('places', [PlaceController::class, 'index'])
    ->name('places.index');
Route::get('places/{place}', [PlaceController::class, 'show'])
    ->name('places.show');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('user/places', [UserzonePlaceController::class, 'index'])
        ->name('userzone.places.index');
    Route::get('user/places/create', [UserzonePlaceController::class, 'create'])
        ->name('userzone.places.create');
    Route::post('user/places', [UserzonePlaceController::class, 'store'])
        ->name('userzone.places.store');
    Route::get('user/places/{place}/edit', [UserzonePlaceController::class, 'edit'])
        ->name('userzone.places.edit');
    Route::put('user/places/{place}', [UserzonePlaceController::class, 'update'])
        ->name('userzone.places.update');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
