# GPS Dental Training - Headless Migration Plan

## Executive Summary

Complete migration of GPS Dental Training from WordPress/WooCommerce to a modern headless architecture, replicating **ALL business logic from the GPS Courses plugin** as the primary source of truth.

**Status:** Planning Phase
**Developer:** Claude AI (WebMinds Agency)
**Target Timeline:** ~15 weeks (3.5-4 months)

---

## Table of Contents

1. [Reference Sources](#reference-sources)
2. [Technology Stack](#technology-stack)
3. [Database Schema (PostgreSQL)](#database-schema-postgresql)
4. [Strapi Content Types](#strapi-content-types)
5. [API Design](#api-design)
6. [Frontend Architecture](#frontend-architecture)
7. [Business Logic Flows](#business-logic-flows)
8. [Email System](#email-system)
9. [Scheduled Tasks](#scheduled-tasks)
10. [Data Migration](#data-migration)
11. [Course Creation Workflow](#course-creation-workflow)
12. [Visual Design](#visual-design)
13. [Implementation Timeline](#implementation-timeline)
14. [Testing & Validation](#testing--validation)

---

## Reference Sources

### Business Logic (PRIMARY SOURCE)
The `gps-courses/` plugin contains ALL logic to replicate:
- **10 database tables** with relationships
- **Ticket flows**: creation, stock, manual sold out, QR codes
- **Waitlist**: positions, 48h notifications, automatic expiration
- **CE Credits**: immutable ledger, auto-award on check-in
- **Seminars**: 10 sessions, $750, 2 CE/session, makeup, bi-annual certificates
- **Certificates**: PDF generation, public validation, email delivery
- **WooCommerce**: orders, webhooks, guest linking

### Visual Design (REFERENCE EXAMPLES)
The provided URLs are **examples of current design** to replicate:
- Course page: `/courses/comprehensive-prf-protocols-handling-clinical-integration/`
- Seminars page: `/product/gps-monthly-seminars/`

**Design must maintain the same visual structure, colors, and UX.**

### Documentation Files
| File | Description |
|------|-------------|
| [GPS_COURSES_FULL_DOCUMENTATION.md](GPS_COURSES_FULL_DOCUMENTATION.md) | Complete plugin documentation |
| [AI_ASSISTANT_INTEGRATION.md](AI_ASSISTANT_INTEGRATION.md) | REST API integration docs |
| [CLAUDE.md](CLAUDE.md) | Development guidelines |

---

## Technology Stack

| Component | Technology | Notes |
|-----------|------------|-------|
| **Frontend** | Astro | Islands architecture, static by default |
| **Backend/CMS** | Strapi | Headless CMS for content management |
| **Database** | PostgreSQL (Supabase) | RLS policies, real-time, automatic backups |
| **Authentication** | Clerk | User management with webhooks |
| **Payments** | Stripe | Checkout Sessions + Webhooks |
| **Transactional Emails** | Resend | React Email templates |
| **PDFs** | Puppeteer | Certificate generation |
| **Marketing Emails** | Elastic Email | Keep existing |
| **Frontend Hosting** | Vercel | Optimized for Astro |
| **Strapi Hosting** | Railway/Render | Managed Node.js |
| **CDN/Assets** | Supabase Storage | Public and private buckets |
| **Cron Jobs** | Vercel Cron/Inngest | Scheduled tasks |

---

## Database Schema (PostgreSQL)

### Core Tables

```sql
-- USERS (synced with Clerk)
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    clerk_id VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(50),
    role VARCHAR(50) DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- EVENTS/COURSES (content from Strapi, operational data here)
CREATE TABLE events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    strapi_id INTEGER UNIQUE,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    start_date TIMESTAMP NOT NULL,
    end_date TIMESTAMP,
    venue VARCHAR(255),
    address TEXT,
    ce_credits INTEGER DEFAULT 0,
    capacity INTEGER,
    schedule_topics JSONB,
    status VARCHAR(50) DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- TICKET TYPES
CREATE TABLE ticket_types (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID REFERENCES events(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    ticket_type VARCHAR(50), -- early_bird, general, vip, group
    price DECIMAL(10,2) NOT NULL,
    quantity INTEGER, -- NULL = unlimited
    sale_start TIMESTAMP,
    sale_end TIMESTAMP,
    stripe_price_id VARCHAR(255),
    stripe_product_id VARCHAR(255),
    manual_sold_out BOOLEAN DEFAULT FALSE,
    features TEXT[],
    internal_label VARCHAR(255),
    status VARCHAR(50) DEFAULT 'inactive',
    created_at TIMESTAMP DEFAULT NOW()
);

-- ORDERS
CREATE TABLE orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id UUID REFERENCES users(id),
    stripe_session_id VARCHAR(255),
    stripe_payment_intent VARCHAR(255),
    billing_email VARCHAR(255) NOT NULL,
    billing_name VARCHAR(255),
    subtotal DECIMAL(10,2),
    total DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status VARCHAR(50) DEFAULT 'pending', -- pending, completed, cancelled, refunded
    payment_status VARCHAR(50) DEFAULT 'unpaid',
    completed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);

-- ORDER ITEMS
CREATE TABLE order_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID REFERENCES orders(id) ON DELETE CASCADE,
    ticket_type_id UUID REFERENCES ticket_types(id),
    event_id UUID REFERENCES events(id),
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

-- TICKETS (individual instances)
CREATE TABLE tickets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_code VARCHAR(50) UNIQUE NOT NULL,
    ticket_type_id UUID REFERENCES ticket_types(id),
    event_id UUID REFERENCES events(id),
    order_id UUID REFERENCES orders(id),
    user_id UUID REFERENCES users(id),
    attendee_name VARCHAR(255) NOT NULL,
    attendee_email VARCHAR(255) NOT NULL,
    qr_code_data JSONB, -- Contains hash for verification
    qr_code_url VARCHAR(500),
    status VARCHAR(50) DEFAULT 'valid', -- valid, used, cancelled
    created_at TIMESTAMP DEFAULT NOW()
);

-- ATTENDANCE
CREATE TABLE attendance (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id UUID REFERENCES tickets(id) UNIQUE,
    event_id UUID REFERENCES events(id),
    user_id UUID REFERENCES users(id),
    checked_in_at TIMESTAMP DEFAULT NOW(),
    check_in_method VARCHAR(50), -- qr_scan, manual, search
    checked_in_by UUID REFERENCES users(id),
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- CE CREDITS LEDGER (immutable)
CREATE TABLE ce_ledger (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id) NOT NULL,
    event_id UUID REFERENCES events(id),
    credits DECIMAL(5,2) NOT NULL,
    source VARCHAR(100), -- course_attendance, seminar_session, manual
    transaction_type VARCHAR(50) DEFAULT 'earned', -- earned, adjustment, revoked
    notes TEXT,
    awarded_at TIMESTAMP DEFAULT NOW()
);

-- WAITLIST
CREATE TABLE waitlist (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_type_id UUID REFERENCES ticket_types(id),
    event_id UUID REFERENCES events(id),
    user_id UUID REFERENCES users(id),
    email VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(50),
    position INTEGER NOT NULL,
    status VARCHAR(50) DEFAULT 'waiting', -- waiting, notified, converted, expired, removed
    notified_at TIMESTAMP,
    expires_at TIMESTAMP, -- 48h after notification
    created_at TIMESTAMP DEFAULT NOW()
);

-- CERTIFICATES
CREATE TABLE certificates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    certificate_code VARCHAR(50) UNIQUE NOT NULL,
    ticket_id UUID REFERENCES tickets(id),
    user_id UUID REFERENCES users(id),
    event_id UUID REFERENCES events(id),
    attendee_name VARCHAR(255) NOT NULL,
    pdf_url VARCHAR(500),
    generated_at TIMESTAMP DEFAULT NOW(),
    sent_at TIMESTAMP
);

-- SEMINARS
CREATE TABLE seminars (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    strapi_id INTEGER UNIQUE,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    year INTEGER NOT NULL,
    price DECIMAL(10,2) DEFAULT 750.00,
    capacity INTEGER,
    total_sessions INTEGER DEFAULT 10,
    stripe_price_id VARCHAR(255),
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT NOW()
);

-- SEMINAR SESSIONS
CREATE TABLE seminar_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    seminar_id UUID REFERENCES seminars(id) ON DELETE CASCADE,
    session_number INTEGER NOT NULL,
    session_date DATE NOT NULL,
    session_time_start TIME,
    session_time_end TIME,
    topic VARCHAR(500),
    description TEXT,
    capacity INTEGER,
    created_at TIMESTAMP DEFAULT NOW()
);

-- SEMINAR REGISTRATIONS
CREATE TABLE seminar_registrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id),
    seminar_id UUID REFERENCES seminars(id),
    order_id UUID REFERENCES orders(id),
    registration_date DATE DEFAULT CURRENT_DATE,
    start_session_date DATE,
    sessions_completed INTEGER DEFAULT 0,
    sessions_remaining INTEGER DEFAULT 10,
    makeup_used BOOLEAN DEFAULT FALSE,
    status VARCHAR(50) DEFAULT 'active', -- active, completed, cancelled, on_hold
    qr_code VARCHAR(100),
    qr_code_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT NOW()
);

-- SEMINAR ATTENDANCE
CREATE TABLE seminar_attendance (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    registration_id UUID REFERENCES seminar_registrations(id),
    session_id UUID REFERENCES seminar_sessions(id),
    user_id UUID REFERENCES users(id),
    seminar_id UUID REFERENCES seminars(id),
    is_makeup BOOLEAN DEFAULT FALSE,
    credits_awarded DECIMAL(5,2) DEFAULT 2.00,
    checked_in_at TIMESTAMP DEFAULT NOW(),
    checked_in_by UUID REFERENCES users(id),
    notes TEXT
);

-- MIGRATION MAPPING (temporary, for data migration)
CREATE TABLE user_migration_map (
    wp_user_id INTEGER PRIMARY KEY,
    clerk_user_id VARCHAR(255) UNIQUE,
    supabase_user_id UUID REFERENCES users(id),
    email VARCHAR(255),
    migrated_at TIMESTAMP DEFAULT NOW()
);
```

### Row Level Security (RLS)

```sql
-- Users can only see their own data
ALTER TABLE orders ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Users view own orders" ON orders
    FOR SELECT USING (user_id = auth.uid());

ALTER TABLE tickets ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Users view own tickets" ON tickets
    FOR SELECT USING (user_id = auth.uid());

ALTER TABLE ce_ledger ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Users view own credits" ON ce_ledger
    FOR SELECT USING (user_id = auth.uid());

-- Events are public
ALTER TABLE events ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Events are public" ON events
    FOR SELECT USING (status = 'published');
```

---

## Strapi Content Types

### Content Types

| Content Type | Fields | Relations |
|--------------|--------|-----------|
| **Event** | title, slug, description, startDate, endDate, venue, ceCredits, capacity, scheduleTopics (component) | speakers (M2M), category (M2O) |
| **Seminar** | title, slug, year, price, sessions (component) | - |
| **Speaker** | name, slug, title, bio, photo, socialLinks | events (M2M) |
| **Event Category** | name, slug, description | events (O2M) |
| **Page** | title, slug, content, seo (component) | - |

### Components

- `event.schedule-topic`: sessionNumber, time, topic, description
- `seminar.session`: sessionNumber, date, startTime, endTime, topic
- `shared.seo`: metaTitle, metaDescription, ogImage

---

## API Design

### Public Endpoints
```
GET  /api/events                    → List events (from Strapi)
GET  /api/events/[slug]             → Single event
GET  /api/events/calendar           → Calendar data
GET  /api/tickets/event/[eventId]   → Available ticket types
GET  /api/availability/[ticketTypeId] → Check availability
POST /api/waitlist/join             → Join waitlist
GET  /api/certificate/[code]        → Public certificate validation
```

### Protected Endpoints (User)
```
GET  /api/user/orders               → My orders
GET  /api/user/tickets              → My tickets
GET  /api/user/credits              → My CE credits ledger
GET  /api/user/seminars             → My seminar registrations
GET  /api/user/certificates         → My certificates
```

### Admin Endpoints
```
POST /api/admin/attendance/check-in → Check-in (QR/manual)
GET  /api/admin/attendance/event/[id] → Attendance list
POST /api/admin/certificates/generate → Generate certificate
POST /api/admin/certificates/send   → Send via email
POST /api/admin/credits/award       → Manual credit award
POST /api/admin/waitlist/notify     → Notify next in line
```

### Webhooks
```
POST /api/webhooks/stripe           → checkout.session.completed, charge.refunded
POST /api/webhooks/clerk            → user.created, user.updated
```

---

## Frontend Architecture

### Page Structure
```
src/pages/
├── index.astro                     # Homepage
├── courses/
│   ├── index.astro                 # Course listing
│   └── [slug].astro                # Single course
├── monthly-seminars/
│   ├── index.astro                 # Seminar listing
│   └── [slug].astro                # Single seminar
├── speakers/
│   ├── index.astro
│   └── [slug].astro
├── calendar/index.astro
├── account/
│   ├── index.astro                 # Dashboard
│   ├── orders.astro
│   ├── tickets.astro
│   ├── courses.astro
│   ├── seminars.astro
│   ├── credits.astro
│   └── certificates.astro
├── checkout/
│   ├── index.astro
│   ├── success.astro
│   └── cancel.astro
├── certificate/[code].astro        # Public validation
└── admin/
    ├── attendance/index.astro      # QR scanner
    ├── certificates/index.astro
    └── waitlist/index.astro
```

### React Islands (Interactive Components)

| Component | Purpose |
|-----------|---------|
| `EventCalendar.tsx` | Interactive month/week/list calendar |
| `TicketSelector.tsx` | Ticket selection + add to cart |
| `WaitlistForm.tsx` | AJAX waitlist form |
| `CheckoutButton.tsx` | Stripe checkout button |
| `Cart.tsx` | Shopping cart |
| `CountdownTimer.tsx` | Event countdown |
| `CECreditsDisplay.tsx` | Credits badge with animation |
| `SeminarProgress.tsx` | Session progress tracker |
| `QRScanner.tsx` | Camera QR scanner (admin) |
| `AttendanceScanner.tsx` | Check-in interface (admin) |

### Astro Components (Static)

- EventCard, EventGrid, EventList
- SpeakerGrid, SpeakerCard
- CourseDescription, CourseObjectives
- ScheduleDisplay (tabs/accordion/timeline)
- CertificatePreview

---

## Business Logic Flows

### 1. Ticket Purchase Flow
```
1. User selects tickets → TicketSelector.tsx
2. Click "Checkout" → API creates Stripe Checkout Session
3. Stripe handles payment → Redirect to checkout.stripe.com
4. Payment successful → Webhook checkout.session.completed
5. Backend:
   a. Create Order in Supabase
   b. Create OrderItems
   c. Generate individual Tickets with QR codes
   d. Send email with tickets (Resend)
6. User redirected to /checkout/success
```

### 2. Waitlist Flow
```
1. Ticket sold out (manual_sold_out OR stock=0)
2. User fills WaitlistForm → POST /api/waitlist/join
3. Sequential position assigned
4. Confirmation email (Resend)
5. When availability opens (cancellation/refund):
   a. Stripe webhook → detects refund
   b. Notify next in waitlist
   c. 48 hours to purchase
   d. If expires → notify next
```

### 3. Check-in & CE Credits Flow
```
1. Admin opens /admin/attendance
2. Scans QR → QRScanner.tsx
3. Backend:
   a. Validate ticket (HMAC hash)
   b. Verify status='valid'
   c. Create attendance record
   d. Update ticket status='used'
   e. Auto-award CE credits (INSERT ce_ledger)
4. Show confirmation with credits awarded
```

### 4. Certificate Flow
```
1. Admin selects event in /admin/certificates
2. List eligible attendees (attendance + credits)
3. Click "Generate" → Puppeteer generates PDF
4. Upload to Supabase Storage
5. Click "Send" → Resend sends email with attachment
6. Record in certificates table
```

### 5. Monthly Seminars Flow
```
1. Purchase: Same as tickets ($750 one-time)
2. Create seminar_registration (sessions_remaining=10)
3. Each monthly session:
   a. Check-in same as events
   b. Update sessions_completed++, sessions_remaining--
   c. Award 2 CE credits
   d. Mark makeup_used if applicable
4. Bi-annual (Jun 30, Dec 31):
   a. Cron generates certificates for completed
   b. Send via email
```

---

## Email System

### Templates (React Email)

| Template | Trigger | Content |
|----------|---------|---------|
| `ticket-confirmation` | Order completed | QR code, event, date, venue |
| `waitlist-confirmation` | Join waitlist | Position, what to expect |
| `waitlist-notification` | Spot available | 48h urgency, CTA button |
| `certificate-delivery` | Cert generated | PDF attachment, validation link |
| `seminar-registration` | Seminar purchase | Welcome, schedule, QR |
| `session-reminder` | 24h before session | Reminder with details |

### Email Configuration

```typescript
// From plugin settings to replicate:
const emailConfig = {
  logo: 'https://...',
  fromName: 'GPS Dental Training',
  fromEmail: 'info@gpsdentaltraining.com',
  headerBgColor: '#0B52AC',
  headerTextColor: '#ffffff',
  bodyBgColor: '#f5f5f5',
  bodyTextColor: '#333333',
  buttonBgColor: '#0B52AC',
  buttonTextColor: '#ffffff',
  footerText: 'Thank you for choosing GPS Dental Training!'
};
```

---

## Scheduled Tasks

| Task | Frequency | Action |
|------|-----------|--------|
| Waitlist expiration | Hourly | Expire notifications >48h, notify next |
| Ticket status update | Daily | Auto-expire tickets from past events |
| Session reminders | Daily 8am | Send reminders for upcoming sessions |
| Bi-annual certificates | Jun 30, Dec 31 | Generate completed seminar certificates |

---

## Data Migration

### 1. Users Migration

```typescript
// Export from WordPress
const wpUsers = await wpdb.query(`
  SELECT u.*,
    MAX(CASE WHEN um.meta_key='first_name' THEN um.meta_value END) as first_name,
    MAX(CASE WHEN um.meta_key='last_name' THEN um.meta_value END) as last_name,
    MAX(CASE WHEN um.meta_key='billing_phone' THEN um.meta_value END) as phone
  FROM wp_users u
  LEFT JOIN wp_usermeta um ON u.ID = um.user_id
  GROUP BY u.ID
`);

// Import to Clerk with password hashes
const clerkImport = wpUsers.map(u => ({
  external_id: `wp_${u.ID}`,
  email_address: [u.user_email],
  first_name: u.first_name,
  last_name: u.last_name,
  password_hasher: 'phpass',
  password_digest: u.user_pass,
  public_metadata: { wp_user_id: u.ID }
}));
```

### 2. Events/Courses Migration

| WordPress | → | New Platform |
|-----------|---|--------------|
| post_title | → | title |
| post_name | → | slug |
| post_content | → | description (HTML→Markdown) |
| _gps_start_date | → | start_date |
| _gps_end_date | → | end_date |
| _gps_venue | → | venue |
| _gps_ce_credits | → | ce_credits |
| _gps_capacity | → | capacity |
| _gps_schedule_topics | → | schedule_topics (JSONB) |

### 3. Critical Data to Preserve

- **ticket_code**: Keep existing codes for valid QR codes
- **qr_code_path**: Migrate QR images to Supabase Storage
- **CE Credits Ledger**: Full immutable history
- **Seminar Progress**: sessions_completed, sessions_remaining

### 4. Media Migration

```typescript
// Supabase Storage buckets
// - media (public): course images, speaker photos
// - qrcodes (private): ticket QR codes
// - certificates (private): PDF certificates

const uploadToSupabase = async (localPath, bucket, newPath) => {
  const file = fs.readFileSync(localPath);
  await supabase.storage.from(bucket).upload(newPath, file);
  return supabase.storage.from(bucket).getPublicUrl(newPath);
};
```

---

## Course Creation Workflow

### Current (Elementor)
1. Duplicate existing course page
2. Edit with visual builder
3. Change content, images

### New (Strapi + Astro)

**Admin creates course in Strapi:**
```
Panel: Content Manager > Events > Create New
├── Title (text)
├── Slug (auto-generated)
├── Featured Image (media upload)
├── Description (Rich Text Editor)
├── Start Date / End Date (date pickers)
├── Venue, Address (text)
├── CE Credits (number)
├── Capacity (number)
├── Schedule Topics (repeatable component)
├── Learning Objectives (repeatable)
├── Speakers (relation selector)
├── Category (dropdown)
└── SEO fields
```

**Flow:**
1. Admin accesses Strapi (cms.gpsdentaltraining.com)
2. Click "Events" > "Create new entry"
3. Fill structured form
4. Add schedule topics (repeatable component)
5. Select existing speakers or create new
6. Click "Publish"
7. Webhook notifies Vercel → Rebuild
8. Course live in ~2 minutes

### Comparison

| Aspect | Elementor (Current) | Strapi + Astro (New) |
|--------|---------------------|----------------------|
| Create course | Duplicate page, edit | Fill structured form |
| Consistency | Can vary between pages | 100% consistent |
| Performance | Page builder JS overhead | Static HTML, ultra fast |
| SEO | Depends on plugins | Full control, optimized |
| Creation time | 30-60 min | 10-15 min |
| Requires designer | Yes, for layouts | No, uses template |
| Mobile responsive | Manual | Automatic |

---

## Visual Design

### Color Palette
```css
:root {
  /* Primary */
  --gps-navy-dark: #13326A;
  --gps-navy: #173D84;
  --gps-blue: #26ACF5;

  /* Accent/Gold */
  --gps-gold: #DDC89D;
  --gps-gold-dark: #BFAC87;

  /* Neutrals */
  --gps-bg: #F2F2F2;
  --gps-white: #FFFFFF;
  --gps-text: #333333;

  /* Actions */
  --gps-cta: #0D6EFD;
  --gps-success: #28A745;
  --gps-warning: #FFC107;
  --gps-danger: #DC3545;
}
```

### Typography
```css
/* Headers */
font-family: 'Montserrat', sans-serif;
font-weight: 500-700;

/* Body */
font-family: 'Open Sans', sans-serif;
font-weight: 400-600;
```

### Components to Create

| Component | Type | Description |
|-----------|------|-------------|
| `CourseHero.astro` | Static | Hero banner with title, date, CE badge |
| `CountdownTimer.tsx` | React Island | Dynamic timer until event |
| `TicketSelector.tsx` | React Island | Ticket selector with quantity and add to cart |
| `WaitlistForm.tsx` | React Island | AJAX waitlist form |
| `SpeakerProfile.astro` | Static | Speaker card with photo and bio |
| `CourseObjectives.astro` | Static | Objectives list with checkmarks |
| `ScheduleTabs.tsx` | React Island | Tabs for multi-day agenda |
| `ImageCarousel.tsx` | React Island | Image gallery |
| `ShareButtons.astro` | Static | Social share buttons |
| `CECreditsBadge.astro` | Static | Gold CE credits badge |

---

## Implementation Timeline

| Phase | Duration | Activities |
|-------|----------|------------|
| **Infrastructure Setup** | 2 weeks | Supabase, Strapi, Clerk, Stripe, Resend |
| **Backend Core** | 3 weeks | API routes, services, webhooks |
| **Frontend Astro** | 4 weeks | Pages, components, islands |
| **Admin Dashboard** | 2 weeks | Attendance, certificates, waitlist |
| **Data Migration** | 1 week | Scripts, validation, QA |
| **Testing** | 2 weeks | E2E, load testing, UAT |
| **Deploy & Cutover** | 1 week | DNS, monitoring, support |

**Total: ~15 weeks (3.5-4 months)**

### Implementation Order
```
1. Initial setup (Astro + Supabase + TypeScript types)
2. Complete PostgreSQL schema
3. Strapi content types
4. Basic API routes (CRUD events)
5. Clerk authentication
6. Stripe checkout flow
7. Ticket generation + QR codes
8. Email system (Resend)
9. Attendance/Check-in
10. CE Credits ledger
11. Certificates (Puppeteer)
12. Waitlist system
13. Monthly Seminars module
14. Admin dashboard
15. Frontend pages
16. Data migration scripts
17. Testing & QA
```

---

## Testing & Validation

### Validation Checklist
- [ ] End-to-end ticket purchase works
- [ ] QR codes generate and scan correctly
- [ ] Complete waitlist notification flow
- [ ] CE credits auto-award on check-in
- [ ] PDF certificates generate correctly
- [ ] Emails send with correct formatting
- [ ] Seminars: registration, session check-in, progress
- [ ] Migrated data: referential integrity verified
- [ ] My Account: all tabs show correct data
- [ ] Admin: scanner, reports, bulk operations

### Rollback Plan
1. Keep WordPress on subdomain for 2 weeks
2. Daily Supabase snapshots
3. Feature flags for gradual rollout
4. Documented emergency runbook

---

## Critical Reference Files

| File | Logic to Replicate |
|------|-------------------|
| [class-woocommerce.php](includes/class-woocommerce.php) | Order processing, ticket creation, guest linking |
| [class-activator.php](includes/class-activator.php) | Complete database schema |
| [class-waitlist.php](includes/class-waitlist.php) | Waitlist business logic, expiration, notifications |
| [class-certificates.php](includes/class-certificates.php) | PDF generation, templates |
| [class-api.php](includes/class-api.php) | REST API patterns, response formats |
| [class-tickets.php](includes/class-tickets.php) | Sold out logic, stock calculation |
| [class-attendance.php](includes/class-attendance.php) | Check-in flow, QR verification |
| [class-credits.php](includes/class-credits.php) | CE ledger immutability, auto-award |

---

## Decisions Made

- **CMS**: Strapi (confirmed)
- **Developer**: Claude AI (autonomous implementation)
- **Priority**: Complete feature parity before launch

### Advantages of New Stack
- **Performance**: Astro generates static HTML, ultra-fast loading
- **Scalability**: Supabase handles growth automatically
- **Developer Experience**: TypeScript end-to-end, better debugging
- **Maintenance**: No WordPress/plugin updates
- **Security**: Smaller attack surface, RLS in DB

### Risks to Mitigate
- **Data migration**: Exhaustive validation required
- **SEO**: Maintain URLs and redirects 1:1
- **Integrations**: Verify Elastic Email continues working
- **Testing**: Thorough testing of each flow before migration

---

## Next Steps

1. **Approve this plan**
2. **Create new repository**: `gps-dental-headless/`
3. **Begin Phase 1**: Infrastructure setup
4. **Incremental implementation** with verification checkpoints
