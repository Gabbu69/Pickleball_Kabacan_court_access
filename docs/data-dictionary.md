# Data dictionary

Money is stored as integer centavos, timestamps are interpreted in `Asia/Manila`, and public UUID/random-token identifiers are used where sequential IDs should not be exposed.

| Table | Purpose | Critical fields and rules |
|---|---|---|
| `users` | Authentication and platform identity | `role`, `status`, `closed_at`; account closure replaces personal fields and stores a unique `anonymized_reference`. |
| `courts` | Venue-level verified listing | Kabacan address, coordinates, type, contacts, publication/verification states; publication and location composite indexes. |
| `court_user` | Scoped owner/manager access | Composite primary key on court and user; owners can act only on managed courts. |
| `owner_applications` | Player request to become a venue owner | Private evidence storage metadata, moderation state, reviewer, and review timestamp. |
| `court_units` | Individually bookable playing surfaces | Belongs to a venue; can be deactivated without deleting history. |
| `court_photos` | Rights-confirmed real venue media | Storage disk/URL/MIME/size, alt text, primary flag, and `rights_confirmed_at`. |
| `amenities` | Controlled amenity vocabulary | Unique slug and optional icon key. |
| `amenity_court` | Venue-to-amenity assignment | Composite primary key prevents duplicate assignments. |
| `court_operating_hours` | Weekly venue opening limits | One row per venue/day; open, close, and closed flag. |
| `court_schedule_rules` | Sellable slots and prices | Unit/day window, slot length, price in centavos, effective dates, active state; overlap is validated before write. |
| `court_blackouts` | Maintenance or unavailable periods | Venue or unit scope, start/end, reason, and `is_public`; private reasons never enter public availability JSON. |
| `court_payment_methods` | Owner-confirmed payment instructions | Type, public label, account reference, instructions, active state. |
| `court_verifications` | Evidence submission | Source type/URL/notes, private evidence metadata, submitter/reviewer, moderation state. |
| `court_verification_claims` | Fact-level evidence link | Field key, evidence relation, value hash, state, verifier, timestamps; accepted claims are invalidated after matching facts change. |
| `bookings` | Reservation lifecycle and immutable price snapshot | Unique reference, player/court/unit/rule, start/end, status, derived payment status, expiry, cancellation, approval, completion, and no-show audit fields. |
| `booking_slot_claims` | Database concurrency guard | Unique unit plus slot start; deleted when a reservation no longer occupies the slot. |
| `booking_attendances` | QR pass and check-in result | One row per booking, unique SHA-256 token hash, status, actor, check-in time, IP, and user agent. Raw QR tokens are never stored. |
| `payments` | Player payment submissions | Amount, optional method/reference/proof, private storage metadata, review status, reviewer, and review time. |
| `payment_refunds` | Non-destructive refund ledger | Payment and booking references, amount, external reference, reason, processor, and processed time. |
| `reviews` | Verified-player feedback | One row per booking; requires completed checked-in attendance and enters moderation as `pending`. |
| `favorites` | Saved courts | Composite primary key on player and court. |
| `waitlist_entries` | Ordered demand for a booked slot | Unique player/unit/start, requested interval, status, and notification time. |
| `waitlist_offers` | Fifteen-minute priority invitation | Public UUID, active/accepted/expired state, offered/expiry/acceptance timestamps. |
| `content_posts` | Announcements, promotions, tournaments, maintenance | Optional verified court relation, schedule, publication state, and public media storage metadata. |
| `audit_logs` | Security and business audit trail | Actor, action, polymorphic subject, metadata, IP address, and creation time. |
| `notifications` | In-app database notifications | Laravel UUID notification rows with payload and read timestamp. |

## Key statuses

| Entity | Values used by the application |
|---|---|
| User role | `player`, `owner`, `admin` |
| User status | `active`, `suspended`, `closed` |
| Court | `draft`, `pending_verification`, `published`, `archived` |
| Booking | `pending`, `confirmed`, `rejected`, `cancelled`, `expired`, `completed`, `no_show` |
| Payment | `unpaid`, `submitted`, `partially_paid`, `verified`, `rejected`, `refunded` |
| Attendance | `issued`, `checked_in`, `revoked` |
| Waitlist offer | `active`, `accepted`, `expired` |
| Verification/review moderation | `pending`, `accepted` or `published`, `rejected` or `hidden` |

## Derived values

- Outstanding balance = booking price minus verified collections plus refunds.
- Net revenue = verified collections minus refunds.
- Utilization = reserved minutes for confirmed/completed/no-show bookings divided by generated sellable minutes.
- Completion and cancellation rates are calculated separately.
