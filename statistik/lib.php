<?php
// Gemeinsame Funktionen der Besucherstatistik.
// Datensparsam: keine Cookies, keine IP-Adressen. Besucher werden über einen Hash gezählt,
// dessen Zufallswert (Salz) jeden Tag neu erzeugt und danach gelöscht wird.

require_once __DIR__ . '/konfig.php';
date_default_timezone_set('Europe/Berlin');

const DATEN = __DIR__ . '/daten';

function statistik_aus(): bool {
    // Eigene Besuche nicht mitzählen (Cookie wird nur über /statistik/ignorieren.php gesetzt)
    return !empty($_COOKIE['mmk_nicht_zaehlen']);
}

function ist_bot(string $ua): bool {
    return $ua === '' || (bool)preg_match('/bot|crawl|spider|slurp|headless|lighthouse|preview|monitor|curl|wget|python|java\/|scrapy|facebookexternalhit/i', $ua);
}

function geraet(string $ua): string {
    if (preg_match('/ipad|tablet|kindle|silk/i', $ua)) return 'Tablet';
    if (preg_match('/mobile|android|iphone|ipod/i', $ua)) return 'Mobil';
    return 'Desktop';
}

function herkunft(string $referrer): string {
    $host = strtolower((string)parse_url($referrer, PHP_URL_HOST));
    if ($host === '' || in_array($host, EIGENE_HOSTS, true)) return '';
    return substr(preg_replace('/^www\./', '', $host), 0, 60);
}

function client_ip(): string {
    // Direkte Verbindungsadresse. Nur wenn ein Proxy mit interner Adresse davorsitzt, gilt der Weiterleitungs-Header.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $oeffentlich = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    if (!$oeffentlich) {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $k) {
            if (!empty($_SERVER[$k])) return trim(explode(',', $_SERVER[$k])[0]);
        }
    }
    return $ip;
}

function besucher_hash(string $ua): string {
    if (!is_dir(DATEN)) @mkdir(DATEN, 0750, true);
    $datei = DATEN . '/salz-' . date('Y-m-d') . '.txt';
    $salz = is_file($datei) ? trim((string)file_get_contents($datei)) : '';
    if ($salz === '') {
        $salz = bin2hex(random_bytes(16));
        file_put_contents($datei, $salz, LOCK_EX);
        foreach (glob(DATEN . '/salz-*.txt') ?: [] as $alt) { if ($alt !== $datei) @unlink($alt); }
    }
    return substr(hash('sha256', $salz . '|' . client_ip() . '|' . $ua), 0, 16);
}

function speichern(array $eintrag): void {
    if (!is_dir(DATEN)) @mkdir(DATEN, 0750, true);
    @file_put_contents(DATEN . '/' . date('Y-m-d') . '.jsonl', json_encode($eintrag, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}

// Serverseitiges Ereignis, z. B. eine abgeschickte Anfrage
function statistik_ereignis(string $name, string $pfad = '/'): void {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (statistik_aus() || ist_bot($ua) || !preg_match('/^[a-z0-9_]{1,40}$/', $name)) return;
    if (!preg_match('#^/[A-Za-z0-9/_\-.]{0,150}$#', $pfad)) $pfad = '/';
    speichern(['z' => date('c'), 't' => 'ev', 'p' => $pfad, 'e' => $name, 'g' => geraet($ua), 'b' => besucher_hash($ua)]);
}
