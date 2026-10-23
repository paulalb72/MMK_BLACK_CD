# makemyki.de

Website von MakeMyKI. Statisches HTML (CSS/JS inline) plus kleine PHP-Skripte für Bilder, das Anfrageformular und eine cookielose Besucherstatistik. Läuft als Docker-Container (PHP 8.3 mit Apache), z. B. auf Coolify.

## Aufbau

- `*/index.html` – alle Seiten (inkl. `/danke/` und `/fehler/`, die auch als 404-Seite dient)
- `bild/index.php` – liefert `/bild/?n=<name>` aus `bild/img/<name>.webp`
- `schrift/` – Schriften lokal (Inter, Poppins, Manrope; SIL Open Font License), keine Verbindung zu Google
- `api/anfrage.php` – Formular-Handler für Anfrageformular und KI-Röntgen, Versand über `api/mail.php`
- `statistik/` – Besucherstatistik und Wochenbericht
- `.htaccess` – 404-Seite, Weiterleitungen (www, HTTPS, alte URLs), Sperren
- `Dockerfile`, `docker-entrypoint.sh` – Container-Image

Extern eingebunden: Calendly (Terminbuchung) und die KI-Röntgen-/Diktat-API unter `fulfillment.makemypage.de`.

## Deployment mit Coolify

1. **Neue Ressource** aus dem GitHub-Repo anlegen, Build Pack **Dockerfile**, Port **80**.
2. **Domains:** `https://makemyki.de` und `https://www.makemyki.de` (www wird auf ohne www umgeleitet). TLS übernimmt der Coolify-Proxy.
3. **Environment Variables** (Developer view, als Block einfügen):
   ```
   SMTP_USER=info@makemyki.de
   SMTP_PASS=<App-Passwort>
   REPORT_TO=<Empfänger des Wochenberichts>
   BERICHT_SCHLUESSEL=<langer Zufallswert>
   ```
4. **Persistent Storage** – zwei Volumes, sonst gehen Daten bei jedem Deployment verloren:
   - `/var/www/html/statistik/daten` (Besucherstatistik)
   - `/var/www/html/api/daten` (Anfragen, die nicht zugestellt werden konnten)
5. **Scheduled Task** für den Wochenbericht, z. B. montags 7:00 Uhr (`0 7 * * 1`):
   `php /var/www/html/statistik/bericht.php`

Die Datenordner sind zusätzlich direkt in der Apache-Konfiguration des Images gesperrt, damit sie auch mit eingehängtem Volume nie öffentlich abrufbar sind.

## Mailversand (Anfragen und Wochenbericht)

Alle Mails gehen per SMTP über Google Workspace (`api/mail.php`). Zugangsdaten kommen aus Umgebungsvariablen (Coolify) oder aus einer `.env`-Datei (eine Ebene über dem Webroot oder im Webroot; dort per `.htaccess` gesperrt). Vorlage: `.env.example`.

- App-Passwort: im Google-Konto von info@makemyki.de unter myaccount.google.com/apppasswords anlegen (2-Faktor-Anmeldung muss aktiv sein).
- Testmail: `php api/mail-test.php` (in Coolify über das Terminal des Containers) oder `https://makemyki.de/api/mail-test.php?schluessel=<BERICHT_SCHLUESSEL>`.
- Kann eine Anfrage nicht zugestellt werden, landet sie in `api/daten/nicht-zugestellt.txt` (gesperrt) und die Person sieht `/fehler/`.

## Besucherstatistik und Wochenbericht

Eigene, cookielose Statistik ohne Drittanbieter (`/statistik/`):

- Jede Seite meldet Seitenaufrufe und Klicks (Calendly, Telefon, E-Mail, WhatsApp, KI-Röntgen) an `statistik/erfassen.php`. Abgeschickte Anfragen zählt `api/anfrage.php` mit.
- Gespeichert werden Datum, Seite, Herkunfts-Domain, Gerätetyp und ein Besucher-Kennwert, der täglich neu gebildet wird. Keine IP-Adressen, keine Cookies. Rohdaten liegen in `statistik/daten/` und werden nach 90 Tagen gelöscht.
- `statistik/bericht.php` schickt die Auswertung der letzten 7 Tage per E-Mail an `REPORT_TO`.

Vorschau ohne Versand: `https://makemyki.de/statistik/bericht.php?schluessel=<BERICHT_SCHLUESSEL>&vorschau=1`. Eigene Besuche ausschließen: auf jedem eigenen Gerät einmal `https://makemyki.de/statistik/ignorieren.php` aufrufen.

## Lokal ansehen

Ohne PHP (Formulare werden nur in der Konsole ausgegeben, keine Mail):

```
node dev-server.js
```

→ http://localhost:8080

Mit Docker (vollständig, wie auf dem Server):

```
docker build -t makemyki .
docker run --rm -p 8080:80 -e SMTP_USER=info@makemyki.de -e SMTP_PASS=... makemyki
```

Lokal ohne Proxy leitet die Seite auf HTTPS um; zum Testen den Header `X-Forwarded-Proto: https` mitschicken.
