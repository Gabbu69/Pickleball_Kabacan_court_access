# Architecture

## Runtime overview

```mermaid
flowchart LR
    U["Player / owner / administrator"] --> CDN["Vercel CDN and static assets"]
    CDN --> PHP["api/index.php<br/>vercel-php 0.6.2"]
    PHP --> L["Laravel 12 application"]
    L --> PG["Neon PostgreSQL<br/>pooled application connection"]
    L --> PB["Public Vercel Blob<br/>approved venue media"]
    L --> VB["Private Vercel Blob<br/>payment and evidence files"]
    L --> MAIL["Synchronous optional mail<br/>failure is non-blocking"]
    CRON["Daily Vercel Cron"] --> PHP
    GA["GitHub Actions release"] --> DIRECT["Neon direct connection<br/>additive migrations"]
    GA --> STAGE["Unaliased Vercel candidate"]
    STAGE --> SMOKE["HTTP and database smoke tests"]
    SMOKE --> PROMOTE["Promote tested candidate"]
```

Static CSS, JavaScript, fonts, images, and video are served from `public`. Dynamic requests enter Laravel through `api/index.php`. Framework-generated temporary files use `/tmp` on Vercel; durable state uses PostgreSQL or Blob.

## Domain boundaries

```mermaid
flowchart TD
    VERIFY["Verification service"] --> COURT["Published court scope"]
    COURT --> AVAIL["Availability service"]
    AVAIL --> BOOK["Booking service"]
    BOOK --> CLAIM["Unique slot claim"]
    BOOK --> WAIT["Waitlist service"]
    BOOK --> PAY["Payment service"]
    BOOK --> ATT["Attendance service"]
    PAY --> REPORT["Reporting service"]
    ATT --> MAINT["Maintenance service"]
    WAIT --> MAINT
    BOOK --> MAINT
    MAINT --> REVIEW["Completed checked-in review eligibility"]
```

### Verification

Each evidence record links to one or more field claims. Accepted claims store a hash of the fact at review time. Editing the fact invalidates the claim and unpublishes the court.

### Availability and booking

Schedule slots are generated in `Asia/Manila` from operating hours and active schedule rules, then filtered by blackouts and occupying bookings. Price and time are selected again on the server. A transaction inserts both the booking and its unique `(court_unit_id, slot_starts_at)` claim, making the database the final concurrency guard.

### Payments

Payment status is derived from verified collections minus refunds. Only one submitted proof may await review at a time, and submissions cannot exceed the remaining balance. Private proof media is served through policy-protected Laravel routes.

### Attendance

The QR payload carries only a random token prefixed with `KPP-CHECKIN:`. Only its SHA-256 hash is stored. A manager of the booked court can check in a confirmed booking from 30 minutes before until 30 minutes after its start.

### Maintenance

Request-driven maintenance expires pending reservations, releases slot claims, advances waitlists, expires priority offers, and marks ended confirmed reservations as completed or no-show. Daily Vercel Cron supplies cleanup redundancy.

## Frontend performance

- Leaflet loads only for pages containing map components.
- QR generation and scanning load only on pass/scanner pages.
- Hero video includes a poster, mobile crop, pause control, and reduced-motion fallback.
- Court images use responsive source sets and Vercel Image Optimization when backed by public Blob.
- Reveal content remains visible when JavaScript fails.
