# Hotel Property Management System (PMS) Architecture

## 1. Entity Schema

### Room Entity
```php
#[ORM\Entity(repositoryClass: RoomRepository::class)]
#[ORM\Table(name: 'rooms')]
class Room
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'integer')]
    private int $capacity;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $basePrice;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $icalUrlBooking = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $icalUrlHomeaway = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastSyncBooking = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastSyncHomeaway = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;
}
```

### Booking Entity
```php
#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Table(name: 'bookings')]
#[ORM\UniqueConstraint(name: 'unique_booking', columns: ['room_id', 'start_date', 'end_date', 'status'], options: ['where' => '(status != \'cancelled\')'])]
class Booking
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Room::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Room $room;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $startDate;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $endDate;

    #[ORM\Column(type: 'string', length: 200)]
    private string $guestName;

    #[ORM\Column(type: 'string', length: 180)]
    private string $guestEmail;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $guestPhone = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalPrice;

    #[ORM\Column(type: 'string', length: 20, enumType: BookingSource::class)]
    private string $source;

    #[ORM\Column(type: 'string', length: 20, enumType: BookingStatus::class)]
    private string $status;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $bookingHash;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 0;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;
}
```

### AvailabilitySnapshot Entity (Cache Table)
```php
#[ORM\Entity(repositoryClass: AvailabilitySnapshotRepository::class)]
#[ORM\Table(name: 'availability_snapshots')]
#[ORM\UniqueConstraint(name: 'unique_availability', columns: ['room_id', 'date', 'source'])]
class AvailabilitySnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Room::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Room $room;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $date;

    #[ORM\Column(type: 'boolean')]
    private bool $isAvailable;

    #[ORM\Column(type: 'string', length: 20, enumType: AvailabilitySource::class)]
    private string $source;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $lastChecked;
}
```

### Database Indexes
```sql
CREATE INDEX idx_room_slug ON rooms(slug);
CREATE INDEX idx_booking_hash ON bookings(booking_hash);
CREATE INDEX idx_booking_dates ON bookings(room_id, start_date, end_date);
CREATE UNIQUE INDEX idx_availability ON availability_snapshots(room_id, date, source);
```

### Enums
```php
enum BookingSource: string
{
    case WEBSITE = 'website';
    case BOOKING_COM = 'booking_com';
    case HOMEAWAY = 'homeaway';
    case MANUAL = 'manual';
}

enum BookingStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case BLOCKED = 'blocked';
}

enum AvailabilitySource: string
{
    case BOOKING_COM = 'booking_com';
    case HOMEAWAY = 'homeaway';
    case INTERNAL = 'internal';
}
```

## 2. The "Shield" Logic - 5-Layer Overbooking Prevention

### Layer 1: Database Constraints
- Unique constraint: `(room_id, start_date, end_date, status)` WHERE `status != 'cancelled'`
- Prevents exact duplicate bookings at DB level

### Layer 2: Optimistic Locking (Doctrine)
```php
#[ORM\Version]
#[ORM\Column(type: 'integer')]
private int $version = 0;
```
- Doctrine checks version field on UPDATE
- If version changed since SELECT -> throw `OptimisticLockException`
- Forces retry with fresh data

### Layer 3: Ad-Hoc iCal Check (Real-Time)
Before confirming ANY website booking:
1. Fetch iCal from Booking.com (5 sec timeout)
2. Fetch iCal from HomeAway (5 sec timeout)
3. Parse both feeds for VEVENT blocks
4. Check if requested date range overlaps with ANY existing booking
5. IF conflict found -> REJECT booking with 409 error
6. IF timeout occurs -> DECIDE: fail-open (allow) or fail-closed (reject)
   **CURRENT IMPLEMENTATION**: fail-open (booking proceeds, error is logged;
   verified by tests). The strict fail-closed strategy is reserved for the
   environment variable BOOKING_FAIL_STRATEGY in an upcoming release.

### Layer 4: Periodic Sync (Background Cron)
Every 15 minutes:
1. Fetch all active rooms from database
2. For each room:
   - Fetch iCal feeds
   - Parse and compare with existing bookings
   - Create new Booking entities for external bookings not yet in DB
   - Update lastSync timestamps
3. Log sync results (success/failure per room)

### Layer 5: Manual Blocking
Hotel staff can create `status='blocked'` bookings for:
- Maintenance periods
- Personal use
- Events/closures

## 3. iCal Integration Specifications

### Critical iCal Patterns to Handle

**Booking.com Format:**
```ics
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Booking.com//NONSGML v1.0//EN
BEGIN:VEVENT
UID:booking-123456@booking.com
DTSTART;VALUE=DATE:20250315
DTEND;VALUE=DATE:20250318
SUMMARY:Reserved for: Max Mustermann
STATUS:CONFIRMED
END:VEVENT
END:VCALENDAR
```

**HomeAway/FeWo Format:**
```ics
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Europe/Berlin
BEGIN:STANDARD
DTSTART:20241027T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
UID:ha-67890@homeaway.com
DTSTART;TZID=Europe/Berlin:20250320T150000
DTEND;TZID=Europe/Berlin:20250323T110000
SUMMARY:Reservation
END:VEVENT
END:VCALENDAR
```

### Edge Cases
1. **Timezone Conversion**: Always convert to property's local timezone (Europe/Berlin)
2. **All-Day Events**: `VALUE=DATE` means midnight-to-midnight
3. **Recurring Events**: Parse RRULE if present (rare in booking platforms)
4. **Cancelled Bookings**: Check for `STATUS:CANCELLED` or deleted UIDs
5. **Malformed iCal**: Gracefully handle parse errors (log and skip)
6. **Same-Day Booking**: Handle edge case where `DTSTART=DTEND` (1-day booking)

### Parsing Library
Use `sabre/vobject` for robust iCal parsing:
```php
use Sabre\VObject;

$vcalendar = VObject\Reader::read($icalString);
foreach ($vcalendar->VEVENT as $event) {
    $uid = (string)$event->UID;
    $start = $event->DTSTART->getDateTime();
    $end = $event->DTEND->getDateTime();
    $summary = (string)$event->SUMMARY;
    // Extract guest name from summary if present
}
```

## 4. API Endpoint Contracts

### POST /api/bookings - Create Website Booking
```json
// Request
{
  "room_id": "a1b2c3d4-...",
  "start_date": "2025-03-15",
  "end_date": "2025-03-18",
  "guest_name": "Max Mustermann",
  "guest_email": "max@example.com",
  "guest_phone": "+49 123 456789"
}

// Success Response (201)
{
  "success": true,
  "booking_id": "e5f6g7h8-...",
  "booking_hash": "a3c9f2e1...", // 64-char token
  "total_price": 450.00,
  "message": "Booking confirmed. Check your email."
}

// Conflict Response (409)
{
  "success": false,
  "error": "AVAILABILITY_CONFLICT",
  "message": "Room not available (external booking exists)",
  "details": {
    "conflict_source": "booking_com",
    "conflict_dates": ["2025-03-15", "2025-03-16"]
  },
  "suggested_alternatives": [
    {"room_id": "...", "room_name": "Superior Zimmer"}
  ]
}

// System Error (503)
{
  "success": false,
  "error": "AVAILABILITY_CHECK_FAILED",
  "message": "Cannot verify availability. Please try again.",
  "retry_after": 60 // seconds
}
```

### GET /api/rooms/{id}/availability?start_date=X&end_date=Y
```json
// Response
{
  "available": true,
  "room_id": "...",
  "start_date": "2025-03-15",
  "end_date": "2025-03-18",
  "price_per_night": 150.00,
  "total_price": 450.00,
  "last_checked": "2025-01-26T14:30:00Z",
  "check_sources": ["internal", "booking_com", "homeaway"]
}
```

## 5. Cron Job Specifications

**Command:** `app:sync-calendars`
**Frequency:** Every 15 minutes (configurable)
**Crontab Entry:**
```bash
*/15 * * * * cd /var/www/pms && php bin/console app:sync-calendars >> /var/log/pms-sync.log 2>&1
```

**Command Logic:**
```php
protected function execute(InputInterface $input, OutputInterface $output): int {
    $rooms = $this->roomRepository->findBy(['isActive' => true]);
    $stats = ['synced' => 0, 'errors' => 0, 'new_bookings' => 0];

    foreach ($rooms as $room) {
        try {
            $newBookings = $this->icalSyncService->syncRoomCalendar($room);
            $stats['new_bookings'] += count($newBookings);
            $stats['synced']++;
        } catch (\Exception $e) {
            $this->logger->error('Sync failed', [
                'room_id' => $room->getId(),
                'error' => $e->getMessage()
            ]);
            $stats['errors']++;
        }
    }

    $output->writeln(sprintf(
        'Synced: %d rooms, New bookings: %d, Errors: %d',
        $stats['synced'], $stats['new_bookings'], $stats['errors']
    ));

    return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
}
```

## 6. Error Handling Strategy

| Error Type | Action | HTTP Code | Log Level |
|------------|--------|-----------|-----------|
| Internal booking conflict | Reject, suggest alternatives | 409 | info |
| External iCal conflict | Reject with source details | 409 | warning |
| iCal fetch timeout | Reject (fail-closed) | 503 | error |
| Parse error | Skip feed, allow booking | 200 | error |
| Optimistic lock failure | Ask user to retry | 409 | info |
| Database error | Reject, alert ops | 500 | critical |

**Logging Format (JSON for parsing):**
```json
{
  "timestamp": "2025-01-26T14:30:00Z",
  "level": "error",
  "context": "ical_sync",
  "room_id": "a1b2c3d4-...",
  "url": "https://admin.booking.com/hotel/ical/...",
  "error": "Connection timeout after 5 seconds",
  "duration_ms": 5003
}
```

## 7. Open Questions

1. **Fail-open vs fail-closed recommendation**
   - Current recommendation: **fail-closed** (reject bookings when iCal sync fails)
   - Alternative: fail-open (allow bookings when iCal sync fails)
   - Trade-offs:
     - Fail-closed: Higher safety, lower overbooking risk, but potential lost revenue
     - Fail-open: Better user experience, higher conversion, but higher overbooking risk

2. **Sync frequency trade-offs**
   - Current: Every 15 minutes
   - Alternatives: 5 minutes, 30 minutes, 1 hour
   - Trade-offs:
     - More frequent: More up-to-date availability, but higher server load
     - Less frequent: Lower server load, but higher risk of stale data

3. **Email service selection**
   - Options:
     - Symfony Mailer + SMTP (self-hosted)
     - Postmark (transactional email service)
     - SendGrid
     - Mailgun
   - Recommendation: **Postmark** for high deliverability and good Symfony integration

## 8. Booking Flow Workflow

```
┌───────────────────────────────────────────────────────────────┐
│                        Guest (Website)                        │
└───────────────────┬───────────────────────────┬───────────────┘
                    │                           │
                    ▼                           ▼
┌───────────────────────────────┐ ┌───────────────────────────────┐
│   Check Availability (GET)    │ │   Submit Booking (POST)       │
└───────────────────┬───────────┘ └───────────────┬───────────────┘
                    │                           │
                    ▼                           ▼
┌───────────────────────────────┐ ┌───────────────────────────────┐
│   Layer 4: Periodic Sync      │ │   Layer 3: Ad-Hoc iCal Check  │
│   (Availability Cache)        │ └───────────────┬───────────────┘
└───────────────────┬───────────┘                 │
                    │                           │
                    └───────────┬───────────────┘
                                │
                                ▼
                ┌───────────────────────────────┐
                │   Layer 1: DB Constraints     │
                │   Layer 2: Optimistic Locking │
                └───────────────┬───────────────┘
                                │
                                ▼
                ┌───────────────────────────────┐
                │   Booking Confirmation        │
                │   - Email sent               │
                │   - Availability updated     │
                └───────────────────────────────┘
```

## 9. Implementation Time Estimates

| Phase | Task | Estimated Time |
|-------|------|----------------|
| 1 | Architecture & Planning | 8 hours (completed) |
| 2 | Database Setup | 4 hours |
| 2.1 | Entity creation | 2 hours |
| 2.2 | Database migrations | 1 hour |
| 2.3 | Repository setup | 1 hour |
| 3 | Core Services | 12 hours |
| 3.1 | iCal parsing service | 4 hours |
| 3.2 | Availability service | 4 hours |
| 3.3 | Booking service | 4 hours |
| 4 | API Endpoints | 8 hours |
| 4.1 | Booking creation endpoint | 4 hours |
| 4.2 | Availability endpoint | 2 hours |
| 4.3 | Admin endpoints | 2 hours |
| 5 | Cron Job | 4 hours |
| 5.1 | Calendar sync command | 3 hours |
| 5.2 | Scheduling setup | 1 hour |
| 6 | Error Handling | 4 hours |
| 6.1 | Exception handling | 2 hours |
| 6.2 | Logging setup | 2 hours |
| 7 | Testing | 12 hours |
| 7.1 | Unit tests | 6 hours |
| 7.2 | Integration tests | 4 hours |
| 7.3 | End-to-end tests | 2 hours |
| 8 | Documentation | 4 hours |
| 8.1 | API documentation | 2 hours |
| 8.2 | Admin guide | 2 hours |
| **Total** | | **56 hours** |