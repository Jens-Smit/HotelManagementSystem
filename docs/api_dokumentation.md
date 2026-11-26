# API-Dokumentation für das Hotel-Management-System

Version 1.0 · Status: getestet (216 Tests) · Basis: Symfony 7.3

Diese Dokumentation beschreibt **alle tatsächlich implementierten Endpunkte**.
Die maschinenlesbare OpenAPI-Spezifikation liegt unter
[openapi.yaml](openapi.yaml) und wird zusätzlich interaktiv bereitgestellt
unter `GET /api/doc` (Swagger UI).

---

## Inhaltsverzeichnis

1. [Einführung](#einführung)
2. [Authentifizierung](#authentifizierung)
3. [Auth-Endpunkte](#auth-endpunkte)
4. [Zimmer-Endpunkte](#zimmer-endpunkte)
5. [Buchungs-Endpunkte](#buchungs-endpunkte)
6. [Verfügbarkeits-Endpunkte](#verfügbarkeits-endpunkte)
7. [CMS-Endpunkte (Posts & Kategorien)](#cms-endpunkte)
8. [Kontaktformular & Captcha](#kontaktformular--captcha)
9. [System-Endpunkte](#system-endpunkte)
10. [Fehlercodes](#fehlercodes)
11. [Beispiele](#beispiele)

---

## Einführung

### Basis-URL

```
https://ihre-domain.tld
```

Alle API-Endpunkte sind unter `/api/...` erreichbar, der Health-Check unter
`/health`. In der Entwicklung: `http://localhost:8000`.

### Inhaltstypen

| Richtung | Content-Type |
|---|---|
| Request (Body) | `application/json` |
| Response | `application/json` |

### Hinweise zur Feldnamen-Konvention

- **Request-Parameter** sind snake_case: `room_id`, `start_date`, `guest_name`
- **Serialisierte Entity-Antworten** sind camelCase: `guestName`, `startDate`

---

## Authentifizierung

Die API nutzt **JWT-basierte Authentifizierung über ein HttpOnly-Cookie
(`BEARER`)**.

1. `POST /api/login` mit E-Mail und Passwort
2. Der JWT wird als `BEARER`-Cookie (HttpOnly, SameSite=Lax) gesetzt
3. Alle geschützten Endpunkte senden dieses Cookie automatisch mit
4. Abgelaufene Tokens können über `POST /api/token/refresh` erneuert werden

### Welche Endpunkte sind öffentlich?

| Endpunkt | Zugriff |
|---|---|
| `POST /api/login`, `POST /api/register` | öffentlich |
| `POST /api/token/refresh` | öffentlich (Refresh-Token nötig) |
| `POST /api/password/request-reset`, `POST /api/password/reset` | öffentlich |
| `POST /api/contact`, `GET/POST /api/captcha/*` | öffentlich |
| `GET /api/posts`, `GET /api/posts/{slug}` | öffentlich (Lesen) |
| `GET /api/categories`, `GET /api/categories/{id}`, `GET /api/categories/{id}/tree` | öffentlich (Lesen) |
| `GET /health` | öffentlich |
| **alle anderen `/api/*`-Routen** | **authentifiziert** (z. B. Zimmer, Buchungen, Posts schreiben) |

Fehlendes oder ungültiges Cookie → `401 Unauthorized`:

```json
{
  "error": "Authentifizierung erforderlich",
  "message": "Es wurde kein gültiges Autorisierungs-Cookie (BEARER) gefunden."
}
```

---

## Auth-Endpunkte

### POST /api/login — Anmeldung

```json
{ "email": "user@example.com", "password": "geheim" }
```

**Erfolg (200):** Token + User-Daten; `BEARER`- und `REFRESH_TOKEN`-Cookies
werden gesetzt. **Fehler:** 400 (unvollständig), 401 (falsche Zugangsdaten),
429 (Rate-Limit).

### POST /api/register — Registrierung

```json
{ "email": "neu@example.com", "password": "mindestens8zeichen" }
```

**Erfolg (201):** `{"message": "Benutzer erfolgreich registriert."}` ·
**Fehler:** 400 (Validierung/Duplikat, Passwort < 8 Zeichen).

### POST /api/token/refresh — Token erneuern

```json
{ "refresh_token": "<token>" }
```

### POST /api/logout — Abmeldung

Löscht die Cookies. **Erfolg (200):** `{"message": "Logout erfolgreich."}`

### POST /api/password/request-reset — Reset anfordern

```json
{ "email": "user@example.com" }
```

Setzt einen gehashten Reset-Token (SHA-256, 1 Std. gültig) und versendet die
E-Mail mit dem Klartext-Token.

### POST /api/password/reset — Passwort mit Token zurücksetzen

```json
{ "email": "user@example.com", "token": "<klartext-token>", "new_password": "neuesPasswort" }
```

### POST /api/password/change — Passwort ändern (authentifiziert)

```json
{ "currentPassword": "alt", "newPassword": "neu" }
```

**Erfolg (200)** · **Fehler:** 400 (falsches aktuelles Passwort).

---

## Zimmer-Endpunkte

Alle Zimmer-Endpunkte erfordern **Authentifizierung**.

### GET /api/rooms — Zimmerliste (paginiert)

Query-Parameter: `page` (int, Standard 1), `limit` (int, Standard 10),
`is_active` (bool, Standard true).

```json
{
  "data": [
    {
      "id": "019a2b3c-…",
      "name": "Standard Doppelzimmer",
      "slug": "standard-doppelzimmer",
      "capacity": 2,
      "basePrice": "85",
      "isActive": true
    }
  ],
  "meta": {
    "current_page": 1,
    "items_per_page": 10,
    "total_items": 3,
    "total_pages": 1
  }
}
```

### GET /api/rooms/{id} — Ein Zimmer

`{id}` ist eine UUID. **Erfolg (200)** mit Zimmerdaten (wie oben) ·
**404:** `{"success": false, "error": "ROOM_NOT_FOUND"}`

### GET /api/rooms/available — Verfügbare Zimmer suchen

Query-Parameter: `checkIn` (Y-m-d, Pflicht), `checkOut` (Y-m-d, Pflicht),
`capacity` (int, Standard 1).

```json
{
  "success": true,
  "rooms": [
    {
      "id": "019a2b3c-…",
      "name": "Luxus Suite",
      "capacity": 4,
      "price_per_night": "250.00",
      "total_price": "750.00"
    }
  ],
  "search_criteria": { "check_in": "…", "check_out": "…", "capacity": 2 }
}
```

**Fehler:** 400 `MISSING_DATES` / `INVALID_DATE_FORMAT` / `INVALID_DATE_RANGE`.

> **Hinweis:** Zimmer werden über `POST /api/categories`-analog **nicht**
> per API angelegt – die Anlage erfolgt derzeit über das Admin-Panel bzw.
> direkt in der Datenbank (Roadmap: `POST /api/rooms`).

---

## Buchungs-Endpunkte

Alle Buchungs-Endpunkte erfordern **Authentifizierung**.

### POST /api/bookings — Buchung erstellen

Prüft **vor** dem Anlegen: interne Konflikte → externe iCal-Kalender
(Booking.com, HomeAway) in Echtzeit.

```json
{
  "room_id": "019a2b3c-…",
  "start_date": "2026-06-01",
  "end_date": "2026-06-05",
  "guest_name": "Max Mustermann",
  "guest_email": "max@example.com",
  "guest_phone": "+49 123 456789"
}
```

**Erfolg (201):**

```json
{
  "success": true,
  "booking_id": "019a2b4d-…",
  "booking_hash": "3f9c…",
  "total_price": 340.0,
  "message": "Booking confirmed. Confirmation email sent."
}
```

**Fehlercodes:**

| HTTP | error | Bedeutung |
|---|---|---|
| 400 | `VALIDATION_ERROR` | Ungültige Felder (Details unter `errors`) |
| 400 | `INVALID_JSON` | Kein gültiges JSON |
| 404 | `ROOM_NOT_FOUND` | Zimmer existiert nicht oder ist inaktiv |
| 409 | `INTERNAL_CONFLICT` | Überlappung mit vorhandener Buchung (mit `details.conflict_count` und `suggested_alternatives`) |
| 409 | `EXTERNAL_CONFLICT` | Externe Portalbuchung im Zeitraum (`details.conflict_source`: `booking_com` \| `homeaway`) |
| 409 | `CONCURRENT_MODIFICATION` | Parallel geändert (Optimistic Lock), bitte wiederholen |
| 500 | `TRANSACTION_FAILED` / `SYSTEM_ERROR` | Interner Fehler |

Angrenzende Zeiträume (Check-out = Check-in des Gastes) sind erlaubt.

### GET /api/bookings/{hash} — Buchung abrufen

`{hash}` ist der 64-Zeichen-`booking_hash` (kein Login des Gastes nötig,
Hash ist sufficiently geheim). **Erfolg (200):**

```json
{
  "success": true,
  "booking": {
    "id": "019a2b4d-…",
    "room": { "id": "…", "name": "…", "slug": "…", "capacity": 2, "basePrice": "85", "isActive": true },
    "startDate": "2026-06-01T00:00:00+00:00",
    "endDate": "2026-06-05T00:00:00+00:00",
    "guestName": "Max Mustermann",
    "guestEmail": "max@example.com",
    "guestPhone": "+49 123 456789",
    "totalPrice": "340",
    "source": "website",
    "status": "confirmed",
    "bookingHash": "3f9c…"
  }
}
```

**404:** `BOOKING_NOT_FOUND`.

### DELETE /api/bookings/{hash} — Buchung stornieren

**Erfolg (200):** `{"success": true, "message": "Booking cancelled successfully."}`

| HTTP | error | Bedeutung |
|---|---|---|
| 400 | `CANCELLATION_TOO_LATE` | Check-in in weniger als 24 Stunden |
| 400 | `ALREADY_CANCELLED` | Buchung ist bereits storniert |
| 404 | `BOOKING_NOT_FOUND` | Unbekannter Hash |
| 409 | `CANCELLATION_CONFLICT` | Für den Zeitraum existiert bereits eine stornierte Buchung |

---

## Verfügbarkeits-Endpunkte

### GET /api/rooms/{id}/availability — Verfügbarkeit prüfen (authentifiziert)

Query-Parameter: `start_date` (Y-m-d, Pflicht), `end_date` (Y-m-d, Pflicht),
`real_time` (bool, Standard true – prüft zusätzlich externe iCal-Kalender).

```json
{
  "available": true,
  "room_id": "019a2b3c-…",
  "room_name": "Standard Doppelzimmer",
  "start_date": "2026-06-01",
  "end_date": "2026-06-05",
  "checks_performed": ["internal_database", "booking_com_ical", "homeaway_ical"],
  "price_per_night": "85",
  "total_price": 340.0,
  "nights": 4,
  "last_checked": "2026-01-15T10:30:00+00:00"
}
```

Bei Konflikt: `"available": false` mit `conflict_reason`
(`internal_booking`, `booking_com_reservation`, `homeaway_reservation`)
und – bei internem Konflikt – `conflict_details` pro Buchung.

**Fehler:** 400 `MISSING_DATES` / `INVALID_DATE_FORMAT` / `INVALID_DATE_RANGE`,
404 `ROOM_NOT_FOUND`.

---

## CMS-Endpunkte

### Posts

| Methode | Route | Zugriff |
|---|---|---|
| GET | `/api/posts` | öffentlich |
| GET | `/api/posts/{slug}` | öffentlich |
| POST | `/api/posts` | authentifiziert |
| POST | `/api/posts/upload` (Medien-Upload) | authentifiziert |
| POST | `/api/posts/{id}` (Update) | authentifiziert |
| DELETE | `/api/posts/{id}` | authentifiziert |

Medien-Uploads werden durch `FileSecurityService` validiert: nur JPEG/PNG/
GIF/WebP, max. 5 MB, 100–10 000 px, MIME-/Inhaltsabgleich, keine
Mehrfach-Extensions.

### Kategorien

| Methode | Route | Zugriff |
|---|---|---|
| GET | `/api/categories` | öffentlich |
| GET | `/api/categories/{id}` | öffentlich |
| GET | `/api/categories/{id}/tree` | öffentlich |
| POST | `/api/categories` | authentifiziert |
| PUT | `/api/categories/{id}` | authentifiziert |
| DELETE | `/api/categories/{id}` | authentifiziert |

---

## Kontaktformular & Captcha

### POST /api/contact (öffentlich)

```json
{ "name": "Max", "email": "max@example.com", "subject": "Anfrage", "message": "…" }
```

**Erfolg (200):** `{"message": "Ihre Nachricht wurde erfolgreich gesendet."}` ·
**400:** Validierungsfehler (`errors`) · **500:** Mailer-Fehler.

### GET /api/captcha/generate / POST /api/captcha/verify (öffentlich)

Generiert bzw. verifiziert ein Bild-Captcha (GD-basiert); Lösung wird
serverseitig in der Session gehalten.

---

## System-Endpunkte

### GET /health (öffentlich)

```json
{ "status": "healthy", "timestamp": "2026-01-15T10:30:00+00:00", "database": "connected" }
```

Bei Datenbank-Fehler: **503** mit `"status": "unhealthy"`,
`"database": "disconnected"`.

### Interaktive API-Doku

- `GET /api/doc` – Swagger UI
- `GET /api/doc.json` – OpenAPI-JSON

---

## Fehlercodes

Zentrale, vom `ExceptionListener` gelieferte Fehlerstruktur:

```json
{
  "error": "Kurzmeldung",
  "timestamp": "2026-01-15T10:30:00+00:00"
}
```

In `dev` werden zusätzliche Felder (`exception`, `file`, `line`, `trace`)
mitgeliefert – in `prod` bewusst **nicht** (kein Information Leakage).

---

## Beispiele

### Buchung mit curl (Cookie-Auth)

```bash
# 1. Login (Cookie speichern)
curl -c cookies.txt -X POST https://ihre-domain.tld/api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "geheim"}'

# 2. Verfügbarkeit prüfen
curl -b cookies.txt \
  "https://ihre-domain.tld/api/rooms/019a2b3c-…/availability?start_date=2026-06-01&end_date=2026-06-05"

# 3. Buchung anlegen
curl -b cookies.txt -X POST https://ihre-domain.tld/api/bookings \
  -H "Content-Type: application/json" \
  -d '{"room_id": "019a2b3c-…", "start_date": "2026-06-01", "end_date": "2026-06-05", "guest_name": "Max Mustermann", "guest_email": "max@example.com"}'
```

### Doppelbuchungs-Fall

```bash
# Zweite, überlappende Buchung -> 409 INTERNAL_CONFLICT
curl -b cookies.txt -X POST https://ihre-domain.tld/api/bookings \
  -H "Content-Type: application/json" \
  -d '{"room_id": "019a2b3c-…", "start_date": "2026-06-03", "end_date": "2026-06-08", "guest_name": "Gast 2", "guest_email": "gast2@example.com"}'
```

```json
{
  "success": false,
  "error": "INTERNAL_CONFLICT",
  "message": "Room already booked for these dates",
  "details": { "conflict_count": 1 },
  "suggested_alternatives": [
    { "room_id": "019a2b99-…", "room_name": "Luxus Suite", "capacity": 4, "base_price": "250.00" }
  ]
}
```

### Python (Requests)

```python
import requests

base = "https://ihre-domain.tld"
s = requests.Session()

s.post(f"{base}/api/login", json={
    "email": "user@example.com", "password": "geheim"
})

rooms = s.get(f"{base}/api/rooms", params={"page": 1, "limit": 10}).json()

booking = s.post(f"{base}/api/bookings", json={
    "room_id": rooms["data"][0]["id"],
    "start_date": "2026-06-01",
    "end_date": "2026-06-05",
    "guest_name": "Max Mustermann",
    "guest_email": "max@example.com",
})

print(booking.status_code, booking.json())
```

---

Siehe auch: [Wissensdatenbank](WISSENSDATENBANK.md) ·
[Architektur](pms_architecture.md) · [Entwicklerdokumentation](entwicklerdokumentation.md)
