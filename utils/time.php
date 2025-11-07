<?php

/**
 * Timezone utility helpers for consistent IST timestamps
 */

if (!function_exists('getIstDateTime')) {
    /**
     * Get a DateTime instance set to Asia/Kolkata timezone.
     *
     * @param string $time A string suitable for DateTime constructor (default 'now')
     * @return DateTime
     */
    function getIstDateTime(string $time = 'now'): DateTime
    {
        static $timezone = null;

        if ($timezone === null) {
            $timezone = new DateTimeZone('Asia/Kolkata');
        }

        return new DateTime($time, $timezone);
    }
}

if (!function_exists('getIstTimestamp')) {
    /**
     * Get a formatted timestamp string in IST.
     *
     * @param string $time A string suitable for DateTime constructor (default 'now')
     * @param string $format PHP date format (default 'Y-m-d H:i:s')
     * @return string
     */
    function getIstTimestamp(string $time = 'now', string $format = 'Y-m-d H:i:s'): string
    {
        return getIstDateTime($time)->format($format);
    }
}

if (!function_exists('formatDateTimeAsIst')) {
    /**
     * Convert an existing DateTimeInterface instance to IST and optionally format it.
     *
     * @param DateTimeInterface $dateTime Source DateTime instance
     * @param string|null $format Optional PHP date format; if null, returns DateTime instance
     * @return DateTime|string
     */
    function formatDateTimeAsIst(DateTimeInterface $dateTime, ?string $format = 'Y-m-d H:i:s')
    {
        static $timezone = null;

        if ($timezone === null) {
            $timezone = new DateTimeZone('Asia/Kolkata');
        }

        $istDateTime = (new DateTimeImmutable('@' . $dateTime->getTimestamp()))
            ->setTimezone($timezone);

        if ($format === null) {
            return DateTime::createFromImmutable($istDateTime);
        }

        return $istDateTime->format($format);
    }
}


