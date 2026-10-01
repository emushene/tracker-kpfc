<?php

namespace App\Console\Commands;

use App\Models\MaintenanceAlert;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceTicket;
use App\Notifications\MaintenanceDueNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('maintenance:check')]
#[Description('Check vehicle mileage and time to automate maintenance tickets and notifications.')]
class CheckMaintenanceWindows extends Command
{
    public function handle()
    {
        $schedules = MaintenanceSchedule::with('vehicle.mileage')->where('active', true)->get();

        $maintenanceTeamEmail = config('mail.maintenance_team_email', 'maintenance@kpfc.co.ke');

        foreach ($schedules as $schedule) {
            $vehicle = $schedule->vehicle;
            if (! $vehicle) {
                continue;
            }

            $isDue = false;
            $dueReason = '';

            // Check based on schedule type
            if ($schedule->schedule_type === 'mileage' && $schedule->next_service_km) {
                $latestMileageRecord = $vehicle->mileage()->latest('date')->first();
                $currentOdometer = $latestMileageRecord ? $latestMileageRecord->odometer : 0;

                $threshold = $schedule->alert_threshold_km ?? 500;

                if ($currentOdometer >= ($schedule->next_service_km - $threshold)) {
                    $isDue = true;
                    $dueReason = "Mileage due: Current odometer {$currentOdometer}km approaches next service at {$schedule->next_service_km}km.";
                }
            } elseif ($schedule->schedule_type === 'time' && $schedule->next_service_at) {
                $thresholdDays = $schedule->alert_threshold_days ?? 7;
                $alertDate = $schedule->next_service_at->copy()->subDays($thresholdDays);

                if (now()->greaterThanOrEqualTo($alertDate)) {
                    $isDue = true;
                    $dueReason = "Time due: Next service scheduled for {$schedule->next_service_at->toDateString()}.";
                }
            }

            if ($isDue) {
                // Check if a ticket already exists for this vehicle and service name that is open
                $existingTicket = MaintenanceTicket::where('vehicle_id', $vehicle->id)
                    ->where('title', "Scheduled Maintenance: {$schedule->service_name}")
                    ->whereIn('status', ['open', 'in_progress', 'prioritized'])
                    ->first();

                if (! $existingTicket) {
                    // Create Ticket
                    $ticket = MaintenanceTicket::create([
                        'ticket_number' => 'TCK-'.strtoupper(uniqid()),
                        'vehicle_id' => $vehicle->id,
                        'ticket_type' => 'service',
                        'status' => 'open',
                        'title' => "Scheduled Maintenance: {$schedule->service_name}",
                        'description' => "Automated ticket. {$dueReason}",
                        'priority' => 'normal',
                        'opened_at' => now(),
                    ]);

                    // Create Alert to show on dashboard
                    MaintenanceAlert::create([
                        'vehicle_id' => $vehicle->id,
                        'maintenance_schedule_id' => $schedule->id,
                        'alert_type' => 'service_due',
                        'title' => "Service Due: {$schedule->service_name}",
                        'message' => $dueReason,
                        'status' => 'active',
                    ]);

                    // Notify Team
                    Notification::route('mail', $maintenanceTeamEmail)
                        ->notify(new MaintenanceDueNotification($ticket, $dueReason));

                    $this->info("Created ticket for Vehicle [{$vehicle->plate_number}] - {$schedule->service_name}");
                }
            }
        }

        $this->info('Maintenance windows check completed.');
    }
}
