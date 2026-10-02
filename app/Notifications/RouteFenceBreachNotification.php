<?php

namespace App\Notifications;

use App\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RouteFenceBreachNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Vehicle $vehicle,
        public float $distanceMeters,
        public float $latitude,
        public float $longitude,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plateNumber = $this->vehicle->plate_number ?: $this->vehicle->imei ?: 'Vehicle';

        return (new MailMessage)
            ->subject("Route fence breach: {$plateNumber}")
            ->greeting('Hello Fleet Operations,')
            ->line("Vehicle {$plateNumber} has moved more than 20 meters away from its active route corridor.")
            ->line("Distance from route: {$this->distanceMeters} m")
            ->line("Last known GPS: {$this->latitude}, {$this->longitude}")
            ->action('View Fleet Dashboard', url('/test-dashboard'))
            ->line('This alert can be used to trigger the messenger or dispatch follow-up workflow.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_type' => 'route_fence_breach',
            'vehicle_id' => $this->vehicle->id,
            'plate_number' => $this->vehicle->plate_number,
            'distance_meters' => round($this->distanceMeters, 1),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
