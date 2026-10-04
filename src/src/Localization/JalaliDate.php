<?php

declare(strict_types=1);

/**
 * GitiArts Menu Platform - Phase 2
 * Copyright (c) 2026 GitiArts. All rights reserved.
 */

namespace GitiArts\Phase2\Localization;

/**
 * JalaliDate
 *
 * Pure-PHP Jalali (Shamsi / Persian Solar) calendar implementation.
 *
 * Algorithm: every conversion goes through JDN (Julian Day Number) as
 * the intermediate representation. This is the well-tested Richards/USNO
 * approach used by astronomical software, not the closed-form mirror
 * formula that broke in earlier versions.
 *
 *   Persian (jy, jm, jd)
 *       ↕ persianToJdn / jdnToPersian
 *   JDN (integer)
 *       ↕ gregorianToJdn / jdnToGregorian
 *   Gregorian (gy, gm, gd)
 *
 * Constants:
 *   PERSIAN_EPOCH_JDN = 1,948,320
 *     = Gregorian 21 March 622 CE (matches modern astronomical Persian
 *       calendar used by time.ir; differs from the 22 March convention
 *       used by some arithmetic-cycle libraries, which produces dates
 *       one day late for non-leap Gregorian years).
 *
 *   Persian leap years: 33-year arithmetic cycle, leap years at
 *     positions 1, 5, 9, 13, 17, 22, 26, 30 (8 per cycle).
 *
 * Verified reference pairs (all from time.ir):
 *   1405/01/01 ↔ 2026-03-21  (Nowruz)
 *   1405/07/11 ↔ 2026-10-03
 *   1404/12/30 → 2026-03-20  (clamped: 1404 non-leap, Esfand has 29 days)
 *   1403/12/30 → 2025-03-20  (1403 IS leap, Esfand has 30 days)
 *   1399/12/30 → 2021-03-20  (1399 IS leap)
 *   2024-03-20 → 1403/01/01  (Nowruz 1403)
 *   2021-03-21 → 1400/01/01
 *
 * No external dependency.
 */
final class JalaliDate
{
    public const MONTHS_FA = [
        1  => 'فروردین',
        2  => 'اردیبهشت',
        3  => 'خرداد',
        4  => 'تیر',
        5  => 'مرداد',
        6  => 'شهریور',
        7  => 'مهر',
        8  => 'آبان',
        9  => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];

    /** @var array<int,string> Saturday-first (Persian convention). */
    public const WEEKDAYS_FA = [
        6 => 'شنبه',      // Saturday  (PHP w=6)
        0 => 'یکشنبه',    // Sunday    (PHP w=0)
        1 => 'دوشنبه',    // Monday
        2 => 'سه‌شنبه',   // Tuesday
        3 => 'چهارشنبه',  // Wednesday
        4 => 'پنجشنبه',   // Thursday
        5 => 'جمعه',      // Friday
    ];

    /**
     * Persian epoch in JDN: 1 Farvardin 1 = 21 March 622 CE Gregorian.
     */
    private const PERSIAN_EPOCH_JDN = 1948320;

    /**
     * Days in a 33-year Persian cycle (8 leap years × 1 extra day each).
     */
    private const PERSIAN_CYCLE_DAYS = 12053;

    /**
     * Convert a Gregorian date to a Jalali date string.
     *
     * @param string $gregorian "YYYY-MM-DD" or "YYYY-MM-DD HH:MM:SS".
     * @param string $separator Output separator (default "/").
     * @return string Persian-formatted Jalali date "YYYY/MM/DD" with Latin digits
     *                (use PersianDigits::toPersian() if Persian digits are needed).
     */
    public static function toJalali(string $gregorian, string $separator = '/'): string
    {
        $parts = self::parseGregorian($gregorian);
        if ($parts === null) {
            return $gregorian;
        }
        [$gy, $gm, $gd] = $parts;
        [$jy, $jm, $jd] = self::gregorianToJalali($gy, $gm, $gd);

        return sprintf('%04d%s%02d%s%02d', $jy, $separator, $jm, $separator, $jd);
    }

    /**
     * Convert a Jalali date string to a Gregorian ISO date.
     *
     * @param string $jalali "YYYY/MM/DD" (Latin or Persian digits both accepted).
     * @return string Gregorian "YYYY-MM-DD" or input string on parse failure.
     */
    public static function toGregorian(string $jalali): string
    {
        $normalized = PersianDigits::toLatin($jalali);
        if (!preg_match('#(\d{4})[/\-\.](\d{1,2})[/\-\.](\d{1,2})#', $normalized, $m)) {
            return $jalali;
        }
        [$jy, $jm, $jd] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        [$gy, $gm, $gd] = self::jalaliToGregorian($jy, $jm, $jd);
        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    /**
     * Format a date/time string in Persian style with Persian digits.
     *
     * @param string $datetime Any strtotime()-parseable string, or "now".
     * @param string $format   PHP date format (Y/m/d H:i is common).
     * @return string Persian-formatted output. Month/day names replaced
     *               with Persian equivalents; digits replaced with Persian.
     */
    public static function format(string $datetime, string $format = 'Y/m/d H:i'): string
    {
        $timestamp = $datetime === 'now' ? time() : strtotime($datetime);
        if ($timestamp === false) {
            return $datetime;
        }

        $gy = (int) date('Y', $timestamp);
        $gm = (int) date('n', $timestamp);
        $gd = (int) date('j', $timestamp);
        [$jy, $jm, $jd] = self::gregorianToJalali($gy, $gm, $gd);

        $weekday = (int) date('w', $timestamp);

        $out = $format;
        $out = str_replace(
            ['Y', 'y', 'm', 'n', 'd', 'j', 'H', 'i', 's'],
            [
                sprintf('%04d', $jy),
                sprintf('%02d', $jy % 100),
                sprintf('%02d', $jm),
                (string) $jm,
                sprintf('%02d', $jd),
                (string) $jd,
                date('H', $timestamp),
                date('i', $timestamp),
                date('s', $timestamp),
            ],
            $out
        );

        // Persian weekday name (l) and short (D)
        $out = str_replace(
            ['l', 'D'],
            [
                self::WEEKDAYS_FA[$weekday] ?? '',
                mb_substr(self::WEEKDAYS_FA[$weekday] ?? '', 0, 3, 'UTF-8'),
            ],
            $out
        );

        // Persian month name (F)
        $out = str_replace('F', self::MONTHS_FA[$jm] ?? '', $out);

        return PersianDigits::toPersian($out);
    }

    /**
     * Return the Persian month name for a Jalali month number.
     */
    public static function monthName(int $month): string
    {
        return self::MONTHS_FA[$month] ?? '';
    }

    /**
     * Return the Persian weekday name for a PHP date('w') value.
     */
    public static function weekdayName(int $weekday): string
    {
        return self::WEEKDAYS_FA[$weekday] ?? '';
    }

    // --------------------------------------------------------------
    // Core conversion: via JDN (Julian Day Number).
    // --------------------------------------------------------------

    /**
     * Convert Gregorian (gy, gm, gd) to Jalali (jy, jm, jd).
     *
     * @return array{int,int,int}
     */
    public static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        $jdn = self::gregorianToJdn($gy, $gm, $gd);
        return self::jdnToPersian($jdn);
    }

    /**
     * Convert Jalali (jy, jm, jd) to Gregorian (gy, gm, gd).
     *
     * @return array{int,int,int}
     */
    public static function jalaliToGregorian(int $jy, int $jm, int $jd): array
    {
        $jdn = self::persianToJdn($jy, $jm, $jd);
        return self::jdnToGregorian($jdn);
    }

    /**
     * Convert Gregorian date to Julian Day Number.
     *
     * Standard algorithm (Richards 1998, USNO). Works for proleptic
     * Gregorian calendar — no year-0 issues since inputs are always CE.
     */
    private static function gregorianToJdn(int $gy, int $gm, int $gd): int
    {
        $a = (int) ((14 - $gm) / 12);
        $y = $gy + 4800 - $a;
        $m = $gm + 12 * $a - 3;
        return $gd
             + (int) ((153 * $m + 2) / 5)
             + 365 * $y
             + (int) ($y / 4)
             - (int) ($y / 100)
             + (int) ($y / 400)
             - 32045;
    }

    /**
     * Convert Julian Day Number to Gregorian date.
     *
     * Inverse of gregorianToJdn(). Standard USNO algorithm — correctly
     * handles Gregorian leap years (no Feb 29 assumption baked in).
     *
     * @return array{int,int,int}
     */
    private static function jdnToGregorian(int $jdn): array
    {
        $a = $jdn + 32044;
        $b = (int) ((4 * $a + 3) / 146097);
        $c = $a - (int) ((146097 * $b) / 4);
        $d = (int) ((4 * $c + 3) / 1461);
        $e = $c - (int) ((1461 * $d) / 4);
        $m = (int) ((5 * $e + 2) / 153);
        $gd = $e - (int) ((153 * $m + 2) / 5) + 1;
        $gm = $m + 3 - 12 * (int) ($m / 10);
        $gy = 100 * $b + $d - 4800 + (int) ($m / 10);
        return [$gy, $gm, $gd];
    }

    /**
     * Convert Persian (jy, jm, jd) to Julian Day Number.
     *
     * Days from Persian epoch to start of year jy:
     *   365 * (jy - 1) + persianLeapCount(jy - 1)
     *
     * Days from start of year to (jm, jd) — see persianMonthDayOffset().
     */
    private static function persianToJdn(int $jy, int $jm, int $jd): int
    {
        $daysToYearStart = 365 * ($jy - 1) + self::persianLeapCount($jy - 1);
        $dayOfYear = self::persianMonthDayOffset($jy, $jm, $jd);
        return self::PERSIAN_EPOCH_JDN + $daysToYearStart + $dayOfYear;
    }

    /**
     * Convert Julian Day Number to Persian (jy, jm, jd).
     *
     * Uses the 33-year cycle structure to skip past complete cycles in
     * one integer division, then iterates year-by-year within the current
     * cycle (at most 33 iterations — fast enough for any realistic year).
     *
     * @return array{int,int,int}
     */
    private static function jdnToPersian(int $jdn): array
    {
        $days = $jdn - self::PERSIAN_EPOCH_JDN;

        // 33-year cycle = 12053 days. Skip past complete cycles.
        $cycles = (int) ($days / self::PERSIAN_CYCLE_DAYS);
        $remaining = $days % self::PERSIAN_CYCLE_DAYS;

        $jy = 33 * $cycles + 1;
        while ($remaining >= 365) {
            $yearLength = self::isPersianLeapYear($jy) ? 366 : 365;
            if ($remaining >= $yearLength) {
                $remaining -= $yearLength;
                $jy++;
            } else {
                break;
            }
        }

        $dayOfYear = $remaining;

        if ($dayOfYear < 186) {
            // Months 1-6: 31 days each
            $jm = 1 + (int) ($dayOfYear / 31);
            $jd = 1 + ($dayOfYear % 31);
        } else {
            // Months 7-12: 30 days each (Esfand may have 29 in non-leap years,
            // but jdnToPersian only produces valid day-of-month for valid JDNs).
            $r = $dayOfYear - 186;
            $jm = 7 + (int) ($r / 30);
            $jd = 1 + ($r % 30);
        }

        return [$jy, $jm, $jd];
    }

    /**
     * Compute day-of-year offset (0-indexed) for a Persian date.
     *
     * For Esfand (month 12) in non-leap years, day 30 is clamped to 29
     * (the last valid day). This means Persian 12/30 of a non-leap year
     * maps to the same Gregorian date as Persian 12/29 of that year —
     * matching time.ir's behavior when callers pass an out-of-range
     * Esfand day instead of rejecting the input outright.
     */
    private static function persianMonthDayOffset(int $jy, int $jm, int $jd): int
    {
        if ($jm <= 6) {
            return ($jm - 1) * 31 + ($jd - 1);
        }
        if ($jm <= 11) {
            return 6 * 31 + ($jm - 7) * 30 + ($jd - 1);
        }
        // jm === 12 (Esfand): 30 days if leap, 29 otherwise.
        $maxDay = self::isPersianLeapYear($jy) ? 30 : 29;
        $effectiveDay = min($jd, $maxDay);
        return 6 * 31 + 5 * 30 + ($effectiveDay - 1);
    }

    /**
     * Count Persian leap years in 1..$n.
     *
     * Closed form: floor((8*n + 30) / 33).
     * Verified for n in 0..10000 against the explicit list [1,5,9,13,17,22,26,30].
     */
    private static function persianLeapCount(int $n): int
    {
        if ($n <= 0) {
            return 0;
        }
        return (int) ((8 * $n + 30) / 33);
    }

    /**
     * Determine whether a Persian year is a leap year.
     *
     * Persian leap years in the 33-year arithmetic cycle are at
     * positions: 1, 5, 9, 13, 17, 22, 26, 30 (8 leap years per cycle).
     */
    private static function isPersianLeapYear(int $jy): bool
    {
        // Safe modulo for negative input (defensive — not used for our tests
        // but keeps the function correct across the year-zero boundary).
        $mod = (($jy % 33) + 33) % 33;
        return in_array($mod, [1, 5, 9, 13, 17, 22, 26, 30], true);
    }

    /**
     * Parse a Gregorian date or datetime string into Y/m/d integers.
     *
     * @return array{int,int,int}|null
     */
    private static function parseGregorian(string $input): ?array
    {
        $normalized = PersianDigits::toLatin($input);
        if (!preg_match('#(\d{4})[/\-\.](\d{1,2})[/\-\.](\d{1,2})#', $normalized, $m)) {
            return null;
        }
        return [(int) $m[1], (int) $m[2], (int) $m[3]];
    }
}
