<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class WeekDay
{
    public const DAYS = [
        'saturday',
        'sunday',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
    ];

    private const CARBON_TO_DAY = [
        0 => 'sunday',
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
    ];

    private const DAY_TO_CARBON = [
        'sunday' => 0,
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
    ];

    private const ARABIC_NAMES = [
        'sunday' => 'الأحد',
        'monday' => 'الإثنين',
        'tuesday' => 'الثلاثاء',
        'wednesday' => 'الأربعاء',
        'thursday' => 'الخميس',
        'friday' => 'الجمعة',
        'saturday' => 'السبت',
    ];

    public static function fromDate(CarbonInterface|string $date): string
    {
        $carbonDate = is_string($date) ? Carbon::parse($date) : $date;

        return self::fromCarbonDay((int) $carbonDate->dayOfWeek);
    }

    public static function fromCarbonDay(int $dayOfWeek): string
    {
        return self::CARBON_TO_DAY[$dayOfWeek] ?? 'sunday';
    }

    public static function toCarbonDay(string $day): int
    {
        return self::DAY_TO_CARBON[self::normalize($day)] ?? 0;
    }

    public static function normalize(mixed $day): string
    {
        if (is_numeric($day)) {
            return self::fromCarbonDay((int) $day);
        }

        $normalized = strtolower(trim((string) $day));

        return in_array($normalized, self::DAYS, true) ? $normalized : 'sunday';
    }

    public static function toIndex(string $day): int
    {
        $normalized = self::normalize($day);
        $index = array_search($normalized, self::DAYS, true);

        return $index !== false ? $index : 0;
    }

    public static function arabicName(string $day): string
    {
        return self::ARABIC_NAMES[self::normalize($day)] ?? self::ARABIC_NAMES['sunday'];
    }

    public static function validationRule(): string
    {
        return implode(',', self::DAYS);
    }
}
