<?php
// Liefert Bilder aus /bild/img/ aus: /bild/?n=logo -> img/logo.webp
$n = isset($_GET['n']) ? (string)$_GET['n'] : '';
if (!preg_match('/^[a-z0-9-]{1,64}$/', $n) || !is_file($f = __DIR__ . '/img/' . $n . '.webp')) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/webp');
header('Cache-Control: public, max-age=31536000, immutable');
header('Content-Length: ' . filesize($f));
readfile($f);
