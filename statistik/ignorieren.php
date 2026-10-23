<?php
// Schließt die eigenen Besuche aus der Statistik aus (Cookie für ein Jahr). Mit ?wieder=1 wird wieder gezählt.
$wieder = !empty($_GET['wieder']);
setcookie('mmk_nicht_zaehlen', $wieder ? '' : '1', [
    'expires' => $wieder ? time() - 3600 : time() + 365 * 86400,
    'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax',
]);
header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex');
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Statistik | MakeMyKI</title></head>
<body style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:80px auto;padding:0 24px;color:#0A0A0A;line-height:1.6">
<?php if ($wieder): ?>
<h1 style="font-size:22px">Deine Besuche werden wieder gezählt.</h1>
<p><a href="?">Wieder ausschließen</a> · <a href="/">Zur Startseite</a></p>
<?php else: ?>
<h1 style="font-size:22px">Deine Besuche werden nicht mehr gezählt.</h1>
<p>Gilt für diesen Browser für ein Jahr. Auf jedem Gerät und in jedem Browser, den du nutzt, einmal aufrufen.</p>
<p><a href="?wieder=1">Wieder mitzählen</a> · <a href="/">Zur Startseite</a></p>
<?php endif; ?>
</body></html>
