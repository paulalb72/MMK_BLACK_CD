<?php
// Gemeinsame Helfer für KI-Röntgen und Diktat: Claude-Client, Anfragelimit, JSON-Antworten.
// API-Key in ANTHROPIC_API_KEY (Coolify-Umgebungsvariable oder .env, siehe api/mail.php).

require_once __DIR__ . '/mail.php'; // env()

// Composer-Abhängigkeiten: im Docker-Image unter /var/www/vendor (außerhalb des Webroots), lokal im Projektordner
foreach ([dirname(__DIR__, 2) . '/vendor/autoload.php', dirname(__DIR__) . '/vendor/autoload.php'] as $autoload) {
    if (is_file($autoload)) { require_once $autoload; break; }
}

const CLAUDE_MODELL = 'claude-haiku-5-5';

function json_antwort(int $status, array $daten): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($daten, JSON_UNESCAPED_UNICODE);
    exit;
}

// Nur POST mit JSON-Body {"text": "..."}; liefert den bereinigten Text
function eingabe_text(int $min, int $max): string {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_antwort(405, ['fehler' => 'Nur POST erlaubt.']);
    $daten = json_decode((string)file_get_contents('php://input', length: 20000), true);
    $text = is_array($daten) && is_string($daten['text'] ?? null) ? $daten['text'] : '';
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if (mb_strlen($text) < $min) json_antwort(400, ['fehler' => 'Beschreib deinen Ablauf bitte in einem ganzen Satz.']);
    return mb_substr($text, 0, $max);
}

function claude_client(): Anthropic\Client {
    $schluessel = env('ANTHROPIC_API_KEY');
    if ($schluessel === '' || !class_exists(Anthropic\Client::class)) {
        error_log('MakeMyKI KI: ANTHROPIC_API_KEY fehlt oder Composer-Abhängigkeiten nicht installiert');
        json_antwort(503, ['fehler' => 'Die KI ist gerade nicht erreichbar. Versuch es später noch einmal.']);
    }
    return new Anthropic\Client(apiKey: $schluessel, requestOptions: ['timeout' => 60, 'maxRetries' => 1]);
}

// Hinter dem Coolify-Proxy steht die Besucher-IP als letzter Eintrag in X-Forwarded-For
function besucher_ip(): string {
    $kette = array_filter(array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));
    return $kette ? end($kette) : ($_SERVER['REMOTE_ADDR'] ?? '');
}

// Anfragelimit je Besucher und Stunde sowie insgesamt je Tag.
// Gespeichert wird nur ein täglich wechselnder Hash der IP, Einträge verfallen nach einer Stunde.
function limit_pruefen(string $art, int $proStunde, int $proTag): void {
    $ordner = __DIR__ . '/daten';
    if (!is_dir($ordner)) @mkdir($ordner, 0750, true);
    $fh = @fopen($ordner . '/ki-limit.json', 'c+');
    if (!$fh) return; // Limit darf die Funktion nie blockieren
    flock($fh, LOCK_EX);
    $stand = json_decode(stream_get_contents($fh) ?: '{}', true) ?: [];
    $jetzt = time();
    $tag = date('Y-m-d');
    $kennung = $art . ':' . substr(hash_hmac('sha256', besucher_ip(), env('BERICHT_SCHLUESSEL', 'makemyki') . $tag), 0, 24);
    foreach ($stand['ip'] ?? [] as $k => $zeiten) {
        $stand['ip'][$k] = array_values(array_filter($zeiten, fn($z) => $z > $jetzt - 3600));
        if (!$stand['ip'][$k]) unset($stand['ip'][$k]);
    }
    if (($stand['tag'] ?? '') !== $tag) { $stand['tag'] = $tag; $stand['gesamt'] = []; }
    $zuViel = count($stand['ip'][$kennung] ?? []) >= $proStunde || ($stand['gesamt'][$art] ?? 0) >= $proTag;
    if (!$zuViel) {
        $stand['ip'][$kennung][] = $jetzt;
        $stand['gesamt'][$art] = ($stand['gesamt'][$art] ?? 0) + 1;
    }
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($stand));
    flock($fh, LOCK_UN);
    fclose($fh);
    if ($zuViel) json_antwort(429, ['fehler' => 'Gerade sind es zu viele Anfragen. Versuch es in einer Stunde noch einmal.']);
}
