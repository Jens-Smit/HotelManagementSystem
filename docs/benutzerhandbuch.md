# Benutzerhandbuch für das Hotel-Management-System

Version 1.0 · Status: ready to publish

> **Wichtiger Hinweis:** Dieses Repository enthält das **Backend (API)** des
> PMS. Es gibt bewusst keine Oberfläche im Code – alle Funktionen werden über
> die [REST-API](api_dokumentation.md) bedient. Die Schritte unten sind daher
> als API-Aufrufe bzw. als Anleitung für den Betrieb beschrieben. Ein
> separates Web-Frontend (Dashboard) kann auf derselben API aufsetzen.

---

## Inhaltsverzeichnis

1. [Systemübersicht](#systemübersicht)
2. [Anmeldung und Benutzerverwaltung](#anmeldung-und-benutzerverwaltung)
3. [Zimmerverwaltung](#zimmerverwaltung)
4. [Buchungsmanagement](#buchungsmanagement)
5. [Kalendersynchronisation (iCal)](#kalendersynchronisation-ical)
6. [Verfügbarkeitsprüfung](#verfügbarkeitsprüfung)
7. [Häufige Fragen (FAQ)](#häufige-fragen-faq)

---

## Systemübersicht

Das System verwaltet für kleine Beherbergungsbetriebe:

- **Zimmer** mit Kapazität, Basispreis und iCal-Kalender-Anbindung
- **Buchungen** aus drei Quellen: eigene Website (`website`),
  Booking.com (`booking_com`), HomeAway/FeWo-direkt (`homeaway`)
- **Verfügbarkeit** in Echtzeit – Doppelbuchungen werden über 5 Schichten
  ausgeschlossen (siehe [Architektur](pms_architecture.md))

Alle Daten werden über die API unter `/api/...` bereitgestellt.
Authentifizierung: JWT im HttpOnly-Cookie (`BEARER`).

---

## Anmeldung und Benutzerverwaltung

### Anmeldung

```
POST /api/login    {"email": "...", "password": "..."}
```

Der Login setzt das `BEARER`-Cookie (Zugriffstoken, 1 Std.) und ein
`REFRESH_TOKEN`-Cookie (14 Tage). Nach Ablauf:

```
POST /api/token/refresh    {"refresh_token": "<token>"}
```

### Benutzer anlegen

```
POST /api/register    {"email": "...", "password": "mindestens8zeichen"}
```

### Passwort ändern / vergessen

- Angemeldet: `POST /api/password/change` mit `currentPassword`, `newPassword`
- Vergessen: `POST /api/password/request-reset` → E-Mail mit Token →
  `POST /api/password/reset`
- Reset-Tokens werden nur **gehasht (SHA-256)** gespeichert und sind 1 Std.
  gültig.

### Benutzerrollen

- **Administrator** (`ROLE_ADMIN`): volle Verwaltung inkl. CMS (Posts/Kategorien)
- **Mitarbeiter** (`ROLE_USER`): laufende Buchungs- und Zimmerarbeit

Die Rollenzuweisung erfolgt derzeit direkt in der Datenbank (Tabelle `user`,
Spalte `roles`).

---

## Zimmerverwaltung

> Zimmer werden aktuell **nicht über die API angelegt/verändert** (Roadmap).
> Die Anlage erfolgt über die Datenbank bzw. das aufsetzende Frontend.

**Abrufen (authentifiziert):**

```
GET /api/rooms?page=1&limit=10&is_active=1    # Liste (paginiert)
GET /api/rooms/{uuid}                          # Einzelzimmer
GET /api/rooms/available?checkIn=…&checkOut=…&capacity=2
```

Ein Zimmer besitzt: `name`, `slug`, `capacity`, `basePrice`,
`isActive` sowie zwei iCal-Felder (`icalUrlBooking`, `icalUrlHomeaway`) und
Zeitstempel des letzten Syncs (`lastSyncBooking`, `lastSyncHomeaway`).

**Deaktivieren:** `isActive = false` – deaktivierte Zimmer erscheinen nicht
in Verfügbarkeits- und Buchungsanfragen.

---

## Buchungsmanagement

### Buchung erstellen (z. B. Direktbuchung von der eigenen Website)

```
POST /api/bookings
{
  "room_id": "<uuid>",
  "start_date": "2026-06-01",
  "end_date": "2026-06-05",
  "guest_name": "Max Mustermann",
  "guest_email": "max@example.com",
  "guest_phone": "+49 123 456789"   // optional
}
```

Das System prüft automatisch: interne Buchungen, externe Portalkalender
(Echtzeit-iCal), Optimistic Locking. Bei Überlappung → **409** mit
Alternativvorschlägen (`suggested_alternatives`).

### Buchung abrufen

```
GET /api/bookings/{booking_hash}
```

Der `booking_hash` (64 Zeichen) eignet sich als Link-Identifikator für den
Gast, z. B. für Bestätigungsseiten.

### Buchung stornieren

```
DELETE /api/bookings/{booking_hash}
```

- Storno nur bis **24 Stunden vor Check-in** möglich (`CANCELLATION_TOO_LATE`)
- Stornierte Buchungen blockieren den Zeitraum nicht mehr – er kann neu
  gebucht werden.

### Buchungsquellen im Überblick

| Quelle | Entstehung |
|---|---|
| `website` | Direktbuchung über die eigene Website-API |
| `booking_com` | Automatischer iCal-Import (Cron `app:sync-calendars`) |
| `homeaway` | Automatischer iCal-Import (Cron `app:sync-calendars`) |

---

## Kalendersynchronisation (iCal)

### iCal-URLs pflegen

Pro Zimmer zwei optionale Feeds: Booking.com und HomeAway/FeWo-direkt.
Die URLs erhalten Sie im jeweiligen Portal:

- **Booking.com:** Extranet → *Channel Manager* → *Calendar Sync*
- **HomeAway/FeWo-direkt:** Dashboard → *Kalender* → *Kalender exportieren*

### Automatische Synchronisation

Der Cron-Job läuft empfohlen **alle 15 Minuten**:

```
*/15 * * * * cd /pfad/zum/projekt && php bin/console app:sync-calendars >> /var/log/pms-sync.log 2>&1
```

Pro Lauf gilt:

- Kürzlich (< 15 Min.) synchronisierte Zimmer werden übersprungen
  (`--force` erzwingt den Sync)
- Neue Portalbuchungen werden importiert und per `external_id` dedupliziert
- Stornierte Events (`STATUS:CANCELLED`) werden ignoriert
- Fehlerhafte Feeds werden geloggt und übersprungen – der Sync bricht nicht ab

### Manuelle Synchronisation

```
php bin/console app:sync-calendars
php bin/console app:sync-calendars --room-id=<uuid> --force
```

Der Sync-Status steht pro Zimmer in `lastSyncBooking`/`lastSyncHomeaway`.

---

## Verfügbarkeitsprüfung

```
GET /api/rooms/{uuid}/availability?start_date=2026-06-01&end_date=2026-06-05&real_time=1
```

Antwortfelder: `available`, `conflict_reason` (bei Konflikt),
`checks_performed` (`internal_database`, `booking_com_ical`, `homeaway_ical`),
`price_per_night`, `total_price`, `nights`.

**Sonderfälle:**

- Angrenzende Zeiträume (Check-out = Check-in des Folgegasts) sind verfügbar
- Stornierte Buchungen blockieren nicht
- Fehlerhafte iCal-Feeds blockieren nicht (fail-open, wird geloggt)

---

## Häufige Fragen (FAQ)

**Warum wird meine Buchung mit 409 abgelehnt?**
Es existiert eine interne Buchung oder eine Portalbuchung im Zeitraum. Die
Antwort enthält Details und ggf. Alternativzimmer.

**Was passiert, wenn Booking.com nicht erreichbar ist?**
Buchungen werden trotzdem angenommen (fail-open); der Fehler wird protokolliert
und der Sync beim nächsten Lauf wiederholt.

**Kann derselbe Zeitraum nach Storno neu gebucht werden?**
Ja. Stornierte Buchungen fließen nicht in die Konfliktprüfung ein.

**Wie sehe ich, ob der Cron läuft?**
`/health` prüft die Datenbank; die Sync-Zeitstempel pro Zimmer und die Logdatei
(`/var/log/pms-sync.log`) zeigen den letzten Lauf.

---

Siehe auch: [API-Dokumentation](api_dokumentation.md) ·
[Installation](installationsanleitung.md) ·
[Wissensdatenbank](WISSENSDATENBANK.md)
