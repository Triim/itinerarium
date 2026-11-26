<?php

use App\Http\Controllers\CityController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\Userzone\CityController as UserzoneCityController;
use App\Http\Controllers\Userzone\ItineraryController as UserzoneItineraryController;
use App\Http\Controllers\Userzone\PlaceController as UserzonePlaceController;
use App\Http\Middleware\IsAdmin;
use App\Models\Itinerary;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'itineraries' => Itinerary::recentForWelcome(),
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

    Route::prefix('user')->name('userzone.')->group(function () {
        Route::resource('itineraries', UserzoneItineraryController::class)->except('show');

        Route::middleware(IsAdmin::class)->group(function () {
            Route::resource('places', UserzonePlaceController::class)->except('show');
            Route::resource('cities', UserzoneCityController::class)->except('show');
        });
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
