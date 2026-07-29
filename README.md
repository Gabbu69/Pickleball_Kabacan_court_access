# Kabacan PicklePlay

Kabacan PicklePlay is an original, local-first pickleball court discovery and reservation platform for **Kabacan, Cotabato**. Players can find verified venues, inspect schedule-based availability, request a reservation, submit payment details, and manage their play history without leaving the application.

The interface, brand, booking workflow, and implementation are authored for this project. It does not embed or depend on a third-party court-booking service.

## What is included

### Players

- Registration, email verification, login, logout, profile, and notification preferences
- Kabacan-only court directory with keyword, barangay, court type, amenity, price, and date filters
- Leaflet/OpenStreetMap directory and venue maps
- Verified court facts, rights-confirmed photos, operating hours, contacts, amenities, rates, and evidence links
- Live schedule generation with date and time-slot selection
- Server-authoritative reservation pricing and atomic double-booking protection
- Cancellation windows, reservation history, favorites, and slot waitlists
- Payment-reference or private proof-of-payment submission with status tracking
- Ratings and reviews restricted to completed bookings
- Responsive navigation, loading feedback, reveal motion, card interactions, map-marker motion, and reduced-motion support

### Court owners

- Court draft creation and Kabacan-only address validation
- Photo, amenity, playable-unit, operating-hour, schedule, price, blackout, payment-method, and verification-evidence management
- Reservation approval, rejection, cancellation, completion, and payment verification
- Booking, revenue, utilization summaries, and CSV export
- Owner-application workflow with private evidence storage

### Administrators

- Publication gate for location, map coordinates, contact, actual photos, operating hours, playable units, rates, and accepted evidence
- Court verification, publication, featuring, and archival
- Owner-application review and role assignment
- User activation, review moderation, and announcement/promotion/tournament/maintenance content
- Platform metrics and audit history

## Verified-data rule

Public listings are queried only when both `status = published` and `verification_status = verified`.

The seed contains one **non-public draft reference** for the University of Southern Mindanao Outdoor Pickle Ball Court at Bai Matabay Plang Avenue, Poblacion, Kabacan, Cotabato 9407. Its name and address are sourced from the [official USM page](https://www.usm.edu.ph/portfolio-item/outdoor-pickle-ball-court/). The seed intentionally leaves unconfirmed coordinates, photos, contact details, amenities, schedules, availability, and prices blank.

Do not publish that draft—or add PickleYam or multipurpose venues—until an administrator has accepted official-page, owner, Google Maps, or field-verification evidence for every required fact.

## Stack

- PHP 8.2+ and Laravel 12
- Blade, Alpine.js, Tailwind CSS, and Vite
- Leaflet with OpenStreetMap tiles
- SQLite for local development; MySQL/MariaDB supported in production
- Laravel database queue, notifications, cache locks, and private/public filesystem disks

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate:fresh --seed
php artisan storage:link
npm run build
php artisan serve
```

On Windows PowerShell, use `Copy-Item .env.example .env` instead of `cp`.

The local administrator is configured by `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PHONE`, and `ADMIN_PASSWORD` in `.env`. Change the example password before using the account beyond local development.

Registration sends verification mail through the configured mailer. With `MAIL_MAILER=log`, the local verification link is written to `storage/logs/laravel.log`.

## Verification commands

```bash
php artisan test
vendor/bin/pint --test
npm audit
npm run build
composer audit
```

The feature suite covers authentication, publication safety, public discovery, availability, server-derived pricing, double-booking rejection, cancellation and slot reopening, owner authorization, payment verification, and completed-booking reviews.

## Storage and privacy

- Court photos use the `public` disk and require explicit rights confirmation.
- Payment proofs, court-verification evidence, and owner-application evidence use the `local` private disk.
- Private files are served only through authorization-checked routes.
- No raw payment credentials or card data are collected.

See [DEPLOYMENT.md](DEPLOYMENT.md) for the production checklist.

## Recommended next additions

Good next phases, once venue owners are participating, are:

1. SMS booking reminders and waitlist alerts through a Philippine messaging provider.
2. A real payment gateway with webhooks and automated reconciliation; keep manual proof upload as a fallback.
3. QR arrival/check-in so completion and utilization reports reflect actual play.
4. A PWA/offline shell for weak mobile connections around venues.
5. Distance-based search after users explicitly grant location access.
6. Calendar export, recurring league reservations, tournament brackets, and no-show policies.

Each addition should preserve the same evidence rule: no venue fact becomes public until its source is recorded and reviewed.
