<?php

namespace App\Notifications;

use App\Models\Itinerary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the author that an administrator approved their itinerary and it is now public.
 * Sent synchronously by mail from the approval action.
 */
class ItineraryPublished extends Notification
{
    public function __construct(public Itinerary $itinerary) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your itinerary "'.$this->itinerary->title.'" has been published')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('An administrator has approved your itinerary "'.$this->itinerary->title.'" in '.$this->itinerary->city->name.'.')
            ->line('It is now public, so every visitor can see it.')
            ->action('View itinerary', route('itineraries.show', $this->itinerary));
    }
}
