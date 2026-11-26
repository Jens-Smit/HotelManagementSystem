# Installationsanleitung für das Hotel-Management-System

## Inhaltsverzeichnis
1. [Systemanforderungen](#systemanforderungen)
2. [Vorbereitung der Umgebung](#vorbereitung-der-umgebung)
3. [Projekt klonen](#projekt-klonen)
4. [Abhängigkeiten installieren](#abhängigkeiten-installieren)
5. [Umgebungsvariablen konfigurieren](#umgebungsvariablen-konfigurieren)
6. [Datenbank einrichten](#datenbank-einrichten)
7. [Datenbankmigrationen ausführen](#datenbankmigrationen-ausführen)
8. [Anwendung starten](#anwendung-starten)
9. [Erste Schritte](#erste-schritte)
10. [Häufige Probleme und Lösungen](#häufige-probleme-und-lösungen)

---

## Systemanforderungen

### Server-Anforderungen
- **Betriebssystem**: Linux (empfohlen), Windows oder macOS
- **Webserver**: Apache 2.4+ oder Nginx 1.18+
- **PHP**: Version 8.2 oder höher
- **Datenbank**: MySQL 8.0+ oder MariaDB 10.4+
- **RAM**: Mindestens 2 GB (4 GB empfohlen)
- **Speicherplatz**: Mindestens 100 MB für die Anwendung, zusätzlich Platz für Datenbank und Logs

### PHP-Erweiterungen
Stellen Sie sicher, dass die folgenden PHP-Erweiterungen installiert und aktiviert sind:
- `ctype`
- `curl`
- `dom`
- `fileinfo`
- `filter`
- `gd` (für Captcha-Generierung)
- `iconv`
- `intl`
- `json`
- `mbstring`
- `openssl`
- `pdo_mysql`
- `simplexml`
- `tokenizer`
- `xml`
- `xmlwriter`

### Composer
- **Composer**: Version 2.0 oder höher

---

## Vorbereitung der Umgebung

### PHP konfigurieren
1. Öffnen Sie die `php.ini`-Datei (Pfad abhängig von Ihrer Installation, z. B. `/etc/php/8.1/apache2/php.ini`).
2. Stellen Sie sicher, dass die folgenden Einstellungen konfiguriert sind:
   ```ini
   memory_limit = 256M
   upload_max_filesize = 20M
   post_max_size = 20M
   max_execution_time = 120
   date.timezone = Europe/Berlin
   ```
3. Starten Sie den Webserver neu:
   ```bash
   sudo systemctl restart apache2  # Für Apache
   sudo systemctl restart nginx    # Für Nginx
   sudo systemctl restart php8.1-fpm  # Für PHP-FPM
   ```

### Datenbank vorbereiten
1. Melden Sie sich bei Ihrem MySQL/MariaDB-Server an:
   ```bash
   mysql -u root -p
   ```
2. Erstellen Sie eine neue Datenbank und einen Benutzer:
   ```sql
   CREATE DATABASE hotel_management_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'pms_user'@'localhost' IDENTIFIED BY 'sicheres_passwort';
   GRANT ALL PRIVILEGES ON hotel_management_system.* TO 'pms_user'@'localhost';
   FLUSH PRIVILEGES;
   ```
3. Beenden Sie die MySQL-Konsole:
   ```sql
   EXIT;
   ```

---

## Projekt klonen

1. Klonen Sie das Projekt-Repository in Ihr gewünschtes Verzeichnis:
   ```bash
   git clone https://github.com/Jens-Smit/HotelManagementSystem.git Hotel-Management-System
   cd Hotel-Management-System
   ```
2. Wechseln Sie in das Projektverzeichnis:
   ```bash
   cd Hotel-Management-System
   ```

---

## Abhängigkeiten installieren

1. Installieren Sie die PHP-Abhängigkeiten mit Composer:
   ```bash
   composer install --optimize-autoloader --no-dev
   ```
   - Verwenden Sie `--no-dev`, wenn Sie die Anwendung in der Produktion installieren.
   - Für die Entwicklung entfernen Sie das `--no-dev`-Flag.

2. Installieren Sie die JavaScript-Abhängigkeiten (falls zutreffend):
   ```bash
   npm install
   npm run build
   ```

---

## Umgebungsvariablen konfigurieren

1. Kopieren Sie die Beispiel-Konfigurationsdatei `.env.example` zu `.env`:
   ```bash
   cp .env.example .env
   ```

2. Öffnen Sie die `.env`-Datei in einem Texteditor und konfigurieren Sie die folgenden Variablen:
   ```ini
   # Anwendungsumgebung
   APP_ENV=dev  # Für Entwicklung, setzen Sie auf "prod" für Produktion
   APP_DEBUG=1  # Setzen Sie auf "0" für Produktion
   APP_SECRET=IhrGeheimesTokenHierGenerieren  # Führen Sie `php bin/console secrets:generate-keys` aus

   # Datenbank-Konfiguration
   DATABASE_URL="mysql://pms_user:sicheres_passwort@127.0.0.1:3306/hotel_management_system?serverVersion=8.0"

   # JWT-Konfiguration (für API-Authentifizierung)
   JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
   JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
   JWT_PASSPHRASE=IhrJWTGeheimnisHier

   # Mailer-Konfiguration
   MAILER_DSN=smtp://benutzername:passwort@smtp.example.com:587
   ```

3. Generieren Sie die JWT-Schlüssel:
   ```bash
   mkdir -p config/jwt
   openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
   openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
   ```

---

## Datenbank einrichten

1. Überprüfen Sie die Datenbankverbindung:
   ```bash
   php bin/console doctrine:database:create --if-not-exists
   ```

2. Führen Sie die Datenbankmigrationen aus (siehe nächster Abschnitt).

---

## Datenbankmigrationen ausführen

1. Überprüfen Sie den Status der Migrationen:
   ```bash
   php bin/console doctrine:migrations:status
   ```

2. Führen Sie die Migrationen aus:
   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   ```

3. Überprüfen Sie, ob alle Migrationen erfolgreich ausgeführt wurden:
   ```bash
   php bin/console doctrine:migrations:status
   ```

---

## Anwendung starten

### Entwicklungsumgebung
1. Starten Sie den Symfony-Entwicklungsserver:
   ```bash
   symfony serve
   ```
   - Die Anwendung ist nun unter `http://localhost:8000` erreichbar.

### Produktionsumgebung
1. Konfigurieren Sie Ihren Webserver (Apache/Nginx) so, dass er auf das `public/`-Verzeichnis zeigt.
2. Setzen Sie die richtigen Dateiberechtigungen:
   ```bash
   chmod -R 775 var/
   chown -R www-data:www-data var/  # Passen Sie den Benutzer an Ihren Webserver an
   ```
3. Leeren Sie den Cache:
   ```bash
   php bin/console cache:clear --env=prod
   php bin/console cache:warmup --env=prod
   ```

---

## Erste Schritte

### Administrator-Benutzer erstellen
1. Registrieren Sie den ersten Benutzer über die API (oder laden Sie die
   Demo-Daten via `php bin/console doctrine:fixtures:load`):

   POST /api/register mit {"email": "admin@example.com", "password": "IhrSicheresPasswort"}

   - Die Rollenvergabe (ROLE_ADMIN) erfolgt derzeit direkt in der
     Datenbanktabelle "user" (Spalte "roles").

### Anwendung testen
1. Öffnen Sie die Anwendung in Ihrem Browser:
   - Entwicklung: `http://localhost:8000`
   - Produktion: Die von Ihnen konfigurierte Domain
2. Melden Sie sich mit dem erstellten Administrator-Benutzer an.
3. Navigieren Sie durch die Anwendung, um die Funktionen zu testen.

---

## Häufige Probleme und Lösungen

### Problem: Composer-Installation schlägt fehl
- **Lösung**: Stellen Sie sicher, dass alle PHP-Erweiterungen installiert sind und die `php.ini`-Einstellungen korrekt sind.

### Problem: Datenbankverbindung schlägt fehl
- **Lösung**: Überprüfen Sie die `DATABASE_URL` in der `.env`-Datei und stellen Sie sicher, dass der Datenbankbenutzer die richtigen Berechtigungen hat.

### Problem: JWT-Schlüssel können nicht generiert werden
- **Lösung**: Stellen Sie sicher, dass OpenSSL installiert ist und Sie Schreibrechte auf das `config/jwt/`-Verzeichnis haben.

### Problem: Migrationen schlagen fehl
- **Lösung**: Überprüfen Sie die Datenbankverbindung und stellen Sie sicher, dass die Datenbank leer ist oder bereits die richtige Struktur hat.

### Problem: Anwendung ist langsam
- **Lösung**: Stellen Sie sicher, dass OPcache aktiviert ist und die `php.ini`-Einstellungen optimiert sind.

### Problem: E-Mails werden nicht gesendet
- **Lösung**: Überprüfen Sie die `MAILER_DSN`-Konfiguration in der `.env`-Datei und stellen Sie sicher, dass der SMTP-Server erreichbar ist.

---