# GPS Courses - WordPress Event Management Plugin

A comprehensive WordPress plugin for managing dental training courses, seminars, and CE credits with WooCommerce integration, QR code ticketing, and attendance tracking.

## Features

### Core Features
- **Event Management** - Create and manage courses/seminars with full details
- **WooCommerce Integration** - Sell tickets through WooCommerce products
- **QR Code Ticketing** - Unique QR codes for each ticket with automatic email delivery
- **Attendance Tracking** - QR scanner for check-ins with multiple modes (camera, manual, search)
- **CE Credits System** - Award continuing education credits upon attendance
- **Speaker Management** - Manage speakers with bios, photos, and social links
- **Session Management** - Organize event schedules with day-by-day agendas

### Frontend Features
- **10 Elementor Widgets** - Complete Elementor integration
- **8 Shortcodes** - Display events, tickets, CE credits, and more
- **WooCommerce My Account Tabs** - My Courses, CE Credits, Tickets, Attendance History
- **Responsive Design** - Mobile-first, fully responsive layouts
- **Interactive Calendar** - Month, week, and list views
- **Countdown Timer** - Live countdown to event start
- **AJAX Ticket Selector** - Add tickets to cart without page reload

### Admin Features
- **Reporting Dashboard** - Statistics, charts, and analytics
- **CSV Exports** - Export attendees, enrollments, CE credits
- **Email Blast** - Send bulk emails to attendees
- **Bulk Operations** - Award credits, resend tickets, mark attendance
- **QR Scanner Interface** - Three check-in methods with real-time updates
- **Comprehensive Settings** - Configure all plugin aspects

## Installation

1. Upload the `gps-courses` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Run `composer install` in the plugin directory to install dependencies
4. Configure settings at **GPS Courses > Settings**

### Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- WooCommerce 5.0 or higher
- Elementor 3.0 or higher (optional, for widgets)
- Composer (for dependencies)

### Dependencies

The plugin uses Composer for dependency management. Required libraries:

```json
{
    "endroid/qr-code": "^4.8",
    "tecnickcom/tcpdf": "^6.6",
    "phpmailer/phpmailer": "^6.8"
}
```

## Configuration

### Initial Setup

1. **General Settings** (`GPS Courses > Settings > General`)
   - Add Google Maps API Key
   - Set ticket code prefix
   - Configure company information

2. **Email Settings** (`GPS Courses > Settings > Email`)
   - Set from name and email
   - Upload header logo
   - Configure primary color
   - Add footer text

3. **Ticket Settings** (`GPS Courses > Settings > Tickets`)
   - Upload ticket logo
   - Configure QR code size
   - Set header/footer text

4. **WooCommerce Settings** (`GPS Courses > Settings > WooCommerce`)
   - Enable product sync
   - Select default category
   - Add Stripe API keys

## Custom Post Types

### Events (`gps_event`)
Main post type for courses and seminars.

**Meta Fields:**
- `_gps_start_date` - Event start date/time
- `_gps_end_date` - Event end date/time
- `_gps_venue` - Venue name
- `_gps_address` - Full address
- `_gps_ce_credits` - CE credits awarded
- `_gps_capacity` - Maximum attendees
- `_gps_registration_deadline` - Last date to register

### Speakers (`gps_speaker`)
Instructors and presenters.

**Meta Fields:**
- `_gps_designation` - Title/position
- `_gps_company` - Company/organization
- `_gps_email` - Contact email
- `_gps_phone` - Phone number
- `_gps_social_twitter` - Twitter handle
- `_gps_social_linkedin` - LinkedIn URL
- `_gps_social_facebook` - Facebook URL

### Sessions (`gps_session`)
Individual sessions within events.

**Meta Fields:**
- `_gps_event_id` - Parent event
- `_gps_start_time` - Session start
- `_gps_end_time` - Session end
- `_gps_speaker_ids` - Array of speaker IDs
- `_gps_day` - Day number
- `_gps_track` - Track name

### Tickets (`gps_ticket`)
Ticket types with pricing and scheduling.

**Meta Fields:**
- `_gps_event_id` - Event ID
- `_gps_product_id` - WooCommerce product ID
- `_gps_ticket_type` - Type (early_bird, vip, general, group)
- `_gps_price` - Ticket price
- `_gps_quantity` - Available quantity
- `_gps_start_date` - Sale start date
- `_gps_end_date` - Sale end date
- `_gps_ticket_status` - Status (active/inactive)

## Database Tables

### `wp_gps_tickets`
Individual ticket records for purchases.

**Columns:**
- `id` - Ticket ID
- `ticket_code` - Unique code (GPST-12345-67890-1234-ABC123)
- `ticket_type_id` - Ticket type post ID
- `event_id` - Event post ID
- `user_id` - Buyer user ID
- `order_id` - WooCommerce order ID
- `attendee_name` - Name on ticket
- `attendee_email` - Email address
- `qr_code_path` - Path to QR code image
- `status` - Status (valid/used/cancelled)
- `created_at` - Purchase timestamp

### `wp_gps_attendance`
Attendance/check-in records.

**Columns:**
- `id` - Record ID
- `ticket_id` - Ticket ID
- `event_id` - Event ID
- `user_id` - User ID
- `checked_in_at` - Check-in timestamp
- `checked_in_by` - Admin user ID who checked in
- `check_in_method` - Method (qr_code/manual/search)
- `notes` - Optional notes

### `wp_gps_enrollments`
User enrollments in events.

**Columns:**
- `id` - Enrollment ID
- `event_id` - Event ID
- `user_id` - User ID
- `order_id` - WooCommerce order ID
- `status` - Status (enrolled/cancelled/completed)
- `enrolled_at` - Enrollment timestamp

### `wp_gps_ce_ledger`
CE credits transaction ledger.

**Columns:**
- `id` - Transaction ID
- `user_id` - User ID
- `event_id` - Event ID (optional)
- `credits` - Credit amount
- `transaction_type` - Type (attendance/manual/adjustment)
- `notes` - Transaction notes
- `awarded_at` - Award timestamp

## Shortcodes

### `[gps_course_credits_plain]`
Display CE credits for an event.

**Parameters:**
- `id` - Event ID (defaults to current post)

**Example:**
```php
[gps_course_credits_plain id="123"]
```

### `[gps_ce_credits_profile]`
Display complete CE credits profile with ledger.

**Example:**
```php
[gps_ce_credits_profile]
```

### `[gps_ce_credits_total]`
Display total CE credits for user.

**Parameters:**
- `user_id` - User ID (defaults to current user)
- `label` - Label text
- `show_label` - Show label (yes/no)

**Example:**
```php
[gps_ce_credits_total label="Total Credits Earned"]
```

### `[gps_events]`
Display list of events.

**Parameters:**
- `posts_per_page` - Number of events (default: 6)
- `layout` - Layout style (grid/list)
- `columns` - Grid columns (2/3/4/6)
- `order` - Sort order (ASC/DESC)
- `orderby` - Sort by (date/title/meta_value)
- `show_past` - Show past events (yes/no)

**Example:**
```php
[gps_events posts_per_page="12" layout="grid" columns="3" show_past="no"]
```

### `[gps_event_calendar]`
Display interactive calendar.

**Parameters:**
- `view` - Initial view (month/week/list)

**Example:**
```php
[gps_event_calendar view="month"]
```

### `[gps_my_tickets]`
Display user's tickets (requires login).

**Example:**
```php
[gps_my_tickets]
```

### `[gps_event_countdown]`
Display countdown timer to event.

**Parameters:**
- `id` - Event ID (defaults to current post)
- `message` - Message text

**Example:**
```php
[gps_event_countdown id="123" message="Event starts in:"]
```

### `[gps_speakers]`
Display speakers grid.

**Parameters:**
- `posts_per_page` - Number of speakers (default: 6)
- `columns` - Grid columns (2/3/4/6)
- `orderby` - Sort by (title/date/menu_order)
- `order` - Sort order (ASC/DESC)

**Example:**
```php
[gps_speakers posts_per_page="9" columns="3" orderby="title"]
```

## Elementor Widgets

### Event Grid
Display events in grid layout with customizable columns and styling.

### Event List
Display events in list format with image position control.

### Event Slider
Swiper.js-powered event slider with autoplay and navigation.

### Event Calendar
Interactive calendar with month/week/list views and AJAX loading.

### Single Event
Complete event display with speakers, agenda, and tickets.

### Speaker Grid
Display speakers with photos, bios, and social links.

### Google Maps
Google Maps integration with geocoding and custom styles.

### Ticket Selector
AJAX ticket selection with add to cart functionality.

### Countdown Timer
Live countdown to event start with automatic updates.

### CE Credits Display
Display CE credits with progress bars and ledger.

## REST API

Base URL: `/wp-json/gps-courses/v1`

### Events

**GET** `/events`
Get list of events.

**Parameters:**
- `per_page` - Results per page (default: 10)
- `page` - Page number (default: 1)
- `orderby` - Sort by (date/title)
- `order` - Sort order (ASC/DESC)
- `upcoming` - Show only upcoming (true/false)

**GET** `/events/{id}`
Get single event by ID.

**GET** `/events/calendar`
Get events for calendar display.

**Parameters:**
- `start` - Start date (YYYY-MM-DD)
- `end` - End date (YYYY-MM-DD)

### Tickets

**GET** `/tickets`
Get tickets (requires authentication).

**Parameters:**
- `user_id` - Filter by user
- `event_id` - Filter by event

**GET** `/tickets/{id}`
Get single ticket by ID (requires authentication).

**POST** `/tickets/verify`
Verify ticket QR code (requires authentication).

**Body:**
```json
{
    "qr_data": "base64_encoded_qr_data"
}
```

### CE Credits

**GET** `/credits/user/{user_id}`
Get user's CE credits (requires authentication).

**GET** `/credits/ledger`
Get CE credits ledger (requires authentication).

**Parameters:**
- `user_id` - Filter by user
- `event_id` - Filter by event

### Attendance

**GET** `/attendance/event/{event_id}`
Get event attendance records (requires admin).

## Hooks & Filters

### Actions

```php
// After ticket is created
do_action('gps_ticket_created', $ticket_id, $order_id, $user_id);

// After check-in
do_action('gps_attendance_checked_in', $ticket_id, $user_id, $event_id);

// After CE credits awarded
do_action('gps_credits_awarded', $user_id, $event_id, $credits);

// Before ticket email sent
do_action('gps_before_ticket_email', $ticket_id, $to_email);

// After event enrollment
do_action('gps_enrolled', $enrollment_id, $event_id, $user_id);
```

### Filters

```php
// Modify ticket code format
apply_filters('gps_ticket_code_format', $code, $order_id, $item_id);

// Modify QR code size
apply_filters('gps_qr_code_size', 300);

// Modify email template
apply_filters('gps_email_template', $template, $email_type);

// Modify CE credits calculation
apply_filters('gps_credits_amount', $credits, $event_id, $user_id);

// Modify event query args
apply_filters('gps_event_query_args', $args);
```

## Email Templates

### Ticket Confirmation Email
Sent when order is completed with tickets.

**Template:** `includes/class-emails.php`

**Includes:**
- Order details
- Ticket information with QR code
- Event details
- Venue and directions

### CE Credits Awarded Email
Sent when CE credits are awarded upon check-in.

**Template:** `includes/class-emails.php`

**Includes:**
- Credits amount
- Event name
- Total credits accumulated
- Certificate link (if available)

## Frontend Assets

### JavaScript Files

- `calendar.js` - Event calendar functionality
- `countdown.js` - Countdown timer
- `ticket-selector.js` - AJAX ticket selection
- `admin-attendance.js` - Attendance scanner interface
- `admin-reports.js` - Reports page functionality
- `admin-settings.js` - Settings page functionality

### CSS Files

- `frontend.css` - Public-facing styles
- `elementor-widgets.css` - Elementor widget styles
- `admin-attendance.css` - Scanner interface styles
- `admin-reports.css` - Reports dashboard styles
- `admin-settings.css` - Settings page styles

## Admin Pages

### Dashboard (`admin.php?page=gps-dashboard`)
Overview with quick stats and recent activity.

### Attendance Scanner (`admin.php?page=gps-attendance`)
QR code scanner with three check-in modes:
- Camera scanner (HTML5 QR code reader)
- Manual ticket code entry
- Attendee search and select

### Attendance Report (`admin.php?page=gps-attendance-report`)
Detailed attendance reports per event.

### Reports (`admin.php?page=gps-reports`)
Comprehensive reporting dashboard with:
- Overview statistics
- CSV exports
- Email blast system
- Bulk operations

### Settings (`admin.php?page=gps-settings`)
Plugin configuration with 5 tabs:
- General
- Email
- Tickets
- CE Credits
- WooCommerce

## Development

### File Structure

```
gps-courses/
├── assets/
│   ├── css/
│   │   ├── admin-attendance.css
│   │   ├── admin-reports.css
│   │   ├── admin-settings.css
│   │   ├── elementor-widgets.css
│   │   └── frontend.css
│   └── js/
│       ├── admin-attendance.js
│       ├── admin-reports.js
│       ├── admin-settings.js
│       ├── calendar.js
│       ├── countdown.js
│       └── ticket-selector.js
├── includes/
│   ├── class-activator.php
│   ├── class-api.php
│   ├── class-attendance.php
│   ├── class-credits.php
│   ├── class-elementor.php
│   ├── class-emails.php
│   ├── class-pdf.php
│   ├── class-plugin.php
│   ├── class-posttypes.php
│   ├── class-qrcode.php
│   ├── class-reports.php
│   ├── class-settings.php
│   ├── class-shortcodes.php
│   ├── class-tickets.php
│   ├── class-woocommerce.php
│   └── helpers.php
├── widgets/
│   ├── base-widget.php
│   ├── ce-credits-display.php
│   ├── countdown-timer.php
│   ├── event-calendar.php
│   ├── event-grid.php
│   ├── event-list.php
│   ├── event-slider.php
│   ├── google-maps.php
│   ├── single-event.php
│   ├── speaker-grid.php
│   └── ticket-selector.php
├── composer.json
├── gps-courses.php
└── README.md
```

### Coding Standards

- Follow WordPress Coding Standards
- Use namespace `GPSC\` for all classes
- Prefix functions with `gps_`
- Sanitize all inputs
- Escape all outputs
- Use nonces for security

### Testing

Test the following workflows:

1. **Event Creation**
   - Create event with all details
   - Add speakers and sessions
   - Create ticket types

2. **Purchase Flow**
   - Add tickets to cart
   - Complete checkout
   - Verify ticket email received
   - Check QR code generation

3. **Attendance**
   - Scan QR code at event
   - Verify check-in recorded
   - Confirm CE credits awarded
   - Check credits appear in user profile

4. **Reporting**
   - Export attendees CSV
   - Send email blast
   - Bulk award credits
   - View attendance reports

## Support

For support, please contact GPS Dental Training or submit issues to the plugin repository.

## Changelog

### Version 1.0.0
- Initial release
- Complete event management system
- WooCommerce integration
- QR code ticketing
- Attendance tracking
- CE credits system
- 10 Elementor widgets
- 8 shortcodes
- REST API
- Admin reporting
- Comprehensive settings

## License

This plugin is proprietary software developed for GPS Dental Training.

## Credits

**Developed by:** Claude (Anthropic)
**For:** GPS Dental Training
**Version:** 1.0.0
