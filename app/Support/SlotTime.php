<?php

namespace App\Support;

/**
 * One way of reading weekly availability everywhere.
 *
 * Forms save the same time in different ways: client intake forms save
 * "10am-1050am", the trainee placement and qualified counsellor forms save
 * "10am-11am", older records hold "morning-early" or "10:00", and day names
 * may be "Tuesday" or "tuesday". Two slots are the same when they START at
 * the same time on the same day, which is how the matching screen, the
 * server and the booking page now all compare them.
 */
class SlotTime
{
    /** Old "abstract" slot names and the session start times they cover. */
    private const ABSTRACT = [
        'morning-early' => ['10:00'],
        'morning-late' => ['11:00', '12:00'],
        'afternoon-early' => ['13:00', '14:00', '15:00'],
        'afternoon-late' => ['16:00'],
        'evening' => ['17:00', '18:00'],
        'morning' => ['10:00', '11:00'],
        'afternoon' => ['12:00', '13:00', '14:00', '15:00', '16:00'],
    ];

    /**
     * The start times ("HH:MM", 24-hour) a saved slot stands for.
     * "10am-1050am", "10am-11am", "10:00am - 10:50am", "10:00" -> ["10:00"].
     */
    public static function startTimes($slot): array
    {
        $clean = strtolower(preg_replace('/\s+/', '', (string) $slot));
        if ($clean === '') {
            return [];
        }
        if (isset(self::ABSTRACT[$clean])) {
            return self::ABSTRACT[$clean];
        }

        $start = explode('-', $clean)[0];
        if (!preg_match('/^(\d{1,2})(?::?(\d{2}))?(am|pm)?$/', $start, $m)) {
            return [];
        }
        $hour = (int) $m[1];
        $minute = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
        $ampm = $m[3] ?? '';
        if ($ampm === 'pm' && $hour < 12) {
            $hour += 12;
        } elseif ($ampm === 'am' && $hour === 12) {
            $hour = 0;
        }
        if ($hour > 23 || $minute > 59) {
            return [];
        }

        return [sprintf('%02d:%02d', $hour, $minute)];
    }

    /** The first start time of a slot, or null if it can't be read. */
    public static function startTime($slot): ?string
    {
        return self::startTimes($slot)[0] ?? null;
    }

    /** Availability as [lowercase day => [slots]], whatever form it was saved in. */
    public static function normalize($availability): array
    {
        if (is_string($availability)) {
            $decoded = json_decode($availability, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true); // stored twice-encoded by some older submissions
            }
            $availability = $decoded;
        }
        if (!is_array($availability)) {
            return [];
        }

        $out = [];
        foreach ($availability as $day => $slots) {
            if (!is_string($day)) {
                continue;
            }
            $key = strtolower(trim($day));
            $out[$key] = array_merge($out[$key] ?? [], array_values(array_filter((array) $slots, 'is_scalar')));
        }

        return $out;
    }

    /** Whether any of a day's saved slots starts when $target starts. */
    public static function dayHasSlot(array $daySlots, $target): bool
    {
        $wanted = self::startTimes($target);
        if (!$wanted) {
            return false;
        }
        foreach ($daySlots as $slot) {
            if (array_intersect($wanted, self::startTimes($slot))) {
                return true;
            }
        }

        return false;
    }

    /** Whether an availability (any saved form) includes $day at $time. */
    public static function offers($availability, ?string $day, $time): bool
    {
        if (!$day) {
            return false;
        }
        $days = self::normalize($availability);

        return self::dayHasSlot($days[strtolower(trim($day))] ?? [], $time);
    }

    /** Every "day|HH:MM" an availability covers. */
    public static function keys($availability): array
    {
        $keys = [];
        foreach (self::normalize($availability) as $day => $slots) {
            foreach ($slots as $slot) {
                foreach (self::startTimes($slot) as $t) {
                    $keys[$day . '|' . $t] = true;
                }
            }
        }

        return array_keys($keys);
    }
}
