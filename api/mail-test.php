<?php
// Testmail an den Anfrage-Empfänger schicken.
// Auf dem Server:  php api/mail-test.php
// Oder im Browser: /api/mail-test.php?schluessel=<SCHLUESSEL aus statistik/konfig.php>
require __DIR__ . '/mail.php';
require __DIR__ . '/../statistik/konfig.php';

if (PHP_SAPI !== 'cli' && (SCHLUESSEL === '' || !hash_equals(SCHLUESSEL, (string)($_GET['schluessel'] ?? '')))) { http_response_code(404); exit; }
if (PHP_SAPI !== 'cli') header('Content-Type: text/plain; charset=UTF-8');

$an = env('MAIL_TO', env('SMTP_USER'));
[$ok, $grund] = smtp_mail($an, 'Testmail von makemyki.de', "Der Mailversand der Website funktioniert.\n\nGesendet am " . date('d.m.Y H:i') . '.');
echo $ok ? "OK: Testmail an $an gesendet.\n" : "FEHLER: $grund\n";
