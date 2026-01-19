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

/**
 * Check if an event/course is sold out
 *
 * This is a simple helper function for external integrations (like AI Assistants)
 * to quickly check if a course is available or sold out.
 *
 * @param int $event_id The event/course post ID
 * @return array {
 *     @type bool   $is_sold_out       Whether ALL tickets are sold out
 *     @type bool   $is_available      Whether there are any available tickets
 *     @type string $reason            'available', 'sold_out', 'manual_override', or 'no_tickets'
 *     @type array  $tickets           Array of ticket availability info
 *     @type string $message           Human-readable status message
 * }
 */
function gps_check_course_availability($event_id) {
    $event = get_post($event_id);

    if (!$event || $event->post_type !== 'gps_event') {
        return [
            'is_sold_out' => false,
            'is_available' => false,
            'reason' => 'not_found',
            'tickets' => [],
            'message' => 'Course not found.',
        ];
    }

    // Get all tickets for this event
    $tickets = get_posts([
        'post_type' => 'gps_ticket',
        'posts_per_page' => -1,
        'meta_query' => [
            ['key' => '_gps_event_id', 'value' => $event_id],
        ],
    ]);

    $all_sold_out = true;
    $has_manual_override = false;
    $has_active_tickets = false;
    $ticket_info = [];

    foreach ($tickets as $ticket) {
        $status = get_post_meta($ticket->ID, '_gps_ticket_status', true);
        if ($status !== 'active') {
            continue;
        }

        $has_active_tickets = true;
        $is_sold_out = Tickets::is_sold_out($ticket->ID);
        $is_manual = Tickets::is_manually_sold_out($ticket->ID);
        $stock = Tickets::get_ticket_stock($ticket->ID);

        if ($is_manual) {
            $has_manual_override = true;
        }

        $ticket_info[] = [
            'id' => $ticket->ID,
            'name' => $ticket->post_title,
            'is_sold_out' => $is_sold_out,
            'is_manual_sold_out' => $is_manual,
            'available' => $stock['available'],
            'unlimited' => $stock['unlimited'],
        ];

        if (!$is_sold_out) {
            $all_sold_out = false;
        }
    }

    // Determine reason and message
    if (!$has_active_tickets) {
        return [
            'is_sold_out' => true,
            'is_available' => false,
            'reason' => 'no_tickets',
            'tickets' => [],
            'message' => sprintf('%s has no available ticket types.', $event->post_title),
        ];
    }

    if ($all_sold_out) {
        $reason = $has_manual_override ? 'manual_override' : 'sold_out';
        return [
            'is_sold_out' => true,
            'is_available' => false,
            'reason' => $reason,
            'tickets' => $ticket_info,
            'message' => sprintf('%s is currently sold out. Customers can join the waitlist to be notified when spots become available.', $event->post_title),
        ];
    }

    return [
        'is_sold_out' => false,
        'is_available' => true,
        'reason' => 'available',
        'tickets' => $ticket_info,
        'message' => sprintf('%s has tickets available.', $event->post_title),
    ];
}

/**
 * Check if a specific ticket is sold out
 *
 * @param int $ticket_id The ticket post ID
 * @return array {
 *     @type bool   $is_sold_out       Whether this ticket is sold out
 *     @type bool   $is_manual         Whether it's manually marked as sold out
 *     @type int    $available         Number of available tickets (0 if sold out)
 *     @type string $reason            'available', 'stock_depleted', or 'manual_override'
 * }
 */
function gps_check_ticket_availability($ticket_id) {
    $is_sold_out = Tickets::is_sold_out($ticket_id);
    $is_manual = Tickets::is_manually_sold_out($ticket_id);
    $stock = Tickets::get_ticket_stock($ticket_id);

    if ($is_manual) {
        return [
            'is_sold_out' => true,
            'is_manual' => true,
            'available' => $stock['available'],
            'reason' => 'manual_override',
        ];
    }

    if ($is_sold_out) {
        return [
            'is_sold_out' => true,
            'is_manual' => false,
            'available' => 0,
            'reason' => 'stock_depleted',
        ];
    }

    return [
        'is_sold_out' => false,
        'is_manual' => false,
        'available' => $stock['unlimited'] ? -1 : $stock['available'],
        'reason' => 'available',
    ];
}
