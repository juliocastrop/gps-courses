<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;

/**
 * QR Code generation and management
 */
class QRCodeGenerator {

    /**
     * Generate unique ticket code
     */
    public static function generate_ticket_code($order_id, $item_id, $user_id) {
        $prefix = get_option('gps_ticket_prefix', 'GPST');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        $timestamp = substr(time(), -4);

        return sprintf('%s-%d-%d-%s-%s', $prefix, $order_id, $item_id, $timestamp, $random);
    }

    /**
     * Generate QR code for ticket
     *
     * @param string $ticket_code Unique ticket identifier
     * @param int $ticket_id Database ticket ID
     * @param array $data Additional data to encode
     * @return string|false Path to generated QR code file or false on failure
     */
    public static function generate_qr_code($ticket_code, $ticket_id, $data = []) {
        // Check if QR code library is available
        if (!class_exists('Endroid\QrCode\Builder\Builder')) {
            error_log('GPS Courses: QR Code library not found. Please run "composer install" in the plugin directory.');
            error_log('GPS Courses: Vendor path: ' . GPSC_PATH . 'vendor/autoload.php exists=' . (file_exists(GPSC_PATH . 'vendor/autoload.php') ? 'yes' : 'no'));
            return false;
        }

        try {
            // Prepare data for QR code
            $qr_data = [
                'ticket_code' => $ticket_code,
                'ticket_id' => $ticket_id,
                'event_id' => $data['event_id'] ?? 0,
                'user_id' => $data['user_id'] ?? 0,
                'order_id' => $data['order_id'] ?? 0,
                'timestamp' => current_time('timestamp'),
                'hash' => self::generate_verification_hash($ticket_code, $ticket_id, $data),
            ];

            // JSON encode the data
            $json_data = wp_json_encode($qr_data);

            // Prepare upload directory
            $upload_dir = wp_upload_dir();
            $qr_dir = $upload_dir['basedir'] . '/gps-qrcodes';

            if (!file_exists($qr_dir)) {
                wp_mkdir_p($qr_dir);
            }

            // Save file
            $filename = 'ticket-' . $ticket_code . '.png';
            $filepath = $qr_dir . '/' . $filename;

            // Build QR code using Builder API (v4+)
            $result = Builder::create()
                ->writer(new PngWriter())
                ->data($json_data)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
                ->size(300)
                ->margin(10)
                ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
                ->build();

            // Save to file
            $result->saveToFile($filepath);

            // Return relative path
            return 'gps-qrcodes/' . $filename;

        } catch (\Exception $e) {
            error_log('GPS Courses QR Code Generation Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate verification hash for security
     */
    private static function generate_verification_hash($ticket_code, $ticket_id, $data) {
        $secret = defined('AUTH_KEY') ? AUTH_KEY : 'gps-courses-secret';
        $string = $ticket_code . $ticket_id . ($data['user_id'] ?? 0) . ($data['order_id'] ?? 0);

        return hash_hmac('sha256', $string, $secret);
    }

    /**
     * Verify QR code data
     */
    public static function verify_qr_data($qr_data) {
        if (!is_array($qr_data)) {
            if (is_string($qr_data)) {
                $qr_data = json_decode($qr_data, true);
            }

            if (!is_array($qr_data)) {
                return false;
            }
        }

        // Check required fields
        $required_fields = ['ticket_code', 'ticket_id', 'hash'];
        foreach ($required_fields as $field) {
            if (!isset($qr_data[$field])) {
                return false;
            }
        }

        // Verify hash
        $expected_hash = self::generate_verification_hash(
            $qr_data['ticket_code'],
            $qr_data['ticket_id'],
            $qr_data
        );

        if (!hash_equals($expected_hash, $qr_data['hash'])) {
            return false;
        }

        // Verify ticket exists in database
        global $wpdb;
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE id = %d AND ticket_code = %s",
            $qr_data['ticket_id'],
            $qr_data['ticket_code']
        ));

        if (!$ticket) {
            return false;
        }

        // Check ticket status
        if ($ticket->status !== 'valid') {
            return [
                'valid' => false,
                'error' => 'ticket_invalid',
                'message' => __('This ticket is no longer valid.', 'gps-courses'),
                'ticket' => $ticket
            ];
        }

        // Check if already checked in
        $attendance = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_attendance WHERE ticket_id = %d",
            $ticket->id
        ));

        if ($attendance) {
            return [
                'valid' => false,
                'error' => 'already_checked_in',
                'message' => __('This ticket has already been checked in.', 'gps-courses'),
                'ticket' => $ticket,
                'attendance' => $attendance
            ];
        }

        return [
            'valid' => true,
            'ticket' => $ticket,
            'qr_data' => $qr_data
        ];
    }

    /**
     * Get QR code URL
     */
    public static function get_qr_code_url($qr_code_path) {
        if (empty($qr_code_path)) {
            return '';
        }

        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/' . $qr_code_path;
    }

    /**
     * Get QR code file path
     */
    public static function get_qr_code_path($qr_code_path) {
        if (empty($qr_code_path)) {
            return '';
        }

        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/' . $qr_code_path;
    }

    /**
     * Delete QR code file
     */
    public static function delete_qr_code($qr_code_path) {
        $filepath = self::get_qr_code_path($qr_code_path);

        if (file_exists($filepath)) {
            return unlink($filepath);
        }

        return false;
    }

    /**
     * Regenerate QR code for a ticket
     */
    public static function regenerate_qr_code($ticket_id) {
        global $wpdb;

        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE id = %d",
            $ticket_id
        ));

        if (!$ticket) {
            return false;
        }

        // Delete old QR code
        if (!empty($ticket->qr_code_path)) {
            self::delete_qr_code($ticket->qr_code_path);
        }

        // Generate new QR code
        $qr_path = self::generate_qr_code(
            $ticket->ticket_code,
            $ticket->id,
            [
                'event_id' => $ticket->event_id,
                'user_id' => $ticket->user_id,
                'order_id' => $ticket->order_id,
            ]
        );

        if ($qr_path) {
            // Update database
            $wpdb->update(
                $wpdb->prefix . 'gps_tickets',
                ['qr_code_path' => $qr_path],
                ['id' => $ticket_id],
                ['%s'],
                ['%d']
            );

            return $qr_path;
        }

        return false;
    }
}
