# Vercel deployment guide

This release is designed as one Vercel Hobby-compatible Laravel application connected to Neon PostgreSQL and two Vercel Blob stores.

## Important platform boundary

Vercel lists PHP under community runtimes rather than its first-party runtimes. This project pins [`vercel-php@0.6.2`](https://github.com/vercel-community/php), which targets PHP 8.2.x. Review the [Vercel runtime documentation](https://vercel.com/docs/functions/runtimes) before upgrading Laravel, PHP, or the runtime package.

Vercel Functions have an ephemeral filesystem. `api/index.php` redirects compiled views and framework caches to `/tmp`; no user, payment, court, or evidence record may depend on that storage.

## 1. Provision isolated data services

Create separate Preview and Production resources:

- Neon PostgreSQL database/branch with a pooled URL for application traffic.
- Neon direct URL for migration jobs.
- Public Vercel Blob store for approved court photos and content images.
- Private Vercel Blob store for payment proofs and verification/owner evidence.

The stores must never be shared between Preview and Production. See [Vercel Marketplace storage](https://vercel.com/docs/marketplace-storage) and the [Blob documentation](https://vercel.com/docs/vercel-blob).

## 2. Import the GitHub repository

Import `Gabbu69/Pickleball_Kabacan_court_access` in Vercel using the [GitHub integration](https://vercel.com/docs/git/vercel-for-github).

Project settings:

- Framework preset: Other
- Build command: leave empty; the committed `public/build` assets are used
- Output directory: leave empty
- Install command: leave empty
- Root directory: repository root

`vercel.json` routes static media and Vite assets directly and sends all other requests to `api/index.php`.

## 3. Generate the application key

Generate once on a trusted machine:

```bash
php artisan key:generate --show
```

Store the returned value as `APP_KEY`. Do not commit it.

## 4. Configure Vercel environment variables

Configure Production and Preview separately.

| Variable | Production value |
|---|---|
| `APP_NAME` | `Kabacan PicklePlay` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | generated secret |
| `APP_URL` | production domain |
| `APP_TIMEZONE` | `Asia/Manila` |
| `DB_CONNECTION` | `pgsql` |
| `DATABASE_URL` | pooled Neon URL |
| `DB_SSLMODE` | `require` |
| `SESSION_DRIVER` | `cookie` |
| `SESSION_ENCRYPT` | `true` |
| `SESSION_SECURE_COOKIE` | `true` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `sync` |
| `LOG_CHANNEL` | `stderr` |
| `BLOB_PUBLIC_READ_WRITE_TOKEN` | public-store token |
| `BLOB_PRIVATE_READ_WRITE_TOKEN` | private-store token |
| `CRON_SECRET` | random bearer secret |
| `ADMIN_NAME` | initial administrator name |
| `ADMIN_EMAIL` | initial administrator email |
| `ADMIN_PHONE` | optional phone |
| `ADMIN_PASSWORD` | unique password of at least 12 characters |

Optional mail variables can be supplied when a provider is available. The default log mailer is not email delivery.

## 5. Configure GitHub Actions

Create the `production` GitHub Environment and add:

### Encrypted secrets

- `APP_KEY`
- `NEON_DIRECT_DATABASE_URL`
- `VERCEL_TOKEN`
- `VERCEL_ORG_ID`
- `VERCEL_PROJECT_ID`
- `ADMIN_NAME`
- `ADMIN_EMAIL`
- `ADMIN_PHONE`
- `ADMIN_PASSWORD`

### Environment variable

- `APP_URL`

The Vercel project itself supplies the pooled `DATABASE_URL` and Blob tokens to the deployed function. The GitHub workflow uses the direct Neon URL only for additive migrations and admin bootstrap.

## 6. Protect `main`

In GitHub repository settings, add a branch protection rule for `main`:

- Require a pull request before merging.
- Require branches to be up to date.
- Require status checks:
  - `Quality and SQLite`
  - `PostgreSQL integration`
- Prevent force pushes and branch deletion.

## 7. Release flow

`.github/workflows/deploy-vercel.yml` performs:

1. dependency installation;
2. Pint, PHPUnit, build, and dependency audits;
3. additive PostgreSQL migrations through the direct Neon connection;
4. idempotent administrator bootstrap;
5. a production build and unaliased staged deployment;
6. `/api/health` plus homepage smoke tests;
7. promotion only after those tests pass.

This follows Vercel’s [CLI deployment](https://vercel.com/docs/cli/deploy) and [promotion](https://vercel.com/docs/cli/promote) workflow.

## 8. Cron behavior

`vercel.json` schedules `/api/cron/maintenance` at `0 18 * * *`, which is 2:00 AM in Asia/Manila. The route requires Vercel’s `Authorization: Bearer <CRON_SECRET>` request.

Hobby cron execution is not precise enough for 12-hour booking or 15-minute waitlist correctness. Therefore, maintenance also runs during relevant booking, availability, payment, pass, and waitlist requests. The cron is cleanup redundancy only. See [Vercel Cron management](https://vercel.com/docs/cron-jobs/manage-cron-jobs).

## 9. Production verification

After the first promoted deployment:

```bash
curl --fail https://YOUR_DOMAIN/api/health
```

Expected response fields include `"application":"ready"` and `"database":"ready"`.

Then complete these role-based checks using non-production venue data until an owner supplies verified evidence:

1. register and verify a player;
2. create an owner application;
3. approve the owner as admin;
4. create a private court draft;
5. submit and accept evidence for each fact;
6. publish the verified listing;
7. reserve an available slot and upload payment proof;
8. verify payment and approve the reservation;
9. open and scan the QR pass within the check-in window;
10. process the ended reservation and submit/moderate a review.

## 10. Rollback

- Application: promote the last known-good Vercel deployment.
- Database: migrations are additive; deploy a corrective forward migration. Do not run destructive production rollback commands.
- Media: do not delete a previous Blob until its database replacement succeeds.

Never use a Preview database or Blob token in Production, and never expose private Blob URLs directly.
