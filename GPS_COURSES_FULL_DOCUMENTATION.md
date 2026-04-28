# GPS Courses - Complete Plugin Documentation

> **This document is the canonical reference for the GPS Courses plugin.** It is auto-maintained against the actual codebase and reflects what is shipped today, not legacy state. Update it when the architecture changes.

## Executive Summary

| Field | Value |
|-------|-------|
| Plugin Name | GPS Courses |
| Version | **1.0.5** |
| Author | WebMinds (Julio Castro) |
| Client | GPS Dental Training (https://gpsdentaltraining.com) |
| Text Domain | `gps-courses` |
| License | Proprietary |
| Repo | https://github.com/juliocastrop/gps-courses |

GPS Courses manages dental training events, courses, monthly seminars, CE credits, ticketing, attendance, waitlists, and certificate generation. It is fully integrated with WooCommerce (sales) and Elementor (frontend display).

---

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Architecture Overview](#architecture-overview)
3. [Directory Structure](#directory-structure)
4. [Database Schema](#database-schema)
5. [Custom Post Types](#custom-post-types)
6. [Core Classes (`includes/`)](#core-classes-includes)
7. [Core Features](#core-features)
8. [Individual Session Tickets (v1.0.4)](#individual-session-tickets-v104)
8b. [Promotional QR Codes (v1.0.5)](#promotional-qr-codes-v105)
9. [Settings & Options](#settings--options)
10. [REST API](#rest-api)
11. [AJAX Endpoints](#ajax-endpoints)
12. [WordPress Hooks](#wordpress-hooks)
13. [Cron Jobs](#cron-jobs)
14. [Admin Menu Structure](#admin-menu-structure)
15. [Elementor Widgets](#elementor-widgets)
16. [Business Logic Details](#business-logic-details)
17. [Known Issues & Solutions](#known-issues--solutions)
18. [Changelog](#changelog)

---

## System Requirements

| Component | Requirement |
|-----------|-------------|
| WordPress | 5.8+ |
| PHP | 7.4+ (8.0+ recommended) |
| WooCommerce | 5.0+ |
| Elementor | 3.0+ |
| MySQL | 5.7+ / MariaDB 10.3+ |
| SSL | Required for payments |

---

## Architecture Overview

The plugin separates concerns across discrete modules:

- **Sales** — WooCommerce integration (`class-woocommerce.php`) handles all monetary transactions. GPS-specific products map to ticket types, monthly seminars, or individual seminar sessions.
- **Catalog** — Custom Post Types define the catalog: `gps_event` (courses), `gps_seminar` (10-session programs), `gps_speaker`, `gps_ticket` (ticket type), `gps_session` (individual session).
- **Fulfillment** — On order completion the plugin generates tickets (`class-tickets.php`), enrollments (`class-seminar-registrations.php`), or session tickets (`class-session-tickets.php`).
- **Attendance** — QR-code based check-in via `class-attendance.php` (events) and `class-seminar-attendance.php` (seminar sessions). Both can also process individual session tickets.
- **CE Credits** — Immutable ledger in `wp_gps_ce_ledger` (`class-credits.php`). Awarded automatically on attendance.
- **Certificates** — TCPDF-generated, validated via public URL (`class-certificates.php`, `class-seminar-certificates.php`).
- **Waitlists** — Position-queued, 48h-window, hourly cron expiration (`class-waitlist.php`, `class-seminar-waitlist.php`).
- **Frontend** — Elementor widgets (`widgets/`) + shortcodes (`class-shortcodes.php`).

---

## Directory Structure

```
gps-courses/
├── gps-courses.php                 # Entry point, defines GPSC_VERSION = '1.0.4'
├── composer.json                   # endroid/qr-code, tcpdf, phpmailer, bacon/qr-code
├── CLAUDE.md                       # Short Claude instructions
├── GPS_COURSES_FULL_DOCUMENTATION.md  # This document (canonical reference)
│
├── includes/                       # 35 PHP classes (see "Core Classes" section)
│   └── emails/                     # WC_Email subclasses
│       ├── class-ticket-email.php
│       └── class-credits-email.php
│
├── widgets/                        # 19 Elementor widgets
├── assets/css/                     # 14 stylesheets
├── assets/js/                      # 17 scripts
├── templates/emails/ticket.php     # Ticket email template
└── vendor/                         # Composer deps
```

---

## Database Schema

**13 tables total.** All prefixed with `wp_gps_`.

### 1. `wp_gps_tickets`
Generated when a course/event order completes. One row = one attendee admission.

| Column | Type | Notes |
|--------|------|-------|
| `ticket_code` | VARCHAR(50) UNIQUE | Embedded in QR |
| `ticket_type_id` | BIGINT | → `gps_ticket` CPT |
| `event_id` | BIGINT | → `gps_event` CPT |
| `user_id` | BIGINT (0 = guest) | Linked on register/login |
| `order_id` / `order_item_id` | BIGINT | WC linkage |
| `attendee_name` / `attendee_email` | VARCHAR | Designated attendee supported |
| `qr_code_path` | VARCHAR(500) | Filesystem path |
| `status` | ENUM('valid','used','cancelled') | |

### 2. `wp_gps_enrollments`
Course session enrollments (legacy/multi-session events).

### 3. `wp_gps_attendance`
Check-in events for course tickets. Methods: `qr_scan`, `manual`, `search`.

### 4. `wp_gps_ce_ledger`
**Immutable** ledger of CE credit transactions. Sources: `course_attendance`, `seminar_session`, `manual`. Types: `earned`, `adjustment`, `revoked`.

### 5. `wp_gps_certificates`
Generated PDFs with `certificate_path`, `certificate_url`, `certificate_sent_at`. Unique per ticket.

### 6. `wp_gps_waitlist`
Event ticket waitlist. Position-ordered, 48h notification window, statuses: `waiting`, `notified`, `converted`, `expired`, `removed`.

### 7. `wp_gps_seminar_registrations`
Monthly seminar enrollment (10 sessions, $750). Tracks `sessions_completed`, `sessions_remaining`, `makeup_used` (1/year), `qr_code`, `qr_scan_count`.

### 8. `wp_gps_seminar_sessions`
Individual sessions of a seminar. **v1.0.4 added 6 columns** for individual sales:

| New Column | Type | Purpose |
|------------|------|---------|
| `individual_price` | DECIMAL(10,2) | Per-session price |
| `individual_ce_credits` | DECIMAL(5,2) | CE for single-session attendee (default 2) |
| `individual_product_id` | BIGINT | WC product for this single session |
| `individual_sales_enabled` | TINYINT(1) | Toggle |
| `individual_capacity` | INT | 0 = unlimited |
| `individual_sold_count` | INT | Auto-incremented on order completion |

### 9. `wp_gps_seminar_attendance`
Per-session check-in. Tracks `is_makeup`, `credits_awarded` (default 2.00).

### 10. `wp_gps_seminar_waitlist`
Seminar program waitlist (whole program, not per-session).

### 12. `wp_gps_qr_codes` ⭐ **NEW v1.0.5**
One row per promotional QR (one per event/seminar). The `short_code` (8 chars, unambiguous alphabet) is the path segment in `/qr/{code}`. `target_url` is a cached permalink for display only — actual redirect resolves the permalink fresh from `post_id`. Soft-deleted via `deleted_at`.

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT PK | |
| `post_id` / `post_type` | BIGINT / VARCHAR | Linked event or seminar |
| `short_code` | VARCHAR(16) UNIQUE | URL path token |
| `target_url` | VARCHAR(500) | Cached, informational |
| `utm_source` / `utm_medium` / `utm_campaign` / `utm_content` / `utm_term` | VARCHAR | Defaults: `qr`/`print`/post-slug |
| `has_logo` | TINYINT(1) | Render logo at center |
| `scan_count` | INT | Cached running total (excludes bots) |
| `last_scanned_at` | DATETIME | |
| `created_at` / `deleted_at` | DATETIME | Soft-delete |

### 13. `wp_gps_qr_scans` ⭐ **NEW v1.0.5**
Per-scan log. Bots (`is_bot=1`) excluded from all dashboard aggregations.

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT PK | |
| `qr_id` / `post_id` | BIGINT | Denormalized |
| `scanned_at` | DATETIME | |
| `ip_hash` | VARCHAR(64) | SHA-256 of `IP + wp_salt('auth')` |
| `user_agent` | VARCHAR(500) | Truncated |
| `device_type` | VARCHAR(20) | `mobile` / `tablet` / `desktop` / `unknown` |
| `is_bot` | TINYINT(1) | Detected by UA regex |
| `country` / `country_code` / `region` / `city` | VARCHAR | Async-filled via ip-api.com |
| `referrer` | VARCHAR(500) | |

### 11. `wp_gps_session_tickets` ⭐ **NEW v1.0.4**
Individual session ticket sales (independent of full seminar registration).

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT PK | |
| `session_id` | BIGINT | → `wp_gps_seminar_sessions.id` |
| `seminar_id` | BIGINT | Denormalized for fast lookup |
| `user_id` / `order_id` | BIGINT | WC linkage |
| `ticket_code` | VARCHAR(50) UNIQUE | QR payload |
| `attendee_name` / `attendee_email` | VARCHAR | |
| `qr_code_path` | VARCHAR(255) | |
| `ce_credits` | DECIMAL(5,2) DEFAULT 2.00 | Awarded on check-in |
| `status` | VARCHAR(20) DEFAULT 'valid' | |
| `checked_in_at` / `checked_in_by` | DATETIME / BIGINT | |
| `created_at` | DATETIME | |

---

## Custom Post Types

Registered in [includes/class-posttypes.php](includes/class-posttypes.php).

### `gps_event` — Courses
**Meta:** `_gps_ce_credits`, `_gps_start_date`, `_gps_end_date`, `_gps_start_time`, `_gps_end_time`, `_gps_venue`, `_gps_address`, `_gps_objectives`, `_gps_speaker_ids`, `_gps_capacity`, `_gps_registration_deadline`, `_gps_schedule_topics` (JSON).

### `gps_seminar` — Monthly seminar program (10 sessions)
**Meta:** `_gps_seminar_year`, `_gps_seminar_capacity`, `_gps_seminar_product_id` (full-program WC product), `_gps_seminar_status`, `_gps_seminar_tuition` ($750 default).

### `gps_session` — Individual session in a multi-day event
**Meta:** `_gps_event_id`, `_gps_start`, `_gps_end`, `_gps_wc_product_id`, `_gps_speaker_ids`.

### `gps_ticket` — Ticket type (Early Bird, VIP, etc.)
Not public. **Meta:** `_gps_event_id`, `_gps_wc_product_id`, `_gps_ticket_type` (early_bird/general/vip/group), `_gps_ticket_price`, `_gps_ticket_quantity`, `_gps_ticket_start_date`, `_gps_ticket_end_date`, `_gps_ticket_status`, `_gps_ticket_features`, `_gps_ticket_internal_label`, `_gps_manual_sold_out`.

### `gps_speaker`
**Meta:** `_gps_designation`, `_gps_company`, `_gps_email`, `_gps_phone`, `_gps_social_twitter`, `_gps_social_linkedin`, `_gps_social_facebook`.

### `gps_organizer`, `gps_sponsor`
Linked entities via post meta.

**Taxonomies:** `gps_event_category`, `gps_event_tag`.

---

## Core Classes (`includes/`)

| Class File | Purpose |
|-----------|---------|
| `class-plugin.php` | Bootstrap, requires all classes, initializes hooks |
| `class-activator.php` | DB table creation, schema migrations (e.g. `migrate_session_individual_sales`, `migrate_tickets_designated_attendee`) |
| `class-posttypes.php` | Registers 6 CPTs + taxonomies, metaboxes, sessions admin table |
| `class-tickets.php` | Ticket CRUD, stock tracking, QR linking |
| `class-tickets-admin.php` | Admin UI for ticket types |
| `class-events.php` | Event helpers |
| `class-schedules.php` | Multi-day schedule management (uses `JSON_UNESCAPED_UNICODE`) |
| `class-qrcode.php` | QR generation via Endroid |
| `class-pdf.php` | TCPDF wrapper |
| `class-woocommerce.php` | All WC integration: order processing, account endpoints, auto-complete, guest linking, admin notifications |
| `class-credits.php` | CE credit ledger ops |
| `class-attendance.php` | Course check-in (QR/manual/search) |
| `class-certificates.php` | Course PDF certificate generation |
| `class-certificate-settings.php` | Certificate template config |
| `class-certificate-validation.php` | Public certificate validation endpoint |
| `class-seminars.php` | Monthly seminars admin (registrants, attendance, reports) |
| `class-seminar-registrations.php` | 10-session enrollment lifecycle |
| `class-seminar-attendance.php` | Session check-in (now also handles individual session ticket QRs) |
| `class-seminar-certificates.php` | Bi-annual seminar certificates |
| `class-seminar-notifications.php` | Daily 8am cron for reminder emails |
| `class-seminar-waitlist.php` | Seminar waitlist + hourly cron |
| `class-seminar-makeup-requests.php` | Makeup session requests |
| **`class-session-tickets.php`** ⭐ | **v1.0.4 NEW** — Individual session sales (see dedicated section) |
| **`class-qr-tracker.php`** ⭐ | **v1.0.5 NEW** — `/qr/{code}` rewrite, scan logging, async geo, redirect to live permalink with UTMs |
| **`class-qr-promotional.php`** ⭐ | **v1.0.5 NEW** — Sidebar metabox in event/seminar edit screens; SVG/PNG download endpoints; logo overlay |
| **`class-qr-dashboard.php`** ⭐ | **v1.0.5 NEW** — `gps-dashboard > QR Analytics` admin page with KPIs, charts (Chart.js CDN), top-N tables |
| `class-waitlist.php` | Event ticket waitlist + hourly cron |
| `class-emails.php` | Core email dispatch + password reset template |
| `class-email-settings.php` | Email branding/config |
| `class-email-template-manager.php` | Visual editor + live preview for email templates |
| `class-settings.php` | Settings registration (general, email, ticket, CE, WC) |
| `class-reports.php` | CSV exports + email blasts |
| `class-api.php` | REST routes (namespace `gps-courses/v1`) |
| `class-elementor.php` | Widget category + 19 widget registrations |
| `class-shortcodes.php` | Frontend shortcodes |
| `class-debug-helper.php` | Diagnostic admin tools |
| `helpers.php` | Date/format/sanitize utilities |
| `emails/class-ticket-email.php` | `WC_Email` subclass for ticket confirmation |
| `emails/class-credits-email.php` | `WC_Email` subclass for CE credit notifications |

---

## Core Features

### 1. Ticketing (Events)
Multiple ticket types per event with time-based pricing. Stock counted from **completed orders only** (HPOS-compatible). QR-coded with email delivery.

### 2. Manual Sold-Out & Waitlist
- Admin can flag any ticket as sold-out via `_gps_manual_sold_out`.
- When sold out, the frontend shows a waitlist form instead of "Add to Cart".
- Waitlist queue tracked by `position`. On order cancel/refund → next person notified with 48h window.
- Hourly cron `gps_process_expired_ticket_waitlist` expires lapsed notifications and rotates the queue.

### 3. Event Calendar
Month/week/list views, AJAX-powered, 5-min cache. Colors: Courses `#0B52AC`, Seminars `#DDC89D`. Now includes individual seminar sessions.

### 4. CE Credits
Immutable ledger. Sources: `course_attendance`, `seminar_session`, `manual`. Types: `earned`, `adjustment`, `revoked`. Awarded automatically on check-in.

### 5. Attendance & Check-in
Three methods: `qr_scan`, `manual`, `search`. The seminar check-in handler in [class-seminar-attendance.php](includes/class-seminar-attendance.php) now branches on `qr_data.type === 'session_ticket'` to delegate to `Session_Tickets::check_in_by_code` for individual session attendees.

### 6. Monthly Seminars
- 10 sessions/year, $750 enrollment, 2 CE per session (20 total).
- 1 makeup per year, tracked via `makeup_used` flag.
- Bi-annual certificates: June 30, December 31.

### 7. Certificate Generation
TCPDF, public validation URL, bulk gen, email delivery with PDF attachment. Tracks `generated_at` and `certificate_sent_at`. Recent fix (commit `8ac0b97`): GPS branding, name capitalization, PDF attachment.

### 8. Email System
Visual template editor (`class-email-template-manager.php`) with live preview. Per-template configurable. Branding via Settings (logo, colors, footer). Recent additions: password reset, designated attendee.

### 9. WooCommerce Integration
- Auto-completes GPS-product orders on payment_complete.
- Tracks status changes and emails admins.
- Links guest orders on user register/login.
- New custom My Account tab: **My Individual Sessions** (v1.0.4).

### 10. My Account Tabs
- My Courses (`/my-courses/`)
- Monthly Seminars (`/seminars/`)
- CE Credits (`/ce-credits/`)
- My Tickets (`/my-tickets/`)
- Attendance History (`/attendance/`)
- **My Individual Sessions** (new, rendered inside seminars endpoint)

---

## Individual Session Tickets (v1.0.4)

The flagship v1.0.4 feature. Allows monthly seminar sessions to be sold standalone in addition to the full $750 program.

### Admin Configuration
In [class-posttypes.php](includes/class-posttypes.php) the seminar sessions metabox now has 6 extra columns per session row:

| Column | Field |
|--------|-------|
| Sell Individually | `individual_enabled` checkbox |
| Ind. Price | `individual_price` |
| Ind. CE | `individual_ce_credits` (default 2) |
| Ind. Product | dropdown of WC products |
| Sold | live count from `individual_sold_count` |

When saving, the linked WC product gets meta `_gps_session_individual_id` and `_gps_seminar_session_id` so reverse lookup works.

### `Session_Tickets` Class — Public Methods

[includes/class-session-tickets.php](includes/class-session-tickets.php)

| Method | Purpose |
|--------|---------|
| `init()` | Register WC + AJAX hooks |
| `process_session_order($order_id)` | On order completion, create one `wp_gps_session_tickets` row per item |
| `create_session_ticket($data)` | Generate code, QR, insert row |
| `get_ticket($id)` / `get_ticket_by_code($code)` | Lookup |
| `get_session_tickets($session_id)` | List valid tickets for a session |
| `get_user_session_tickets($user_id)` | User's purchased individual sessions (joined with seminar/session metadata) |
| `check_in($ticket_id, $checked_in_by)` | Mark attended, award CE |
| `check_in_by_code($code, $session_id)` | QR scan path; validates session match |
| `generate_certificate($ticket_id)` | Post-attendance PDF |
| `get_session_by_product($product_id)` | WC product → session lookup |
| `is_session_product($product_id)` | Boolean used by `class-woocommerce.php` to recognize GPS items |
| `get_available_count($session_id)` | Capacity remaining (0 = unlimited) |
| `generate_qr_code(...)` | QR image generation |
| `ajax_checkin_session_ticket()` | AJAX check-in |
| `ajax_get_session_individual_tickets()` | Admin: list tickets for a session |

### QR Payload Format
```json
{ "type": "session_ticket", "ticket_code": "GPS-S-XXXXXX" }
```
The seminar attendance handler dispatches based on `type`. Standard seminar registration QRs lack the `type` field and fall through to `Seminar_Attendance::check_in`.

---

## Promotional QR Codes (v1.0.5)

Generates downloadable promotional QR codes for events and seminars, with full server-side scan tracking and an analytics dashboard.

### Architectural choice: redirect-based QR
The QR encodes `https://gpsdentaltraining.com/qr/{short_code}` (not the permalink directly). On scan:
1. WordPress dispatches via rewrite rule to `QR_Tracker::handle_scan`.
2. Plugin looks up `short_code`, logs the scan, resolves `get_permalink($post_id)` **fresh**, appends UTMs, 302-redirects.
3. Final URL also carries `qr_id={short_code}` for downstream attribution.

This means: changing an event's slug never breaks printed QRs; the `post_id` is the stable anchor.

### Three modules

**[class-qr-tracker.php](includes/class-qr-tracker.php) — data + redirect (Phase 1)**
- Registers rewrite rule `^qr/([a-z0-9]{6,16})/?$` and `gps_qr` query var.
- `handle_scan()` is the entry point on `template_redirect`.
- `log_scan()` inserts into `wp_gps_qr_scans` and increments `scan_count` on the QR row (only when `is_bot=0`).
- `get_or_create_for_post($post_id)` is the public API used by the metabox to lazily create the QR row.
- `update_qr($qr_id, $fields)` whitelist-updates UTM/has_logo.
- `async_geo_lookup($ip)` runs as a one-shot cron (`gps_qr_geo_lookup`), hits `ip-api.com` (free tier, no key, 5s timeout), caches results in transients (~30 days), then back-fills geo on existing scan rows for that IP hash. Geo never blocks the redirect.
- IP hashing: SHA-256 with `wp_salt('auth')` — privacy-preserving but stable for the unique-scan count.
- Bot detection: UA regex covers `bot`, `crawler`, `spider`, `facebookexternalhit`, `slurp`, `lighthouse`, `headlesschrome`, `preview`.
- Short code alphabet: `abcdefghijkmnpqrstuvwxyz23456789` (no `0`/`O`/`l`/`1`).

**[class-qr-promotional.php](includes/class-qr-promotional.php) — metabox + downloads (Phase 2)**
- Registers a sidebar metabox `Promotional QR Code` on `gps_event` and `gps_seminar` edit screens.
- Inline live preview (PNG, 400px) via `gps_qr_preview` AJAX action.
- Download endpoints `gps_qr_download` for SVG (vector) and PNG (1024px). Logo toggle overlays a centered logo (20% width, with `logoPunchoutBackground=true` so background is cleared under the logo for crisp scanning).
- Logo source order: `gps_qr_logo_url` (option, dedicated) → `gps_email_header_image` (existing email logo). Toggle is disabled if no logo is uploadable.
- `gps_qr_save` AJAX persists UTM defaults and `has_logo` per QR.
- Generation uses `endroid/qr-code` 4.x with `ErrorCorrectionLevelHigh` (allows ~30% obscuring without breaking scannability — important for the logo overlay).

**[class-qr-dashboard.php](includes/class-qr-dashboard.php) — analytics (Phase 3)**
Admin page at `gps-dashboard > QR Analytics`. Bots excluded everywhere. Range selector: 7d/30d/90d/365d/all.

KPIs:
- Total scans (excluding bots)
- Unique scans (distinct `ip_hash`)
- Active QRs (count with ≥1 scan in range)
- Average scans per QR (across all non-deleted QRs)

Charts (Chart.js 4.4.1 from CDN, loaded only on this page):
- **Scans Over Time** — daily line chart, gap-filled with zeros so the line is continuous.
- **Device Breakdown** — donut by `device_type`.
- **Top QR Codes** — horizontal bar by scans, joined to post titles.
- **Top Countries** — horizontal bar by `country` (excludes empty/unknown).

Detail table at the bottom: every QR with title, type, short code, total scans, unique scans, last-scanned (relative time), created date.

### Future (not in v1.0.5)
- Conversion attribution (scan → purchase): would require capturing checkout IP and joining on hash + 24h window. Skipped for MVP.
- Custom logo per QR (currently all QRs share the global logo setting).
- Scheduled CSV exports of scan log.

---

## Settings & Options

Registered in [class-settings.php](includes/class-settings.php).

### General Settings (`gps_general_settings`)
- `gps_google_maps_api_key`
- `gps_ticket_prefix`
- `gps_company_name`, `gps_company_email`, `gps_company_phone`, `gps_company_address`
- **`gps_notification_emails`** ⭐ **NEW v1.0.4** — Newline-separated list, replaces hardcoded `Woo::ADMIN_NOTIFICATION_EMAILS`. Sanitized via `Settings::sanitize_notification_emails()`. Read via `Settings::get_notification_emails()` (falls back to admin email).

### Email Settings (`gps_email_settings`)
`gps_email_from_name`, `gps_email_from_address`, `gps_email_header_image`, `gps_email_footer_text`, `gps_email_primary_color`, plus header/body bg/text colors and CTA button colors.

### Ticket Settings
`gps_ticket_logo`, `gps_ticket_header_text`, `gps_ticket_footer_text`, `gps_qr_code_size`, `gps_ticket_include_qr`.

### CE Credits Settings
`gps_credits_enabled`, `gps_credits_require_attendance`, `gps_credits_certificate_template`.

### WooCommerce Settings
`gps_woo_enable_sync`, `gps_woo_product_category`, `gps_stripe_publishable_key`, `gps_stripe_secret_key`.

---

## REST API

Namespace: **`gps-courses/v1`** (registered in [class-api.php](includes/class-api.php)).

### Public
| Method | Path | Description |
|--------|------|-------------|
| GET | `/events` | Paginated list |
| GET | `/events/{id}` | Single event |
| GET | `/events/calendar` | Date-range query for calendar |
| GET | `/availability/event/{event_id}` | AI Assistant capacity check |
| GET | `/availability/ticket/{ticket_id}` | Ticket-level availability |
| POST | `/waitlist/add` | Frontend signup |
| GET | `/waitlist/check?email=&event_id=` | Status check |

### Authenticated
| Method | Path |
|--------|------|
| GET | `/tickets`, `/tickets/{id}` |
| POST | `/tickets/verify` |
| GET | `/credits/user/{user_id}`, `/credits/ledger` |
| GET | `/attendance/event/{event_id}` |

---

## AJAX Endpoints

Frontend (`wp_ajax_nopriv_` + `wp_ajax_`):
`gps_add_tickets_to_cart`, `gps_get_calendar_events`, `gps_validate_certificate`.

Admin (`wp_ajax_` only): see [Explore agent snapshot] — 50+ actions including `gps_scan_ticket`, `gps_manual_checkin`, `gps_search_attendees`, `gps_bulk_checkin`, `gps_generate_certificate`, `gps_send_certificate`, `gps_export_attendees`, `gps_send_email_blast`, `gps_save_email_template`, **`gps_checkin_session_ticket`** (v1.0.4), **`gps_get_session_individual_tickets`** (v1.0.4), and seminar variants.

---

## WordPress Hooks

### Plugin-fired Actions (extensibility)
```php
do_action('gps_ticket_created',     $ticket_id,     $order_id);
do_action('gps_enrollment_created', $enrollment_id, $user_id);
do_action('gps_attendance_recorded',$attendance_id, $user_id);
do_action('gps_credits_awarded',    $credit_id,     $user_id);
```

### Listened (key WC hooks in `class-woocommerce.php`)
- `woocommerce_order_status_completed` → ticket/enrollment/session-ticket creation
- `woocommerce_payment_complete` → auto-complete GPS-product orders
- `woocommerce_order_status_changed` → admin notifications
- `woocommerce_order_status_cancelled` / `_refunded` → waitlist trigger
- `user_register`, `wp_login` → guest order linking
- `woocommerce_account_menu_items` (filter) → custom My Account tabs

---

## Cron Jobs

| Hook | Frequency | Source | Purpose |
|------|-----------|--------|---------|
| `gps_process_expired_ticket_waitlist` | Hourly | `class-waitlist.php` | Expire 48h notifications, advance queue |
| `gps_process_expired_waitlist` | Hourly | `class-seminar-waitlist.php` | Same for seminar program waitlist |
| `gps_seminar_daily_cron` | Daily 8 AM | `class-seminar-notifications.php` | Reminder emails |
| `gps_qr_geo_lookup` | One-shot per IP | `class-qr-tracker.php` | Async ip-api.com lookup, transient-cached |

---

## Admin Menu Structure

Parent slug: **`gps-dashboard`** (capability: `manage_options`).

**Submenu pages:**
- Categories / Tags (taxonomies)
- Attendance Scanner / Attendance Report
- Certificates / Certificate Settings
- Email Settings / Email Template Manager
- Reports & Analytics
- Settings
- Monthly Seminars / Seminar Registrants / Session Attendance / Seminar Waitlist / Seminar Reports
- Email Notifications
- Purchased Tickets
- **QR Analytics** (v1.0.5)
- Debug Tools

---

## Elementor Widgets

19 widgets registered in [class-elementor.php](includes/class-elementor.php):

`event-grid`, `event-list`, `event-slider`, `event-calendar`, `single-event`, `speaker-grid`, `ticket-selector`, `schedule-display`, `google-maps`, `countdown-timer`, `ce-credits-display`, `course-objectives`, `course-description`, `event-dates-display`, `share-course`, `add-to-calendar`, `seminar-registration`, `seminar-progress`, `seminar-schedule`.

---

## Business Logic Details

### Ticket Sold-Out
```php
// 1. Manual override wins
if (get_post_meta($ticket_id, '_gps_manual_sold_out', true)) return true;
// 2. Otherwise stock-based (skipped if unlimited)
$s = self::get_ticket_stock($ticket_id);
return !$s['unlimited'] && $s['available'] === 0;
```

### Waitlist Notification Flow
1. Order cancelled/refunded fires WC hook.
2. If ticket no longer sold out → `notify_next_on_waitlist()`.
3. Update entry: `status='notified'`, `notified_at=NOW()`, `expires_at=+48h`.
4. Email "Spot Available" with urgency CTA.
5. Hourly cron expires lapsed entries and rotates.

### Seminar Certificate Generation
Bi-annual triggers (June 30, December 31):
1. Query users with `sessions_completed >= threshold`.
2. Generate PDF, store in `wp_gps_certificates`, email with attachment, set `certificate_sent_at`.

### Guest Order Linking
On `user_register` or `wp_login`:
1. Match `attendee_email` in `wp_gps_tickets`/`wp_gps_enrollments` where `user_id = 0`.
2. Bulk-update `user_id` to the new user.
3. Surface in their My Account tabs.

### Individual Session Purchase Flow (v1.0.4)
1. Admin enables `Sell Individually` on a session, sets price, links a WC product.
2. Customer buys the WC product → order completes.
3. `Woo::on_order_completed` detects via `Session_Tickets::is_session_product($product_id)`.
4. `Session_Tickets::process_session_order` creates a `wp_gps_session_tickets` row, generates QR.
5. `individual_sold_count` is incremented on the session.
6. Email with QR sent to attendee.
7. At the session, QR scanner detects `type === 'session_ticket'` and check-in path goes via `Session_Tickets::check_in_by_code`. CE credits awarded.

---

## Known Issues & Solutions

| Issue | Solution |
|-------|----------|
| Guest orders not visible in My Account | Orders Diagnostic tool links by email |
| Special chars in schedule topics encoded as `“` | `JSON_UNESCAPED_UNICODE` in `wp_json_encode` |
| Orders stuck on `processing` after Stripe payment | `woocommerce_payment_complete` auto-completes GPS-product orders |
| Calendar missing seminars | API queries `wp_gps_seminar_sessions` |
| Email Templates menu under wrong parent | Changed parent slug to `gps-dashboard` (commit `8ca63e3`) |

---

## Changelog

### 1.0.5 (current)
- **Promotional QR codes** — generate downloadable SVG/PNG QR codes per event/seminar, served from a redirect URL (`/qr/{code}`) so printed QRs survive slug changes.
- New tables `wp_gps_qr_codes` (one per post) and `wp_gps_qr_scans` (per-scan log with hashed IP, device, geo, referrer).
- New modules: `QR_Tracker` (Phase 1: data + redirect), `QR_Promotional` (Phase 2: metabox UI + downloads), `QR_Dashboard` (Phase 3: KPIs + Chart.js analytics).
- Async geo enrichment via ip-api.com (cron-driven, transient-cached, never blocks redirect).
- Bot detection excludes crawlers from all aggregations.
- New admin page `gps-dashboard > QR Analytics` with range selector, 4 KPIs, 4 charts, and detail table.

### 1.0.4 — committed `966cea4`
- **Individual session ticket sales** — sell single seminar sessions standalone with QR + CE credits.
- New table `wp_gps_session_tickets`; new `individual_*` columns on `wp_gps_seminar_sessions`.
- New `Session_Tickets` module + admin UI in seminar sessions metabox.
- Seminar QR check-in dispatches between full-program and individual-session tickets.
- New "My Individual Sessions" My Account section.
- **Configurable notification emails** — `gps_notification_emails` setting replaces hardcoded `ADMIN_NOTIFICATION_EMAILS` constant.
- `Settings::get_notification_emails()` is the new accessor; `Woo::get_admin_notification_emails()` delegates to it.

### 1.0.3
- Email Template Manager (visual editor + live preview).
- Designated attendee system, certificate improvements, Letter format.
- Email Templates menu re-parented to `gps-dashboard`.

### 1.0.2
- Manual sold-out toggle, enhanced waitlist (positions, expiration, admin management).
- Guest order linking + Orders Diagnostic.
- Special-character fix in schedules.
- Monthly seminars added to calendar.
- AI Assistant REST integration.

### 1.0.1
- WooCommerce integration, basic ticketing, QR codes.

### 1.0.0
- Initial release.

---

## Maintenance Notes for Future Contributors

- **Bumping version**: edit `GPSC_VERSION` in `gps-courses.php` AND add migration in `class-activator.php` if schema changes. The activator runs `migrate_*` methods only on activation, so changes to existing installs require either re-activation or a separate migration trigger.
- **Notification emails**: never reintroduce the hardcoded constant. Use `Settings::get_notification_emails()`.
- **GPS-product detection**: when adding a new product type (e.g., a new fulfillment kind), add a corresponding `is_*_product()` static + branch in `Woo::on_order_completed` and `Woo::auto_complete_gps_orders`.
- **QR payload schema**: standard tickets have no `type` key; v1.0.4 introduced `type === 'session_ticket'`. Future ticket kinds should follow the typed-payload pattern.

---

**Developer:** WebMinds Agency · juliocastro@thewebminds.agency
**Client:** GPS Dental Training · https://gpsdentaltraining.com
