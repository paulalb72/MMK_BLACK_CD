<?php
// Anfrage-Formulare (Kontakt-Wizard "makemyki" und KI-Röntgen "makemyki-roentgen")
// Erfolg -> 303 /danke/, Fehler -> 303 /fehler/, GET -> 303 /

// Versand per SMTP, Zugangsdaten in der .env (siehe api/mail.php)
require_once __DIR__ . '/mail.php';

function weiter(string $ziel): void {
    header('Location: ' . $ziel, true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') weiter('/');

// Honeypot: Bots füllen das versteckte Feld aus -> stillschweigend "Danke"
if (trim($_POST['website_url'] ?? '') !== '') weiter('/danke/');

function feld(string $k, int $max = 500): string {
    $v = $_POST[$k] ?? '';
    if (is_array($v)) $v = implode(', ', array_map('strval', $v));
    $v = trim(str_replace("\r", '', (string)$v));
    return mb_substr($v, 0, $max);
}

$formular = feld('formular', 40);
$name     = feld('name', 120);
$email    = feld('email', 160);

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || feld('datenschutz') !== 'ja') {
    weiter('/fehler/');
}

$felder = [
    'Formular'         => $formular,
    'Name'             => $name,
    'Firma'            => feld('firma', 160),
    'E-Mail'           => $email,
    'Telefon'          => feld('telefon', 60),
    'Anliegen'         => feld('anliegen'),
    'Bereiche'         => feld('bereich'),
    'Konkreter Ablauf' => feld('branche_text', 300),
    'Unternehmensgröße'=> feld('groesse', 60),
    'Tools'            => feld('tools'),
    'Nachricht'        => feld('nachricht', 3000),
    'KI-Röntgen'       => feld('ablauf', 2000),
];

$text = '';
foreach ($felder as $k => $v) {
    if ($v === '') continue;
    $text .= str_contains($v, "\n") ? "$k:\n$v\n\n" : "$k: $v\n";
}
$text .= "\n---\nGesendet am " . date('d.m.Y H:i') . ' von ' . ($_SERVER['REMOTE_ADDR'] ?? '?') . "\n";

$betreff = ($formular === 'makemyki-roentgen' ? 'KI-Röntgen-Anfrage' : 'Neue Anfrage') . ' von ' . $name;
[$ok, $grund] = smtp_mail(env('MAIL_TO', env('SMTP_USER')), $betreff, $text, false, $email, $name);

if (!$ok) {
    // Anfrage nicht verlieren: in gesperrtem Ordner ablegen und Fehler ins Server-Log schreiben
    error_log('MakeMyKI Anfrage nicht zugestellt: ' . $grund);
    $ordner = __DIR__ . '/daten';
    if (!is_dir($ordner)) @mkdir($ordner, 0750, true);
    @file_put_contents($ordner . '/nicht-zugestellt.txt', "=== " . date('c') . " · $grund\n$betreff\n$text\n", FILE_APPEND | LOCK_EX);
}

// Für den Wochenbericht mitzählen (ohne personenbezogene Daten)
if ($ok && is_file(__DIR__ . '/../statistik/lib.php')) {
    try {
        require_once __DIR__ . '/../statistik/lib.php';
        $seite = (string)parse_url($_SERVER['HTTP_REFERER'] ?? '/', PHP_URL_PATH);
        statistik_ereignis($formular === 'makemyki-roentgen' ? 'anfrage_roentgen' : 'anfrage_gesendet', $seite ?: '/');
        if (str_contains(feld('anliegen'), 'KI-Check')) statistik_ereignis('anfrage_kicheck', $seite ?: '/');
    } catch (Throwable $e) { /* Statistik darf die Anfrage nie blockieren */ }
}
weiter($ok ? '/danke/' : '/fehler/');
