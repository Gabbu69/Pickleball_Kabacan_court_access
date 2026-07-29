# Role and permission matrix

All protected actions require an authenticated, email-verified, active account. Owners are additionally restricted to courts present in their `court_user` management scope.

| Capability | Guest | Player | Court owner | Administrator |
|---|:---:|:---:|:---:|:---:|
| View homepage, published courts, public updates | Yes | Yes | Yes | Yes |
| View availability without private blackout reasons | Yes | Yes | Yes | Yes |
| Register and authenticate | Yes | Yes | Yes | Yes |
| Reserve/cancel own eligible booking | No | Yes | Yes | Yes |
| Submit own payment proof | No | Yes | Yes | Yes |
| View own private proof and QR pass | No | Yes | Yes | Yes |
| Favorite court / join waitlist / accept own offer | No | Yes | Yes | Yes |
| Review completed checked-in own booking | No | Yes | Yes | Yes |
| Apply to become an owner | No | Yes | Yes | Yes |
| Create/manage a court draft | No | No | Managed courts | All courts |
| Upload photos and fact-specific evidence | No | No | Managed courts | All courts |
| Configure hours, schedules, rates, blackouts, payment methods | No | No | Managed courts | All courts |
| Approve/reject/cancel/complete reservation | No | No | Managed courts | All courts |
| Verify/reject payment | No | No | Managed courts | All courts |
| Scan QR and record attendance | No | No | Managed courts | All courts |
| View/export operational reports | No | No | Managed courts | All courts |
| Accept/reject verification evidence | No | No | No | Yes |
| Publish, feature, or administratively archive court | No | No | No | Yes |
| Refund verified payment | No | No | No | Yes |
| Moderate owner applications, users, reviews, content | No | No | No | Yes |
| Demote/close final active administrator | No | No | No | Never |

## Private-file rules

- Payment proof: booking player, manager of the booked court, or administrator.
- Court verification evidence: manager of the evidence court or administrator.
- Owner-application evidence: applicant or administrator.
- Public court/content media: only approved public-store files are linked from public pages.

Private files are streamed through Laravel after authorization; raw private Blob URLs are not shown.
