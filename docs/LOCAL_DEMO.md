# Isolated fictional booking demonstration

This optional local demonstration contains one fictional venue, an authored court diagram, and two fictional accounts. Its coordinates, address, rates, verification record, and availability are synthetic. Do not use them for travel, payments, or real reservations. The normal database seeder still leaves real venue candidates unpublished.

Use a separate checkout and a new SQLite file. Never point this demonstration at an operational database. Install the locked PHP and frontend dependencies first, following the main README. Required PHP extensions include fileinfo, PDO SQLite, mbstring, and the normal Laravel requirements.

In PowerShell, from the project directory:

```powershell
Copy-Item .env.example .env
php artisan key:generate
$env:APP_ENV = 'local'
$env:PICKLEPLAY_DEMO = 'true'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = Join-Path (Get-Location) 'database/pickleplay.demo.sqlite'
$env:MAIL_MAILER = 'log'
$env:QUEUE_CONNECTION = 'sync'
if (-not (Test-Path -LiteralPath $env:DB_DATABASE)) {
    New-Item -ItemType File -Path $env:DB_DATABASE | Out-Null
}
php artisan config:clear
php artisan migrate --force
php artisan db:seed --class=LocalDemoSeeder --force
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open `http://127.0.0.1:8000`. Demo mode refuses production, external mail providers, ordinary database files, and non-loopback HTTP requests. The fixture seeder refuses databases containing other users or courts. Keep these environment variables set while serving: a `.demo.sqlite` file with demo mode disabled is also rejected. Do not expose this server through a tunnel or deploy these known accounts.

1. Browse **Fictional Demo Court - Not a real venue** and inspect its explicitly fictional illustration and map point.
2. Sign in as `player@pickleplay.example.invalid` with password `local-demo-only-2026`.
3. Pick a date at least two days ahead, then reserve an available 18:00, 19:00, or 20:00 slot. Each one-hour slot costs a fictional PHP 275. Keep the generated reference; the booking starts pending.
4. Sign out, then sign in as `owner@pickleplay.example.invalid` with the same local demo password. Open owner bookings and confirm the request.
5. Sign in as the player again. Check the confirmed status, then cancel while more than four hours remain. Verify the slot becomes available again.

No real payment or email delivery belongs in this walkthrough. Re-running the seeder preserves the single venue and its two accounts. To restart with no bookings, stop the server and create a new dedicated demo SQLite file; never reset an operational database.

## Verification boundary

`tests/Feature/LocalDemoTest.php` exercises the isolated fixture and its safeguards, then discovery, availability, server-priced reservation, owner confirmation, and cancellation. Run `php artisan test --filter=LocalDemoTest`. Automated HTTP checks do not substitute for phone layout, accessibility, or real venue/operator acceptance.
