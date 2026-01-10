<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

use TCPDF;

/**
 * PDF Generation
 * Handles PDF ticket generation
 */
class PDF_Generator {

    public static function init() {
        // Handle PDF downloads
        add_action('template_redirect', [__CLASS__, 'handle_download']);
    }

    /**
     * Handle ticket PDF download
     */
    public static function handle_download() {
        if (!isset($_GET['download_ticket'])) {
            return;
        }

        $ticket_id = (int) $_GET['download_ticket'];

        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(add_query_arg('download_ticket', $ticket_id)));
            exit;
        }

        global $wpdb;

        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE id = %d",
            $ticket_id
        ));

        if (!$ticket) {
            wp_die(__('Ticket not found.', 'gps-courses'));
        }

        // Verify user owns this ticket
        if ($ticket->user_id != get_current_user_id() && !current_user_can('manage_options')) {
            wp_die(__('Unauthorized access.', 'gps-courses'));
        }

        self::generate_ticket_pdf($ticket);
        exit;
    }

    /**
     * Generate ticket PDF
     */
    public static function generate_ticket_pdf($ticket) {
        // Get event details
        $event = get_post($ticket->event_id);
        $start_date = get_post_meta($ticket->event_id, '_gps_start_date', true);
        $end_date = get_post_meta($ticket->event_id, '_gps_end_date', true);
        $venue = get_post_meta($ticket->event_id, '_gps_venue', true);
        $ce_credits = get_post_meta($ticket->event_id, '_gps_ce_credits', true);

        // Check if checked in
        global $wpdb;
        $checked_in = $wpdb->get_var($wpdb->prepare(
            "SELECT checked_in_at FROM {$wpdb->prefix}gps_attendance WHERE ticket_id = %d",
            $ticket->id
        ));

        // Create new PDF document
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('GPS Courses');
        $pdf->SetAuthor('GPS Dental Training');
        $pdf->SetTitle('Event Ticket - ' . $event->post_title);
        $pdf->SetSubject('Event Ticket');

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 12);

        // Logo (if exists)
        $logo_path = GPSC_PATH . 'assets/images/logo.png';
        if (file_exists($logo_path)) {
            $pdf->Image($logo_path, 15, 15, 50, 0, 'PNG');
            $pdf->SetY(35);
        }

        // Title
        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->SetTextColor(34, 113, 177);
        $pdf->Cell(0, 10, 'EVENT TICKET', 0, 1, 'C');
        $pdf->Ln(5);

        // Event Title
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->MultiCell(0, 10, $event->post_title, 0, 'C');
        $pdf->Ln(5);

        // Draw line
        $pdf->SetDrawColor(34, 113, 177);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(10);

        // Event Details
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(0, 0, 0);

        // Date
        if ($start_date) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(50, 8, 'Date:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 12);
            $date_str = date_i18n(get_option('date_format'), strtotime($start_date));
            if ($end_date && $end_date !== $start_date) {
                $date_str .= ' - ' . date_i18n(get_option('date_format'), strtotime($end_date));
            }
            $pdf->Cell(0, 8, $date_str, 0, 1, 'L');
        }

        // Venue
        if ($venue) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(50, 8, 'Venue:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 12);
            $pdf->MultiCell(0, 8, $venue, 0, 'L');
        }

        // CE Credits
        if ($ce_credits) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(50, 8, 'CE Credits:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(0, 8, $ce_credits . ' Credits', 0, 1, 'L');
        }

        $pdf->Ln(5);

        // Attendee Information Box
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetLineWidth(0.2);
        $y_start = $pdf->GetY();
        $pdf->Rect(15, $y_start, 180, 35, 'DF');

        $pdf->SetXY(15, $y_start + 5);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(34, 113, 177);
        $pdf->Cell(0, 8, 'ATTENDEE INFORMATION', 0, 1, 'L');

        $pdf->SetX(15);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(50, 7, 'Name:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 7, $ticket->attendee_name, 0, 1, 'L');

        $pdf->SetX(15);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(50, 7, 'Email:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 7, $ticket->attendee_email, 0, 1, 'L');

        $pdf->SetXY(15, $y_start + 35);
        $pdf->Ln(10);

        // Ticket Code Box
        $y_start = $pdf->GetY();
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(34, 113, 177);
        $pdf->SetLineWidth(0.5);
        $pdf->Rect(15, $y_start, 180, 25, 'D');

        $pdf->SetXY(15, $y_start + 5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 6, 'TICKET CODE', 0, 1, 'C');

        $pdf->SetX(15);
        $pdf->SetFont('courier', 'B', 18);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 10, $ticket->ticket_code, 0, 1, 'C');

        $pdf->Ln(10);

        // QR Code
        if ($ticket->qr_code_path && file_exists(ABSPATH . $ticket->qr_code_path)) {
            $qr_y = $pdf->GetY();
            $pdf->Image(ABSPATH . $ticket->qr_code_path, 65, $qr_y, 80, 80, 'PNG');
            $pdf->SetY($qr_y + 85);

            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 5, 'Scan this QR code at the event entrance for check-in', 0, 1, 'C');
        }

        $pdf->Ln(5);

        // Status
        if ($checked_in) {
            $pdf->SetFillColor(236, 247, 237);
            $pdf->SetDrawColor(44, 102, 45);
            $pdf->SetTextColor(44, 102, 45);
            $pdf->SetFont('helvetica', 'B', 12);
            $y_pos = $pdf->GetY();
            $pdf->Rect(15, $y_pos, 180, 15, 'DF');
            $pdf->SetXY(15, $y_pos + 4);
            $pdf->Cell(0, 7, 'CHECKED IN: ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($checked_in)), 0, 1, 'C');
            $pdf->Ln(5);
        }

        // Footer
        $pdf->SetY(-30);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 5, 'GPS Dental Training', 0, 1, 'C');
        $pdf->Cell(0, 5, get_site_url(), 0, 1, 'C');
        $pdf->Cell(0, 5, 'Generated on: ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), current_time('timestamp')), 0, 1, 'C');

        // Output PDF
        $filename = sanitize_file_name('ticket-' . $ticket->ticket_code . '.pdf');
        $pdf->Output($filename, 'D');
    }

    /**
     * Generate PDF for multiple tickets (batch)
     */
    public static function generate_batch_pdf($ticket_ids) {
        if (empty($ticket_ids)) {
            return false;
        }

        global $wpdb;

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('GPS Courses');
        $pdf->SetAuthor('GPS Dental Training');
        $pdf->SetTitle('Event Tickets');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);

        foreach ($ticket_ids as $ticket_id) {
            $ticket = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE id = %d",
                $ticket_id
            ));

            if (!$ticket) {
                continue;
            }

            // Add page for this ticket
            $pdf->AddPage();

            // Use same template as single ticket
            // (simplified version - you can extract the ticket rendering to a separate method)
            $event = get_post($ticket->event_id);

            $pdf->SetFont('helvetica', 'B', 18);
            $pdf->Cell(0, 10, $event->post_title, 0, 1, 'C');
            $pdf->Ln(5);

            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(50, 8, 'Attendee:', 0, 0, 'L');
            $pdf->Cell(0, 8, $ticket->attendee_name, 0, 1, 'L');

            $pdf->Cell(50, 8, 'Ticket Code:', 0, 0, 'L');
            $pdf->Cell(0, 8, $ticket->ticket_code, 0, 1, 'L');

            if ($ticket->qr_code_path && file_exists(ABSPATH . $ticket->qr_code_path)) {
                $pdf->Image(ABSPATH . $ticket->qr_code_path, 65, $pdf->GetY() + 10, 80, 80, 'PNG');
            }
        }

        $filename = 'tickets-batch-' . time() . '.pdf';
        $pdf->Output($filename, 'D');
    }
}
