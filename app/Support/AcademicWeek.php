<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class AcademicWeek
{
    public static function start(CarbonInterface|string|null $value = null, ?string $timezone = null): CarbonImmutable
    {
        $timezone ??= config('academic.timezone');
        $date = $value instanceof CarbonInterface
            ? CarbonImmutable::instance($value)->setTimezone($timezone)
            : CarbonImmutable::parse($value ?? 'now', $timezone);

        $daysSinceSaturday = ($date->dayOfWeek - CarbonInterface::SATURDAY + 7) % 7;

        return $date->startOfDay()->subDays($daysSinceSaturday);
    }

    public static function end(CarbonInterface|string|null $value = null, ?string $timezone = null): CarbonImmutable
    {
        return self::start($value, $timezone)->addDays(7);
    }
}
