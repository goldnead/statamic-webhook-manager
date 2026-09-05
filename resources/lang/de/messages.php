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

    // Steht dort, wo ein gespeichertes Handle kein registriertes Label hat —
    // ein Verfahren oder Handler, den ein Upgrade entfernt hat, oder ein
    // Datensatz aus einer neueren Version. Nie das Handle selbst.
    'unknown_option' => 'Unbekannt',

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

    /*
     * Oberflaeche des Control Panels: Ueberschriften, Spaltenkoepfe,
     * Feldbeschriftungen, Zeilenaktionen.
     *
     * Bewusst im eigenen Namensraum statt in einer `resources/lang/de.json`:
     * JSON-Schluessel gelten global ueber alle installierten Addons, und die
     * Kollision ist keine Theorie. Sechs Geschwister-Addons registrieren
     * `loadJsonTranslationsFrom`, `statamic-marketing` definiert darin
     * `"Delivery": "Versand"`. Bis zu dieser Datei hiess die
     * Auslieferungs-Detailseite dieses Addons auf Deutsch „Versand #266" —
     * ein Wort aus dem Marketing-Addon fuer eine Webhook-Zustellung. Ein
     * Schluessel mit Namensraum laesst sich von aussen nicht umdeuten.
     */
    'cp' => [
        // Übersicht
        'overview' => 'Übersicht',
        'stats_heading' => 'Kennzahlen',
        'stats_sub' => 'Aktueller Stand dieser Installation.',
        'stat_metric' => 'Kennzahl',
        'stat_value' => 'Wert',
        'stat_outbound_active' => 'Aktive ausgehende Webhooks',
        'stat_inbound_active' => 'Aktive eingehende Endpunkte',
        'stat_success_rate' => 'Erfolgsquote (24 h)',
        'stat_failures' => 'Fehlgeschlagene Zustellungen',
        'webhooks_heading' => 'Ausgehende Webhooks',
        'webhooks_sub' => 'Alle ausgehenden Webhooks dieser Marke, alphabetisch.',
        'webhooks_all' => 'Alle ausgehenden Webhooks',
        'webhooks_empty' => 'Noch kein ausgehender Webhook angelegt.',
        'failures_heading' => 'Letzte Fehler',
        'failures_sub' => 'Die acht letzten fehlgeschlagenen Zustellungen — ansehen oder erneut senden.',
        'failures_all' => 'Alle Zustellungen',
        'get_started' => 'Erste Schritte mit dem Webhook Manager',
        'get_started_sub' => 'Benachrichtigungen an externe Dienste senden, eingehende Anfragen annehmen und Regeln ausführen — alles aus Statamic heraus.',
        'create_outbound' => 'Ausgehenden Webhook anlegen',
        'create_inbound' => 'Eingehenden Endpunkt anlegen',
        'create_rule' => 'Regel anlegen',

        // Trigger and auth-scheme labels. They come out of the registries
        // (TriggerRegistry / AuthSchemeRegistry) and were the last English
        // strings on otherwise German screens: they render as badges in both
        // listings, on the delivery page and inside the editor's own tabs.
        'trigger_entry_saved' => 'Eintrag — gespeichert',
        'trigger_entry_published' => 'Eintrag — veröffentlicht',
        'trigger_entry_unpublished' => 'Eintrag — Veröffentlichung zurückgenommen',
        'trigger_entry_deleted' => 'Eintrag — gelöscht',
        'trigger_form_submitted' => 'Formular — abgeschickt',
        'trigger_user_saved' => 'Benutzer:in — gespeichert',
        'trigger_asset_saved' => 'Datei — gespeichert',
        'auth_none' => 'Keine Authentifizierung',
        'auth_static_header' => 'Fester Header mit Geheimnis',
        'auth_bearer' => 'Bearer-Token',
        'auth_basic' => 'Basic Auth',
        'auth_hmac' => 'HMAC-Signatur',
        'auth_ip_allowlist' => 'IP-Freigabeliste',

        // Gemeinsame Spaltenköpfe
        'col_name' => 'Name',
        'col_trigger' => 'Trigger',
        'col_method' => 'Methode',
        'col_url' => 'URL',
        'col_status' => 'Status',
        'col_when' => 'Wann',
        'col_error' => 'Fehler',

        // Zeilenaktionen
        'action_edit' => 'Bearbeiten',
        'action_test' => 'Testen',
        'action_open_delivery' => 'Zustellung öffnen',
        'action_not_available_here' => 'Diese Aktion gibt es auf diesem Bildschirm nicht.',
        'action_selection_gone' => 'Keiner der gewählten Datensätze existiert noch. Seite neu laden.',
        'action_wrong_type' => 'Diese Aktion passt nicht zu den gewählten Datensätzen.',
        'status_active' => 'Aktiv',
        'status_disabled' => 'Deaktiviert',

        // Mehrfachauswahl (echte Statamic-Aktionen, im „…"-Menü und in der
        // Aktionsleiste, sobald Zeilen angehakt sind).
        'bulk_enable' => 'Aktivieren',
        'bulk_enable_button' => '{1} Webhook aktivieren|[2,*] :count Webhooks aktivieren',
        'bulk_enabled' => '{1} Webhook aktiviert.|[2,*] :count Webhooks aktiviert.',
        'bulk_disable' => 'Deaktivieren',
        'bulk_disable_button' => '{1} Webhook deaktivieren|[2,*] :count Webhooks deaktivieren',
        'bulk_disabled' => '{1} Webhook deaktiviert.|[2,*] :count Webhooks deaktiviert.',
        'bulk_delete' => 'Löschen',
        'bulk_delete_button' => '{1} Webhook löschen|[2,*] :count Webhooks löschen',
        'bulk_delete_confirm' => '{1} Damit ist die Webhook-Konfiguration endgültig weg. Bisherige Zustellungen bleiben erhalten.|[2,*] Damit sind :count Webhook-Konfigurationen endgültig weg. Bisherige Zustellungen bleiben erhalten.',
        'bulk_deleted' => '{1} Webhook gelöscht.|[2,*] :count Webhooks gelöscht.',
        'bulk_replay' => 'Erneut senden',
        'bulk_replay_button' => '{1} Zustellung erneut senden|[2,*] :count Zustellungen erneut senden',
        'bulk_replayed' => '{1} Zustellung zum erneuten Senden eingereiht.|[2,*] :count Zustellungen zum erneuten Senden eingereiht.',

        // Zustellungs-Detailseite
        'delivery' => 'Zustellung',
        'delivery_status' => [
            'pending' => 'Wartet',
            'processing' => 'Läuft',
            'success' => 'Erfolgreich',
            'failed' => 'Fehlgeschlagen',
            'cancelled' => 'Abgebrochen',
        ],
        'attempts' => 'Versuche',
        'duration' => 'Dauer',
        'correlation_id' => 'Korrelations-ID',
        'request' => 'Anfrage',
        'response' => 'Antwort',
        'headers' => 'Header',
        'body' => 'Body',
        'status_code' => 'Statuscode',
        'timing_errors' => 'Zeiten und Fehler',
        'error_type' => 'Fehlerart',
        'error_message' => 'Meldung',
        'next_retry' => 'Nächster Versuch',
        'payload_snapshot' => 'Payload-Abzug',
        'payload_snapshot_sub' => 'Der ursprüngliche Ereignis-Payload, der diese Zustellung ausgelöst hat, festgehalten beim Absenden.',
        'reproduce' => 'Nachstellen',
        'reproduce_sub' => 'Die Anfrage als cURL-Befehl. Maskierte Header bleiben maskiert.',
        'copy' => 'Kopieren',
        'copied' => 'Kopiert',
        'replay' => 'Erneut senden',
        'replay_ok' => 'Erneut gesendet',
        'replay_failed' => 'Erneutes Senden fehlgeschlagen',

        // Editor für ausgehende Webhooks
        'tab_general' => 'Allgemein',
        'tab_trigger' => 'Trigger',
        'tab_request' => 'Anfrage',
        'tab_auth' => 'Authentifizierung',
        'tab_payload' => 'Payload',
        'tab_delivery' => 'Zustellung',
        'tab_has_errors' => 'Dieser Reiter enthält Fehler',
        'edit_title_new' => 'Ausgehenden Webhook anlegen',
        'edit_title_fallback' => 'Ausgehender Webhook',
        'field_name' => 'Name',
        'field_name_hint' => 'Lesbarer Name, der im Control Panel überall auftaucht.',
        'field_handle' => 'Handle',
        'field_handle_hint' => 'Interner Bezeichner. Nur Kleinbuchstaben, Binde- oder Unterstriche.',
        'field_description' => 'Beschreibung',
        'field_status' => 'Status',
        'field_status_on' => 'Aktiv',
        'field_status_off' => 'Deaktiviert',
        'field_trigger_type' => 'Trigger-Typ',
        'field_trigger_type_hint' => 'Internes Ereignis, das diesen Webhook auslöst.',
        'field_url' => 'URL',
        'field_url_hint' => 'Die Ziel-URL, die bei jeder Zustellung aufgerufen wird.',
        'field_method' => 'Methode',
        'field_timeout' => 'Zeitlimit (Sekunden)',
        'field_timeout_hint' => 'Harte Obergrenze je Anfrage, inklusive TLS und Weiterleitungen.',
        'field_follow_redirects' => 'Weiterleitungen folgen',
        'field_follow_redirects_text' => 'HTTP-Weiterleitungen folgen',
        'field_auth_type' => 'Typ',
        'field_auth_config' => 'Auth-Konfiguration (JSON)',
        'auth_secret_set_heading' => 'Geheimnis ist bereits hinterlegt',
        'auth_secret_set_text' => 'Feld leer lassen, damit das gespeicherte verschlüsselte Geheimnis erhalten bleibt.',
        'auth_hint_configured' => 'Ein Geheimnis ist hinterlegt. Leer lassen behält es. Neues JSON einfügen ersetzt es — der Wert wird verschlüsselt gespeichert.',
        'auth_hint_new' => 'Wird verschlüsselt gespeichert. Das Format hängt vom Auth-Typ ab — der Platzhalter zeigt ein Beispiel.',
        'field_payload_type' => 'Typ',
        'field_body_source' => 'Herkunft des Body',
        'field_body_source_hint' => 'Body hier schreiben oder eine wiederverwendbare Vorlage aus der Bibliothek wählen. Ist beides gesetzt, gewinnt die Bibliotheksvorlage.',
        'body_source_inline' => 'Eigener Body',
        'body_source_library' => 'Vorlage aus der Bibliothek',
        'field_library_template' => 'Vorlage aus der Bibliothek',
        'field_library_template_hint' => 'Eine wiederverwendbare Body-Vorlage wählen. Sie wird bei jeder Zustellung geladen und gerendert.',
        'field_library_template_empty' => 'Noch keine Body-Vorlagen vorhanden. Zuerst unter Webhook Manager → Vorlagen eine anlegen.',
        'field_template' => 'Vorlage',
        'field_template_hint' => 'Platzhalter wie {{ entry:title }} oder {{ system:timestamp_iso }} verwenden.',
        'template_pick' => '— Vorlage wählen —',
        'field_queue' => 'Warteschlange',
        'field_queue_hint' => 'Empfohlen. Synchron nur senden, wenn es auf jede Millisekunde ankommt.',
        'field_queue_text' => 'Asynchron über die Warteschlange senden',
        'field_log_body' => 'Body protokollieren',
        'field_log_body_hint' => 'Legt fest, wie viel von Anfrage- und Antwort-Body ins Zustellungsprotokoll geschrieben wird.',
        'payload_raw_json' => 'JSON-Vorlage',
        'payload_mapped' => 'Zugeordnetes Objekt',
        'payload_form' => 'Formularkodiert',
        'log_full' => 'Vollständig',
        'log_partial' => 'Teilweise',
        'log_none' => 'Nichts',
        'delete_webhook' => 'Webhook löschen',
        'delete_webhook_confirm' => 'Damit ist die Webhook-Konfiguration endgültig weg. Bisherige Zustellungen bleiben erhalten.',
        'delete' => 'Löschen',
        'test_webhook' => 'Webhook testen',
        'test_ok' => 'Testanfrage erfolgreich',
        'test_failed' => 'Testanfrage fehlgeschlagen',
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
