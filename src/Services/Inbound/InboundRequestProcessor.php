<?php

namespace Goldnead\WebhookManager\Services\Inbound;

/**
 * TODO: REVIEW — orchestrates the inbound flow:
 *  1. InboundAuthVerifier → 401 on failure
 *  2. InboundPayloadParser → 400 on parse failure
 *  3. ReplayProtectionService check
 *  4. InboundMappingService → mapped payload
 *  5. InboundActionDispatcher → execute target action
 *  6. InboundResponseBuilder → final HTTP response
 *
 * Stub-class for v1; controller short-circuits with 501 until this is enabled.
 */
class InboundRequestProcessor
{
}
