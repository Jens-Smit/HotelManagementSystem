# API-Dokumentation für das Hotel-Management-System

## Inhaltsverzeichnis
1. [Einführung](#einführung)
2. [Authentifizierung](#authentifizierung)
3. [API-Endpunkte](#api-endpunkte)
   - [Authentifizierung](#authentifizierung-endpunkte)
   - [Zimmerverwaltung](#zimmerverwaltung)
   - [Buchungsmanagement](#buchungsmanagement)
   - [Verfügbarkeitsprüfung](#verfügbarkeitsprüfung)
   - [Kalendersynchronisation](#kalendersynchronisation)
4. [Fehlerbehandlung](#fehlerbehandlung)
5. [Beispielanfragen](#beispielanfragen)
6. [Rate Limiting](#rate-limiting)
7. [API-Versionierung](#api-versionierung)

---

## Einführung

Das Hotel-Management-System bietet eine RESTful API für die Verwaltung von Zimmern, Buchungen und Verfügbarkeiten. Die API ist für die Integration mit externen Systemen wie Websites, Buchungsplattformen und mobilen Anwendungen konzipiert.

### Basis-URL
```
https://ihre-domain.de/api
```

### Inhaltstypen
- **Anfragen**: `application/json`
- **Antworten**: `application/json`

### HTTP-Methoden
| Methode | Verwendung |
|---------|------------|
| `GET`   | Daten abrufen |
| `POST`  | Neue Ressourcen erstellen |
| `PUT`   | Bestehende Ressourcen vollständig aktualisieren |
| `PATCH` | Bestehende Ressourcen teilweise aktualisieren |
| `DELETE`| Ressourcen löschen |

---

## Authentifizierung

Die API verwendet JWT (JSON Web Tokens) für die Authentifizierung. Um auf geschützte Endpunkte zuzugreifen, müssen Sie einen gültigen JWT im `Authorization`-Header Ihrer Anfrage senden.

### Anmeldung (Token abrufen)
**Endpunkt**: `POST /api/login_check`

**Anfrage**:
```json
{
  "username": "ihre@email.de",
  "password": "IhrPasswort"
}
```

**Erfolgreiche Antwort (200 OK)**:
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "refresh_token": "def50200a1b2c3d4..."
}
```

**Header für authentifizierte Anfragen**:
```
Authorization: Bearer {token}
```

### Token aktualisieren
**Endpunkt**: `POST /api/token/refresh`

**Anfrage**:
```json
{
  "refresh_token": "def50200a1b2c3d4..."
}
```

**Erfolgreiche Antwort (200 OK)**:
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "refresh_token": "new_refresh_token_here..."
}
```

---

## API-Endpunkte

### Authentifizierung Endpunkte

#### Benutzer registrieren
**Endpunkt**: `POST /api/register`

**Anfrage**:
```json
{
  "email": "neuer@benutzer.de",
  "password": "SicheresPasswort123!",
  "name": "Max Mustermann"
}
```

**Erfolgreiche Antwort (201 Created)**:
```json
{
  "success": true,
  "message": "Benutzer erfolgreich registriert",
  "user": {
    "id": "a1b2c3d4-...",
    "email": "neuer@benutzer.de",
    "name": "Max Mustermann",
    "roles": ["ROLE_USER"]
  }
}
```

---

### Zimmerverwaltung

#### Alle Zimmer abrufen
**Endpunkt**: `GET /api/rooms`

**Parameter**:
- `page` (optional): Seitenzahl für die Paginierung (Standard: 1)
- `limit` (optional): Anzahl der Ergebnisse pro Seite (Standard: 10)
- `is_active` (optional): Filter nach aktivem Status (true/false)

**Erfolgreiche Antwort (200 OK)**:
```json
{
  "data": [
    {
      "id": "a1b2c3d4-...",
      "name": "Standard Zimmer",
      "slug": "standard-zimmer",
      "capacity": 2,
      "base_price": "120.00",
      "is_active": true,
      "created_at": "2026-01-27T10:00:00+01:00",
      "updated_at": "2026-01-27T10:00:00+01:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "items_per_page": 10,
    "total_items": 1,
    "total_pages": 1
  }
}
```

#### Einzelnes Zimmer abrufen
**Endpunkt**: `GET /api/rooms/{id}`

**Erfolgreiche Antwort (200 OK)**:
```json
{
  "id": "a1b2c3d4-...",
  "name": "Standard Zimmer",
  "slug": "standard-zimmer",
  "capacity": 2,
  "base_price": "120.00",
  "ical_url_booking": "https://admin.booking.com/ical/...",
  "ical_url_homeaway": "https://www.homeaway.com/ical/...",
  "is_active": true,
  "created_at": "2026-01-27T10:00:00+01:00",
  "updated_at": "2026-01-27T10:00:00+01:00"
}
```

#### Zimmer erstellen
**Endpunkt**: `POST /api/rooms`

**Anfrage**:
```json
{
  "name": "Superior Zimmer",
  "slug": "superior-zimmer",
  "capacity": 3,
  "base_price": "180.00",
  "ical_url_booking": "https://admin.booking.com/ical/...",
  "ical_url_homeaway": "https://www.homeaway.com/ical/...",
  "is_active": true
}
```

**Erfolgreiche Antwort (201 Created)**:
```json
{
  "success": true,
  "message": "Zimmer erfolgreich erstellt",
  "room": {
    "id": "e5f6g7h8-...",
    "name": "Superior Zimmer",
    "slug": "superior-zimmer",
    "capacity": 3,
    "base_price": "180.00",
    "is_active": true,
    "created_at": "2026-01-27T10:30:00+01:00",
    "updated_at": "2026-01-27T10:30:00+01:00"
  }
}
```

#### Zimmer aktualisieren
**Endpunkt**: `PUT /api/rooms/{id}`

**Anfrage**:
```json
{
  "name": "Superior Zimmer (Aktualisiert)",
  "capacity": 4,
  "base_price": "200.00",
  "is_active": true
}
```

**Erfolgreiche Antwort (200 OK)**:
```json
{
  "success": true,
  "message": "Zimmer erfolgreich aktualisiert",
  "room": {
    "id": "e5f6g7h8-...",
    "name": "Superior Zimmer (Aktualisiert)",
    "slug": "superior-zimmer",
    "capacity": 4,
    "base_price": "200.00",
    "is_active": true,
    "created_at": "2026-01-27T10:30:00+01:00",
    "updated_at": "2026-01-27T10:45:00+01:00"
  }
}
```

#### Zimmer löschen
**Endpunkt**: `DELETE /api/rooms/{id}`

**Erfolgreiche Antwort (204 No Content)**:
```
(Leere Antwort)
```

---

### Buchungsmanagement

#### Alle Buchungen abrufen
**Endpunkt**: `GET /api/bookings`

**Parameter**:
- `page` (optional): Seitenzahl für die Paginierung (Standard: 1)
- `limit` (optional): Anzahl der Ergebnisse pro Seite (Standard: 10)
- `room_id` (optional): Filter nach Zimmer-ID
- `status` (optional): Filter nach Buchungsstatus (confirmed, cancelled, pending, blocked)
- `start_date` (optional): Filter nach Startdatum (YYYY-MM-DD)
- `end_date` (optional): Filter nach Enddatum (YYYY-MM-DD)

**Erfolgreiche Antwort (200 OK)**:
```json
{
  "data": [
    {
      "id": "i9j8k7l6-...",
      "room": {
        "id": "a1b2c3d4-...",
        "name": "Standard Zimmer"
      },
      "start_date": "2026-03-15",
      "end_date": "2026-03-18",
      "guest_name": "Max Mustermann",
      "guest_email": "max@example.com",
      "guest_phone": "+49 123 456789",
      "total_price": "360.00",
      "source": "website",
      "status": "confirmed",
      "booking_hash": "a3c9f2e1...",
      "created_at": "2026-01-27T11:00:00+01:00",
      "updated_at": "2026-01-27T11:00:00+01:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "items_per_page": 10,
    "total_items": 1,
    "total_pages": 1
  }
}
```

#### Einzelne Buchung abrufen
**Endpunkt**: `GET /api/bookings/{id}`

**Erfolgreiche Antwort (200 OK)**:
```json
{
  "id": "i9j8k7l6-...",
  "room": {
    "id": "a1b2c3d4-...",
    "name": "Standard Zimmer"
  },
  "start_date": "2026-03-15",
  "end_date": "2026-03-18",
  "guest_name": "Max Mustermann",
  "guest_email": "max@example.com",
  "guest_phone": "+49 123 456789",
  "total_price": "360.00",
  "source": "website",
  "status": "confirmed",
  "external_id": "EXT12345",
  "notes": "Frühstück inklusive",
  "booking_hash": "a3c9f2e1...",
  "created_at": "2026-01-27T11:00:00+01:00",
  "updated_at": "2026-01-27T11:00:00+01:00"
}
```

#### Buchung erstellen
**Endpunkt**: `POST /api/bookings`

**Anfrage**:
```json
{
  "room_id": "a1b2c3d4-...",
  "start_date": "2026-03-15",
  "end_date": "2026-03-18",
  "guest_name": "Max Mustermann",
  "guest_email": "max@example.com",
  "guest_phone": "+49 123 456789"
}
```

**Erfolgreiche Antwort (201 Created)**:
```json
{
  "success": true,
  "booking_id": "i9j8k7l6-...",
  "booking_hash": "a3c9f2e1...",
  "total_price": "360.00",
  "message": "Buchung erfolgreich erstellt. Bitte überprüfen Sie Ihre E-Mails für die Bestätigung."
}
```

**Fehlerantwort bei Verfügbarkeitskonflikt (409 Conflict)**:
```json
{
  "success": false,
  "error": "AVAILABILITY_CONFLICT",
  "message": "Zimmer nicht verfügbar (externe Buchung existiert)",
  "details": {
    "conflict_source": "booking_com",
    "conflict_dates": ["2026-03-15", "2026-03-16", "2026-03-17"]
  },
  "suggested_alternatives": [
    {
      "room_id": "e5f6g7h8-...",
      "room_name": "Superior Zimmer",
      "price_difference": "+20.00"
    }
  ]
}
```

#### Buchung aktualisieren
**Endpunkt**: `PUT /api/bookings/{id}`

**Anfrage**:
```json
{
  "guest_name": "Max Mustermann (Aktualisiert)",
  "guest_phone": "+49 987 654321",
  "status": "confirmed",
  "notes": "Sonderwünsche: Allergikerfreundliches Zimmer"
}
```

**Erfolgreiche Antwort (200 OK)**:
```json
{
  "success": true,
  "message": "Buchung erfolgreich aktualisiert",
  "booking": {
    "id": "i9j8k7l6-...",
    "room": {
      "id": "a1b2c3d4-...",
      "name": "Standard Zimmer"
    },
    "start_date": "2026-03-15",
    "end_date": "2026-03-18",
    "guest_name": "Max Mustermann (Aktualisiert)",
    "guest_email": "max@example.com",
    "guest_phone": "+49 987 654321",
    "total_price": "360.00",
    "status": "confirmed",
    "notes": "Sonderwünsche: Allergikerfreundliches Zimmer",
    "booking_hash": "a3c9f2e1...",
    "created_at": "2026-01-27T11:00:00+01:00",
    "updated_at": "2026-01-27T11:30:00+01:00"
  }
}
```

#### Buchung stornieren
**Endpunkt**: `PATCH /api/bookings/{id}/cancel`

**Erfolgreiche Antwort (200 OK)**:
```json
{
  "success": true,
  "message": "Buchung erfolgreich storniert",
  "booking": {
    "id": "i9j8k7l6-...",
    "status": "cancelled",
    "updated_at": "2026-01-27T11:45:00+01:00"
  }
}
```

---

### Verfügbarkeitsprüfung

#### Verfügbarkeit prüfen
**Endpunkt**: `GET /api/availability`

**Parameter**:
- `room_id` (erforderlich): Die ID des Zimmers
- `start_date` (erforderlich): Startdatum (YYYY-MM-DD)
- `end_date` (erforderlich): Enddatum (YYYY-MM-DD)

**Erfolgreiche Antwort (200 OK)**:
```json
{
  "available": true,
  "room_id": "a1b2c3d4-...",
  "start_date": "2026-03-15",
  "end_date": "2026-03-18",
  "price_per_night": "120.00",
  "total_price": "360.00",
  "last_checked": "2026-01-27T12:00:00+01:00",
  "check_sources": ["internal", "booking_com", "homeaway"],
  "breakdown": {
    "2026-03-15": {"price": "120.00", "available": true},
    "2026-03-16": {"price": "120.00", "available": true},
    "2026-03-17": {"price": "120.00", "available": true}
  }
}
```

**Antwort bei Nichtverfügbarkeit (200 OK)**:
```json
{
  "available": false,
  "room_id": "a1b2c3d4-...",
  "start_date": "2026-03-15",
  "end_date": "2026-03-18",
  "conflict_dates": ["2026-03-16", "2026-03-17"],
  "conflict_sources": ["booking_com"],
  "last_checked": "2026-01-27T12:05:00+01:00",
  "check_sources": ["internal", "booking_com", "homeaway"]
}
```

#### Verfügbarkeitskalender abrufen
**Endpunkt**: `GET /api/availability/calendar`

**Parameter**:
- `room_id` (erforderlich): Die ID des Zimmers
- `start_date` (erforderlich): Startdatum (YYYY-MM-DD)
- `end_date` (erforderlich): Enddatum (YYYY-MM-DD)
- `source` (optional): Verfügbarkeitsquelle (internal, booking_com, homeaway)

**Erfolgreiche Antwort (200 OK)**:
```json
{
  "room_id": "a1b2c3d4-...",
  "room_name": "Standard Zimmer",
  "start_date": "2026-03-01",
  "end_date": "2026-03-31",
  "availability": {
    "2026-03-01": {"available": true, "price": "120.00", "source": "internal"},
    "2026-03-02": {"available": false, "price": "120.00", "source": "booking_com"},
    "2026-03-03": {"available": true, "price": "120.00", "source": "internal"},
    "...": "..."
  }
}
```

---

### Kalendersynchronisation

#### Kalender manuell synchronisieren
**Endpunkt**: `POST /api/calendar/sync`

**Anfrage**:
```json
{
  "room_id": "a1b2c3d4-...",
  "force": true
}
```

**Erfolgreiche Antwort (200 OK)**:
```json
{
  "success": true,
  "message": "Kalendersynchronisation abgeschlossen",
  "room_id": "a1b2c3d4-...",
  "new_bookings": 2,
  "updated_bookings": 0,
  "deleted_bookings": 0,
  "last_sync": "2026-01-27T12:15:00+01:00"
}
```

#### Synchronisationsstatus abrufen
**Endpunkt**: `GET /api/calendar/sync/status`

**Erfolgreiche Antwort (200 OK)**:
```json
{
  "last_sync": "2026-01-27T12:15:00+01:00",
  "next_sync": "2026-01-27T12:30:00+01:00",
  "rooms": [
    {
      "room_id": "a1b2c3d4-...",
      "room_name": "Standard Zimmer",
      "last_sync": "2026-01-27T12:15:00+01:00",
      "last_sync_status": "success",
      "last_sync_message": "2 neue Buchungen importiert"
    }
  ]
}
```

---

## Fehlerbehandlung

Die API gibt standardisierte Fehlermeldungen im folgenden Format zurück:

```json
{
  "success": false,
  "error": "FEHLER_CODE",
  "message": "Beschreibung des Fehlers",
  "details": {
    "field": "Feldname, das den Fehler verursacht hat",
    "value": "Ungültiger Wert"
  }
}
```

### Häufige Fehlercodes

| Fehlercode | HTTP-Status | Beschreibung |
|------------|-------------|--------------|
| `AUTHENTICATION_FAILED` | 401 | Ungültige Anmeldedaten oder abgelaufenes Token |
| `ACCESS_DENIED` | 403 | Keine Berechtigung für diese Aktion |
| `VALIDATION_ERROR` | 400 | Validierungsfehler in den Eingabedaten |
| `RESOURCE_NOT_FOUND` | 404 | Die angeforderte Ressource existiert nicht |
| `AVAILABILITY_CONFLICT` | 409 | Verfügbarkeitskonflikt mit bestehender Buchung |
| `AVAILABILITY_CHECK_FAILED` | 503 | Verfügbarkeitsprüfung konnte nicht durchgeführt werden |
| `RATE_LIMIT_EXCEEDED` | 429 | Zu viele Anfragen |
| `INTERNAL_SERVER_ERROR` | 500 | Interner Serverfehler |

### Beispiel-Fehlerantworten

**Validierungsfehler (400 Bad Request)**:
```json
{
  "success": false,
  "error": "VALIDATION_ERROR",
  "message": "Es sind Validierungsfehler aufgetreten",
  "details": {
    "start_date": "Das Startdatum muss ein gültiges Datum sein",
    "end_date": "Das Enddatum muss nach dem Startdatum liegen"
  }
}
```

**Nicht gefunden (404 Not Found)**:
```json
{
  "success": false,
  "error": "RESOURCE_NOT_FOUND",
  "message": "Zimmer mit ID a1b2c3d4-... nicht gefunden"
}
```

**Authentifizierungsfehler (401 Unauthorized)**:
```json
{
  "success": false,
  "error": "AUTHENTICATION_FAILED",
  "message": "Ungültiges oder abgelaufenes Token"
}
```

---

## Beispielanfragen

### Buchung erstellen mit cURL
```bash
curl -X POST https://ihre-domain.de/api/bookings \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." \
  -d '{
    "room_id": "a1b2c3d4-...",
    "start_date": "2026-03-15",
    "end_date": "2026-03-18",
    "guest_name": "Max Mustermann",
    "guest_email": "max@example.com",
    "guest_phone": "+49 123 456789"
  }'
```

### Verfügbarkeit prüfen mit JavaScript (Fetch API)
```javascript
fetch('https://ihre-domain.de/api/availability?room_id=a1b2c3d4-...&start_date=2026-03-15&end_date=2026-03-18', {
  method: 'GET',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'
  }
})
.then(response => response.json())
.then(data => {
  if (data.available) {
    console.log('Zimmer verfügbar! Preis:', data.total_price);
  } else {
    console.log('Zimmer nicht verfügbar an folgenden Tagen:', data.conflict_dates);
  }
})
.catch(error => console.error('Fehler:', error));
```

### Alle Zimmer abrufen mit Python (Requests)
```python
import requests

response = requests.get(
    'https://ihre-domain.de/api/rooms',
    headers={
        'Authorization': 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'
    },
    params={
        'page': 1,
        'limit': 10,
        'is_active': True
    }
)

if response.status_code == 200:
    rooms = response.json()['data']
    for room in rooms:
        print(f"{room['name']} - {room['base_price']}€ pro Nacht")
else:
    print('Fehler:', response.json())
```

---

## Rate Limiting

Die API implementiert Rate Limiting, um Missbrauch zu verhindern. Die aktuellen Limits sind:

- **Authentifizierte Anfragen**: 100 Anfragen pro Minute pro Benutzer
- **Nicht authentifizierte Anfragen**: 30 Anfragen pro Minute pro IP-Adresse

### Rate Limiting Header
Jede Antwort enthält die folgenden Header, die den aktuellen Status des Rate Limitings anzeigen:

| Header | Beschreibung |
|--------|--------------|
| `X-RateLimit-Limit` | Maximale Anzahl von Anfragen im aktuellen Zeitfenster |
| `X-RateLimit-Remaining` | Verbleibende Anzahl von Anfragen im aktuellen Zeitfenster |
| `X-RateLimit-Reset` | Zeit in Sekunden, bis das Zeitfenster zurückgesetzt wird |

### Beispiel-Header
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 42
```

### Fehlerantwort bei Rate Limit Überschreitung (429 Too Many Requests)
```json
{
  "success": false,
  "error": "RATE_LIMIT_EXCEEDED",
  "message": "Zu viele Anfragen. Bitte versuchen Sie es später erneut.",
  "retry_after": 60
}
```

---

## API-Versionierung

Die API verwendet eine Versionierung im URL-Pfad, um abwärtskompatible Änderungen zu ermöglichen.

### Aktuelle Version
```
https://ihre-domain.de/api/v1/...
```

### Versionierungsschema
- **Major-Version (v1)**: Größere Änderungen, die die Abwärtskompatibilität brechen
- **Minor-Version**: Abwärtskompatible neue Funktionen (werden in der Dokumentation gekennzeichnet)

### Beispiel für Versionierung
```
GET /api/v1/rooms       # Aktuelle Version
GET /api/v2/rooms       # Zukünftige Version 2
```

### Deprecation Policy
- Alte Versionen werden mindestens 6 Monate nach Einführung einer neuen Major-Version unterstützt
- Deprecierte Endpunkte werden in der Dokumentation gekennzeichnet
- Clients werden über die `Warning`-Header über bevorstehende Änderungen informiert

---