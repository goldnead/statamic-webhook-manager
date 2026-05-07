<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers;

use Goldnead\WebhookManager\Contracts\InboundActionHandlerInterface;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Statamic\Facades\Form;

/**
 * Creates a Statamic form submission from the mapped payload.
 *
 * Endpoint `action_config` must include:
 *   - `form` (string, required) — handle of the Statamic form
 *
 * The mapped payload is stored as submission data verbatim. Form handlers
 * (mailer, etc.) are intentionally not triggered to keep behaviour
 * predictable when a webhook is replayed; downstream handlers can
 * subscribe to `\Statamic\Events\SubmissionCreated`.
 */
class CreateFormSubmissionHandler implements InboundActionHandlerInterface
{
    public function handle(): string
    {
        return 'create_form_submission';
    }

    public function label(): string
    {
        return 'Create form submission';
    }

    public function handleAction(InboundEndpoint $endpoint, array $mappedPayload, array $rawPayload): array
    {
        $config = $endpoint->action_config ?? [];
        $formHandle = (string) ($config['form'] ?? '');

        if ($formHandle === '') {
            return [
                'ok' => false,
                'message' => 'Missing required action_config.form.',
                'data' => [],
            ];
        }

        try {
            $form = Form::find($formHandle);
            if (! $form) {
                return [
                    'ok' => false,
                    'message' => "Form '{$formHandle}' not found.",
                    'data' => [],
                ];
            }

            $submission = $form->makeSubmission()->data($mappedPayload);
            $submission->save();

            return [
                'ok' => true,
                'message' => 'Submission created.',
                'data' => [
                    'id' => $submission->id(),
                    'form' => $formHandle,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Failed to create submission: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }
}
