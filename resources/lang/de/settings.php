<?php

/*
 * Deutsche Übersetzungen des Einstellungs-Formulars.
 *
 * Schlüsselgleich mit resources/lang/en/settings.php — LangFilesAreCompleteTest
 * schlägt sonst fehl. Die Feldschlüssel sind der Config-Pfad mit Unterstrichen
 * statt Punkten (`retry.max_attempts` → `retry_max_attempts`), weil ein Punkt
 * im Übersetzungsschlüssel als Pfadtrenner gelesen würde.
 */

return [

    'title' => 'Webhook-Manager-Einstellungen',
    'intro' => 'Diese Einstellungen gelten für die ganze Installation, nicht für eine Marke. Alles Unangetastete folgt :path — ein geänderter Wert wird als Abweichung gespeichert, ein zurückgesetzter Wert löscht sie wieder.',
    'save' => 'Speichern',
    'saving' => 'Speichere…',
    'saved' => 'Einstellungen gespeichert.',
    'save_failed' => 'Die Einstellungen konnten nicht gespeichert werden.',
    'default_placeholder' => 'Standard',

    'groups' => [
        'features' => [
            'title' => 'Module',
            'description' => 'Welche Teile des Addons aktiv sind. Ein abgeschaltetes Modul verliert seine Control-Panel-Seiten, seinen Navigationseintrag und seine Laufzeit-Verdrahtung — die bereits angelegten Daten bleiben liegen.',
        ],
        'retry' => [
            'title' => 'Wiederholungen',
            'description' => 'Was nach einer fehlgeschlagenen Zustellung passiert. Gilt für Zustellungen, die nach der Änderung starten; bereits geplante Versuche behalten ihren Plan.',
        ],
        'http' => [
            'title' => 'HTTP-Vorgaben',
            'description' => 'Wie ausgehende Anfragen gestellt werden. Ein einzelner Webhook kann Timeout und Redirect-Verhalten für sich überschreiben.',
        ],
        'inbound' => [
            'title' => 'Eingehende Grenzen',
            'description' => 'Was eine eingehende Anfrage kosten darf, bevor sie abgelehnt wird. Das öffentliche URL-Präfix steht nicht hier: es wird beim Registrieren der Routen gelesen und vom Route-Cache eingefroren.',
        ],
        'security' => [
            'title' => 'Signaturen',
            'description' => 'Woran signierte Anfragen auf beiden Seiten erkannt werden. Ein geänderter Header-Name muss jedem Sender mitgeteilt werden, der auf den alten konfiguriert ist.',
        ],
        'logging' => [
            'title' => 'Zustellungs-Protokoll',
            'description' => 'Wie viel von Anfrage und Antwort mitgeschrieben wird, und was vorher unkenntlich gemacht wird.',
        ],
        'pruning' => [
            'title' => 'Aufbewahrung',
            'description' => 'Wie lange die Historie bleibt. Das Aufräumen läuft über den Befehl webhook-manager:prune, ein Wert hier wirkt also nur dort, wo dieser Befehl eingeplant ist.',
        ],
        'debug' => [
            'title' => 'Debug',
            'description' => 'Zusätzliche Details im Control Panel.',
        ],
    ],

    'fields' => [

        'features_outbound' => [
            'label' => 'Ausgehende Webhooks',
            'description' => 'Die Maschinerie, die Anfragen rausschickt, wenn in Statamic etwas passiert.',
        ],
        'features_inbound' => [
            'label' => 'Eingehende Endpunkte',
            'description' => 'Der öffentliche Empfänger. Aus heißt: konfigurierte Endpunkte antworten nicht mehr, Sender bekommen einen 404.',
        ],
        'features_rules' => [
            'label' => 'Regel-Engine',
            'description' => 'Bedingtes Routing und Aktionen oberhalb der Trigger.',
        ],
        'features_templates' => [
            'label' => 'Payload-Vorlagen',
            'description' => 'Die gemeinsame Vorlagen-Bibliothek. Webhooks, die bereits eine Vorlage nutzen, nutzen sie weiter.',
        ],
        'features_debug_tools' => [
            'label' => 'Debug-Werkzeuge',
            'description' => 'Die Debug-Seite mit Trigger-Simulation und Payload-Ansicht.',
        ],

        'retry_strategy' => [
            'label' => 'Strategie',
            'description' => 'Wie die Wartezeit zwischen den Versuchen wächst.',
        ],
        'retry_max_attempts' => [
            'label' => 'Maximale Versuche',
            'description' => 'Versuche insgesamt, den ersten mitgezählt. Ist die Zahl erreicht, gilt die Zustellung als endgültig gescheitert und die Fehlermeldung geht raus.',
        ],
        'retry_base_delay_seconds' => [
            'label' => 'Grundverzögerung (Sekunden)',
            'description' => 'Die Wartezeit vor dem zweiten Versuch, und der Wert, aus dem die Strategie weiterrechnet.',
        ],
        'retry_max_delay_seconds' => [
            'label' => 'Maximale Verzögerung (Sekunden)',
            'description' => 'Obergrenze für die exponentielle Strategie, damit ein lange kaputter Endpunkt nicht erst am nächsten Tag wieder drankommt.',
        ],
        'retry_retry_on_status' => [
            'label' => 'Wiederholen bei Statuscodes',
            'description' => 'Ein HTTP-Statuscode pro Zeile. Alles, was nicht hier steht, gilt als endgültige Antwort und wird nicht wiederholt.',
        ],
        'retry_retry_on_network_errors' => [
            'label' => 'Wiederholen bei Netzwerkfehlern',
            'description' => 'Ein Timeout oder eine abgelehnte Verbindung wird wie ein gelisteter Statuscode wiederholt.',
        ],

        'http_timeout_seconds' => [
            'label' => 'Timeout (Sekunden)',
            'description' => 'Harte Obergrenze pro Anfrage, TLS und Redirects eingerechnet.',
        ],
        'http_connect_timeout_seconds' => [
            'label' => 'Verbindungs-Timeout (Sekunden)',
            'description' => 'Wie lange allein der Verbindungsaufbau dauern darf. Zählt in das Gesamt-Timeout hinein.',
        ],
        'http_follow_redirects' => [
            'label' => 'Weiterleitungen folgen',
            'description' => 'Aus heißt: ein 3xx ist die Antwort, und ob das als Erfolg zählt, entscheidet der Erfolgs-Auswerter.',
        ],
        'http_max_redirects' => [
            'label' => 'Maximale Weiterleitungen',
            'description' => 'Wie vielen Sprüngen gefolgt wird, bevor die Zustellung aufgegeben wird.',
        ],
        'http_user_agent' => [
            'label' => 'User-Agent',
            'description' => 'Geht mit jeder ausgehenden Anfrage raus. Manche Empfänger filtern danach.',
        ],
        'http_verify_ssl' => [
            'label' => 'TLS-Zertifikate prüfen',
            'description' => 'Aus schickt Payloads an einen Server, dessen Identität nicht geprüft wird. Nur für einen Staging-Host mit selbst signiertem Zertifikat, und nur mit Absicht.',
        ],

        'inbound_max_payload_kb' => [
            'label' => 'Maximale Payload (KB)',
            'description' => 'Größere Anfragen werden mit 413 abgelehnt, bevor irgendetwas den Body liest.',
        ],
        'inbound_rate_limit_per_minute' => [
            'label' => 'Ratenlimit (pro Minute)',
            'description' => 'Anfragen pro Minute und Endpunkt, geprüft vor der Authentifizierung. 0 schaltet die Drosselung ganz ab.',
        ],
        'inbound_replay_protection_ttl_seconds' => [
            'label' => 'Replay-Schutz-Fenster (Sekunden)',
            'description' => 'Wie lange eine Signatur oder Request-ID gemerkt wird, damit dieselbe Anfrage nicht zweimal durchgeht. Länger kostet Cache, kürzer lässt eine alte Anfrage wieder durch.',
        ],

        'security_default_hash_algorithm' => [
            'label' => 'Standard-Hash-Algorithmus',
            'description' => 'Vorausgewählt, wenn ein neuer Webhook oder Endpunkt signiert wird. Bestehende behalten den Algorithmus, mit dem sie gespeichert wurden.',
        ],
        'security_signature_header' => [
            'label' => 'Signatur-Header',
            'description' => 'Der Header, der die HMAC-Signatur trägt, ausgehend wie eingehend.',
        ],
        'security_timestamp_header' => [
            'label' => 'Zeitstempel-Header',
            'description' => 'Der Header, der den Zeitstempel der Anfrage trägt und gegen Replays genutzt wird.',
        ],
        'security_timestamp_tolerance_seconds' => [
            'label' => 'Zeitstempel-Toleranz (Sekunden)',
            'description' => 'Wie weit die Uhr des Senders abweichen darf, bevor eine signierte Anfrage abgelehnt wird.',
        ],
        'security_mask_secrets_in_ui' => [
            'label' => 'Secrets im Control Panel maskieren',
            'description' => 'Zeigt gespeicherte Zugangsdaten als *** statt im Klartext. Es versteckt sie auf dem Bildschirm, es ändert nichts an dem, was gespeichert ist.',
        ],

        'logging_mode' => [
            'label' => 'Body-Protokollierung',
            'description' => 'Wie viel vom Body der Anfrage und der Antwort zu jeder Zustellung gespeichert wird.',
        ],
        'logging_partial_bytes' => [
            'label' => 'Teilgröße (Bytes)',
            'description' => 'Im Teil-Modus: wie viele Bytes jedes Bodys aufbewahrt werden.',
        ],
        'logging_mask_headers' => [
            'label' => 'Maskierte Header',
            'description' => 'Ein Header-Name pro Zeile. Ihre Werte werden durch *** ersetzt, bevor die Zustellung mitgeschrieben wird.',
        ],
        'logging_mask_payload_keys' => [
            'label' => 'Maskierte Payload-Schlüssel',
            'description' => 'Ein Schlüsselname pro Zeile. Eine leere Liste heißt: Payloads werden genau so gespeichert, wie sie gesendet wurden.',
        ],

        'pruning_deliveries_after_days' => [
            'label' => 'Zustellungen aufbewahren (Tage)',
            'description' => 'Ältere Zustellungs-Datensätze werden entfernt. 0 behält sie dauerhaft.',
        ],
        'pruning_logs_after_days' => [
            'label' => 'Logs aufbewahren (Tage)',
            'description' => 'Ältere Log-Einträge werden entfernt. 0 behält sie dauerhaft.',
        ],

        'debug_expose_full_response_in_dev' => [
            'label' => 'Vollständige Antworten in der Entwicklung zeigen',
            'description' => 'Zeigt den kompletten Antwort-Body im Control Panel, auch wenn nur ein Teil gespeichert wurde.',
        ],

    ],

    'options' => [
        'retry_strategy' => [
            'none' => 'Keine Wiederholung',
            'linear' => 'Linear',
            'exponential' => 'Exponentiell',
        ],
        'logging_mode' => [
            'full' => 'Ganzer Body',
            'partial' => 'Erste N Bytes',
            'none' => 'Kein Body',
        ],
    ],

    'environment' => [
        'heading' => 'Von der Umgebung gesetzt',
        'description' => 'Diese Werte kommen aus Umgebungsvariablen oder aus der Routen-Registrierung. Sie stehen hier zum Nachsehen und werden dort geändert, wo sie gesetzt sind: ein Datenbankwert, der eine Umgebungsvariable übersteuert, wäre beim nächsten Deploy wieder da, und der Chat-Webhook ist ein Zugangsschlüssel.',
        'queue_connection' => 'Queue-Verbindung',
        'queue_name' => 'Queue-Name',
        'circuit_breaker' => 'Sicherung',
        'alerts' => 'Fehlermeldungen',
        'alert_recipients' => 'Empfänger der Meldungen',
        'alert_chat_webhook' => 'Chat-Webhook für Meldungen',
        'inbound_prefix' => 'URL-Präfix für Eingehendes',
        'threshold' => 'nach :count Fehlschlägen in Folge',
        'throttle' => 'höchstens alle :count Min.',
        'app_default' => '(Standard der Anwendung)',
        'configured' => 'Eingerichtet',
        'not_set' => 'Nicht gesetzt',
        'none' => 'Keine',
        'on' => 'An',
        'off' => 'Aus',
    ],

];
