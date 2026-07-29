# Kabacan PicklePlay

Kabacan PicklePlay is an original, mobile-first pickleball court discovery and reservation platform built specifically for Kabacan, Cotabato. It replaces the previous third-party booking dependency with a first-party Laravel workflow for verified venue publication, live schedule selection, reservations, payment review, waitlists, QR attendance, reviews, and reporting.

No Court Access source code, branding, proprietary content, or page layout is used.

## What the application does

### Players

- Register, verify an email address, sign in, manage a profile, or close and anonymize an account.
- Search published Kabacan courts by name, barangay, date, court type, price, and amenities.
- Browse verified facts, actual rights-confirmed photos, operating hours, contact details, rates, maps, and availability.
- Reserve a server-validated slot protected by a database uniqueness constraint.
- Submit partial or full payment information and optional private proof files.
- Cancel eligible reservations, join waitlists, and accept 15-minute priority offers.
- Present a personal-data-free QR booking pass after approval.
- Review a venue only after a completed, checked-in reservation.

### Court owners

- Follow a nine-step setup checklist covering identity and map, media, amenities, courts and rates, hours, availability, payment, verification, and publication.
- Manage playable court units, schedule rules, operating hours, prices, public/private blackouts, payment methods, and real venue photos.
- Submit field-specific verification evidence without exposing private evidence files.
- Approve, reject, cancel, complete, and check in reservations for managed courts only.
- Verify or reject submitted payments.
- View utilization, completion, cancellation, gross collection, refund, net revenue, pending-payment, and no-show metrics.
- Export formula-safe CSV booking reports.

### Administrators

- Moderate field-level court claims, owner applications, payment refunds, reviews, users, announcements, promotions, tournaments, and maintenance notices.
- Publish a listing only after all required facts have accepted evidence.
- Archive listings without deleting booking history.
- Keep at least one active administrator at all times.
- Preserve financial records when accounts are closed by anonymizing personal data instead of deleting users.

## Verification-first publication

A court is public only when:

1. It is physically within Kabacan, Cotabato.
2. It has a verified address, coordinates, classification, operating hours, rental rate, schedule, contact, rights-confirmed photo, and amenities claim.
3. Every required `court_verification_claim` is accepted and still matches the stored court data.
4. It has an active playable unit and valid schedule.
5. An administrator explicitly publishes it.

Editing an accepted fact invalidates its claim and removes the listing from public discovery until it is reviewed again.

The seed includes one admin-only draft for the University of Southern Mindanao Outdoor Pickle Ball Court. Its name and Bai Matabay Plang Avenue address are linked to the [official USM page](https://www.usm.edu.ph/portfolio-item/outdoor-pickle-ball-court/). No unconfirmed rate, hours, phone number, coordinates, amenities, availability, or reusable photo is seeded.

## Technology

- PHP 8.2+ and Laravel 12
- Blade, Alpine.js, Tailwind CSS, and Vite
- Leaflet with OpenStreetMap tiles, loaded only on map routes
- ZXing browser scanner and QRCode, loaded only for pass/check-in pages
- SQLite for local development and fast tests
- PostgreSQL/Neon for preview and production
- Vercel Blob for public venue media and private payment/verification evidence
- Vercel Functions through pinned `vercel-php@0.6.2`

## Local setup

Requirements: PHP 8.2+, Composer, Node.js 22+, and npm.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

On PowerShell, use `Copy-Item .env.example .env` in place of `cp`. Open [http://127.0.0.1:8000](http://127.0.0.1:8000).

The local administrator comes from `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PHONE`, and `ADMIN_PASSWORD`. Change the sample password before sharing the application. Production uses the idempotent command:

```bash
php artisan app:bootstrap-production-admin
```

## Quality checks

```bash
vendor/bin/pint --test
php artisan test
npm run build
npm audit --audit-level=high
composer validate --strict
composer audit --locked
```

The current local release candidate passes 50 tests with 199 assertions. GitHub Actions repeats the suite on SQLite and PostgreSQL 16.

## Vercel deployment

This repository contains:

- `api/index.php` as the Laravel function entry point.
- `api/php.ini` and `vercel.json` for the pinned PHP 8.2 community runtime.
- committed Vite assets under `public/build` for deterministic static delivery.
- a secured daily maintenance cron route.
- GitHub Actions for quality checks and staged production deployment.

Vercel classifies PHP as a community runtime, so this is a practical Hobby-compatible deployment target rather than first-party Laravel hosting. Production data must use Neon PostgreSQL and Vercel Blob; Vercel’s temporary filesystem is used only for framework caches.

Follow [DEPLOYMENT.md](DEPLOYMENT.md) for project creation, environment variables, preview isolation, migration, smoke-test, promotion, and branch-protection steps.

## Documentation

- [Architecture](docs/architecture.md)
- [Entity relationship diagram](docs/erd.md)
- [Data dictionary](docs/data-dictionary.md)
- [Role and permission matrix](docs/role-permission-matrix.md)
- [Verified-data register](docs/verified-data-register.md)
- [Test results and acceptance status](docs/test-results.md)

## Scope boundaries

Online payment gateways, SMS, tournament brackets, non-Kabacan venues, precise sub-hour background jobs, and a full offline PWA are intentionally outside this release. Manual payment evidence and synchronous database notifications are supported; failed notifications never roll back a successful booking.
