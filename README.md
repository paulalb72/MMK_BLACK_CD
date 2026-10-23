# makemyki.de

Wiederhergestellt aus der Live-Seite am 23.10.2026. Statisches HTML (CSS/JS inline) plus kleine PHP-Skripte. Hosting bei Hetzner, braucht PHP 8 und Apache (wegen der `.htaccess`-Dateien).

## Aufbau

- `*/index.html` – alle Seiten (inkl. `/danke/` und `/fehler/`, die auch als 404-Seite dient)
- `bild/index.php` – liefert `/bild/?n=<name>` aus `bild/img/<name>.webp`
- `api/anfrage.php` – Formular-Handler für Kontakt-Wizard und KI-Röntgen; verschickt eine Mail und leitet auf `/danke/` bzw. `/fehler/` weiter. **Empfänger und Absender oben in der Datei prüfen.**
- `.htaccess` – 404-Seite, www → ohne www, HTTPS, Header
- `robots.txt`, `sitemap.xml`

Extern eingebunden (nicht Teil des Repos): Google Fonts, Calendly, die KI-Röntgen- und Diktat-API unter `fulfillment.makemypage.de`.

## Mailversand (Anfragen und Wochenbericht)

Alle Mails gehen per SMTP über Google Workspace (`api/mail.php`). Zugangsdaten stehen in der `.env`:

```
SMTP_USER=info@makemyki.de
SMTP_PASS=<App-Passwort>
```

- App-Passwort: im Google-Konto von info@makemyki.de unter myaccount.google.com/apppasswords anlegen (2-Faktor-Anmeldung muss aktiv sein).
- Die `.env` am besten **eine Ebene über dem Webroot** ablegen; im Webroot funktioniert sie auch und ist per `.htaccess` gesperrt.
- Optionale Einstellungen siehe `.env.example` (z. B. `REPORT_TO` für den Wochenbericht).
- Testmail: `php api/mail-test.php` oder `https://makemyki.de/api/mail-test.php?schluessel=<BERICHT_SCHLUESSEL aus der .env>`.
- Kann eine Anfrage nicht zugestellt werden, landet sie in `api/daten/nicht-zugestellt.txt` (gesperrt) und die Person sieht `/fehler/`.

## Besucherstatistik und Wochenbericht

Eigene, cookielose Statistik ohne Drittanbieter (`/statistik/`):

- Jede Seite meldet Seitenaufrufe und Klicks (Calendly, Telefon, E-Mail, WhatsApp, KI-Röntgen) an `statistik/erfassen.php`. Abgeschickte Anfragen zählt `api/anfrage.php` mit.
- Gespeichert werden Datum, Seite, Herkunfts-Domain, Gerätetyp und ein Besucher-Kennwert, der täglich neu gebildet wird. Keine IP-Adressen, keine Cookies. Rohdaten liegen in `statistik/daten/` (gesperrt) und werden nach 90 Tagen gelöscht.
- `statistik/bericht.php` schickt die Auswertung der letzten 7 Tage per E-Mail. Empfänger (`REPORT_TO`) und Schlüssel (`BERICHT_SCHLUESSEL`) stehen in der `.env`.

**Wöchentlichen Versand einrichten (Hetzner):** einen Cronjob anlegen, z. B. montags 7:00 Uhr.
- Hetzner Webhosting (konsoleH → Cronjobs): Skript `statistik/bericht.php` oder diese Adresse aufrufen:
  `https://makemyki.de/statistik/bericht.php?schluessel=<BERICHT_SCHLUESSEL aus der .env>`
- Eigener Server (crontab): `0 7 * * 1 php /pfad/zum/webroot/statistik/bericht.php`

Vorschau ohne Versand: dieselbe Adresse mit `&vorschau=1`. Eigene Besuche ausschließen: auf jedem eigenen Gerät einmal `https://makemyki.de/statistik/ignorieren.php` aufrufen.

## Lokal ansehen

```
node dev-server.js
```

→ http://localhost:8080 (Formulare werden nur in der Konsole ausgegeben, keine Mail).

## Deployment

Den Ordnerinhalt (ohne `dev-server.js` und `README.md`) per FTP/SFTP in das Webroot von makemyki.de laden.
