<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Periodically expire pending website bookings whose expiry deadline has passed (BR-13).
 *
 * BookingService::expire() performs the actual atomic transition under a row lock,
 * setting status to expired, releasing booking_nights (is_active = false), and writing
 * the status log with changed_by_user_id = null (the system actor, EX-06).
 *
 * Each booking is handled inside its own try-catch block so that an isolated failure
 * (e.g. transient database lock or unexpected state) does not abort the processing
 * of remaining overdue bookings.
 */
class ExpirePendingBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:expire-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire pending website bookings that have passed their expiry deadline (BR-13)';

    /**
     * Execute the console command.
     */
    public function handle(BookingService $bookingService): int
    {
        $overdueBookings = Booking::query()
            ->where('status', BookingStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->get();

        $expiredCount = 0;
        $failedCount = 0;

        foreach ($overdueBookings as $booking) {
            try {
                $bookingService->expire($booking);
                $expiredCount++;
            } catch (Throwable $e) {
                $failedCount++;
                Log::warning(sprintf(
                    'Failed to expire booking ID %d (code: %s): %s',
                    $booking->id,
                    $booking->booking_code,
                    $e->getMessage(),
                ), [
                    'exception' => $e,
                    'booking_id' => $booking->id,
                ]);

                $this->error(sprintf(
                    'Failed to expire booking #%s: %s',
                    $booking->booking_code,
                    $e->getMessage(),
                ));
            }
        }

        $this->info(sprintf(
            'Expired %d pending booking(s)%s.',
            $expiredCount,
            $failedCount > 0 ? sprintf(' (%d failed)', $failedCount) : '',
        ));

        return Command::SUCCESS;
    }
}
