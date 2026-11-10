<?php

namespace App\Policies;

use App\Models\Itinerary;
use App\Models\User;

class ItineraryPolicy
{
    /** Published routes are public; drafts are visible to their author only. */
    public function view(?User $user, Itinerary $itinerary): bool
    {
        return $itinerary->is_published || $user?->id === $itinerary->user_id;
    }

    public function update(User $user, Itinerary $itinerary): bool
    {
        return $user->id === $itinerary->user_id;
    }

    public function delete(User $user, Itinerary $itinerary): bool
    {
        return $user->id === $itinerary->user_id;
    }
}
