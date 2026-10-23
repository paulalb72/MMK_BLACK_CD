<?php
// Einstellungen für die Besucherstatistik und den Wochenbericht.
// Persönliche Werte (Empfänger, Schlüssel) stehen in der .env, nicht im Code.
require_once __DIR__ . '/../api/mail.php';

define('EMPFAENGER', env('REPORT_TO'));          // Empfänger des Wochenberichts
define('SCHLUESSEL', env('BERICHT_SCHLUESSEL')); // für Aufrufe per URL; leer = nur per Kommandozeile/Cronjob
const AUFBEWAHRUNG_TAGE = 90;                    // ältere Rohdaten werden beim Bericht gelöscht
const EIGENE_HOSTS = ['makemyki.de', 'www.makemyki.de', 'localhost'];
