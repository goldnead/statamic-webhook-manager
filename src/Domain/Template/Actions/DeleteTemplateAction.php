<?php

namespace Goldnead\WebhookManager\Domain\Template\Actions;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Contracts\Repositories\TemplateRepositoryInterface;
use Goldnead\WebhookManager\Domain\Template\Models\Template;

/**
 * Delete a template. If outbound webhooks reference this template by
 * handle (`payload_template_handle`), they are detached but kept —
 * deleting the library entry shouldn't silently disable hooks. The
 * caller is expected to surface the detached count to the operator.
 */
class DeleteTemplateAction
{
    public function __construct(
        protected TemplateRepositoryInterface $templates,
        protected OutboundWebhookRepositoryInterface $outbounds,
    ) {
    }

    /**
     * @return array{deleted:bool, detached_outbounds:int}
     */
    public function __invoke(Template $template): array
    {
        // Detach via the repository so it works under either storage driver.
        $detached = 0;
        foreach ($this->outbounds->all() as $hook) {
            if ($hook->payload_template_handle === $template->handle) {
                $hook->payload_template_handle = null;
                $this->outbounds->save($hook);
                $detached++;
            }
        }

        $this->templates->delete($template);

        return [
            'deleted' => true,
            'detached_outbounds' => $detached,
        ];
    }
}
