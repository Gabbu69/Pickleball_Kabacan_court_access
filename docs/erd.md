# Entity relationship diagram

The diagram focuses on domain data. Laravel framework tables for sessions, cache, jobs, password resets, and notifications are omitted for readability.

```mermaid
erDiagram
    USERS ||--o{ BOOKINGS : creates
    USERS }o--o{ COURTS : manages
    USERS ||--o{ OWNER_APPLICATIONS : submits
    USERS ||--o{ FAVORITES : saves
    USERS ||--o{ AUDIT_LOGS : acts

    COURTS ||--o{ COURT_UNITS : contains
    COURTS ||--o{ COURT_PHOTOS : displays
    COURTS }o--o{ AMENITIES : offers
    COURTS ||--o{ COURT_OPERATING_HOURS : opens
    COURTS ||--o{ COURT_BLACKOUTS : blocks
    COURTS ||--o{ COURT_PAYMENT_METHODS : accepts
    COURTS ||--o{ COURT_VERIFICATIONS : evidenced_by
    COURTS ||--o{ COURT_VERIFICATION_CLAIMS : verifies
    COURTS ||--o{ CONTENT_POSTS : relates_to

    COURT_UNITS ||--o{ COURT_SCHEDULE_RULES : schedules
    COURT_UNITS ||--o{ BOOKINGS : receives
    COURT_UNITS ||--o{ WAITLIST_ENTRIES : queues

    COURT_VERIFICATIONS ||--o{ COURT_VERIFICATION_CLAIMS : supports
    BOOKINGS ||--o{ BOOKING_SLOT_CLAIMS : occupies
    BOOKINGS ||--o| BOOKING_ATTENDANCES : admits
    BOOKINGS ||--o{ PAYMENTS : receives
    BOOKINGS ||--o{ PAYMENT_REFUNDS : records
    BOOKINGS ||--o| REVIEWS : permits
    PAYMENTS ||--o{ PAYMENT_REFUNDS : refunds
    WAITLIST_ENTRIES ||--o{ WAITLIST_OFFERS : receives

    USERS {
        bigint id PK
        string role
        string status
        timestamp closed_at
        string anonymized_reference UK
    }
    COURTS {
        bigint id PK
        string slug UK
        string municipality
        string verification_status
        string status
        timestamp verification_invalidated_at
        timestamp published_at
    }
    COURT_VERIFICATION_CLAIMS {
        bigint id PK
        bigint court_id FK
        bigint court_verification_id FK
        string field_key
        string status
        string value_hash
        timestamp invalidated_at
    }
    COURT_UNITS {
        bigint id PK
        bigint court_id FK
        string name
        boolean is_active
    }
    COURT_SCHEDULE_RULES {
        bigint id PK
        bigint court_unit_id FK
        int day_of_week
        time starts_at
        time ends_at
        int slot_minutes
        bigint price_centavos
    }
    BOOKINGS {
        bigint id PK
        string reference UK
        bigint user_id FK
        bigint court_unit_id FK
        datetime starts_at
        datetime ends_at
        string status
        string payment_status
        timestamp expires_at
    }
    BOOKING_SLOT_CLAIMS {
        bigint id PK
        bigint booking_id FK
        bigint court_unit_id FK
        datetime slot_starts_at
        datetime slot_ends_at
    }
    BOOKING_ATTENDANCES {
        bigint id PK
        bigint booking_id FK_UK
        string token_hash UK
        string status
        timestamp checked_in_at
    }
    PAYMENTS {
        bigint id PK
        bigint booking_id FK
        bigint amount_centavos
        string status
        string proof_disk
    }
    PAYMENT_REFUNDS {
        bigint id PK
        bigint payment_id FK
        bigint booking_id FK
        bigint amount_centavos
        timestamp processed_at
    }
    WAITLIST_ENTRIES {
        bigint id PK
        bigint user_id FK
        bigint court_unit_id FK
        datetime starts_at
        string status
    }
    WAITLIST_OFFERS {
        bigint id PK
        uuid public_id UK
        bigint waitlist_entry_id FK
        string status
        timestamp expires_at
    }
```

Critical uniqueness rules:

- `court_verifications + field_key` prevents duplicate claims for one evidence item.
- `court_unit_id + slot_starts_at` in `booking_slot_claims` prevents duplicate reservations for a generated slot.
- one `booking_attendance` and one `review` per booking.
- one waitlist entry per player, unit, and slot.
