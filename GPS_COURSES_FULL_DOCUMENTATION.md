# GPS Courses - Complete Plugin Documentation

## Executive Summary

**Plugin Name:** GPS Courses
**Version:** 1.0.2
**Author:** WebMinds (Julio Castro)
**Client:** GPS Dental Training (https://gpsdentaltraining.com)
**Text Domain:** gps-courses
**License:** Proprietary

GPS Courses is a comprehensive WordPress plugin for managing dental training events, courses, monthly seminars, CE (Continuing Education) credits, ticketing, attendance tracking, waitlist management, and certificate generation. Fully integrated with WooCommerce for payment processing and Elementor for frontend display.

---

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Directory Structure](#directory-structure)
3. [Database Schema](#database-schema)
4. [Custom Post Types](#custom-post-types)
5. [Core Features](#core-features)
   - [Ticketing System](#1-ticketing-system)
   - [Manual Sold Out & Waitlist](#2-manual-sold-out--waitlist)
   - [Event Calendar](#3-event-calendar)
   - [CE Credits Management](#4-ce-credits-management)
   - [Attendance & Check-in](#5-attendance--check-in)
   - [Monthly Seminars Module](#6-monthly-seminars-module)
   - [Certificate Generation](#7-certificate-generation)
   - [Email System](#8-email-system)
   - [WooCommerce Integration](#9-woocommerce-integration)
   - [My Account Integration](#10-my-account-integration)
6. [REST API Endpoints](#rest-api-endpoints)
7. [AJAX Endpoints](#ajax-endpoints)
8. [WordPress Hooks](#wordpress-hooks)
9. [Elementor Widgets](#elementor-widgets)
10. [Configuration Constants](#configuration-constants)
11. [Business Logic Details](#business-logic-details)

---

## System Requirements

| Component | Requirement |
|-----------|-------------|
| WordPress | 5.8+ |
| PHP | 7.4+ (8.0+ recommended) |
| WooCommerce | 5.0+ |
| Elementor | 3.0+ (for widgets) |
| MySQL | 5.7+ or MariaDB 10.3+ |
| Server | Apache/Nginx with mod_rewrite |
| SSL | Required for payment processing |

---

## Directory Structure

```
gps-courses/
├── gps-courses.php              # Main plugin file (entry point)
├── composer.json                # Composer dependencies
├── CLAUDE.md                    # Claude AI instructions
├── AI_ASSISTANT_INTEGRATION.md  # AI Assistant integration docs
├── GPS_COURSES_FULL_DOCUMENTATION.md  # This file
│
├── includes/                    # Core PHP classes (34 files)
│   ├── class-plugin.php         # Main plugin class, autoloader
│   ├── class-activator.php      # Database tables creation
│   ├── class-posttypes.php      # Custom post types registration
│   ├── class-events.php         # Event management
│   ├── class-schedules.php      # Event schedules (multi-day/session)
│   ├── class-tickets.php        # Ticket types, pricing, sold out logic
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
│   ├── class-waitlist.php       # Enhanced event waitlist
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
│   ├── css/                     # Stylesheets (14 files)
│   │   ├── admin.css
│   │   ├── admin-seminars.css
│   │   ├── admin-attendance.css
│   │   ├── admin-certificates.css
│   │   ├── admin-settings.css
│   │   ├── admin-reports.css
│   │   ├── admin-waitlist.css
│   │   ├── schedule-admin.css
│   │   ├── frontend.css
│   │   ├── calendar.css
│   │   ├── elementor-widgets.css
│   │   ├── add-to-calendar.css
│   │   └── share-course.css
│   │
│   └── js/                      # JavaScript files (17 files)
│       ├── admin-seminars.js
│       ├── admin-attendance.js
│       ├── admin-certificates.js
│       ├── admin-seminar-certificates.js
│       ├── admin-settings.js
│       ├── admin-reports.js
│       ├── admin-email-settings.js
│       ├── admin-waitlist.js
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
Stores individual ticket instances generated when orders are completed.

```sql
CREATE TABLE wp_gps_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_code VARCHAR(50) UNIQUE NOT NULL,      -- Unique ticket identifier
    ticket_type_id BIGINT UNSIGNED NOT NULL,      -- References gps_ticket CPT
    event_id BIGINT UNSIGNED NOT NULL,            -- References gps_event CPT
    user_id BIGINT UNSIGNED DEFAULT 0,            -- WP user ID (0 for guests)
    order_id BIGINT UNSIGNED NOT NULL,            -- WooCommerce order ID
    order_item_id BIGINT UNSIGNED NOT NULL,       -- WooCommerce order item ID
    attendee_name VARCHAR(255) NOT NULL,
    attendee_email VARCHAR(255) NOT NULL,
    qr_code_path VARCHAR(500),                    -- Path to QR code image
    status ENUM('valid', 'used', 'cancelled') DEFAULT 'valid',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event (event_id),
    INDEX idx_user (user_id),
    INDEX idx_order (order_id)
);
```

#### 2. wp_gps_enrollments
Tracks user enrollments in events/sessions.

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
Records check-in events for course attendance.

```sql
CREATE TABLE wp_gps_attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    checked_in_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    checked_in_by BIGINT UNSIGNED,                -- Admin who checked them in
    check_in_method ENUM('qr_scan', 'manual', 'search') DEFAULT 'manual',
    notes TEXT,
    INDEX idx_ticket (ticket_id),
    INDEX idx_event (event_id),
    INDEX idx_user (user_id)
);
```

#### 4. wp_gps_ce_ledger
Immutable ledger for CE credit transactions.

```sql
CREATE TABLE wp_gps_ce_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED,                     -- Can be NULL for manual adjustments
    credits DECIMAL(5,2) NOT NULL,
    source VARCHAR(100),                          -- 'course_attendance', 'seminar_session', 'manual'
    transaction_type ENUM('earned', 'adjustment', 'revoked') DEFAULT 'earned',
    notes TEXT,
    awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_event (event_id)
);
```

#### 5. wp_gps_certificates
Tracks generated certificates.

```sql
CREATE TABLE wp_gps_certificates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED UNIQUE,             -- One certificate per ticket
    user_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    certificate_path VARCHAR(500),
    certificate_url VARCHAR(500),
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    certificate_sent_at DATETIME,                 -- When emailed to user
    INDEX idx_user (user_id),
    INDEX idx_event (event_id)
);
```

#### 6. wp_gps_waitlist
Enhanced waitlist for sold-out ticket types.

```sql
CREATE TABLE wp_gps_waitlist (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED DEFAULT NULL,         -- WP user ID if logged in
    email VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(50),
    ticket_type_id BIGINT UNSIGNED NOT NULL,      -- References gps_ticket CPT
    event_id BIGINT UNSIGNED NOT NULL,
    position INT DEFAULT 1,                        -- Position in queue
    status ENUM('waiting', 'notified', 'converted', 'expired', 'removed') DEFAULT 'waiting',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notified_at DATETIME,                         -- When spot available email sent
    expires_at DATETIME,                          -- 48h after notification
    notes TEXT,
    INDEX idx_email (email),
    INDEX idx_ticket_type (ticket_type_id),
    INDEX idx_event (event_id),
    INDEX idx_status (status),
    INDEX idx_position (position)
);
```

#### 7. wp_gps_seminar_registrations
Tracks 10-session seminar enrollments.

```sql
CREATE TABLE wp_gps_seminar_registrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    seminar_id BIGINT UNSIGNED NOT NULL,          -- References gps_seminar CPT
    order_id BIGINT UNSIGNED,
    registration_date DATE,
    start_session_date DATE,                      -- When user started their 10 sessions
    sessions_completed INT DEFAULT 0,
    sessions_remaining INT DEFAULT 10,
    makeup_used TINYINT(1) DEFAULT 0,             -- Only 1 makeup allowed per year
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
Individual seminar sessions (10 per year).

```sql
CREATE TABLE wp_gps_seminar_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seminar_id BIGINT UNSIGNED NOT NULL,
    session_number INT NOT NULL,                  -- 1-10
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
Tracks attendance for each seminar session.

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
    is_makeup TINYINT(1) DEFAULT 0,               -- Marked if this is a makeup session
    credits_awarded DECIMAL(5,2) DEFAULT 2.00,    -- 2 CE per session
    notes TEXT,
    INDEX idx_registration (registration_id),
    INDEX idx_session (session_id),
    INDEX idx_user (user_id),
    INDEX idx_seminar (seminar_id)
);
```

#### 10. wp_gps_seminar_waitlist
Waitlist for seminar programs.

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
Events and courses.

| Setting | Value |
|---------|-------|
| Public | Yes |
| Show UI | Yes |
| Supports | title, editor, excerpt, thumbnail |
| Show in REST | Yes |

**Meta Fields:**
| Meta Key | Type | Description |
|----------|------|-------------|
| `_gps_start_date` | datetime | Event start date/time |
| `_gps_end_date` | datetime | Event end date/time |
| `_gps_venue` | string | Venue name |
| `_gps_address` | string | Full address |
| `_gps_location` | string | Legacy location field |
| `_gps_ce_credits` | integer | CE credits awarded |
| `_gps_capacity` | integer | Maximum capacity |
| `_gps_registration_deadline` | datetime | Registration cutoff |
| `_gps_schedule_topics` | JSON | Schedule topics array |

### gps_seminar
Monthly seminar programs (10-session cycles).

| Setting | Value |
|---------|-------|
| Public | Yes |
| Show UI | Yes |
| Supports | title, editor, thumbnail |

**Meta Fields:**
| Meta Key | Type | Description |
|----------|------|-------------|
| `_gps_seminar_year` | integer | Program year (e.g., 2025) |
| `_gps_seminar_price` | decimal | Enrollment price ($750) |
| `_gps_wc_product_id` | integer | Linked WooCommerce product |

### gps_speaker
Speaker/instructor profiles.

| Setting | Value |
|---------|-------|
| Public | Yes |
| Show UI | Yes |
| Supports | title, editor, thumbnail |

**Meta Fields:**
| Meta Key | Type | Description |
|----------|------|-------------|
| `_gps_speaker_title` | string | Professional title (DDS, DMD, etc.) |
| `_gps_speaker_bio` | text | Biography |
| `_gps_speaker_social` | JSON | Social media links |

### gps_ticket
Ticket types for events (not public, admin-only).

| Setting | Value |
|---------|-------|
| Public | No |
| Show UI | Yes |
| Supports | title |

**Meta Fields:**
| Meta Key | Type | Description |
|----------|------|-------------|
| `_gps_event_id` | integer | Parent event ID |
| `_gps_wc_product_id` | integer | Linked WooCommerce product |
| `_gps_ticket_type` | string | early_bird, general, vip, group |
| `_gps_ticket_price` | decimal | Ticket price |
| `_gps_ticket_quantity` | integer | Total available (empty = unlimited) |
| `_gps_ticket_start_date` | datetime | Sale start date |
| `_gps_ticket_end_date` | datetime | Sale end date |
| `_gps_ticket_status` | string | active, inactive |
| `_gps_ticket_features` | text | Features list (one per line) |
| `_gps_ticket_internal_label` | string | Admin-only label |
| `_gps_manual_sold_out` | boolean | Manual sold out override |

---

## Core Features

### 1. Ticketing System

**Functionality:**
- Multiple ticket types per event (Early Bird, VIP, General, Group)
- Time-based pricing (automatic activation based on sale dates)
- QR code generation for each ticket
- Email delivery with embedded QR codes
- Stock management synced with WooCommerce
- Internal labels for admin organization

**Stock Calculation:**
```php
// From class-tickets.php
public static function get_ticket_stock($ticket_id) {
    // Count sold tickets from COMPLETED orders only (HPOS compatible)
    // Returns: ['total' => int, 'sold' => int, 'available' => int, 'unlimited' => bool]
}
```

### 2. Manual Sold Out & Waitlist

**Manual Sold Out Feature:**
- Admin can mark any ticket as "Sold Out" regardless of actual stock
- Uses `_gps_manual_sold_out` meta field
- When enabled, shows waitlist form instead of add to cart
- Visual indicator in admin ticket list

**Waitlist System:**
- Position-based queue management
- Automatic email confirmation on signup
- 48-hour window when spot becomes available
- Automatic expiration and notification of next person
- Cron job: `gps_process_expired_ticket_waitlist` (hourly)

**Waitlist Statuses:**
| Status | Description |
|--------|-------------|
| `waiting` | In queue, waiting for spot |
| `notified` | Spot available, has 48h to purchase |
| `converted` | Successfully purchased |
| `expired` | Didn't purchase within 48h |
| `removed` | Manually removed by admin |

**Email Templates (using plugin branding):**
- Waitlist Confirmation: Confirms addition to waitlist
- Spot Available: 48h urgency notification with purchase CTA

### 3. Event Calendar

**Features:**
- Interactive month/week/list views
- Filter by event type (courses, seminars)
- Sidebar with event details on click
- Visual distinction by colors:
  - Courses: #0B52AC (blue)
  - Seminars: #DDC89D (gold)
- AJAX-powered navigation
- 5-minute caching for performance

### 4. CE Credits Management

**Features:**
- Automatic credit award on attendance check-in
- Immutable ledger for audit trail
- Transaction types: earned, adjustment, revoked
- Per-user credit history
- Bulk credit operations for admins
- CSV export for reports

**Credit Sources:**
| Source | Description |
|--------|-------------|
| `course_attendance` | Check-in at course event |
| `seminar_session` | Check-in at seminar session (2 CE each) |
| `manual` | Admin manual adjustment |

### 5. Attendance & Check-in

**Check-in Methods:**
| Method | Description |
|--------|-------------|
| `qr_scan` | Camera-based QR code scanning |
| `manual` | Admin enters ticket code manually |
| `search` | Search by name/email |

**Process:**
1. Admin opens attendance page for event
2. Scans QR or searches for attendee
3. System validates ticket (status = 'valid')
4. Creates attendance record
5. Updates ticket status to 'used'
6. Auto-awards CE credits to user's ledger

### 6. Monthly Seminars Module

**Program Structure:**
- 10 sessions per calendar year
- $750 one-time enrollment fee
- 2 CE credits per session (20 total)
- Only 1 makeup session allowed per year
- Bi-annual certificates (June 30, December 31)

**Registration Flow:**
1. User purchases seminar via WooCommerce
2. System creates `seminar_registration` record
3. QR code generated for all sessions
4. User attends sessions, gets checked in
5. After each session: `sessions_completed++`, `sessions_remaining--`
6. After 10 sessions or at bi-annual dates: certificate generated

**Makeup Rules:**
- `makeup_used` flag tracks if makeup was used
- Only 1 makeup per calendar year
- `is_makeup` flag on attendance record

### 7. Certificate Generation

**Features:**
- PDF generation using TCPDF library
- Customizable design and branding
- Public validation URL for verification
- Bulk generation capability
- Email delivery with PDF attachment
- Tracking: `generated_at`, `certificate_sent_at`

**Certificate Data:**
- Attendee name
- Event/course title
- Date of completion
- CE credits earned
- Unique certificate code for validation

### 8. Email System

**Configuration Options:**
| Setting | Description |
|---------|-------------|
| `gps_email_logo` | Header logo URL |
| `gps_email_from_name` | Sender name |
| `gps_email_from_email` | Sender email |
| `gps_email_header_bg_color` | Header background (#0B52AC) |
| `gps_email_header_text_color` | Header text (#ffffff) |
| `gps_email_body_bg_color` | Body background (#f5f5f5) |
| `gps_email_body_text_color` | Body text (#333333) |
| `gps_email_button_bg_color` | CTA button background |
| `gps_email_button_text_color` | CTA button text |
| `gps_email_footer_text` | Footer message |

**Email Types:**
- Ticket confirmation (with QR code)
- Waitlist confirmation
- Spot available notification (48h urgency)
- Certificate delivery
- Order status notifications
- Session reminders

### 9. WooCommerce Integration

**Features:**
- Product-based ticket sales
- Auto-complete for GPS products on payment
- Order status tracking with notifications
- Guest order linking to user accounts
- Order diagnostic tools

**Hooks Used:**
```php
// Order processing
add_action('woocommerce_order_status_completed', 'on_order_completed');
add_action('woocommerce_payment_complete', 'auto_complete_gps_orders');
add_action('woocommerce_order_status_changed', 'track_order_status_change');

// Waitlist triggers
add_action('woocommerce_order_status_cancelled', 'on_order_cancelled');
add_action('woocommerce_order_status_refunded', 'on_order_refunded');

// Guest order linking
add_action('user_register', 'link_guest_orders_on_register');
add_action('wp_login', 'link_guest_orders_on_login');
```

### 10. My Account Integration

**Custom Tabs Added:**
| Tab | Endpoint | Description |
|-----|----------|-------------|
| My Courses | `/my-courses/` | Enrolled events list |
| Monthly Seminars | `/seminars/` | Seminar progress |
| CE Credits | `/ce-credits/` | Credit history |
| My Tickets | `/my-tickets/` | Purchased tickets |
| Attendance History | `/attendance/` | Check-in records |

---

## REST API Endpoints

Base URL: `/wp-json/gps-courses/v1/`

### Public Endpoints (No Auth Required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/events` | List all published events |
| GET | `/events/{id}` | Get single event details |
| GET | `/events/calendar` | Get events for calendar display |
| GET | `/availability/event/{event_id}` | Check event availability (AI Assistant) |
| GET | `/availability/ticket/{ticket_id}` | Check specific ticket availability |
| POST | `/waitlist/add` | Add user to waitlist |
| GET | `/waitlist/check?email=&event_id=` | Check waitlist status |

### Protected Endpoints (Requires Authentication)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tickets` | Get user's tickets |
| GET | `/tickets/{id}` | Get single ticket |
| POST | `/tickets/verify` | Verify QR code |
| GET | `/credits/user/{user_id}` | Get user CE credits |
| GET | `/credits/ledger` | Get CE ledger entries |
| GET | `/attendance/event/{event_id}` | Get event attendance (admin) |

### API Response Examples

**Event Availability (for AI Assistant):**
```json
{
  "success": true,
  "event": {
    "id": 123,
    "title": "Implant Fundamentals Course",
    "url": "https://gpsdentaltraining.com/event/implant-fundamentals/",
    "start_date": "2025-03-15",
    "start_date_formatted": "March 15, 2025"
  },
  "availability": {
    "is_available": false,
    "is_sold_out": true,
    "has_active_tickets": true,
    "reason": "sold_out"
  },
  "tickets": [
    {
      "id": 456,
      "name": "General Admission",
      "price": 1800,
      "is_sold_out": true,
      "is_manual_sold_out": true,
      "stock": {
        "total": 12,
        "sold": 7,
        "available": 5,
        "unlimited": false
      }
    }
  ],
  "waitlist_enabled": true
}
```

---

## AJAX Endpoints

### Frontend

| Action | Description |
|--------|-------------|
| `gps_get_calendar_events` | Fetch events for calendar |
| `gps_add_tickets_to_cart` | Add ticket to cart (AJAX) |
| `gps_join_waitlist` | Join waitlist from frontend |

### Admin

| Action | Description |
|--------|-------------|
| `gps_check_in_attendee` | Process check-in |
| `gps_search_attendees` | Search for attendees |
| `gps_generate_certificate` | Generate PDF certificate |
| `gps_send_certificate` | Email certificate |
| `gps_save_seminar_session` | Save session data |
| `gps_delete_seminar_session` | Delete session |
| `gps_admin_remove_waitlist` | Remove from waitlist |
| `gps_admin_notify_waitlist` | Send notification |
| `gps_admin_mark_converted` | Mark as converted |
| `gps_waitlist_bulk_action` | Bulk waitlist operations |
| `gps_waitlist_test_email` | Send test email |

---

## WordPress Hooks

### Actions Fired by Plugin

```php
// Custom events for extensibility
do_action('gps_ticket_created', $ticket_id, $order_id);
do_action('gps_enrollment_created', $enrollment_id, $user_id);
do_action('gps_attendance_recorded', $attendance_id, $user_id);
do_action('gps_credits_awarded', $credit_id, $user_id);
```

### Filters Used

```php
add_filter('woocommerce_account_menu_items', 'add_account_menu_items');
```

---

## Elementor Widgets

| Widget | Description |
|--------|-------------|
| Event Calendar | Interactive calendar with month/week/list views |
| Event Grid | Grid layout of events with filtering |
| Event List | List view of events |
| Event Slider | Carousel of events |
| Event Dates Display | Display event dates/times |
| Single Event | Complete single event display |
| Course Description | Event description with formatting |
| Course Objectives | Learning objectives list |
| Schedule Display | Multi-day schedule with tabs/accordion |
| Seminar Registration | Seminar enrollment form |
| Seminar Schedule | Session schedule display |
| Seminar Progress | User's seminar progress |
| CE Credits Display | User's CE credits total/history |
| Speaker Grid | Grid of speaker profiles |
| Google Maps | Location map |
| Ticket Selector | AJAX ticket selection and cart |
| Countdown Timer | Countdown to event start |
| Add to Calendar | iCal/Google/Outlook buttons |
| Share Course | Social sharing buttons |

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

## Business Logic Details

### Ticket Sold Out Logic

```php
// A ticket is sold out if:
// 1. Manual override is enabled (_gps_manual_sold_out = '1')
// 2. OR actual stock is depleted (available = 0 and not unlimited)

public static function is_sold_out($ticket_id) {
    // Check manual override first
    $manual_sold_out = get_post_meta($ticket_id, '_gps_manual_sold_out', true);
    if ($manual_sold_out) {
        return true;
    }

    // Check actual stock
    $stock = self::get_ticket_stock($ticket_id);
    return !$stock['unlimited'] && $stock['available'] == 0;
}
```

### Waitlist Notification Flow

```
1. Order cancelled/refunded
2. Check if ticket is no longer sold out
3. If available → notify_next_on_waitlist()
4. Update entry: status='notified', notified_at=now(), expires_at=+48h
5. Send "Spot Available" email with 48h urgency
6. Cron job (hourly) checks expired notifications
7. If expired → mark as 'expired', notify next person
```

### Seminar Certificate Generation

```
Bi-annual trigger dates: June 30, December 31

1. Query users with sessions_completed >= required threshold
2. For each eligible user:
   a. Generate PDF certificate
   b. Store in wp_gps_certificates
   c. Send email with PDF attachment
   d. Update certificate_sent_at
```

### Guest Order Linking

```
Trigger: user_register OR wp_login

1. Get user email
2. Query wp_gps_tickets WHERE attendee_email = $email AND user_id = 0
3. Update user_id to new user ID
4. Query wp_gps_enrollments similarly
5. Link all matching records
```

---

## Known Issues & Solutions

| Issue | Solution |
|-------|----------|
| Guest orders not visible in user accounts | Use Orders Diagnostic tool to link |
| Special characters in schedule topics | Fixed with JSON_UNESCAPED_UNICODE flag |
| Orders stuck on processing | Auto-complete hook on payment_complete |
| Calendar not showing seminars | Query wp_gps_seminar_sessions table |

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

### Waitlist Flow
- [ ] Mark ticket as manually sold out
- [ ] Join waitlist from frontend
- [ ] Receive confirmation email
- [ ] Test spot available notification
- [ ] Test 48h expiration

### Admin Tools
- [ ] Orders Diagnostic page
- [ ] Test email sending
- [ ] Link guest orders
- [ ] Generate certificates
- [ ] View reports

---

## Changelog

### Version 1.0.2 (Current)
- Added manual sold out toggle for tickets
- Enhanced waitlist with positions, expiration, admin management
- Added waitlist test email functionality
- Added guest order linking functionality
- Added Orders Diagnostic tool
- Fixed special character encoding in schedules
- Added monthly seminars to calendar
- Added email notifications for order status changes
- Fixed auto-complete for GPS product orders
- Added AI Assistant integration (REST API)

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

**Developer:** WebMinds Agency
**Email:** juliocastro@thewebminds.agency
**Client:** GPS Dental Training
**Website:** https://gpsdentaltraining.com
