# Wissensdatenbank – Hotel-Management-System (PMS)

Stand: November 2026 · Version 1.0.x · Status: **ready to publish**

Diese Wissensdatenbank fasst das gesamte Projektwissen kompakt zusammen und ist
der Einstiegspunkt für alle weiteren Dokumente (siehe [INDEX.md](INDEX.md)).

---

## 1. Was ist das Projekt?

Ein **Property-Management-System (PMS) für kleine Beherbergungsbetriebe**
(Hotels, Pensionen, Fewos, B&Bs) auf Basis von **Symfony 7.3 / PHP 8.2+**.

Das System verbindet drei Welten in einer Plattform:

1. **Website / Direktbuchung** – die Betriebe betreiben ihre Gästewebsite
   direkt über die Plattform (Blog/CMS für Seiten, öffentliche API für die
   Buchungsmaske).
2. **Reservierungsverwaltung** – alle Buchungen (Website, Booking.com,
   HomeAway/FeWo-direkt) werden zentral in einem Dashboard verwaltet.
3. **iCal-Synchronisation** – Buchungen von externen Portalen werden per iCal
   importiert und bei jeder Neubuchung in Echtzeit gegengeprüft.

**Zentrale Anforderung: Doppelbuchungen müssen ausgeschlossen sein.**

---

## 2. Architektur in Kürze

| Baustein | Umsetzung |
|---|---|
| Framework | Symfony 7.3, PHP 8.2+ |
| Datenbank | PostgreSQL 13+ / MySQL 8 (Testbetrieb: SQLite) |
| ORM | Doctrine ORM 3 + Migrations |
| Auth | JWT (lexik) + Refresh-Tokens (gesdinet), Cookie-basiert (`BEARER`) |
| iCal-Parsing | **sabre/vobject 4** (`VObject\Reader`) |
| HTTP | Symfony HttpClient (mit injizierbarem `HttpClientInterface`, gut testbar) |
| API-Doku | Nelmio ApiDoc / OpenAPI (`docs/openapi.yaml`) |
| Tests | PHPUnit 9 (Unit, Integration, Controller) |
| Statische Analyse | PHPStan Level 6 – **0 Fehler** |

Kern-Services:

- `src/Service/BookingService.php` – Buchung anlegen, Preise berechnen,
  interne + externe Konfliktprüfung, Transaktion mit Optimistic Locking.
- `src/Service/AvailabilityService.php` – Verfügbarkeit (Datenbank + iCal).
- `src/Service/ICalSyncService.php` – iCal-Import (`syncRoomCalendar`),
  Echtzeitprüfung (`checkAvailability`), Gastnamen-Extraktion.
- `src/Command/SyncCalendarsCommand.php` – Cron-Job `app:sync-calendars`
  (empfohlen: alle 15 Minuten).

---

## 3. Doppelbuchungsschutz (5 Schichten)

1. **Interne DB-Prüfung** – `BookingRepository::findConflicts()` prüft
   Überschneidungen (`start < requested_end AND end > requested_start`,
   Status `cancelled` ausgenommen). Antwortet mit HTTP 409
   (`INTERNAL_CONFLICT`).
2. **Echtzeit-iCal-Prüfung** – vor jeder Buchung werden die externen
   Kalender (Booking.com, HomeAway) abgerufen und auf Überschneidung
   geprüft → HTTP 409 (`EXTERNAL_CONFLICT`). Stornierte Events
   (`STATUS:CANCELLED`) werden ignoriert.
3. **Optimistic Locking** – `#[ORM\Version]` auf `Booking::$version`;
   parallele Schreibzugriffe lösen `OptimisticLockException` →
   `CONCURRENT_MODIFICATION`.
4. **Periodischer Sync** – Cron importiert externe Buchungen (Deduplizierung
   über `external_id` + `source`).
5. **DB-Constraints** – Unique-Index `(room_id, start_date, end_date,
   status)` plus Datums-Index für schnelle Konfliktabfragen.

Angrenzende Zeiträume (Check-out = Check-in) sind erlaubt und werden durch
Tests abgesichert (`testBookingAdjacentDatesDoNotConflict`).

---

## 4. Qualität / Testabdeckung

- **216 Tests, 654 Assertions – alle grün, keine Skips**
  (`php bin/phpunit`).
- **Zeilenabdeckung (Coverage): 90,95 %** (Methods 77,7 %, Klassen 51,2 %),
  gemessen mit Xdebug – Kernfunktionen deutlich höher:
  praktisch alle Kernkomponenten >= 85 %, Repositories und zentrale
  Services weitgehend 100 % (Booking/Room/PostRepository, Captcha, Password,
  Auth, Fixtures, FileSecurity, AuditLogger).
- **PHPStan Level 6: 0 Fehler** (`vendor/bin/phpstan analyse`).
- CI: `.github/workflows/tests.yml` (PHP 8.2 + MySQL 8, Migrations, Fixtures,
  JWT-Keypair, PHPUnit).

Wichtige Test-Cluster:

- `tests/Integration/Controller/BookingControllerTest.php` –
  Buchung erstellen, **Überlappungs-Konflikt (409)**, angrenzende Termine,
  Validierungsfehler.
- `tests/Unit/Service/ICalSyncServiceTest.php` – iCal-Parsing (echte
  sabre-Pipeline mit Mock-HttpClient), Konflikt-Erkennung, freie Zeiträume.
- `tests/Integration/Controller/RoomControllerTest.php` – Zimmer-API:
  Liste/Pagination, is_active-Filter, Detail, 404, verfügbare Zimmer
  (belegte Zimmer werden ausgeschlossen).
- `tests/Integration/Controller/AvailabilityControllerTest.php` –
  Verfügbarkeits-API: freier Zeitraum mit Preisen, interner Konflikt,
  externer iCal-Konflikt (via `http_client.mock_response_factory`), stornierte
  Buchung blockiert nicht, Validierungsfehler (400/404).
- `tests/Unit/Service/` – Vollständige Unit-Abdeckung für BookingService
  (alle Fehlercodes inkl. SYSTEM_ERROR/CONCURRENT_MODIFICATION, fail-open),
  AvailabilityService, FileSecurityService (Upload-Sicherheit: MIME-Spoofing,
  Größenlimits, Pfad-Traversal), AuditLoggerService und AppCustomAuthenticator/
  JwtTokenAuthenticator (Cookie-Handling, Token-Fehler).
- `tests/Unit/EventListener/SecurityListenerTest.php` – Exception-Listener
  (kein Stacktrace-Leak in prod), Security-Header (HTTPS-Redirect, HSTS).
- `tests/Integration/Controller/ApiEdgeCasesTest.php` – INVALID_JSON,
  INVALID_DATE_FORMAT, Register (inkl. Duplikat), ungültiges Token, Health.
- `tests/Integration/Controller/HealthAndUserRepositoryTest.php` –
  Health-Check unhealthy-Zweig (DB-Fehler → 503), upgradePassword.
- `tests/Integration/Command/SyncCalendarsCommandTest.php` – Cron-Import:
  echte Buchung aus iCal-Feed in der DB, Idempotenz (keine Dubletten),
  kaputter Feed importiert nichts, Skip bei kürzlich synchronisierten
  Zimmern, unbekannte Raum-ID.
- iCal-HTTP-Antworten werden im Test-Env global über
  `framework.http_client.mock_response_factory` deterministisch geliefert
  (`App\Tests\HttpMock\IcalMockResponseFactory`, URLs mit „error"
  antworten mit HTTP 500).
- `tests/Controller/*` – Auth (JWT/Refresh), Captcha (GD), Contact (inkl.
  Mailer-Exception), Kategorien, Posts.

---

## 5. Behobene Fehler (finaler Hardening-Durchlauf)

| # | Fehler | Fix |
|---|---|---|
| 1 | `ICalSyncService` nutzte die nicht installierte Bibliothek `eluceo/ical` → iCal-Sync wäre in Produktion gecrasht | Komplett auf `sabre/vobject` umgestellt; `checkAvailability(string $url, …)`-Signatur an die Aufrufer angepasst; `HttpClientInterface` injizierbar |
| 2 | `Booking::$source/$status` mit `enumType`, aber String-Werte → „Attempt to read property 'value' on string", Buchungen schlugen fehl | `enumType` entfernt, `Assert\Choice` für `source` ergänzt |
| 3 | `DateTimeImmutable` an `date`-Spalten (mutable) → Buchungs-Flush schlug fehl | `BookingService` erzeugt `\DateTime` |
| 4 | `BookingResponse::$bookingId` erwartete `string`, bekam `Uuid` → 500 bei erfolgreicher Buchung | `toRfc4122()`-Konvertierung |
| 5 | `findConflicts()` fand keine Konflikte (Uuid-Binding als Text vs. BLOB) → **Doppelbuchungen waren möglich!** | Expliziter Parametertyp `'uuid'` |
| 6 | `findByHash()` suchte nach nicht existierendem Feld `hash` → Buchungs-Abfrage/Storno kaputt | `bookingHash` verwendet |
| 7 | `RoomRepository::findByFilters()/countByFilters()` fehlten → `GET /api/rooms` war kaputt | Methoden implementiert (Pagination) |
| 8 | `AvailabilityService` übergab `Uuid` an `string`-Parameter | `toRfc4122()` |
| 9 | String-Arithmetik bei Preisberechnung (`basePrice * nights`) | Float-Casts in `BookingService`/`AvailabilityController` |
| 10 | PHP-8.4-Deprecation (implizit nullable Parameter) | Explizit `?AuthenticationException` |
| 11 | Zwei Buchungs-Integrationstests und der Mailer-Exception-Test waren skipped | Aktiviert/repariert; neue Tests für iCal-Konflikt-Erkennung ergänzt |
| 12 | PHPStan: 65 Fehler | Reale Fehler behoben; Baseline-Config `phpstan.dist.neon` (annotierungs-basierte Muster dokumentiert ignoriert) |
| 13 | `Room`-Entity hatte keine Serializer-`Groups` → `GET /api/rooms` und `GET /api/rooms/{id}` lieferten leere Objekte | `#[Groups(['api'])]` auf id, name, slug, capacity, basePrice, isActive |
| 14 | Routenkonflikt: `/api/rooms/{id}` schluckte `/api/rooms/available` (500er) | UUID-Requirements auf `{id}`-Routen |
| 15 | `bcmul()` ohne ext-bcmath → `GET /api/rooms/available` crash (HTTP 500) | Float-Arithmetik mit `sprintf('%.2F', …)` |
| 16 | sabre liefert `DateTimeImmutable`, Buchungs-Spalten sind mutable `date` → iCal-Import schlug beim Flush fehl | `DateTime::createFromInterface()` beim Import |
| 17 | `Booking`-Entity hatte keine Serializer-`Groups` → `GET /api/bookings/{hash}` lieferte ein leeres Objekt | `#[Groups(['api'])]` auf allen API-relevanten Feldern |
| 18 | Storno einer Buchung, deren Zeitraum bereits storniert war, crashte am Unique-Constraint mit HTTP 500 | Abfangen von `UniqueConstraintViolationException` → HTTP 409 `CANCELLATION_CONFLICT` |
| 19 | Dokumentation beschrieb nicht existierende Endpunkte (`POST /api/rooms`, Kalender-Sync-Endpunkte, Rechnungsstellung), falsche Health-Route (`/api/health`) und nicht vorhandene Konsolen-Befehle (`app:create-room`, `app:create-user`) | API-Doku auf die tatsächlich implementierten Routen neu geschrieben (`debug:router` als Quelle), Health-Route korrigiert zu `GET /health`, Installations-/Benutzerhandbuch auf API-only-Realität umgestellt |
| 20 | `docs/openapi.yaml` war veraltet | Aus den Code-Annotationen regeneriert (`nelmio:apidoc:dump`), Beschreibung im Nelmio-Config auf den PMS-Zweck korrigiert |

---

## 6. Betrieb

```bash
# Installation
composer install
cp .env.example .env.local        # DATABASE_URL etc. anpassen
php bin/console doctrine:migrations:migrate
php bin/console lexik:jwt:generate-keypair

# Cron (alle 15 Minuten)
*/15 * * * * cd /pfad/zum/projekt && php bin/console app:sync-calendars
```

Gesundheit prüfen: `GET /health` · Sync-Status: Spalten
`rooms.last_sync_booking` / `rooms.last_sync_homeaway`.

Fehlverhalten bei iCal-Ausfällen: aktuell **fail-open** (Buchung wird nicht
blockiert, Fehler wird geloggt). Über `BOOKING_FAIL_STRATEGY=closed` ist eine
strikte Variante dokumentiert (siehe [pms_architecture.md](pms_architecture.md)).

---

## 7. Weiterführende Dokumente

| Thema | Dokument |
|---|---|
| Übersicht aller Dokumente | [INDEX.md](INDEX.md) |
| Benutzeranleitung | [benutzerhandbuch.md](benutzerhandbuch.md) |
| Installation | [installationsanleitung.md](installationsanleitung.md) |
| API (Referenz) | [api_dokumentation.md](api_dokumentation.md) · [openapi.yaml](openapi.yaml) |
| Architektur & Überbuchungsschutz | [pms_architecture.md](pms_architecture.md) |
| Entwickler-Handbuch | [entwicklerdokumentation.md](entwicklerdokumentation.md) |
| Deployment-Checkliste | [deployment_checklist.md](deployment_checklist.md) |
| Performance | [performance_testing.md](performance_testing.md) |

### Nachweis der Doppelbuchungs-Vermeidung (Schichtentests)

- `tests/Integration/Service/OverbookingProtectionTest.php`
  - DB-Unique-Constraint blockt identische (Raum, Zeitraum, Status)-Tupel
  - stornierter Zeitraum kann neu gebucht werden (Status ist Teil des Constraints)
  - überlappende, nicht identische Zeiträume werden app-seitig via
    `findConflicts` erkannt (nicht via Constraint)
  - Optimistic Locking verhindert verlorene Updates bei parallelen Änderungen
- `tests/Integration/ICalEndToEndTest.php` – komplette Kette:
  Cron-Import einer Portalbuchung → Verfügbarkeits-API meldet belegt →
  überlappende Direktbuchung wird mit 409 abgelehnt → angrenzende Buchung
  (Check-out = Check-in) wird akzeptiert.

**Bekannte Einschränkung des Unique-Constraints:** Der in der Doku
angenommene partielle Index (`WHERE status != 'cancelled'`) lässt sich mit
ORM-Attributen nicht ausdrücken; der Constraint wirkt auf alle Status. Zwei
*stornierte* Buchungen mit identischem Zeitraum kollidieren daher – dieser
Fall wird seit dem Fix (Nr. 18) mit HTTP 409 sauber gemeldet statt mit 500.

**Hinweis zur API-Nomenklatur:** Request-Felder sind snake_case
(`room_id`, `start_date`), serialisierte Entity-Antworten camelCase
(`guestName`, `startDate`). Konsolidierung (NameConverter) ist als
Roadmap-Punkt notiert.

## 8. Bekannte Grenzen / Roadmap

- E-Mail-Bestätigung bei Buchungen ist als TODO im `BookingService`
  vermerkt (Event-Dispatch vorbereitet).
- Wiederkehrende iCal-Serientermine werden nicht expandiert (in
  Portal-Feeds üblicherweise nicht relevant).
- Frontend/Dashboard liegt außerhalb dieses Repos (API-first).
