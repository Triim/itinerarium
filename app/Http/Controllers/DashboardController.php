<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** "My routes": every route of the current author, drafts included. */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('dashboard', [
            'itineraries' => $request->user()->itineraries()
                ->with(['city:id,name,slug', 'places.layer'])
                ->latest('updated_at')
                ->get(),
        ]);
    }
}
