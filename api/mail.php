<?php
// Mailversand über SMTP (Standard: Google Workspace / Gmail) mit Zugangsdaten aus der .env.
// Gesucht wird die .env eine Ebene über dem Webroot (empfohlen) oder im Webroot selbst.
//
// .env:
//   SMTP_USER=info@makemyki.de
//   SMTP_PASS=abcd efgh ijkl mnop      (App-Passwort, Leerzeichen sind egal)
// optional: SMTP_HOST (smtp.gmail.com), SMTP_PORT (465), MAIL_TO (Empfänger der Anfragen, sonst SMTP_USER),
//           REPORT_TO (Empfänger des Wochenberichts), MAIL_FROM_NAME

date_default_timezone_set('Europe/Berlin');

function env_laden(): array {
    static $env = null;
    if ($env !== null) return $env;
    $env = [];
    foreach ([dirname(__DIR__, 2) . '/.env', dirname(__DIR__) . '/.env'] as $datei) {
        if (!is_readable($datei)) continue;
        foreach (file($datei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $zeile) {
            $zeile = trim($zeile);
            if ($zeile === '' || $zeile[0] === '#' || !str_contains($zeile, '=')) continue;
            [$k, $v] = array_map('trim', explode('=', $zeile, 2));
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) $v = substr($v, 1, -1);
            $env[$k] = $v;
        }
        break;
    }
    return $env;
}

function env(string $schluessel, string $standard = ''): string {
    $e = env_laden();
    if (isset($e[$schluessel]) && $e[$schluessel] !== '') return $e[$schluessel];
    $g = getenv($schluessel);
    return ($g !== false && $g !== '') ? $g : $standard;
}

function kopf_kodieren(string $text): string {
    return preg_match('/[^\x20-\x7E]/', $text) ? '=?UTF-8?B?' . base64_encode($text) . '?=' : $text;
}

/**
 * Schickt eine Mail per SMTP. Gibt [true, ''] oder [false, Fehlerbeschreibung] zurück.
 * $an: eine Adresse oder mehrere mit Komma getrennt.
 */
function smtp_mail(string $an, string $betreff, string $inhalt, bool $html = false, string $antwortAn = '', string $antwortName = ''): array {
    $host = env('SMTP_HOST', 'smtp.gmail.com');
    $port = (int)env('SMTP_PORT', '465');
    $user = env('SMTP_USER');
    $pass = str_replace(' ', '', env('SMTP_PASS'));
    if ($user === '' || $pass === '') return [false, 'SMTP_USER oder SMTP_PASS fehlt in der .env'];

    $empfaenger = array_values(array_filter(array_map('trim', explode(',', $an)), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
    if (!$empfaenger) return [false, 'Kein gültiger Empfänger'];

    // ssl = verschlüsselt ab Verbindungsaufbau (Port 465), tls = STARTTLS (Port 587), none = nur für lokale Tests
    $sicher = strtolower(env('SMTP_SECURE', $port === 465 ? 'ssl' : 'tls'));
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $s = @stream_socket_client(($sicher === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$s) return [false, "Keine Verbindung zu $host:$port (" . ($errstr ?: 'TLS-Aufbau oder Zertifikatsprüfung fehlgeschlagen') . ')'];
    stream_set_timeout($s, 20);

    $lesen = function () use ($s): string {
        $antwort = '';
        while (($z = fgets($s, 1024)) !== false) {
            $antwort .= $z;
            if (strlen($z) < 4 || $z[3] === ' ') break;
        }
        return $antwort;
    };
    // $anzeige ersetzt den Befehl in Fehlermeldungen (damit Zugangsdaten nie im Log landen)
    $befehl = function (?string $cmd, array $ok, ?string $anzeige = null) use ($s, $lesen): string {
        if ($cmd !== null) fwrite($s, $cmd . "\r\n");
        $a = $lesen();
        if (!in_array((int)substr($a, 0, 3), $ok, true)) {
            throw new RuntimeException(($anzeige ?? $cmd ?? 'Verbindung') . ' → ' . trim($a ?: 'keine Antwort'));
        }
        return $a;
    };

    try {
        $befehl(null, [220]);
        $ich = preg_replace('/[^A-Za-z0-9.\-]/', '', $_SERVER['SERVER_NAME'] ?? '') ?: 'localhost';
        $befehl('EHLO ' . $ich, [250]);
        if ($sicher === 'tls') {
            $befehl('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('STARTTLS fehlgeschlagen');
            $befehl('EHLO ' . $ich, [250]);
        }
        $befehl('AUTH LOGIN', [334]);
        $befehl(base64_encode($user), [334], 'Benutzername');
        $befehl(base64_encode($pass), [235], 'Passwort (App-Passwort prüfen)');
        $befehl('MAIL FROM:<' . $user . '>', [250]);
        foreach ($empfaenger as $e) $befehl('RCPT TO:<' . $e . '>', [250, 251]);
        $befehl('DATA', [354]);

        $domain = substr(strrchr($user, '@'), 1);
        $kopf = [
            'Date: ' . date('r'),
            'From: ' . kopf_kodieren(env('MAIL_FROM_NAME', 'MakeMyKI Website')) . ' <' . $user . '>',
            'To: ' . implode(', ', $empfaenger),
            'Subject: ' . kopf_kodieren($betreff),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/' . ($html ? 'html' : 'plain') . '; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        if ($antwortAn !== '' && filter_var($antwortAn, FILTER_VALIDATE_EMAIL)) {
            $name = trim(preg_replace('/[\r\n<>"]/', '', $antwortName));
            $kopf[] = 'Reply-To: ' . ($name !== '' ? kopf_kodieren($name) . ' ' : '') . '<' . $antwortAn . '>';
        }
        $nachricht = implode("\r\n", $kopf) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($inhalt), 76, "\r\n"));
        fwrite($s, $nachricht . "\r\n.\r\n");
        $befehl(null, [250], 'Nachricht');
        fwrite($s, "QUIT\r\n");
        fclose($s);
        return [true, ''];
    } catch (Throwable $e) {
        @fwrite($s, "QUIT\r\n");
        @fclose($s);
        return [false, $e->getMessage()];
    }
}
