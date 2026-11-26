<?php

namespace App\Http\Controllers\Userzone;

use App\Http\Controllers\Controller;
use App\Models\Itinerary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The publication workflow: the owner submits a draft for review, an administrator approves it.
 * The approve route is behind the IsAdmin middleware.
 */
class ItineraryPublicationController extends Controller
{
    public function submit(Request $request, Itinerary $itinerary): RedirectResponse
    {
        abort_unless($itinerary->user()->is($request->user()), 403);

        if (! $itinerary->submitForReview()) {
            throw ValidationException::withMessages(['status' => 'Only a draft itinerary can be submitted for review.']);
        }

        return redirect()->route('userzone.itineraries.index');
    }

    public function approve(Itinerary $itinerary): RedirectResponse
    {
        if (! $itinerary->approve()) {
            throw ValidationException::withMessages(['status' => 'Only an itinerary awaiting approval can be approved.']);
        }

        return redirect()->route('userzone.itineraries.index');
    }
}
