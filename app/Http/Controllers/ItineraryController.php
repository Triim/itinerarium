<?php

namespace App\Http\Controllers;

use App\Models\Itinerary;
use Inertia\Inertia;
use Inertia\Response;

class ItineraryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('itineraries/index', [
            'itineraries' => Itinerary::with(['city:id,name', 'user:id,name'])
                ->orderBy('title')
                ->get(['id', 'title', 'city_id', 'user_id']),
        ]);
    }

    public function show(Itinerary $itinerary): Response
    {
        return Inertia::render('itineraries/show', [
            'itinerary' => $itinerary->load(['city:id,name', 'user:id,name']),
        ]);
    }
}
