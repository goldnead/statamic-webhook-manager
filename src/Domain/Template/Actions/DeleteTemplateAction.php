<?php

namespace Goldnead\WebhookManager\Domain\Template\Actions;

use Goldnead\WebhookManager\Domain\Template\Models\Template;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;

/**
 * Delete a template. If outbound webhooks reference this template by
 * handle (`payload_template_handle`), they are detached but kept —
 * deleting the library entry shouldn't silently disable hooks. The
 * caller is expected to surface the detached count to the operator.
 */
class DeleteTemplateAction
{
    /**
     * @return array{deleted:bool, detached_outbounds:int}
     */
    public function __invoke(Template $template): array
    {
        $detached = OutboundWebhook::where('payload_template_handle', $template->handle)
            ->update(['payload_template_handle' => null]);

        $template->delete();

        return [
            'deleted' => true,
            'detached_outbounds' => (int) $detached,
        ];
    }
}
