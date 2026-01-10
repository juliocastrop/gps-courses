<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Nov 21, 2025  |  Nov 21 – 22, 2025  |  Nov 30 – Dec 1, 2025  |  Dec 31, 2025 – Jan 1, 2026
 */
function format_date_range($start, $end, $tz = null) {
    if (!$start || !$end) return '';
    $tz = $tz ? new \DateTimeZone($tz) : wp_timezone();

    $a = new \DateTime($start, $tz);
    $b = new \DateTime($end,   $tz);

    if ($a->format('Y-m-d') === $b->format('Y-m-d')) {
        return $a->format('M j, Y');
    }
    $sameYear  = $a->format('Y') === $b->format('Y');
    $sameMonth = $a->format('m') === $b->format('m');

    if ($sameYear && $sameMonth)  return $a->format('M j').' – '.$b->format('j, Y');
    if ($sameYear && !$sameMonth) return $a->format('M j').' – '.$b->format('M j, Y');

    return $a->format('M j, Y').' – '.$b->format('M j, Y');
}
