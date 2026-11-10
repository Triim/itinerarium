<?php

namespace App\Http\Controllers;

use App\Models\Selection;
use Inertia\Inertia;
use Inertia\Response;

class SelectionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('selections/index', [
            'selections' => Selection::with(['author:id,name', 'city:id,name,slug'])
                ->withCount(['itineraries' => fn ($q) => $q->published()])
                ->latest()
                ->get(),
        ]);
    }

    public function show(Selection $selection): Response
    {
        return Inertia::render('selections/show', [
            'selection' => $selection->load([
                'author:id,name',
                'city:id,name,slug',
                'itineraries' => fn ($q) => $q->published()->with(['author:id,name', 'city:id,name,slug', 'places.layer']),
            ]),
        ]);
    }
}
