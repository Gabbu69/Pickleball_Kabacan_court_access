# Test results and acceptance status

Release candidate checked locally on 2026-07-29.

## Automated results

| Check | Result |
|---|---|
| Laravel PHPUnit suite | Passed: 50 tests, 199 assertions |
| Fresh migration and seed | Passed on SQLite |
| Laravel Pint | Passed |
| Vite production build | Passed: 336 modules transformed |
| Composer validation | Passed |
| Composer locked dependency audit | Passed: no advisories |
| npm high-severity audit | Passed: zero vulnerabilities |
| Route registration | Passed: 92 application routes |
| Mobile Lighthouse — homepage | Performance 85, accessibility 100, best practices 100, SEO 100 |
| Mobile Lighthouse — court directory | Performance 97, accessibility 100, best practices 100, SEO 100 |
| Responsive browser widths | Passed at 360, 390, 768, 1024, and 1440 pixels |
| Visible interactive target size | Passed: no tested target below 44×44 pixels |
| Horizontal overflow | Passed on homepage, directory, login, and 404 mobile views |
| Browser console | No errors or warnings on tested public pages |

GitHub Actions additionally defines a PostgreSQL 16 migration and test job. That result becomes confirmed only after the branch is pushed and the remote workflow completes.

## Covered behaviors

- authentication, verification, suspension, and profile workflows;
- account closure/anonymization and final-administrator protection;
- publication blocking for incomplete or unverified courts;
- field-claim invalidation after a verified fact changes;
- public directory and availability behavior;
- server-derived schedule and price;
- duplicate slot rejection through a unique claim;
- operating-hour and overlapping-rule validation;
- private blackout reason suppression;
- pending booking expiry and slot release;
- cancellation and waitlist promotion;
- 15-minute offer expiry and next-player advancement;
- partial payment, duplicate-proof prevention, verification, rejection, and refunds;
- private payment-proof authorization;
- QR token validity, owner scope, time window, and idempotent check-in;
- checked-in completion and unclaimed no-show processing;
- completed-and-checked-in review eligibility;
- health and secured maintenance routes.

## Manual acceptance still required after deployment

These checks cannot be honestly marked complete until Vercel, Neon, Blob, and secrets exist:

- PostgreSQL GitHub Actions result;
- Preview and Production deploys;
- separate Preview/Production data and Blob isolation;
- real email delivery, if enabled;
- private Vercel Blob upload/streaming;
- live QR camera permissions on a physical mobile device;
- complete player, owner, and administrator production journeys;

## Local browser verification

- The redesigned logo rendered at 48×48 pixels on desktop and retained a clear compact wordmark on mobile.
- The mobile menu opened with an accurate expanded state and exposed all navigation/account actions.
- All directory form controls had accessible labels.
- Pause/resume stopped the supplied video and CSS motion and persisted after reload.
- The supplied hero video loaded successfully, remained muted, and played only after its media area approached the viewport.
- The original night-rally animation showed continuous frame-to-frame movement; the ball reset occurred while hidden.
- The branded 404 page rendered at 390 pixels without overflow.

Lighthouse was run against the local production Vite build with mobile emulation. Scores can vary slightly by machine and network, so the release workflow should record a fresh report on the deployed candidate.
