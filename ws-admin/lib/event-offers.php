<?php
/*
 * THE OFFERS OF AN EVENT: what it costs, for whom, on which date, and where.
 *
 * A theatre does not have one price. It has full and reduced, under-12 for
 * children's theatre, a school matinée, a second child at half price, a
 * workshop fee with sign-up by email, a free evening booked on Eventbrite.
 * All of it is schema.org Offer, one per price, in `offers`:
 *
 *   name                     "Intero", "Ridotto under 12", "Matinée per le scuole"
 *   price, priceCurrency     10, "EUR" (0 = free, still worth an Offer when it
 *                            carries a booking link)
 *   url                      where to buy or sign up: the event's own page on
 *                            the ticket site, or mailto: for a sign-up by email
 *   description              the condition in words: "dal secondo figlio"
 *   eligibleQuantity         {"minValue": 2} for the second child
 *   meetoo:eligibleAge       who, in the typicalAgeRange format: "0-11" = under 12
 *   meetoo:audience          a group: "scuole"
 *   meetoo:dates             the days it applies to (a matinée replica): absent
 *                            = every date of the event
 *   meetoo:registration      "required": sign-up or booking is compulsory
 *
 * Older files have a single Offer object: it is read as a list of one.
 */

if (!function_exists('event_offers')) {

    /** The offers of an event as a list of arrays (the raw Offer objects). */
    function event_offers(array $e): array {
        $o = $e['offers'] ?? null;
        if (!is_array($o) || !$o) return [];
        $list = array_key_exists(0, $o) ? $o : [$o];
        return array_values(array_filter($list, 'is_array'));
    }

    /** The price as a number, null when none is written. */
    function event_offer_price(array $o): ?float {
        $p = $o['price'] ?? null;
        if ($p === null || $p === '') return null;
        return is_numeric($p) ? (float)$p : null;
    }

    /** Only the offers valid on $day (Y-m-d): those without dates, and those naming it. */
    function event_offers_on(array $offers, string $day): array {
        return array_values(array_filter($offers, function ($o) use ($day) {
            $dates = (array)($o['meetoo:dates'] ?? []);
            return !$dates || in_array($day, array_map(fn($d) => substr((string)$d, 0, 10), $dates), true);
        }));
    }

    /**
     * What a card needs: the lowest and highest price, whether it is free, and
     * whether signing up is required. ['min' => ?float, 'max' => ?float,
     * 'free' => bool, 'registration' => bool]
     */
    function event_offers_summary(array $e): array {
        $prices = [];
        $reg = false;
        foreach (event_offers($e) as $o) {
            $p = event_offer_price($o);
            if ($p !== null) $prices[] = $p;
            if (($o['meetoo:registration'] ?? '') === 'required') $reg = true;
        }
        $free = !empty($e['isAccessibleForFree']) && $e['isAccessibleForFree'] !== 'false';
        if ($prices && max($prices) == 0) $free = true;
        return [
            'min' => $prices ? min($prices) : null,
            'max' => $prices ? max($prices) : null,
            'free' => $free,
            'registration' => $reg,
        ];
    }

    /** A price as people read it: 10 -> "10 €", 7.5 -> "7,50 €". */
    function event_price_text(float $p, string $currency = 'EUR'): string {
        $n = (floor($p) == $p) ? (string)(int)$p : number_format($p, 2, ',', '');
        return $currency === 'EUR' ? "$n €" : "$n $currency";
    }
}
