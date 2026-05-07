<?php

namespace Goldnead\WebhookManager\Actions;

use Goldnead\WebhookManager\Contracts\ActionInterface;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;
use Statamic\Facades\Form;

/**
 * Create a Statamic form submission from the trigger payload.
 *
 * Rule config:
 *   - `form` (string, required) — handle of the Statamic form
 *   - `data` (object/array, optional) — explicit data; defaults to trigger payload
 */
class CreateFormSubmissionAction implements ActionInterface
{
    public function handle(): string
    {
        return 'create_form_submission';
    }

    public function label(): string
    {
        return 'Create form submission';
    }

    public function execute(array $config, ExecutionContext $context): ExecutionResult
    {
        $formHandle = (string) ($config['form'] ?? '');
        if ($formHandle === '') {
            return ExecutionResult::fail('Missing required config.form.');
        }

        try {
            $form = Form::find($formHandle);
            if (! $form) {
                return ExecutionResult::fail("Form '{$formHandle}' not found.");
            }

            $data = is_array($config['data'] ?? null) ? $config['data'] : $context->payload();
            $submission = $form->makeSubmission()->data($data);
            $submission->save();

            return ExecutionResult::ok('Submission created.', [
                'id' => $submission->id(),
                'form' => $formHandle,
            ]);
        } catch (\Throwable $e) {
            return ExecutionResult::fail('Failed to create submission: '.$e->getMessage());
        }
    }
}
