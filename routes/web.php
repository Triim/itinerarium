<?php

use App\Http\Controllers\CityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\SelectionController;
use App\Models\City;
use App\Models\Selection;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'cities' => City::orderBy('name')->get(['id', 'name', 'slug', 'country']),
        'selections' => Selection::latest()->take(3)->get(['id', 'title', 'slug', 'description']),
    ]);
})->name('home');

// Authoring (registered before the public {itinerary} route so /routes/create is not swallowed).
Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::resource('routes', ItineraryController::class)
        ->except(['index', 'show'])
        ->parameters(['routes' => 'itinerary'])
        ->names('itineraries');
});

// Public browsing.
Route::get('cities', [CityController::class, 'index'])->name('cities.index');
Route::get('cities/{city}', [CityController::class, 'show'])->name('cities.show');
Route::resource('routes', ItineraryController::class)
    ->only(['index', 'show'])
    ->parameters(['routes' => 'itinerary'])
    ->names('itineraries');
Route::get('collections', [SelectionController::class, 'index'])->name('selections.index');
Route::get('collections/{selection}', [SelectionController::class, 'show'])->name('selections.show');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
