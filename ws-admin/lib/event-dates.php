<?php
/*
 * THE DATES OF AN EVENT, from what it says about itself.
 *
 * One event, one folder - and its dates can be many things:
 *
 *   single   one date (startDate, maybe endDate on the same day)
 *   dates    a list: the replicas of a show, each one a Schedule without a
 *            repeatFrequency - Transfert on 29 and 30 October
 *   rule     a Schedule that repeats: every Monday 20:30-22 from September to
 *            June, except the holidays; an exhibition open Fri-Sun 17-20
 *   period   from one day to another, without a rule: Giornate Sarde, 2-4 Oct
 *
 * all written in schema.org's own words (Event.eventSchedule -> Schedule:
 * startDate, endDate, startTime, endTime, repeatFrequency, byDay, repeatCount,
 * exceptDate, scheduleTimezone). The index stores the expanded dates, and the
 * site picks the next one when it draws a list: "next" changes every day, a
 * list of dates does not.
 *
 * Tolerant on input, because the files are written by hand, by the editor
 * and by other programs: byDay may be "MO", "Monday", "https://schema.org/Monday"
 * or several of them, in a string or in an array; a schedule without its own
 * start takes the event's.
 */

if (!function_exists('event_dates_expand')) {

    /** ISO weekday numbers (1 = Monday) from whatever byDay holds. */
    function event_dates_days($byDay): array {
        $map = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7,
                'MONDAY' => 1, 'TUESDAY' => 2, 'WEDNESDAY' => 3, 'THURSDAY' => 4, 'FRIDAY' => 5, 'SATURDAY' => 6, 'SUNDAY' => 7];
        $out = [];
        $parts = is_array($byDay) ? $byDay : preg_split('/[\s,;]+/', (string)$byDay);
        foreach ($parts as $p) {
            if (is_array($p)) $p = $p['@id'] ?? '';
            $p = strtoupper(trim((string)$p));
            $p = preg_replace('#^HTTPS?://SCHEMA\.ORG/#', '', $p);
            if (isset($map[$p])) $out[$map[$p]] = true;
        }
        $out = array_keys($out);
        sort($out);
        return $out;
    }

    /** The Schedules of an event, always as a list. */
    function event_dates_schedules(array $e): array {
        $s = $e['eventSchedule'] ?? null;
        if (!is_array($s) || !$s) return [];
        return array_key_exists(0, $s) ? array_values(array_filter($s, 'is_array')) : [$s];
    }

    /** 'Y-m-d' and 'H:i' of an ISO date or datetime ('' when missing). */
    function event_dates_day(string $iso): string {
        return preg_match('/^(\d{4}-\d{2}-\d{2})/', $iso, $m) ? $m[1] : '';
    }
    function event_dates_time(string $iso): string {
        return preg_match('/T(\d{2}:\d{2})/', $iso, $m) ? $m[1] : '';
    }

    /** An ISO datetime with offset, in $tz, from a day and a time ('' = no time). */
    function event_dates_at(string $day, string $time, DateTimeZone $tz): string {
        if ($day === '') return '';
        if ($time === '') return $day;   // a whole day: no hour is claimed
        try {
            return (new DateTimeImmutable("$day $time", $tz))->format('Y-m-d\TH:i:sP');
        } catch (Exception $x) {
            return $day . 'T' . $time;
        }
    }

    /** P1W -> ['W', 1]; P7D -> ['D', 7]; '' when it does not repeat. */
    function event_dates_frequency(string $f): array {
        return preg_match('/^P(\d+)([DWMY])$/i', trim($f), $m) ? [strtoupper($m[2]), max(1, (int)$m[1])] : ['', 0];
    }

    /**
     * Everything the index needs about the dates of $e.
     *
     * Returns [
     *   'pattern' => single | dates | rule | period,
     *   'dates'   => [['start' => ISO, 'end' => ISO|''], ...] sorted, capped,
     *   'until'   => 'Y-m-d' of the last one ('' if open-ended),
     *   'rule'    => ['freq' => D|W|M|Y, 'interval' => n, 'days' => [1..7], 'time' => 'H:i'] | null,
     * ]
     * $max caps the list; an open-ended rule stops at $horizon from today.
     */
    function event_dates_expand(array $e, int $max = 200, string $horizon = '+18 months'): array {
        $tzName = (string)($e['meetoo:timezone'] ?? '');
        $scheds = event_dates_schedules($e);
        if ($tzName === '' && $scheds) $tzName = (string)($scheds[0]['scheduleTimezone'] ?? '');
        try { $tz = new DateTimeZone($tzName !== '' ? $tzName : 'Europe/Rome'); }
        catch (Exception $x) { $tz = new DateTimeZone('Europe/Rome'); }

        $eStart = (string)($e['startDate'] ?? '');
        $eEnd = (string)($e['endDate'] ?? '');
        $out = ['pattern' => 'single', 'dates' => [], 'until' => '', 'rule' => null];

        if (!$scheds) {
            if ($eStart === '') return $out;
            $out['dates'][] = ['start' => $eStart, 'end' => $eEnd];
            $d0 = event_dates_day($eStart); $d1 = event_dates_day($eEnd);
            $out['pattern'] = ($d1 !== '' && $d1 > $d0) ? 'period' : 'single';
            $out['until'] = $d1 !== '' ? $d1 : $d0;
            return $out;
        }

        $limit = (new DateTimeImmutable('today', $tz))->modify($horizon)->format('Y-m-d');
        $repeats = false;
        $spans = 0;
        foreach ($scheds as $s) {
            [$freq, $every] = event_dates_frequency((string)($s['repeatFrequency'] ?? ''));
            $from = event_dates_day((string)($s['startDate'] ?? '')) ?: event_dates_day($eStart);
            if ($from === '') continue;
            $to = event_dates_day((string)($s['endDate'] ?? ''));
            if ($to === '' && $freq !== '') $to = event_dates_day($eEnd);
            $t0 = (string)($s['startTime'] ?? '') ?: event_dates_time($eStart);
            $t1 = (string)($s['endTime'] ?? '') ?: (event_dates_day($eEnd) === event_dates_day($eStart) ? event_dates_time($eEnd) : '');
            $t0 = substr($t0, 0, 5); $t1 = substr($t1, 0, 5);
            $except = [];
            foreach ((array)($s['exceptDate'] ?? []) as $x) { $x = event_dates_day((string)$x); if ($x !== '') $except[$x] = true; }
            $count = (int)($s['repeatCount'] ?? 0);

            if ($freq === '') {
                // One date, or a span of days without a rule.
                if ($to !== '' && $to > $from) {
                    $out['dates'][] = ['start' => event_dates_at($from, $t0, $tz), 'end' => event_dates_at($to, $t1, $tz)];
                    $spans++;
                } elseif (!isset($except[$from])) {
                    $out['dates'][] = ['start' => event_dates_at($from, $t0, $tz), 'end' => $t1 !== '' ? event_dates_at($from, $t1, $tz) : ''];
                }
                continue;
            }

            $repeats = true;
            if ($out['rule'] === null) {
                $out['rule'] = ['freq' => $freq, 'interval' => $every, 'days' => event_dates_days($s['byDay'] ?? ''), 'time' => $t0];
            }
            $days = event_dates_days($s['byDay'] ?? '');
            $stop = ($to !== '' && $to < $limit) ? $to : $limit;
            $first = new DateTimeImmutable($from, $tz);
            $n = 0;
            for ($d = $first; $d->format('Y-m-d') <= $stop && count($out['dates']) < $max; ) {
                $day = $d->format('Y-m-d');
                $take = true;
                if ($freq === 'W') {
                    // Weekly: the chosen weekdays (or the start's), in the weeks
                    // that fall on the interval counted from the first one.
                    $weeks = intdiv((int)$first->diff($d)->days, 7);
                    $take = ($weeks % $every === 0) && in_array((int)$d->format('N'), $days ?: [(int)$first->format('N')], true);
                }
                if ($take && !isset($except[$day])) {
                    $out['dates'][] = ['start' => event_dates_at($day, $t0, $tz), 'end' => $t1 !== '' ? event_dates_at($day, $t1, $tz) : ''];
                    $n++;
                    if ($count > 0 && $n >= $count) break;
                }
                $d = ($freq === 'W' || $freq === 'D')
                    ? $d->modify($freq === 'D' ? "+$every day" : '+1 day')
                    : $d->modify($freq === 'M' ? "+$every month" : "+$every year");
            }
        }

        usort($out['dates'], fn($a, $b) => strcmp(event_dates_day($a['start']) . event_dates_time($a['start']), event_dates_day($b['start']) . event_dates_time($b['start'])));
        $out['dates'] = array_slice($out['dates'], 0, $max);
        $n = count($out['dates']);
        $out['pattern'] = $repeats ? 'rule' : ($n > 1 ? 'dates' : ($spans ? 'period' : 'single'));
        if ($n) {
            $last = $out['dates'][$n - 1];
            $out['until'] = event_dates_day($last['end'] !== '' ? $last['end'] : $last['start']);
        }
        // A rule with no end of its own is open-ended: its "last" date is only
        // where the expansion stopped.
        if ($repeats) {
            $open = false;
            foreach ($scheds as $s) {
                if (event_dates_frequency((string)($s['repeatFrequency'] ?? ''))[0] !== ''
                    && empty($s['endDate']) && empty($s['repeatCount']) && event_dates_day($eEnd) === '') $open = true;
            }
            if ($open) $out['until'] = '';
        }
        return $out;
    }
}
