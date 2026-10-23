<?php
// Wochenbericht: wertet die letzten 7 Tage (bis gestern) aus, vergleicht mit der Woche davor und schickt eine E-Mail.
// Aufruf per Cronjob: php statistik/bericht.php
// oder per URL:       /statistik/bericht.php?schluessel=…            (sendet)
//                     /statistik/bericht.php?schluessel=…&vorschau=1 (zeigt nur an, sendet nicht)
require __DIR__ . '/lib.php';

$cli = PHP_SAPI === 'cli';
if (!$cli && (SCHLUESSEL === '' || !hash_equals(SCHLUESSEL, (string)($_GET['schluessel'] ?? '')))) { http_response_code(404); exit; }
$vorschau = !$cli && !empty($_GET['vorschau']);

$gestern  = new DateTimeImmutable('yesterday');
$start    = $gestern->modify('-6 days');
$vorEnde  = $start->modify('-1 day');
$vorStart = $vorEnde->modify('-6 days');

function lesen(DateTimeImmutable $von, DateTimeImmutable $bis): array {
    $alle = [];
    for ($tag = $von; $tag <= $bis; $tag = $tag->modify('+1 day')) {
        $datei = DATEN . '/' . $tag->format('Y-m-d') . '.jsonl';
        if (!is_file($datei)) continue;
        foreach (file($datei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $zeile) {
            $e = json_decode($zeile, true);
            if (is_array($e) && isset($e['z'], $e['t'], $e['b'])) $alle[] = $e;
        }
    }
    usort($alle, fn($a, $b) => strcmp($a['z'], $b['z']));
    return $alle;
}

function auswerten(array $eintraege): array {
    $r = ['besucher' => 0, 'aufrufe' => 0, 'tage' => [], 'seiten' => [], 'herkunft' => [], 'geraete' => [], 'aktionen' => []];
    $besuch = [];                       // Besucher-Tag => erste Herkunft
    $seitenBesucher = [];
    foreach ($eintraege as $e) {
        $tag = substr($e['z'], 0, 10);
        $schluessel = $tag . '|' . $e['b'];
        if ($e['t'] === 'pv') {
            $r['aufrufe']++;
            $r['tage'][$tag]['aufrufe'] = ($r['tage'][$tag]['aufrufe'] ?? 0) + 1;
            $r['seiten'][$e['p']] = ($r['seiten'][$e['p']] ?? 0) + 1;
            $seitenBesucher[$e['p']][$schluessel] = true;
            if (!isset($besuch[$schluessel])) {
                $besuch[$schluessel] = $e['r'] ?? 'Direkt / unbekannt';
                $r['tage'][$tag]['besucher'] = ($r['tage'][$tag]['besucher'] ?? 0) + 1;
                $r['geraete'][$e['g']] = ($r['geraete'][$e['g']] ?? 0) + 1;
            }
        } elseif (!empty($e['e'])) {
            $r['aktionen'][$e['e']] = ($r['aktionen'][$e['e']] ?? 0) + 1;
        }
    }
    $r['besucher'] = count($besuch);
    foreach ($besuch as $quelle) $r['herkunft'][$quelle] = ($r['herkunft'][$quelle] ?? 0) + 1;
    foreach ($r['seiten'] as $p => $n) $r['seiten'][$p] = ['aufrufe' => $n, 'besucher' => count($seitenBesucher[$p])];
    uasort($r['seiten'], fn($a, $b) => $b['aufrufe'] <=> $a['aufrufe']);
    arsort($r['herkunft']); arsort($r['geraete']); arsort($r['aktionen']);
    return $r;
}

$jetzt = auswerten(lesen($start, $gestern));
$vorher = auswerten(lesen($vorStart, $vorEnde));

// ---------- E-Mail bauen ----------
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$zahl = fn($n) => number_format((float)$n, 0, ',', '.');
function vergleich(int $jetzt, int $vorher): string {
    if ($vorher === 0) return $jetzt > 0 ? 'neu' : '±0';
    $p = round(($jetzt - $vorher) / $vorher * 100);
    return ($p > 0 ? '+' : ($p < 0 ? '−' : '±')) . abs($p) . ' % zur Vorwoche';
}
$AKTIONEN = [
    'anfrage_gesendet'    => 'Anfragen über das Formular',
    'anfrage_kicheck'     => '… davon mit kostenlosem KI-Check',
    'anfrage_roentgen'    => 'Anfragen aus dem KI-Röntgen',
    'erstgespraech_klick' => 'Klicks auf „Erstgespräch buchen“ (Calendly)',
    'ki_roentgen'         => 'KI-Röntgen-Analysen durchgeführt',
    'ki_roentgen_cta'     => 'Klicks auf „Anfrage“ im KI-Röntgen',
    'diktat_start'        => 'Spracheingabe genutzt',
    'anruf_klick'         => 'Klicks auf eine Telefonnummer',
    'mail_klick'          => 'Klicks auf die E-Mail-Adresse',
    'whatsapp_klick'      => 'Klicks auf WhatsApp',
];
$zeitraum = $start->format('d.m.') . '–' . $gestern->format('d.m.Y');
$td = 'padding:8px 0;border-bottom:1px solid #E4E4E7;font-size:14px;';
$tabelle = function (array $zeilen, string $kopfLinks, string $kopfRechts) use ($h, $td) {
    if (!$zeilen) return '<p style="color:#62626B;font-size:14px;margin:0 0 8px">Keine Daten in diesem Zeitraum.</p>';
    $html = '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:0 0 8px"><tr>'
          . '<th align="left" style="' . $td . 'color:#62626B;font-weight:600">' . $h($kopfLinks) . '</th>'
          . '<th align="right" style="' . $td . 'color:#62626B;font-weight:600">' . $h($kopfRechts) . '</th></tr>';
    foreach ($zeilen as [$links, $rechts]) $html .= '<tr><td style="' . $td . 'color:#0A0A0A">' . $h($links) . '</td><td align="right" style="' . $td . 'color:#0A0A0A;font-weight:600">' . $h($rechts) . '</td></tr>';
    return $html . '</table>';
};
$abschnitt = fn($titel) => '<h2 style="font-size:16px;margin:28px 0 8px;color:#0A0A0A">' . $titel . '</h2>';

$tage = [];
for ($tag = $start; $tag <= $gestern; $tag = $tag->modify('+1 day')) {
    $k = $tag->format('Y-m-d');
    $tage[] = [['Mo','Di','Mi','Do','Fr','Sa','So'][(int)$tag->format('N') - 1] . ', ' . $tag->format('d.m.'), $zahl($jetzt['tage'][$k]['besucher'] ?? 0) . ' Besucher · ' . $zahl($jetzt['tage'][$k]['aufrufe'] ?? 0) . ' Aufrufe'];
}
$seiten = [];
foreach (array_slice($jetzt['seiten'], 0, 10, true) as $p => $s) $seiten[] = [$p, $zahl($s['aufrufe']) . ' Aufrufe · ' . $zahl($s['besucher']) . ' Besucher'];
$quellen = [];
foreach (array_slice($jetzt['herkunft'], 0, 8, true) as $q => $n) $quellen[] = [$q, $zahl($n)];
$geraete = [];
foreach ($jetzt['geraete'] as $g => $n) $geraete[] = [$g, round($n / max(1, $jetzt['besucher']) * 100) . ' %'];
$aktionen = [];
foreach ($AKTIONEN as $k => $label) if (!empty($jetzt['aktionen'][$k]) || !empty($vorher['aktionen'][$k])) $aktionen[] = [$label, $zahl($jetzt['aktionen'][$k] ?? 0) . ' (Vorwoche ' . $zahl($vorher['aktionen'][$k] ?? 0) . ')'];
foreach ($jetzt['aktionen'] as $k => $n) if (!isset($AKTIONEN[$k])) $aktionen[] = [$k, $zahl($n)];

$kachel = fn($wert, $label, $hinweis) => '<td style="width:33%;padding:16px;border:1px solid #E4E4E7;border-radius:12px;vertical-align:top">'
    . '<div style="font-size:26px;font-weight:800;color:#0A0A0A;line-height:1.1">' . $wert . '</div>'
    . '<div style="font-size:13px;color:#3F3F46;margin-top:4px">' . $label . '</div>'
    . '<div style="font-size:12px;color:#62626B;margin-top:2px">' . $hinweis . '</div></td>';
$proBesucher = $jetzt['besucher'] ? number_format($jetzt['aufrufe'] / $jetzt['besucher'], 1, ',', '.') : '0';

$html = '<!doctype html><html lang="de"><body style="margin:0;padding:24px;background:#FAFAFA;font-family:Arial,Helvetica,sans-serif">'
  . '<div style="max-width:620px;margin:0 auto;background:#fff;border:1px solid #E4E4E7;border-radius:16px;padding:28px">'
  . '<p style="margin:0;font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#62626B;font-weight:700">MakeMyKI · Wochenbericht</p>'
  . '<h1 style="margin:6px 0 20px;font-size:22px;color:#0A0A0A">' . $h($zeitraum) . '</h1>'
  . '<table cellpadding="0" cellspacing="8" style="width:100%;margin:0 -8px"><tr>'
  . $kachel($zahl($jetzt['besucher']), 'Besucher', $h(vergleich($jetzt['besucher'], $vorher['besucher'])))
  . $kachel($zahl($jetzt['aufrufe']), 'Seitenaufrufe', $h(vergleich($jetzt['aufrufe'], $vorher['aufrufe'])))
  . $kachel($zahl($jetzt['aktionen']['anfrage_gesendet'] ?? 0) . ' / ' . $zahl($jetzt['aktionen']['erstgespraech_klick'] ?? 0), 'Anfragen / Erstgespräch-Klicks', 'Ø ' . $proBesucher . ' Seiten pro Besucher')
  . '</tr></table>'
  . $abschnitt('Was Besucher gemacht haben') . $tabelle($aktionen, 'Aktion', 'Anzahl')
  . $abschnitt('Meistbesuchte Seiten') . $tabelle($seiten, 'Seite', 'Aufrufe')
  . $abschnitt('Woher die Besucher kamen') . $tabelle($quellen, 'Quelle', 'Besucher')
  . $abschnitt('Geräte') . $tabelle($geraete, 'Gerät', 'Anteil')
  . $abschnitt('Verlauf') . $tabelle($tage, 'Tag', 'Besucher · Aufrufe')
  . '<p style="margin:24px 0 0;font-size:12px;color:#62626B;line-height:1.5">Besucher werden ohne Cookies gezählt: Wer an mehreren Tagen kommt, zählt pro Tag einmal. Eigene Besuche lassen sich über /statistik/ignorieren.php ausschließen.</p>'
  . '</div></body></html>';

// Alte Rohdaten löschen
foreach (glob(DATEN . '/*.jsonl') ?: [] as $datei) {
    if (filemtime($datei) < time() - AUFBEWAHRUNG_TAGE * 86400) @unlink($datei);
}

if ($vorschau) { header('Content-Type: text/html; charset=UTF-8'); echo $html; exit; }

require_once __DIR__ . '/../api/mail.php';
$betreff = 'MakeMyKI · Wochenbericht ' . $zeitraum . ' · ' . $zahl($jetzt['besucher']) . ' Besucher';
$an = env('REPORT_TO', EMPFAENGER);
[$ok, $grund] = smtp_mail($an, $betreff, $html, true);
if (!$cli) header('Content-Type: text/plain; charset=UTF-8');
echo $ok ? "Wochenbericht gesendet an $an\n" : "Versand fehlgeschlagen: $grund\n";
