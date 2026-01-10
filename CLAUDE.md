# GPS COURSES - WordPress Plugin Documentation

## Project Overview

**Plugin Name:** GPS Courses
**Version:** 1.0.2
**Author:** WebMinds (Julio Castro)
**Text Domain:** gps-courses
**License:** Proprietary
**Client:** GPS Dental Training (https://gpsdentaltraining.com)

### Description
GPS Courses is a comprehensive WordPress plugin for managing dental training events, courses, monthly seminars, CE (Continuing Education) credits, ticketing, attendance tracking, and certificate generation. Fully integrated with WooCommerce for payment processing and Elementor for frontend display.

---

## System Requirements

- **WordPress:** 5.8+
- **PHP:** 7.4+ (8.0+ recommended)
- **WooCommerce:** 5.0+
- **Elementor:** 3.0+ (for widgets)
- **MySQL:** 5.7+ or MariaDB 10.3+
- **Server:** Apache/Nginx with mod_rewrite
- **SSL:** Required for payment processing

---

## Directory Structure

```
gps-courses/
├── gps-courses.php              # Main plugin file (entry point)
├── composer.json                # Composer dependencies
├── CLAUDE.md                    # This documentation file
│
├── includes/                    # Core PHP classes
│   ├── class-plugin.php         # Main plugin class, autoloader
│   ├── class-activator.php      # Database tables creation
│   ├── class-posttypes.php      # Custom post types registration
│   ├── class-events.php         # Event management
│   ├── class-schedules.php      # Event schedules (multi-day/session)
│   ├── class-tickets.php        # Ticket types and pricing
│   ├── class-tickets-admin.php  # Ticket admin interface
│   ├── class-qrcode.php         # QR code generation
│   ├── class-woocommerce.php    # WooCommerce integration
│   ├── class-credits.php        # CE credits ledger
│   ├── class-attendance.php     # Check-in and attendance
│   ├── class-certificates.php   # PDF certificate generation
│   ├── class-certificate-settings.php
│   ├── class-certificate-validation.php
│   ├── class-seminars.php       # Monthly seminars module
│   ├── class-seminar-registrations.php
│   ├── class-seminar-attendance.php
│   ├── class-seminar-notifications.php
│   ├── class-seminar-certificates.php
│   ├── class-seminar-waitlist.php
│   ├── class-waitlist.php       # Event waitlist
│   ├── class-emails.php         # Email handler
│   ├── class-email-settings.php # Email configuration
│   ├── class-settings.php       # Plugin settings
│   ├── class-reports.php        # Admin reports/analytics
│   ├── class-api.php            # REST API endpoints
│   ├── class-elementor.php      # Elementor integration
│   ├── class-shortcodes.php     # Shortcode handlers
│   ├── class-pdf.php            # PDF generation (TCPDF)
│   ├── class-debug-helper.php   # Debug utilities
│   ├── helpers.php              # Utility functions
│   └── emails/                  # Email classes
│       ├── class-ticket-email.php
│       └── class-credits-email.php
│
├── widgets/                     # Elementor widgets (20 total)
│   ├── base-widget.php          # Base widget class
│   ├── event-calendar.php       # Interactive calendar
│   ├── event-grid.php           # Event grid display
│   ├── event-list.php           # Event list with filters
│   ├── event-slider.php         # Event carousel
│   ├── event-dates-display.php  # Event dates display
│   ├── single-event.php         # Single event details
│   ├── course-description.php   # Course description
│   ├── course-objectives.php    # Learning objectives
│   ├── schedule-display.php     # Full schedule display
│   ├── seminar-registration.php # Seminar enrollment
│   ├── seminar-schedule.php     # Seminar sessions
│   ├── seminar-progress.php     # Student progress
│   ├── ce-credits-display.php   # User CE credits
│   ├── speaker-grid.php         # Speaker profiles
│   ├── google-maps.php          # Google Maps
│   ├── ticket-selector.php      # AJAX ticket selector
│   ├── countdown-timer.php      # Countdown to event
│   ├── add-to-calendar.php      # Calendar integration
│   └── share-course.php         # Social sharing
│
├── assets/
│   ├── css/                     # Stylesheets (13 files)
│   │   ├── admin.css
│   │   ├── admin-seminars.css
│   │   ├── admin-attendance.css
│   │   ├── admin-certificates.css
│   │   ├── admin-settings.css
│   │   ├── admin-reports.css
│   │   ├── schedule-admin.css
│   │   ├── frontend.css
│   │   ├── calendar.css
│   │   ├── elementor-widgets.css
│   │   ├── add-to-calendar.css
│   │   └── share-course.css
│   │
│   └── js/                      # JavaScript files (16 files)
│       ├── admin-seminars.js
│       ├── admin-attendance.js
│       ├── admin-certificates.js
│       ├── admin-seminar-certificates.js
│       ├── admin-settings.js
│       ├── admin-reports.js
│       ├── admin-email-settings.js
│       ├── event-admin.js
│       ├── schedule-admin.js
│       ├── elementor-widgets.js
│       ├── elementor-editor.js
│       ├── calendar.js
│       ├── countdown.js
│       ├── ticket-selector.js
│       ├── share-course.js
│       └── add-to-calendar.js
│
├── templates/
│   └── emails/
│       └── ticket.php           # Ticket email template
│
└── vendor/                      # Composer dependencies
    ├── endroid/qr-code          # QR code generation
    ├── tecnickcom/tcpdf         # PDF generation
    ├── phpmailer/phpmailer      # Email handling
    ├── bacon/bacon-qr-code      # QR code library
    └── dasprid/enum             # PHP enum support
```

---

## Database Schema

### Tables (10 total)

#### 1. wp_gps_tickets
```sql
CREATE TABLE wp_gps_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_code VARCHAR(50) UNIQUE NOT NULL,
    ticket_type_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT 0,
    order_id BIGINT UNSIGNED NOT NULL,
    order_item_id BIGINT UNSIGNED NOT NULL,
    attendee_name VARCHAR(255) NOT NULL,
    attendee_email VARCHAR(255) NOT NULL,
    qr_code_path VARCHAR(500),
    status ENUM('valid', 'used', 'cancelled') DEFAULT 'valid',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event (event_id),
    INDEX idx_user (user_id),
    INDEX idx_order (order_id)
);
```

#### 2. wp_gps_enrollments
```sql
CREATE TABLE wp_gps_enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    status ENUM('enrolled', 'completed', 'cancelled') DEFAULT 'enrolled',
    attended TINYINT(1) DEFAULT 0,
    checked_in_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_session (session_id)
);
```

#### 3. wp_gps_attendance
```sql
CREATE TABLE wp_gps_attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    checked_in_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    checked_in_by BIGINT UNSIGNED,
    check_in_method ENUM('qr_scan', 'manual', 'search') DEFAULT 'manual',
    notes TEXT,
    INDEX idx_ticket (ticket_id),
    INDEX idx_event (event_id),
    INDEX idx_user (user_id)
);
```

#### 4. wp_gps_ce_ledger
```sql
CREATE TABLE wp_gps_ce_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED,
    credits DECIMAL(5,2) NOT NULL,
    source VARCHAR(100),
    transaction_type ENUM('earned', 'adjustment', 'revoked') DEFAULT 'earned',
    notes TEXT,
    awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_event (event_id)
);
```

#### 5. wp_gps_certificates
```sql
CREATE TABLE wp_gps_certificates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED UNIQUE,
    user_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    certificate_path VARCHAR(500),
    certificate_url VARCHAR(500),
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    certificate_sent_at DATETIME,
    INDEX idx_user (user_id),
    INDEX idx_event (event_id)
);
```

#### 6. wp_gps_waitlist
```sql
CREATE TABLE wp_gps_waitlist (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    ticket_type_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notified_at DATETIME,
    status ENUM('waiting', 'notified', 'converted', 'expired') DEFAULT 'waiting',
    INDEX idx_email (email),
    INDEX idx_ticket_type (ticket_type_id),
    INDEX idx_event (event_id),
    INDEX idx_status (status)
);
```

#### 7. wp_gps_seminar_registrations
```sql
CREATE TABLE wp_gps_seminar_registrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    seminar_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED,
    registration_date DATE,
    start_session_date DATE,
    sessions_completed INT DEFAULT 0,
    sessions_remaining INT DEFAULT 10,
    makeup_used TINYINT(1) DEFAULT 0,
    status ENUM('active', 'completed', 'cancelled', 'on_hold') DEFAULT 'active',
    qr_code VARCHAR(100),
    qr_code_path VARCHAR(500),
    qr_scan_count INT DEFAULT 0,
    notes TEXT,
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_seminar (seminar_id),
    INDEX idx_order (order_id),
    INDEX idx_status (status)
);
```

#### 8. wp_gps_seminar_sessions
```sql
CREATE TABLE wp_gps_seminar_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seminar_id BIGINT UNSIGNED NOT NULL,
    session_number INT NOT NULL,
    session_date DATE NOT NULL,
    session_time_start TIME,
    session_time_end TIME,
    topic VARCHAR(500),
    description TEXT,
    capacity INT DEFAULT 0,
    registered_count INT DEFAULT 0,
    INDEX idx_seminar (seminar_id),
    INDEX idx_date (session_date),
    INDEX idx_number (session_number)
);
```

#### 9. wp_gps_seminar_attendance
```sql
CREATE TABLE wp_gps_seminar_attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id BIGINT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    seminar_id BIGINT UNSIGNED NOT NULL,
    attended TINYINT(1) DEFAULT 1,
    checked_in_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    checked_in_by BIGINT UNSIGNED,
    is_makeup TINYINT(1) DEFAULT 0,
    credits_awarded DECIMAL(5,2) DEFAULT 2.00,
    notes TEXT,
    INDEX idx_registration (registration_id),
    INDEX idx_session (session_id),
    INDEX idx_user (user_id),
    INDEX idx_seminar (seminar_id)
);
```

#### 10. wp_gps_seminar_waitlist
```sql
CREATE TABLE wp_gps_seminar_waitlist (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seminar_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED,
    email VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(50),
    position INT NOT NULL,
    status ENUM('waiting', 'notified', 'converted', 'expired', 'cancelled') DEFAULT 'waiting',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notified_at DATETIME,
    expires_at DATETIME,
    notes TEXT,
    INDEX idx_seminar (seminar_id),
    INDEX idx_user (user_id),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_position (position)
);
```

---

## Custom Post Types

### gps_event
- **Purpose:** Events and courses
- **Supports:** title, editor, excerpt, thumbnail
- **Public:** Yes
- **Meta Fields:**
  - `_gps_start_date` - Event start date
  - `_gps_end_date` - Event end date
  - `_gps_location` - Event location
  - `_gps_ce_credits` - CE credits awarded
  - `_gps_capacity` - Maximum capacity
  - `_gps_schedule_topics` - JSON array of schedule topics

### gps_seminar
- **Purpose:** Monthly seminar programs (10-session)
- **Supports:** title, editor, thumbnail
- **Public:** Yes
- **Meta Fields:**
  - `_gps_seminar_year` - Year of the seminar
  - `_gps_seminar_price` - Enrollment price ($750)
  - `_gps_wc_product_id` - Linked WooCommerce product

### gps_speaker
- **Purpose:** Speaker profiles
- **Supports:** title, editor, thumbnail
- **Public:** Yes
- **Meta Fields:**
  - `_gps_speaker_title` - Professional title
  - `_gps_speaker_bio` - Biography
  - `_gps_speaker_social` - Social media links

### gps_ticket
- **Purpose:** Ticket types for events
- **Supports:** title
- **Public:** No
- **Meta Fields:**
  - `_gps_event_id` - Parent event
  - `_gps_wc_product_id` - Linked WooCommerce product
  - `_gps_price` - Ticket price
  - `_gps_quantity` - Available quantity
  - `_gps_sale_start` - Sale start date
  - `_gps_sale_end` - Sale end date

---

## Key Features

### 1. Ticketing System
- Multiple ticket types per event (Early Bird, VIP, General, etc.)
- Time-based pricing (automatic price changes based on dates)
- QR code generation for each ticket
- Email delivery with embedded QR codes
- Ticket stock management synced with WooCommerce

### 2. Event Calendar
- Interactive month/week/list views
- Filter by event type (courses, seminars)
- Sidebar with event details
- Visual distinction: Courses (blue #0B52AC), Seminars (gold #DDC89D)
- AJAX-powered for smooth navigation

### 3. CE Credits Management
- Automatic credit award on attendance
- Credit ledger per user
- Transaction history
- Bulk credit operations
- Reports and CSV exports

### 4. Attendance & Check-in
- QR code scanner (camera mode)
- Manual entry mode
- Search by name/email mode
- Real-time attendance tracking
- Multiple check-in methods recorded

### 5. Monthly Seminars Module
- 10-session program structure
- $750 one-time enrollment fee
- 2 CE credits per session (20 total)
- Makeup session support (1 allowed)
- Bi-annual certificates (June 30, December 31)
- Session-by-session attendance tracking

### 6. Certificate Generation
- PDF certificates using TCPDF
- Customizable design and branding
- Public validation URL
- Bulk generation and email delivery
- Certificate sent tracking

### 7. Email System
- Customizable email templates
- Branding (logo, colors)
- Test email functionality
- Order status notification emails
- Multiple admin recipients support

### 8. WooCommerce Integration
- Product-based ticket sales
- Auto-complete for digital products
- Order status tracking
- Guest order linking to user accounts
- Order diagnostic tools

### 9. Waitlist Management
- Automatic waitlist when sold out
- Email notifications when spots open
- Position tracking
- Expiration handling

### 10. My Account Integration
Custom tabs in WooCommerce My Account:
- My Courses - Enrolled events
- Monthly Seminars - Seminar progress
- CE Credits - Credit history
- My Tickets - Purchased tickets
- Attendance History - Check-in records

---

## WordPress Hooks Used

### Actions
```php
// Order Processing
add_action('woocommerce_order_status_completed', 'on_order_completed');
add_action('woocommerce_payment_complete', 'auto_complete_gps_orders');
add_action('woocommerce_order_status_changed', 'track_order_status_change');

// User Account
add_action('user_register', 'link_guest_orders_on_register');
add_action('wp_login', 'link_guest_orders_on_login');

// Admin
add_action('admin_menu', 'add_diagnostic_menu');
add_action('admin_init', 'handle_test_email');
add_action('add_meta_boxes', 'add_order_metabox');

// Custom Events
do_action('gps_ticket_created', $ticket_id, $order_id);
do_action('gps_enrollment_created', $enrollment_id, $user_id);
do_action('gps_attendance_recorded', $attendance_id, $user_id);
do_action('gps_credits_awarded', $credit_id, $user_id);
```

### Filters
```php
add_filter('woocommerce_account_menu_items', 'add_account_menu_items');
```

---

## AJAX Endpoints

### Frontend
- `gps_get_calendar_events` - Fetch events for calendar
- `gps_add_to_cart` - Add ticket to cart without page reload
- `gps_get_ticket_availability` - Check ticket stock

### Admin
- `gps_check_in_attendee` - Process QR scan check-in
- `gps_search_attendees` - Search for attendees
- `gps_generate_certificate` - Generate PDF certificate
- `gps_send_certificate` - Email certificate to user
- `gps_save_seminar_session` - Save session data
- `gps_delete_seminar_session` - Delete session

---

## REST API Endpoints

Base URL: `/wp-json/gps/v1/`

- `GET /events` - List events
- `GET /events/{id}` - Get event details
- `GET /tickets/{event_id}` - Get ticket types for event
- `GET /user/credits` - Get current user's CE credits
- `GET /user/enrollments` - Get current user's enrollments

---

## Configuration Constants

```php
// Admin notification emails
const ADMIN_NOTIFICATION_EMAILS = [
    'info@gpsdentaltraining.com',
    'juliocastro@thewebminds.agency'
];

// Seminar settings
const SEMINAR_SESSIONS_COUNT = 10;
const SEMINAR_CREDITS_PER_SESSION = 2;
const SEMINAR_PRICE = 750.00;

// Calendar colors
const COURSE_COLOR = '#0B52AC';
const SEMINAR_COLOR = '#DDC89D';
```

---

## Development Environment

### Local Development
- **IDE:** Any (VSCode, PHPStorm recommended)
- **Local Server:** LocalWP, MAMP, XAMPP, or Docker
- **WordPress:** Latest version
- **WooCommerce:** Latest version
- **Elementor:** Pro recommended for full widget support

### Dependencies Installation
```bash
cd gps-courses
composer install
```

### File Permissions
```bash
chmod -R 755 gps-courses/
chmod -R 777 gps-courses/vendor/
```

---

## Known Issues & Solutions

### 1. Guest Orders Not Visible in User Accounts
**Problem:** Orders placed as guest don't show in My Account after user creates account.
**Solution:** Use the Orders Diagnostic tool (GPS Courses > Orders Diagnostic) to link guest orders or use "Fix All Guest Tickets/Enrollments" button.

### 2. Special Characters in Schedule Topics
**Problem:** Characters like curly quotes and em dashes encoded as `u201c`, `u201d`.
**Solution:** Fixed by adding `JSON_UNESCAPED_UNICODE` flag to `wp_json_encode()` in class-schedules.php.

### 3. Orders Stuck on Processing
**Problem:** Stripe payments received but orders stay on "processing".
**Solution:** Added `woocommerce_payment_complete` hook to auto-complete GPS product orders.

### 4. Calendar Not Showing Seminars
**Problem:** Calendar only showed courses, not monthly seminars.
**Solution:** Modified class-api.php to query `wp_gps_seminar_sessions` table and added event type filtering.

---

## Testing Checklist

### Order Flow
- [ ] Purchase ticket as logged-in user
- [ ] Purchase ticket as guest
- [ ] Verify ticket email received
- [ ] Verify QR code generated
- [ ] Check My Account > My Courses
- [ ] Check My Account > My Tickets

### Check-in Flow
- [ ] Scan QR code
- [ ] Manual check-in
- [ ] Search check-in
- [ ] Verify attendance recorded
- [ ] Verify CE credits awarded

### Seminar Flow
- [ ] Register for seminar
- [ ] Check-in to session
- [ ] Verify session count updated
- [ ] Test makeup session
- [ ] Generate bi-annual certificate

### Admin Tools
- [ ] Orders Diagnostic page
- [ ] Test email sending
- [ ] Link guest orders
- [ ] Generate certificates
- [ ] View reports

---

## Deployment

### Files to Upload
When deploying updates, upload the entire `gps-courses` folder to:
```
/wp-content/plugins/gps-courses/
```

### Post-Deployment
1. Clear any caching plugins
2. Regenerate Elementor CSS (Elementor > Tools > Regenerate CSS)
3. Flush rewrite rules (Settings > Permalinks > Save)

---

## Support & Contact

**Developer:** WebMinds Agency
**Email:** juliocastro@thewebminds.agency
**Client:** GPS Dental Training
**Website:** https://gpsdentaltraining.com

---

## Changelog

### Version 1.0.2
- Added guest order linking functionality
- Added Orders Diagnostic tool
- Fixed special character encoding in schedules
- Added monthly seminars to calendar
- Added email notifications for order status changes
- Fixed auto-complete for GPS product orders

### Version 1.0.1
- Initial WooCommerce integration
- Basic ticketing system
- QR code generation

### Version 1.0.0
- Initial release
- Event management
- CE credits tracking
- Basic attendance

---

## License

This plugin is proprietary software developed for GPS Dental Training. All rights reserved.
