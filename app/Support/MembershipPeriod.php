<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class MembershipPeriod
{
    public static function endDateFromStart(Carbon $startDate, int $durationDays): Carbon
    {
        return $startDate->copy()->addDays(max($durationDays, 1) - 1);
    }

    public static function endDateAfterExistingExpiry(Carbon $existingExpiry, int $durationDays): Carbon
    {
        return $existingExpiry->copy()->addDays(max($durationDays, 1));
    }
}
