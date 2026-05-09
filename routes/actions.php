<?php

/*
 * Action routes have been folded into routes/cp.php under the
 * `webhook-manager.actions.*` sub-group so cp_route() resolves them
 * correctly. This file is intentionally left empty for backwards
 * compatibility — the addon service provider still references it as a
 * route registration target via the `actions` $routes key, but registering
 * an empty group is a no-op.
 *
 * To remove this file entirely, also drop the
 *   'actions' => __DIR__.'/../routes/actions.php',
 * entry from WebhookManagerServiceProvider::$routes.
 */
