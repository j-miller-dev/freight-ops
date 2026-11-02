<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\HoldingLaneOverdue;
use App\Support\Unload\HoldingLaneBoard;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class AlertOverdueHoldingLanesCommand extends Command
{
    protected $signature = 'holding-lanes:alert-overdue';

    protected $description = 'Notify scalers, supervisors and admins about pallets that have overstayed their holding lane';

    public function handle(HoldingLaneBoard $board): int
    {
        $overdue = $board->overdueHandlingUnits();

        if ($overdue === []) {
            $this->info('No overdue holding lanes.');

            return self::SUCCESS;
        }

        // A pallet already alerted on for its current stay is skipped, so
        // re-running this on a schedule doesn't spam the same overdue
        // pallet every time. If it gets released and held again later,
        // held_since moves forward and it's treated as a fresh stay.
        $lastAlertedAt = DatabaseNotification::query()
            ->where('type', HoldingLaneOverdue::class)
            ->where('created_at', '>=', now()->subDays(30))
            ->get()
            ->mapToGroups(fn (DatabaseNotification $notification): array => [
                $notification->data['handling_unit_id'] ?? '' => $notification->created_at,
            ])
            ->map(fn ($createdAts) => $createdAts->max());

        $toAlert = array_filter($overdue, function (array $row) use ($lastAlertedAt): bool {
            $heldSince = $row['held_since'];
            $alertedAt = $lastAlertedAt->get($row['handling_unit_id']);

            return $alertedAt === null || ! $heldSince instanceof Carbon || $alertedAt->lt($heldSince);
        });

        if ($toAlert === []) {
            $this->info('No new overdue pallets to alert on.');

            return self::SUCCESS;
        }

        $recipients = User::query()
            ->whereIn('role', array_map(fn (UserRole $role): string => $role->value, UserRole::trailerAlertRoles()))
            ->get();

        foreach ($toAlert as $row) {
            Notification::send($recipients, new HoldingLaneOverdue(
                handlingUnitId: $row['handling_unit_id'],
                barcode: $row['barcode'],
                connoteNumber: $row['connote_number'],
                laneCode: $row['lane_code'],
                reasons: $row['reasons'],
            ));
        }

        $this->info(count($toAlert).' overdue pallet(s) alerted.');

        return self::SUCCESS;
    }
}
