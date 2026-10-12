<?php

// T-011 (docs/tasks/BACKLOG_FASE4_5.md): only BookingService may change
// bookings.status - AGENTS.md rule 3 and the transition matrix of
// docs/business_rules_kamarin.md section 5.
//
// pest-plugin-arch is present (pestphp/pest requires
// pestphp/pest-plugin-arch ^4.0.2), and its API was probed before this
// test was written: arch()->expect(...)->toUse()/toBeUsedIn() compare
// *class-level dependencies*, so they can answer "who references the
// BookingStatus enum" but never "who assigns bookings.status". Column
// ownership is asserted by scanning the runtime sources for
// booking-status write idioms instead. The second test below is the
// canary that keeps this scan honest: it proves the scanner does catch
// the known writer and a synthetic violation, and does not cry wolf on
// the status columns of payments, rooms and housekeeping.

/**
 * Runtime source roots. database/ is left out on purpose: factories and
 * seeders set a status to build test data, they never transition a live
 * booking, and T-007's seeder will keep doing exactly that.
 *
 * @return array<int, string>
 */
function bookingStatusSourceRoots(): array
{
    return ['app', 'routes'];
}

/**
 * The one file allowed to write bookings.status at runtime.
 *
 * @return array<int, string>
 */
function bookingStatusWriterAllowlist(): array
{
    return ['app/Services/BookingService.php'];
}

/**
 * Every PHP source file under the runtime roots, keyed by its path
 * relative to the project root.
 *
 * @return array<string, string>
 */
function bookingStatusSourceFiles(): array
{
    $files = [];
    $base = rtrim(base_path(), '/\\');

    foreach (bookingStatusSourceRoots() as $root) {
        $directory = $base.DIRECTORY_SEPARATOR.$root;

        if (! is_dir($directory)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
            $files[$relative] = $file->getPathname();
        }
    }

    ksort($files);

    return $files;
}

/**
 * Statements that write bookings.status, one entry per violation,
 * reported as "<relative path>: <statement>".
 *
 * @param  array<string, string>  $files
 * @return array<int, string>
 */
function bookingStatusOwnershipViolations(array $files): array
{
    $violations = [];
    $allowlist = bookingStatusWriterAllowlist();

    foreach ($files as $relative => $absolute) {
        if (in_array($relative, $allowlist, true)) {
            continue;
        }

        $contents = file_get_contents($absolute);

        if ($contents === false) {
            continue;
        }

        foreach (bookingStatusWriteStatements($contents) as $statement) {
            $violations[] = $relative.': '.$statement;
        }
    }

    return $violations;
}

/**
 * The statements in $contents that write the status of a booking.
 *
 * @return array<int, string>
 */
function bookingStatusWriteStatements(string $contents): array
{
    // A write, whatever surrounds it: an array key, a property
    // assignment or a direct setAttribute() call.
    $writes = '/([\'"])status\1\s*=>|->status\s*=(?!=)|->setAttribute\(\s*([\'"])status\2\s*,/';

    $count = preg_match_all($writes, $contents, $matches, PREG_OFFSET_CAPTURE);

    if ($count === 0 || $count === false || ! isset($matches[0])) {
        return [];
    }

    $statements = [];

    foreach ($matches[0] as [, $offset]) {
        $statement = bookingStatusStatementAt($contents, (int) $offset);

        if (bookingStatusStatementTargetsBooking($statement)) {
            $statements[] = (string) preg_replace('/\s+/', ' ', trim($statement));
        }
    }

    return $statements;
}

/**
 * The statement containing $offset: everything since the previous
 * statement, block or closing brace up to the next semicolon.
 */
function bookingStatusStatementAt(string $contents, int $offset): string
{
    $start = 0;

    foreach ([';', '{', '}'] as $marker) {
        $position = strrpos(substr($contents, 0, $offset), $marker);

        if ($position !== false && $position + 1 > $start) {
            $start = $position + 1;
        }
    }

    $end = strpos($contents, ';', $offset);
    $end = $end === false ? strlen($contents) : $end + 1;

    return (string) substr($contents, $start, $end - $start);
}

/**
 * Whether the statement writes to the bookings table rather than to
 * payments.status, rooms.status or housekeeping_statuses.status, which
 * are separate columns with their own writers.
 */
function bookingStatusStatementTargetsBooking(string $statement): bool
{
    $targets = [
        // Booking::create([...]), Booking::where(...)->update([...])
        '/\bBooking\s*::/',
        // DB::table('bookings')->update([...])
        "/DB::table\(\s*['\"]bookings['\"]\s*\)/",
        // $booking->update([...]), $this->booking->forceFill([...]), $myBooking->save()
        '/booking\s*->\s*(?:update|fill|forceFill|forceCreate|save|setAttribute|offsetSet)\s*\(/i',
        // $property->bookings()->update([...])
        '/->\s*bookings\s*\(\s*\)\s*->\s*(?:update|save)\s*\(/i',
        // $booking->status = BookingStatus::Cancelled;
        '/booking\s*->\s*status\s*=(?!=)/i',
    ];

    foreach ($targets as $target) {
        if (preg_match($target, $statement) === 1) {
            return true;
        }
    }

    return false;
}

it('T-011: scans a real set of runtime sources', function () {
    $files = bookingStatusSourceFiles();

    // Guards the whole file: an empty directory would make the
    // ownership test below pass without looking at anything.
    expect($files)->not->toBeEmpty()
        ->and(count($files))->toBeGreaterThan(40)
        ->and(array_keys($files))->toContain(
            'app/Services/BookingService.php',
            'app/Enums/BookingStatus.php',
            'routes/web.php',
        );
});

it('T-011: only BookingService writes bookings.status', function () {
    expect(bookingStatusOwnershipViolations(bookingStatusSourceFiles()))->toBe([]);
});

it('T-011: the booking-status ownership scan is not vacuous', function () {
    // 1. The one allowed writer is still detected when the allowlist is
    //    taken away, so the scanner really sees BookingService.
    expect(bookingStatusWriteStatements(
        (string) file_get_contents(base_path('app/Services/BookingService.php')),
    ))->not->toBeEmpty();

    // 2. A synthetic violation outside BookingService is detected in
    //    each of the three write idioms the scanner looks for.
    expect(bookingStatusWriteStatements('
        public function cancel(Booking $booking): void
        {
            $booking->update(["status" => "cancelled"]);
        }
    '))->not->toBeEmpty();

    expect(bookingStatusWriteStatements('
        public function cancel(Booking $booking): void
        {
            $booking->status = BookingStatus::Cancelled;
            $booking->save();
        }
    '))->not->toBeEmpty();

    expect(bookingStatusWriteStatements('
        public function cancel(): void
        {
            DB::table("bookings")->where("id", 1)->update(["status" => "cancelled"]);
        }
    '))->not->toBeEmpty();

    // 3. The status columns of other tables are not violations, even
    //    when the same statement mentions a booking.
    expect(bookingStatusWriteStatements('
        public function record(Booking $booking): void
        {
            Payment::create(["booking_id" => $booking->id, "status" => "pending"]);
            HousekeepingStatus::updateOrCreate(["room_id" => 1], ["status" => "dirty"]);
            $room->status = RoomStatus::Maintenance;
            $booking->status; // a read is not a write
        }
    '))->toBe([]);
});
