# Benutzerhandbuch für das Hotel-Management-System

## Inhaltsverzeichnis
1. [Systemübersicht](#systemübersicht)
2. [Anmeldung und Benutzerverwaltung](#anmeldung-und-benutzerverwaltung)
3. [Zimmerverwaltung](#zimmerverwaltung)
4. [Buchungsmanagement](#buchungsmanagement)
5. [Kalendersynchronisation](#kalendersynchronisation)
6. [Verfügbarkeitsprüfung](#verfügbarkeitsprüfung)
7. [Rechnungsstellung und Zahlungen](#rechnungsstellung-und-zahlungen)
8. [Berichte und Statistiken](#berichte-und-statistiken)
9. [Häufig gestellte Fragen (FAQ)](#häufig-gestellte-fragen-faq)

---

## Systemübersicht

Das Hotel-Management-System (PMS) ist eine webbasierte Anwendung zur Verwaltung von Hotelzimmern, Buchungen und Verfügbarkeiten. Das System bietet folgende Hauptfunktionen:

- **Zimmerverwaltung**: Verwalten Sie alle Zimmer Ihres Hotels mit Details wie Kapazität, Preis und Verfügbarkeit.
- **Buchungsmanagement**: Erstellen, bearbeiten und stornieren Sie Buchungen von Gästen.
- **Kalendersynchronisation**: Synchronisieren Sie Buchungen mit externen Plattformen wie Booking.com und HomeAway.
- **Verfügbarkeitsprüfung**: Prüfen Sie die Verfügbarkeit von Zimmern in Echtzeit.
- **Berichte**: Erstellen Sie Berichte über Auslastung, Umsatz und andere wichtige Kennzahlen.

---

## Anmeldung und Benutzerverwaltung

### Anmeldung
1. Öffnen Sie die Anmeldeseite des Systems in Ihrem Browser.
2. Geben Sie Ihren Benutzernamen und Ihr Passwort ein.
3. Klicken Sie auf "Anmelden".

### Benutzerrollen
- **Administrator**: Voller Zugriff auf alle Funktionen, einschließlich Benutzerverwaltung.
- **Rezeptionist**: Zugriff auf Buchungsmanagement und Verfügbarkeitsprüfung.
- **Mitarbeiter**: Eingeschränkter Zugriff auf bestimmte Funktionen.

### Passwort ändern
1. Klicken Sie auf Ihr Profilbild in der oberen rechten Ecke.
2. Wählen Sie "Passwort ändern".
3. Geben Sie Ihr aktuelles Passwort und das neue Passwort ein.
4. Bestätigen Sie das neue Passwort und klicken Sie auf "Speichern".

---

## Zimmerverwaltung

### Zimmer anlegen
1. Navigieren Sie zu "Zimmer" > "Neues Zimmer".
2. Geben Sie die folgenden Informationen ein:
   - **Name**: Der Name des Zimmers (z. B. "Standard Zimmer").
   - **Slug**: Ein eindeutiger Bezeichner für das Zimmer (z. B. "standard-zimmer").
   - **Kapazität**: Die maximale Anzahl von Gästen, die im Zimmer übernachten können.
   - **Basispreis**: Der Standardpreis pro Nacht.
   - **iCal-URLs**: URLs für die Kalendersynchronisation mit externen Plattformen (optional).
3. Klicken Sie auf "Speichern".

### Zimmer bearbeiten
1. Navigieren Sie zu "Zimmer" und wählen Sie das gewünschte Zimmer aus.
2. Klicken Sie auf "Bearbeiten".
3. Nehmen Sie die gewünschten Änderungen vor und klicken Sie auf "Speichern".

### Zimmer deaktivieren
1. Navigieren Sie zu "Zimmer" und wählen Sie das gewünschte Zimmer aus.
2. Klicken Sie auf "Bearbeiten".
3. Setzen Sie das Häkchen bei "Aktiv" auf "Nein".
4. Klicken Sie auf "Speichern".

---

## Buchungsmanagement

### Buchung erstellen
1. Navigieren Sie zu "Buchungen" > "Neue Buchung".
2. Wählen Sie das gewünschte Zimmer aus.
3. Geben Sie die folgenden Informationen ein:
   - **Anreisedatum**: Das Datum, an dem der Gast anreist.
   - **Abreisedatum**: Das Datum, an dem der Gast abreist.
   - **Gastname**: Der Name des Gastes.
   - **Gast-E-Mail**: Die E-Mail-Adresse des Gastes.
   - **Gast-Telefon**: Die Telefonnummer des Gastes (optional).
   - **Buchungsquelle**: Die Quelle der Buchung (z. B. Website, Booking.com, HomeAway).
   - **Status**: Der Status der Buchung (z. B. "Bestätigt", "Storniert").
4. Klicken Sie auf "Speichern".

### Buchung bearbeiten
1. Navigieren Sie zu "Buchungen" und wählen Sie die gewünschte Buchung aus.
2. Klicken Sie auf "Bearbeiten".
3. Nehmen Sie die gewünschten Änderungen vor und klicken Sie auf "Speichern".

### Buchung stornieren
1. Navigieren Sie zu "Buchungen" und wählen Sie die gewünschte Buchung aus.
2. Klicken Sie auf "Bearbeiten".
3. Ändern Sie den Status auf "Storniert".
4. Klicken Sie auf "Speichern".

---

## Kalendersynchronisation

### iCal-URLs hinzufügen
1. Navigieren Sie zu "Zimmer" und wählen Sie das gewünschte Zimmer aus.
2. Klicken Sie auf "Bearbeiten".
3. Geben Sie die iCal-URLs für Booking.com und/oder HomeAway ein.
4. Klicken Sie auf "Speichern".

### Manuelle Synchronisation
1. Navigieren Sie zu "Kalender" > "Synchronisation".
2. Klicken Sie auf "Jetzt synchronisieren", um die Kalender manuell zu aktualisieren.

### Automatische Synchronisation
Das System synchronisiert die Kalender automatisch alle 15 Minuten. Sie können diesen Intervall in der Konfiguration anpassen.

---

## Verfügbarkeitsprüfung

### Verfügbarkeit prüfen
1. Navigieren Sie zu "Verfügbarkeit".
2. Wählen Sie das gewünschte Zimmer aus.
3. Geben Sie das Anreisedatum und das Abreisedatum ein.
4. Klicken Sie auf "Verfügbarkeit prüfen".

### Verfügbarkeitskalender
Der Verfügbarkeitskalender zeigt die Verfügbarkeit aller Zimmer für einen bestimmten Zeitraum an. Sie können den Kalender nach Zimmer und Datum filtern.

---

## Rechnungsstellung und Zahlungen

### Rechnung erstellen
1. Navigieren Sie zu "Buchungen" und wählen Sie die gewünschte Buchung aus.
2. Klicken Sie auf "Rechnung erstellen".
3. Geben Sie die Zahlungsinformationen ein (z. B. Zahlungsmethode, Betrag).
4. Klicken Sie auf "Speichern".

### Zahlung bestätigen
1. Navigieren Sie zu "Buchungen" und wählen Sie die gewünschte Buchung aus.
2. Klicken Sie auf "Zahlung bestätigen".
3. Geben Sie die Zahlungsdetails ein und klicken Sie auf "Speichern".

---

## Berichte und Statistiken

### Auslastungsbericht
1. Navigieren Sie zu "Berichte" > "Auslastung".
2. Wählen Sie den gewünschten Zeitraum aus.
3. Klicken Sie auf "Bericht generieren".

### Umsatzbericht
1. Navigieren Sie zu "Berichte" > "Umsatz".
2. Wählen Sie den gewünschten Zeitraum aus.
3. Klicken Sie auf "Bericht generieren".

### Buchungsquellenbericht
1. Navigieren Sie zu "Berichte" > "Buchungsquellen".
2. Wählen Sie den gewünschten Zeitraum aus.
3. Klicken Sie auf "Bericht generieren".

---

## Häufig gestellte Fragen (FAQ)

### Wie kann ich eine Buchung stornieren?
Navigieren Sie zu "Buchungen", wählen Sie die gewünschte Buchung aus und ändern Sie den Status auf "Storniert".

### Wie füge ich ein neues Zimmer hinzu?
Navigieren Sie zu "Zimmer" > "Neues Zimmer" und geben Sie die erforderlichen Informationen ein.

### Wie synchronisiere ich die Kalender manuell?
Navigieren Sie zu "Kalender" > "Synchronisation" und klicken Sie auf "Jetzt synchronisieren".

### Wie prüfe ich die Verfügbarkeit eines Zimmers?
Navigieren Sie zu "Verfügbarkeit", wählen Sie das gewünschte Zimmer aus und geben Sie das Anreisedatum und das Abreisedatum ein.

### Wie erstelle ich eine Rechnung?
Navigieren Sie zu "Buchungen", wählen Sie die gewünschte Buchung aus und klicken Sie auf "Rechnung erstellen".

---