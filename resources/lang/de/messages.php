<?php

/*
 * German translations for the Webhook Manager control panel.
 *
 * Complete against resources/lang/en/messages.php as of this release. A key
 * that is missing here falls back to English automatically, which is why the
 * previous eight-key stub produced a CP that switched language mid-screen.
 * When you add an English key, add it here too — LangFilesAreCompleteTest
 * fails otherwise.
 */

return [
    'created' => 'Webhook erstellt.',
    'integration_created' => 'Integration :name erstellt — prüfen und speichern.',

    // Auth config validation (Outbound / Inbound edit screens)
    'auth_config_invalid_json' => 'Die Auth-Konfiguration muss ein gültiges JSON-Objekt sein.',
    'auth_config_required' => 'Dieser Auth-Typ braucht Zugangsdaten. Trag die Auth-Konfiguration als JSON ein, sonst ginge die Anfrage unauthentifiziert raus.',
    'auth_config_hmac_secret_required' => 'HMAC-Signierung braucht einen Schlüssel "secret" in der Auth-Konfiguration.',

    // Delivery detail / replay
    'test_sent' => 'Testanfrage gesendet.',
    'send_webhook' => 'Webhook senden',
    'send_webhook_button' => '{1} Webhook senden|[2,*] Webhook senden',
    'send_webhook_missing' => 'Der gewählte Webhook existiert nicht mehr.',
    'send_webhook_done' => ':name für :count Einträge ausgelöst.',

    // Storage driver (Settings → Storage)
    'storage_heading' => 'Speicher',
    'storage_sub' => 'Wo deine Webhook-Konfiguration liegt. Die Auslieferungshistorie bleibt immer in der Datenbank.',
    'storage_database' => 'Datenbank',
    'storage_flat' => 'Flat File (YAML)',
    'storage_active_driver' => 'Aktiver Treiber',
    'storage_source_control_panel' => 'im Control Panel gesetzt',
    'storage_source_config' => 'aus Config / Env',
    'storage_flat_path_label' => 'Ablageort der YAML-Dateien',
    'storage_records' => 'Gespeicherte Konfiguration',
    'storage_switch_to' => 'Auf :driver wechseln',
    'storage_switch_hint' => 'Kopiert die gesamte Webhook-Konfiguration nach :driver (ID für ID) und macht sie zum aktiven Speicher. Auslieferungen bleiben unberührt.',
    'storage_switched' => 'Speicher auf :driver umgestellt — :count Datensätze migriert.',
    'storage_already_active' => 'Der Speicher :driver ist bereits aktiv.',
    'storage_counts_line' => ':outbound ausgehend · :inbound eingehend · :rules Regeln · :templates Vorlagen',

    // Insights / observability screen
    'insights_title' => 'Auswertung',
    'insights_subtitle' => 'Auslieferungsvolumen, Erfolgsquote, Antwortzeiten und Fehler deiner ausgehenden Webhooks.',
    'insights_all_webhooks' => 'Alle Webhooks',
    'insights_range_days' => '{1} Letzter :count Tag|[2,*] Letzte :count Tage',
    'insights_total_deliveries' => 'Auslieferungen',
    'insights_success_rate' => 'Erfolgsquote',
    'insights_failed' => 'Fehlgeschlagen',
    'insights_p95_latency' => 'p95-Antwortzeit',
    'insights_volume_heading' => 'Auslieferungsvolumen',
    'insights_volume_sub' => 'Erfolgreiche und fehlgeschlagene Auslieferungen pro Tag.',
    'insights_success_heading' => 'Erfolgsquote im Verlauf',
    'insights_success_sub' => 'Täglicher Anteil erfolgreicher Auslieferungen.',
    'insights_latency_heading' => 'Antwortzeiten',
    'insights_latency_sub' => 'Perzentile der Antwortzeit über alle erfassten Versuche.',
    'insights_errors_heading' => 'Fehlerverteilung',
    'insights_errors_sub' => 'Fehlschläge nach klassifiziertem Fehlertyp.',
    'insights_top_failing_heading' => 'Endpunkte mit den meisten Fehlern',
    'insights_top_failing_sub' => 'Ausgehende Webhooks mit den meisten Fehlschlägen in diesem Zeitraum.',
    'insights_empty' => 'In diesem Zeitraum wurden noch keine Auslieferungen erfasst.',
    'insights_no_failures' => 'Keine Fehlschläge in diesem Zeitraum.',
    'insights_no_latency' => 'Noch keine Antwortzeiten erfasst.',
    'insights_view_deliveries' => 'Auslieferungen ansehen',

    'updated' => 'Webhook aktualisiert.',
    'deleted' => 'Webhook gelöscht.',
    'enabled' => 'Webhook aktiviert.',
    'disabled' => 'Webhook deaktiviert.',
    'tested' => 'Testanfrage ausgelöst.',
    'replayed' => 'Auslieferung erneut gesendet.',
    'pruned' => ':count Datensätze aufgeräumt.',

    'endpoint_created' => 'Endpunkt erstellt.',
    'endpoint_updated' => 'Endpunkt aktualisiert.',
    'endpoint_deleted' => 'Endpunkt gelöscht.',
    'endpoint_enabled' => 'Endpunkt aktiviert.',
    'endpoint_disabled' => 'Endpunkt deaktiviert.',

    'rule_created' => 'Regel erstellt.',
    'rule_updated' => 'Regel aktualisiert.',
    'rule_deleted' => 'Regel gelöscht.',
    'rule_enabled' => 'Regel aktiviert.',
    'rule_disabled' => 'Regel deaktiviert.',
    'rule_test_succeeded' => 'Regeltest erfolgreich.',
    'rule_test_failed' => 'Regeltest fehlgeschlagen.',

    'template_created' => 'Vorlage erstellt.',
    'template_updated' => 'Vorlage aktualisiert.',
    'template_deleted' => 'Vorlage gelöscht.',
    'template_deleted_with_detach' => 'Vorlage gelöscht. :count ausgehende Webhooks wurden gelöst und nutzen wieder ihren eigenen Payload.',

    // Empty-state copy used by the index pages of the redesigned CP.
    'outbound_empty_intro' => 'Ausgehende Webhooks schicken Benachrichtigungen von deiner Statamic-Seite an externe Dienste, sobald ein Trigger auslöst.',
    'outbound_create_description' => 'Richte einen ausgehenden Webhook mit Trigger, Ziel-URL, Payload-Vorlage und Authentifizierung ein.',

    'inbound_empty_intro' => 'Eingehende Endpunkte nehmen HTTP-Anfragen externer Dienste an und übersetzen sie in Statamic-Aktionen.',
    'inbound_create_description' => 'Definiere einen eingehenden Endpunkt mit Pfad, Auth-Verfahren und einem Mapping auf Einträge, Aktionen oder gespeicherte Payloads.',

    'rules_empty_intro' => 'Regeln wenden bedingte Logik auf Webhook-Auslieferungen an: Sie treffen ein Event mit Bedingungen und führen eine oder mehrere Aktionen aus.',
    'rules_create_description' => 'Baue eine Regel mit einem Trigger, optionalen Bedingungen und den Aktionen, die bei einem Treffer ausgeführt werden.',

    'templates_empty_intro' => 'Vorlagen sind wiederverwendbare Payload-Bodies und Benachrichtigungstexte, die von ausgehenden Webhooks und Regeln referenziert werden.',
    'templates_create_description' => 'Erstelle eine Vorlage mit Handle, Typ und gerendertem Body über Token-Variablen wie {{ entry:title }}.',

    // Persistent help copy shown above the populated listings and edit screens.
    'rules_help' => 'Regeln reagieren auf einen eingehenden Trigger (ein Statamic-Event oder einen eingehenden Webhook), prüfen optionale Bedingungen und führen dann Aktionen aus, etwa das Senden eines ausgehenden Webhooks. So verknüpfst du Events mit Webhook-Auslieferungen, ganz ohne Code.',
    'templates_help' => 'Vorlagen definieren den wiederverwendbaren JSON- oder Body-Payload, den ein ausgehender Webhook sendet. Verwende Template-Variablen, die beim Versand aus dem Trigger-Payload gefüllt werden. Weise eine Vorlage einem ausgehenden Webhook zu, damit mehrere Webhooks dieselbe Payload-Struktur teilen.',
    'rules_edit_hint' => 'Wähle einen Trigger, ergänze optionale Bedingungen und definiere die Aktionen, die bei einem Treffer laufen. So verbindest du ein Event mit einer oder mehreren Webhook-Auslieferungen.',
    'templates_edit_hint' => 'Definiere einen wiederverwendbaren Payload-Body mit Template-Variablen, die beim Versand aus dem Trigger-Payload gefüllt werden. Weise ihn einem ausgehenden Webhook zu, damit mehrere Webhooks dieselbe Struktur teilen.',

    // Das Objekt, um das es bei einer Zustellung ging (subject_type / subject_id).
    'subject' => 'Objekt',
    'subject_type_placeholder' => 'Objekttyp',
    'subject_id_placeholder' => 'Objekt-ID',
    'subject_apply' => 'Filtern',
    'subject_clear' => 'Filter aufheben',
    'subject_filter_active' => 'Zustellungen zu :type :id',
    'subject_deliveries_heading' => 'Webhook-Zustellungen zu diesem Objekt',
    'subject_deliveries_empty' => 'Zu diesem Objekt wurden keine Webhook-Zustellungen aufgezeichnet.',
    'subject_deliveries_all' => 'Alle Zustellungen',

    'subject_types' => [
        'payment' => 'Zahlung',
        'offer' => 'Angebot',
        'funnel' => 'Funnel',
        'contact' => 'Kontakt',
        'entry' => 'Eintrag',
        'user' => 'Benutzer',
        'asset' => 'Datei',
        'form_submission' => 'Formulareingang',
    ],

    'errors' => [
        'invalid_template' => 'Die Template-Syntax ist ungültig.',
        'invalid_url' => 'Die Ziel-URL ist ungültig.',
        'unsupported_method' => 'Die HTTP-Methode :method wird nicht unterstützt.',
        'inbound_endpoint_not_found' => 'Endpunkt nicht gefunden oder deaktiviert.',
        'inbound_unauthorized' => 'Nicht autorisiert.',
        'inbound_method_not_allowed' => 'Methode nicht erlaubt.',
        'inbound_payload_too_large' => 'Payload zu groß.',
        'inbound_bad_request' => 'Ungültige Anfrage.',
        'inbound_replay_blocked' => 'Doppelte Anfrage vom Replay-Schutz abgewiesen.',
        'inbound_mapping_failed' => 'Mapping fehlgeschlagen.',
        'rule_unknown_action' => 'Unbekannter Action-Handler: :handle',
        'rule_invalid_conditions' => 'Ungültiger Bedingungsbaum.',
    ],

    'failure_types' => [
        'network' => 'Netzwerkfehler',
        'timeout' => 'Zeitüberschreitung',
        'auth' => 'Authentifizierungsfehler',
        'client' => 'Client-Fehler (4xx)',
        'server' => 'Server-Fehler (5xx)',
        'payload' => 'Payload-Fehler',
        'configuration' => 'Konfigurationsfehler',
        'internal' => 'Interner Anwendungsfehler',
    ],
];
