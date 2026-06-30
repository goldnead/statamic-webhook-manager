#!/usr/bin/env bash
#
# End-to-end smoke test for goldnead/statamic-webhook-manager.
#
# Spins up a fresh Statamic v6 project in /tmp, wires the Webhook Manager
# source tree as a Composer path repository, then exercises the real outbound
# delivery pipeline against a local PHP webhook receiver:
#
#   1. install        — addon auto-discovered, config published, tables migrated
#   2. receiver        — a throwaway PHP built-in server records POST bodies
#   3. outbound        — create an OutboundWebhook pointed at the receiver,
#                        snapshot + send a delivery through DeliveryEngine
#   4. assertions      — Delivery is STATUS_SUCCESS / HTTP 200, the rendered
#                        template body reached the receiver verbatim
#
# Usage:
#   scripts/smoke-test.sh                      # run with defaults
#   WM_PATH=/path/to/webhook-manager scripts/smoke-test.sh
#
# Requirements:
#   - PHP >=8.2 with sqlite, dom, mbstring, fileinfo, gd
#   - Composer 2.x
#   - Network access (Composer downloads Statamic + deps; ~150 MB)
#
# Exit code is 0 on success, non-zero on the first failed step.

set -euo pipefail
IFS=$'\n\t'

# ============================================================
# Configuration
# ============================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WM_PATH="${WM_PATH:-$(cd "$SCRIPT_DIR/.." && pwd)}"
TIMESTAMP="$(date +%s)"
TEST_DIR="${TEST_DIR:-/tmp/webhook-manager-smoketest-$TIMESTAMP}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
STATAMIC_VERSION="${STATAMIC_VERSION:-^6.0}"
RECEIVER_PORT="${RECEIVER_PORT:-8123}"

export COMPOSER_ALLOW_SUPERUSER=1

# Colors (skip if not a tty)
if [ -t 1 ]; then
    GREEN='\033[0;32m'; RED='\033[0;31m'; BLUE='\033[0;34m'; YELLOW='\033[0;33m'; BOLD='\033[1m'; NC='\033[0m'
else
    GREEN='' RED='' BLUE='' YELLOW='' BOLD='' NC=''
fi

step()    { echo; echo -e "${BLUE}${BOLD}▸${NC} ${BOLD}$*${NC}"; }
ok()      { echo -e "  ${GREEN}✓${NC} $*"; }
fail()    { echo -e "  ${RED}✗${NC} $*" >&2; }
warn()    { echo -e "  ${YELLOW}⚠${NC} $*"; }

RECEIVER_PID=""
cleanup_on_error() {
    local exit_code=$?
    [ -n "$RECEIVER_PID" ] && kill "$RECEIVER_PID" >/dev/null 2>&1 || true
    if [ $exit_code -ne 0 ]; then
        echo
        fail "Smoke test failed. Test directory preserved for inspection:"
        echo "    $TEST_DIR"
    fi
}
trap cleanup_on_error EXIT

# ============================================================
# Pre-flight
# ============================================================
step "Pre-flight checks"

command -v "$PHP_BIN" >/dev/null 2>&1 || { fail "$PHP_BIN not found"; exit 1; }
command -v "$COMPOSER_BIN" >/dev/null 2>&1 || { fail "$COMPOSER_BIN not found"; exit 1; }

PHP_VERSION="$($PHP_BIN -r 'echo PHP_VERSION;')"
PHP_MAJOR_MINOR="$($PHP_BIN -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
case "$PHP_MAJOR_MINOR" in
    8.2|8.3|8.4) ok "PHP $PHP_VERSION" ;;
    *) fail "PHP $PHP_VERSION found — Webhook Manager requires 8.2+"; exit 1 ;;
esac

"$PHP_BIN" -m | grep -q -i sqlite || { fail "PHP sqlite extension required"; exit 1; }
ok "Composer $($COMPOSER_BIN --version | head -1 | awk '{print $3}')"

if [ ! -f "$WM_PATH/composer.json" ] || ! grep -q "goldnead/statamic-webhook-manager" "$WM_PATH/composer.json"; then
    fail "Webhook Manager source not found at $WM_PATH"
    fail "Set WM_PATH=/absolute/path/to/statamic-webhook-manager or run from inside the repo."
    exit 1
fi
ok "Webhook Manager source: $WM_PATH"

# ============================================================
# 1. Fresh Statamic install
# ============================================================
step "Creating Statamic v6 project at $TEST_DIR"

mkdir -p "$TEST_DIR"
cd "$TEST_DIR"

"$COMPOSER_BIN" create-project --prefer-dist --no-interaction --no-scripts \
    statamic/statamic . "$STATAMIC_VERSION" 2>&1 | tail -5
"$COMPOSER_BIN" install --no-interaction --prefer-dist 2>&1 | tail -3
ok "Statamic v6 installed"

# ============================================================
# 2. Configure SQLite & base migrations
# ============================================================
step "Configuring SQLite + APP_KEY"

[ -f .env ] || { cp .env.example .env 2>/dev/null || touch .env; }
"$PHP_BIN" -r '
$path = ".env";
$env = file_exists($path) ? file_get_contents($path) : "";
$lines = preg_split("/\r?\n/", $env);
$lines = array_filter($lines, fn ($line) => ! preg_match(
    "/^(DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)=/",
    $line
));
$lines[] = "DB_CONNECTION=sqlite";
$lines[] = "DB_DATABASE=" . __DIR__ . "/database/database.sqlite";
file_put_contents($path, implode("\n", $lines) . "\n");
'
mkdir -p database
touch database/database.sqlite

"$PHP_BIN" artisan key:generate --force --no-interaction >/dev/null
"$PHP_BIN" artisan migrate --force --no-interaction 2>&1 | tail -3
ok "Laravel base tables migrated"

# ============================================================
# 3. Add Webhook Manager via path repository
# ============================================================
step "Wiring Webhook Manager source as a path repository"

"$COMPOSER_BIN" config repositories.webhook-manager path "$WM_PATH" --no-interaction
"$COMPOSER_BIN" config minimum-stability dev --no-interaction
"$COMPOSER_BIN" config prefer-stable true --no-interaction
"$COMPOSER_BIN" config --no-interaction allow-plugins.pixelfear/composer-dist-plugin true
"$COMPOSER_BIN" config --no-interaction allow-plugins.composer/installers true
"$COMPOSER_BIN" config --no-interaction allow-plugins.php-http/discovery true

"$COMPOSER_BIN" require "goldnead/statamic-webhook-manager:@dev" -W \
    --no-interaction --prefer-dist 2>&1 | tail -5
ok "Webhook Manager installed via path repository"

"$PHP_BIN" artisan vendor:publish --tag=webhook-manager-config --force --no-interaction >/dev/null
ok "webhook-manager.php config published"

"$PHP_BIN" artisan migrate --force --no-interaction 2>&1 | tail -3
ok "Webhook Manager tables migrated"

# ============================================================
# 4. Start a local webhook receiver
# ============================================================
step "Starting local webhook receiver on 127.0.0.1:$RECEIVER_PORT"

RECEIVER_LOG="$TEST_DIR/received.json"
RECEIVER_DOCROOT="$TEST_DIR/receiver"
mkdir -p "$RECEIVER_DOCROOT"
cat > "$RECEIVER_DOCROOT/index.php" <<PHP
<?php
// Throwaway webhook receiver: append each POST body to a log file and 200.
file_put_contents('$RECEIVER_LOG', file_get_contents('php://input') . "\n", FILE_APPEND);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
PHP

"$PHP_BIN" -S "127.0.0.1:$RECEIVER_PORT" -t "$RECEIVER_DOCROOT" >/dev/null 2>&1 &
RECEIVER_PID=$!
sleep 1
if ! kill -0 "$RECEIVER_PID" >/dev/null 2>&1; then
    fail "Receiver failed to start on port $RECEIVER_PORT"
    exit 1
fi
ok "Receiver up (pid $RECEIVER_PID), logging to received.json"

# ============================================================
# 5. Create an outbound webhook + send a delivery
# ============================================================
step "Outbound webhook: render template, deliver to receiver, record Delivery"

cat > smoke-outbound.php <<'PHP'
<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Domain\Delivery\Actions\CreateDeliverySnapshotAction;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Services\DeliveryEngine;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;

$port = getenv('RECEIVER_PORT') ?: '8123';

$hook = OutboundWebhook::query()->updateOrCreate(
    ['handle' => 'smoke-on-publish'],
    [
        'name' => 'Smoke on publish',
        'enabled' => true,
        'trigger_type' => 'entry.published',
        'url' => "http://127.0.0.1:{$port}/",
        'method' => 'POST',
        'auth_type' => 'none',
        'payload_type' => 'raw_json',
        'payload_template' => '{"id":"{{ entry:id }}","title":"{{ entry:title }}"}',
        'queue_enabled' => false,
    ]
);

$event = new TriggerEvent(
    triggerHandle: 'entry.published',
    sourceType: 'entry',
    sourceReference: '42',
    payload: ['id' => '42', 'title' => 'Hello from the smoke test', 'site' => 'default'],
    site: 'default',
);
$context = new ExecutionContext($event);

$snapshot = app(CreateDeliverySnapshotAction::class);
$delivery = ($snapshot)($hook, $context);

$engine = app(DeliveryEngine::class);
$delivery = $engine->send($delivery);

if ($delivery->status !== Delivery::STATUS_SUCCESS) {
    fwrite(STDERR, "Delivery status is {$delivery->status}, expected success\n");
    fwrite(STDERR, "error: ".(string) $delivery->error_message."\n");
    exit(3);
}

printf("delivery_id=%s\n", $delivery->id);
printf("status=%s\n", $delivery->status);
printf("http=%d\n", (int) $delivery->response_status);
printf("attempts=%d\n", (int) $delivery->attempts);
printf("deliveries=%d\n", Delivery::count());
exit(0);
PHP

RECEIVER_PORT="$RECEIVER_PORT" "$PHP_BIN" smoke-outbound.php | while IFS='=' read -r k v; do
    case "$k" in
        delivery_id) ok "Delivery id: $v" ;;
        status)      ok "Delivery status: $v" ;;
        http)        ok "Response HTTP status: $v" ;;
        attempts)    ok "Attempts: $v" ;;
        deliveries)  ok "Delivery records in DB: $v" ;;
    esac
done

# ============================================================
# 6. Assert the receiver actually got the rendered payload
# ============================================================
step "Verifying the receiver got the rendered template body"

if [ ! -s "$RECEIVER_LOG" ]; then
    fail "Receiver log is empty — webhook body never arrived"
    exit 4
fi
if ! grep -q '"id":"42"' "$RECEIVER_LOG"; then
    fail "Receiver did not get the expected rendered body:"
    cat "$RECEIVER_LOG" | sed 's/^/    /'
    exit 5
fi
ok "Receiver got: $(tail -1 "$RECEIVER_LOG")"

# ============================================================
# 7. Summary
# ============================================================
kill "$RECEIVER_PID" >/dev/null 2>&1 || true
RECEIVER_PID=""

echo
echo -e "${GREEN}${BOLD}════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}${BOLD} Smoke test PASSED${NC}"
echo -e "${GREEN}${BOLD}════════════════════════════════════════════════════════════${NC}"
echo
echo "Test project: $TEST_DIR"
echo
echo "What just happened:"
echo "  1. Fresh Statamic v$STATAMIC_VERSION installed."
echo "  2. Webhook Manager from $WM_PATH wired as a Composer path repo."
echo "  3. A local PHP webhook receiver recorded the delivered payload."
echo "  4. An outbound webhook rendered its template and DeliveryEngine"
echo "     delivered it → Delivery recorded as success (HTTP 200)."
echo
echo "To open the CP:"
echo "  cd $TEST_DIR && php please make:user && php artisan serve"
echo "  → visit http://127.0.0.1:8000/cp"
echo

# Disarm the failure trap on success
trap - EXIT
