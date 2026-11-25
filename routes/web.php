<?php

use App\Http\Controllers\CityController;
use App\Http\Controllers\ItineraryController;
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

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
