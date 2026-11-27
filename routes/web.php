<?php

use App\Http\Controllers\CityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\Userzone\CityController as UserzoneCityController;
use App\Http\Controllers\Userzone\ItineraryController as UserzoneItineraryController;
use App\Http\Controllers\Userzone\ItineraryPublicationController;
use App\Http\Controllers\Userzone\PlaceController as UserzonePlaceController;
use App\Http\Controllers\WelcomeController;
use App\Http\Middleware\IsAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

Route::get('itineraries', [ItineraryController::class, 'index'])
    ->name('itineraries.index');
Route::get('itineraries/{itinerary:slug}', [ItineraryController::class, 'show'])
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
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('user')->name('userzone.')->group(function () {
        Route::resource('itineraries', UserzoneItineraryController::class);
        Route::post('itineraries/{itinerary}/submit', [ItineraryPublicationController::class, 'submit'])
            ->name('itineraries.submit');

        Route::middleware(IsAdmin::class)->group(function () {
            Route::post('itineraries/{itinerary}/approve', [ItineraryPublicationController::class, 'approve'])
                ->name('itineraries.approve');
            Route::resource('places', UserzonePlaceController::class)->except('show');
            Route::resource('cities', UserzoneCityController::class)->except('show');
        });
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
