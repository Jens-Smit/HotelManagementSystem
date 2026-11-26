# Hotel Property Management System (PMS)

## Overview

A Symfony-based PMS for small hotels with iCal-based channel integration for Booking.com and HomeAway/FeWo-direkt.

## Features

- ✅ Direct booking API for your website
- ✅ Real-time availability checks against external calendars
- ✅ Automatic import of Booking.com/HomeAway reservations
- ✅ Multi-layer overbooking prevention
- ✅ Optimistic locking for concurrent bookings
- ✅ CMS functionality (reusing Blog system for pages)

## Requirements

- PHP 8.2+
- Symfony 7.3+
- PostgreSQL 13+ or MySQL 8+
- Composer
- Cron access (for background sync)

## Installation

```bash
# 1. Clone and install dependencies
composer install

# 2. Configure database
cp .env .env.local
# Edit DATABASE_URL in .env.local

# 3. Run migrations
php bin/console doctrine:migrations:migrate

# 4. Create first room (via CLI or admin panel)
php bin/console app:create-room

# 5. Setup cron job
crontab -e
# Add: */15 * * * * cd /path/to/project && php bin/console app:sync-calendars >> /var/log/pms-sync.log 2>&1
```

## Configuration

### Environment Variables

```env
# Database
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/pms_db"

# iCal Settings
ICAL_FETCH_TIMEOUT=5  # seconds
BOOKING_FAIL_STRATEGY=closed  # closed|open

# Email (for booking confirmations)
MAILER_DSN=smtp://user:pass@smtp.example.com:587
```

### Obtaining iCal URLs

**Booking.com:**
1. Login to Booking.com Extranet
2. Go to Settings → Channel Manager → Calendar Sync
3. Copy the iCal URL

**HomeAway/FeWo-direkt:**
1. Login to HomeAway dashboard
2. Go to Calendar → Export Calendar
3. Copy the iCal link

## API Documentation

### POST /api/bookings
Create a new booking (with real-time availability check).

**Request:**
```json
{
  "room_id": "uuid",
  "start_date": "2025-06-01",
  "end_date": "2025-06-05",
  "guest_name": "Max Mustermann",
  "guest_email": "max@example.com",
  "guest_phone": "+49123456789"
}
```

**Success Response (201):**
```json
{
  "success": true,
  "booking_id": "uuid",
  "booking_hash": "abc123...",
  "total_price": 400.00,
  "message": "Booking confirmed"
}
```

**Conflict Response (409):**
```json
{
  "success": false,
  "error": "EXTERNAL_CONFLICT",
  "message": "Room not available (Booking.com reservation exists)"
}
```

### GET /api/rooms/{id}/availability
Check room availability.

**Query Parameters:**
- `start_date` (required): Y-m-d format
- `end_date` (required): Y-m-d format
- `real_time` (optional): boolean, default true

**Response:**
```json
{
  "available": true,
  "room_id": "uuid",
  "start_date": "2025-06-01",
  "end_date": "2025-06-05",
  "price_per_night": 100.00,
  "total_price": 400.00,
  "checks_performed": ["internal_database", "booking_com_ical", "homeaway_ical"]
}
```

Full API documentation: [docs/api_dokumentation.md](docs/api_dokumentation.md) · [docs/openapi.yaml](docs/openapi.yaml)

## Architecture

See [docs/pms_architecture.md](docs/pms_architecture.md) for detailed architecture documentation.

### Key Components

- **Entities:** Room, Booking, AvailabilitySnapshot
- **Services:** ICalSyncService, BookingService, AvailabilityService
- **Commands:** app:sync-calendars
- **Controllers:** BookingController, AvailabilityController

### Overbooking Prevention (5 Layers)

1. Database constraints (unique indexes)
2. Optimistic locking (version fields)
3. Real-time iCal checks before booking
4. Periodic background sync (every 15 min)
5. Manual blocking capability

## Project Knowledge Base

A compact knowledge base covering architecture, overbooking prevention,
quality status and all fixes is available in **[docs/WISSENSDATENBANK.md](docs/WISSENSDATENBANK.md)**
(German).

## Testing

```bash
# Run all tests (115 tests, all green)
php bin/phpunit

# Run static analysis
vendor/bin/phpstan analyse

# Run code style fixer
vendor/bin/php-cs-fixer fix
```

## Monitoring

### Check Sync Status

```bash
# View recent sync logs
tail -f /var/log/pms-sync.log

# Manual sync
php bin/console app:sync-calendars

# Check last sync timestamps in database
SELECT id, name, last_sync_booking, last_sync_homeaway
FROM rooms
WHERE is_active = true;
```

### Health Check

```bash
curl http://localhost/api/health
```

## Troubleshooting

### Sync Failures

**Problem:** "iCal fetch timeout"
**Solution:** Check network connectivity to Booking.com/HomeAway. Verify iCal URLs are still valid.

**Problem:** "Parse error in iCal feed"
**Solution:** Download the iCal file manually and check for formatting issues. Contact platform support if malformed.

### Concurrent Bookings

**Problem:** "OptimisticLockException"
**Solution:** This is expected behavior - the user will be asked to retry. Verify the retry mechanism works.

## Support

For issues or questions:
- GitHub Issues: [link]
- Email: support@example.com
- Docs: [docs/](docs/)

## License

Dieses Projekt ist unter CC BY-NC-ND 4.0 lizenziert. Kommerzielle Nutzung ist untersagt. Für kommerzielle Lizenzen kontaktieren Sie bitte info@jenssmit.de.
