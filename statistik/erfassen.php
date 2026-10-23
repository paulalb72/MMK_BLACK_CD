<?php
// Nimmt Seitenaufrufe und Klicks aus dem Browser entgegen (navigator.sendBeacon). Antwortet immer mit 204.
require __DIR__ . '/lib.php';

http_response_code(204);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || statistik_aus()) exit;

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (ist_bot($ua)) exit;

$d = json_decode((string)file_get_contents('php://input', false, null, 0, 2048), true);
if (!is_array($d)) exit;

$pfad = (string)($d['p'] ?? '/');
if (!preg_match('#^/[A-Za-z0-9/_\-.]{0,150}$#', $pfad)) $pfad = '/';

$eintrag = ['z' => date('c'), 't' => ($d['t'] ?? '') === 'ev' ? 'ev' : 'pv', 'p' => $pfad, 'g' => geraet($ua), 'b' => besucher_hash($ua)];
if ($eintrag['t'] === 'ev') {
    $ev = (string)($d['e'] ?? '');
    if (!preg_match('/^[a-z0-9_]{1,40}$/', $ev)) exit;
    $eintrag['e'] = $ev;
} else {
    $ref = herkunft((string)($d['r'] ?? ''));
    if ($ref !== '') $eintrag['r'] = $ref;
}
speichern($eintrag);
