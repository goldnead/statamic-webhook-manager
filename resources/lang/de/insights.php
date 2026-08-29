<?php

/*
 * Die Worte fuer die Zahlen, die dieses Addon an statamic-insights meldet.
 *
 * Eigene Datei statt eines Abschnitts in messages.php: das Analytics-Addon ist
 * optional, und wer messages.php liest, soll nicht heraussuchen muessen, welche
 * Haelfte davon nur mit einem Geschwister-Addon ueberhaupt greift.
 *
 * Vollstaendig gegen resources/lang/en/insights.php, sonst faellt
 * LangFilesAreCompleteTest.
 */

return [
    'group' => 'Webhooks',

    'deliveries' => 'Zustellungen',
    'deliveries_description' => 'Ereignisse, die in diesem Zeitraum hinausgehen mussten. Datiert auf das Ereignis, nicht auf den letzten Wiederholungsversuch.',

    'failures' => 'Fehlgeschlagene Zustellungen',
    'failures_description' => 'Zustellungen, die endgültig aufgegeben haben. Was noch auf einen Wiederholungsversuch wartet, zählt nicht mit, und was jemand abgebrochen hat, auch nicht.',

    'success_rate' => 'Erfolgsquote',
    'success_rate_description' => 'Von den Zustellungen mit einem Urteil der Anteil, der angekommen ist. Was noch auf einen Wiederholungsversuch wartet, hat kein Urteil und bleibt außen vor.',

    'latency_avg' => 'Antwortzeit (Durchschnitt)',
    'latency_avg_description' => 'Wie lange eine Zustellung im Schnitt gedauert hat. Werte unter einer Sekunde stehen hier als 0 s; die genauen Millisekunden und die Perzentile p50/p95/p99 zeigt der eigene Auswertungsschirm dieses Addons.',

    'breakdown_status' => 'Status',

    'no_status' => 'Ohne Status',

    'status' => [
        'pending' => 'Wartet',
        'processing' => 'Geht raus',
        'success' => 'Angekommen',
        'failed' => 'Fehlgeschlagen',
        'cancelled' => 'Abgebrochen',
    ],
];
