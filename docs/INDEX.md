# Hotel-Management-System Dokumentation

Willkommen in der vollständigen Dokumentation des Hotel-Management-Systems (PMS). Diese Dokumentation bietet umfassende Informationen für verschiedene Zielgruppen, darunter Hotelmitarbeiter, Systemadministratoren und Entwickler.

## Dokumentationsübersicht

> **Einstieg & Projektwissen:** [Wissensdatenbank](WISSENSDATENBANK.md) – Kompakter Überblick: Architektur, Doppelbuchungsschutz, Test-/Qualitätsstatus und behobene Fehler.

### Für Benutzer
- **[Benutzerhandbuch](benutzerhandbuch.md)**: Anleitung zur Nutzung des Systems für Hotelmitarbeiter
  - Systemübersicht und Hauptfunktionen
  - Anmeldung und Benutzerverwaltung
  - Zimmerverwaltung
  - Buchungsmanagement
  - Kalendersynchronisation
  - Verfügbarkeitsprüfung
  - Häufig gestellte Fragen (FAQ)

  *Hinweis: Das Repository enthält das Backend (API) – die Bedienung erfolgt
  über die REST-API bzw. ein aufsetzendes Frontend.*

### Für Administratoren
- **[Installationsanleitung](installationsanleitung.md)**: Schritt-für-Schritt-Anleitung zur Installation und Konfiguration
  - Systemanforderungen
  - Vorbereitung der Umgebung
  - Projektinstallation
  - Datenbankkonfiguration
  - Anwendungskonfiguration
  - Erste Schritte
  - Häufige Probleme und Lösungen

### Für Entwickler
- **[API-Dokumentation](api_dokumentation.md)**: Vollständige Dokumentation der RESTful API
  - Einführung und Basis-URL
  - Authentifizierung (JWT im HttpOnly-Cookie)
  - Implementierte Endpunkte (Auth, Zimmer, Buchungen, Verfügbarkeit, CMS, Kontakt, Captcha, Health)
  - Fehlercodes (u. a. 409-Konflikte bei Doppelbuchungen)
  - Beispielanfragen (curl, Python)

- **[Entwicklerdokumentation](entwicklerdokumentation.md)**: Technische Dokumentation für Entwickler
  - Projektübersicht und Architektur
  - Technologie-Stack
  - Projektstruktur
  - Entwicklungsumgebung einrichten
  - Code-Standards und Best Practices
  - Datenbank und Entitäten
  - API-Entwicklung
  - Services und Geschäftslogik
  - Tests
  - Deployment
  - CI/CD
  - Sicherheit
  - Fehlerbehandlung und Logging
  - Performance-Optimierung
  - Beitrag zum Projekt

### Technische Dokumentation
- **[Systemarchitektur](pms_architecture.md)**: Technische Architektur des Systems
  - Entity-Schema und Datenbankdesign
  - 5-Schichten-Überbuchungsschutz
  - iCal-Integrationsspezifikationen
  - API-Endpunkt-Verträge
  - Cron-Job-Spezifikationen
  - Fehlerbehandlungsstrategie

- **[Deployment-Checkliste](deployment_checklist.md)**: Checkliste für die Produktionsbereitstellung
  - Vorbereitung vor dem Deployment
  - Datenbankkonfiguration
  - Cron-Jobs
  - Webserver-Konfiguration
  - Monitoring
  - Sicherheit
  - Post-Deployment-Checks
  - Rollback-Plan

- **[Performance-Testing](performance_testing.md)**: Anleitung zum Performance-Testing
  - Datenbankabfrage-Performance
  - Concurrent Booking Test (Race Condition)
  - iCal Fetch Performance
  - Load Testing
  - Caching-Strategie
  - Datenbankoptimierung
  - Monitoring-Metriken

## Schnellzugriff

| Dokument | Zielgruppe | Beschreibung |
|----------|------------|--------------|
| [Benutzerhandbuch](benutzerhandbuch.md) | Hotelmitarbeiter | Anleitung zur täglichen Nutzung des Systems |
| [Installationsanleitung](installationsanleitung.md) | Systemadministratoren | Schritt-für-Schritt-Installation und Konfiguration |
| [API-Dokumentation](api_dokumentation.md) | Entwickler | Vollständige API-Spezifikation für Integrationen |
| [Entwicklerdokumentation](entwicklerdokumentation.md) | Entwickler | Technische Details zur Entwicklung und Erweiterung |
| [Systemarchitektur](pms_architecture.md) | Entwickler, Architekten | Technische Architektur und Designentscheidungen |
| [Deployment-Checkliste](deployment_checklist.md) | DevOps, Administratoren | Checkliste für Produktionsdeployments |
| [Performance-Testing](performance_testing.md) | Entwickler, QA | Anleitung zum Performance-Testing |

## Suchfunktion

Verwenden Sie die Suchfunktion Ihres Browsers (Strg+F / Cmd+F), um schnell nach bestimmten Themen oder Schlüsselwörtern in der Dokumentation zu suchen.

## Feedback und Verbesserungen

Wenn Sie Fehler in der Dokumentation finden oder Verbesserungsvorschläge haben, erstellen Sie bitte ein Issue im GitHub-Repository oder reichen Sie einen Pull Request mit Ihren Änderungen ein.

---
© 2026 Hotel-Management-System. Alle Rechte vorbehalten.