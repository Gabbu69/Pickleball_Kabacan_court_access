# Production deployment

Kabacan PicklePlay runs on a standard Laravel host: a PHP/MySQL shared host, VPS, Laravel Forge, Ploi, or equivalent. Point the web root to the repository's `public` directory.

## Requirements

- PHP 8.2 or newer with `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `mbstring`, `openssl`, `pdo_mysql` or `pdo_sqlite`, `tokenizer`, `xml`, and `zip`
- Composer 2
- MySQL/MariaDB for production
- Node.js 20+ to build frontend assets
- A scheduler entry and persistent queue worker
- HTTPS

## Environment

Copy `.env.example` to `.env`, generate a unique key, and configure at least:

```env
APP_NAME="Kabacan PicklePlay"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kabacan_pickleplay
DB_USERNAME=replace_me
DB_PASSWORD=replace_me

ADMIN_NAME="Platform Administrator"
ADMIN_EMAIL=admin@your-domain.example
ADMIN_PHONE=09XXXXXXXXX
ADMIN_PASSWORD=use-a-long-unique-password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=replace_me
MAIL_PORT=587
MAIL_USERNAME=replace_me
MAIL_PASSWORD=replace_me
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@your-domain.example
MAIL_FROM_NAME="${APP_NAME}"
```

Do not commit `.env`, API credentials, uploaded evidence, payment proofs, or database backups.

## Release commands

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan storage:link
php artisan migrate --force
php artisan db:seed --force
php artisan optimize
```

Seeding is idempotent. It creates the administrator, amenity vocabulary, and a non-public USM reference draft; it does not publish invented venue data.

Production seeding stops with an error when `ADMIN_PASSWORD` is still `password` or contains fewer than 12 characters.

Run before promotion:

```bash
php artisan test
vendor/bin/pint --test
composer audit
npm audit
```

## Background processes

Run the queue under a process supervisor:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

Add Laravel's scheduler to cron:

```cron
* * * * * cd /path/to/kabacan-pickleplay && php artisan schedule:run >> /dev/null 2>&1
```

Restart workers after every release:

```bash
php artisan queue:restart
```

## Files and permissions

- Make `storage` and `bootstrap/cache` writable by the web process.
- Keep `storage/app/private` inaccessible from the public web root.
- Expose only `storage/app/public` through `php artisan storage:link`.
- Confirm that payment-proof and verification downloads return `403` for unrelated accounts.
- Configure upload limits of at least 8 MB in PHP and the reverse proxy.

## Launch checklist

1. Replace the example administrator password and verify the administrator email.
2. Configure SMTP and test registration, verification, reservation, payment, and waitlist notifications.
3. Create the queue supervisor and scheduler.
4. Test database, `storage/app/private`, and public-photo backups plus restoration.
5. Review retention rules for identity evidence and payment proofs.
6. Verify cancellation language, privacy notice, terms, and venue-owner agreements with the project owner.
7. Check mobile layouts, map tile loading, keyboard navigation, and reduced-motion mode.
8. Verify every public venue against recorded evidence. Do not publish incomplete seed data.
9. Configure uptime monitoring and application-error alerts.

## Rollback

Keep the previous release directory and a database backup. Application code can be rolled back to the prior release; database rollback should be done only after reviewing the migration and restoring from a tested backup when necessary.
