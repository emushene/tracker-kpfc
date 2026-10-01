<?php

namespace App\Notifications;

use App\Models\MaintenanceTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MaintenanceTicket $ticket,
        public string $reason
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

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Maintenance Due: {$this->ticket->vehicle->plate_number}")
            ->greeting('Hello Maintenance Team,')
            ->line('A new maintenance ticket has been automatically generated.')
            ->line("Vehicle: {$this->ticket->vehicle->plate_number} ({$this->ticket->vehicle->imei})")
            ->line("Service: {$this->ticket->title}")
            ->line("Reason: {$this->reason}")
            ->action('View Ticket Details', url('/test-dashboard#maintenance-hub'))
            ->line('Please schedule this vehicle for service soon.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'vehicle_id' => $this->ticket->vehicle_id,
            'reason' => $this->reason,
        ];
    }
}
