<?php

namespace App\Http\Controllers;

use App\Models\Itinerary;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('welcome', [
            'itineraries' => Itinerary::recentForWelcome(),
        ]);
    }
}
