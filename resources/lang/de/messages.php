<?php

/*
 * German translations for the Webhook Manager control panel.
 *
 * Only keys that benefit from a localized version are listed here. Any key
 * that is missing falls back to resources/lang/en/messages.php automatically.
 */

return [
    // Empty-state copy on the index pages.
    'rules_empty_intro' => 'Regeln wenden bedingte Logik auf Webhook-Auslieferungen an: Sie treffen ein Event mit Bedingungen und führen eine oder mehrere Aktionen aus.',
    'rules_create_description' => 'Baue eine Regel mit einem Trigger, optionalen Bedingungen und den Aktionen, die bei einem Treffer ausgeführt werden.',

    'templates_empty_intro' => 'Templates sind wiederverwendbare Payload-Bodies und Benachrichtigungstexte, die von ausgehenden Webhooks und Regeln referenziert werden.',
    'templates_create_description' => 'Erstelle ein Template mit Handle, Typ und gerendertem Body über Token-Variablen wie {{ entry:title }}.',

    // Persistent help copy shown above the populated listings and edit screens.
    'rules_help' => 'Regeln reagieren auf einen eingehenden Trigger (ein Statamic-Event oder einen eingehenden Webhook), prüfen optionale Bedingungen und führen dann Aktionen aus, etwa das Senden eines ausgehenden Webhooks. So verknüpfst du Events mit Webhook-Auslieferungen, ganz ohne Code.',
    'templates_help' => 'Templates definieren den wiederverwendbaren JSON- oder Body-Payload, den ein ausgehender Webhook sendet. Verwende Template-Variablen, die beim Versand aus dem Trigger-Payload gefüllt werden. Weise ein Template einem ausgehenden Webhook zu, damit mehrere Webhooks dieselbe Payload-Struktur teilen.',
    'rules_edit_hint' => 'Wähle einen Trigger, ergänze optionale Bedingungen und definiere die Aktionen, die bei einem Treffer laufen. So verbindest du ein Event mit einer oder mehreren Webhook-Auslieferungen.',
    'templates_edit_hint' => 'Definiere einen wiederverwendbaren Payload-Body mit Template-Variablen, die beim Versand aus dem Trigger-Payload gefüllt werden. Weise ihn einem ausgehenden Webhook zu, damit mehrere Webhooks dieselbe Struktur teilen.',
];
