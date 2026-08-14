<?php

namespace Goldnead\WebhookManager\Http\Requests;

use Goldnead\WebhookManager\Support\Settings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for the settings form, built from {@see Settings::fields()}.
 *
 * The rules are generated rather than written out, so a field cannot be added
 * to the screen and forgotten here — which is the failure that lets an
 * unvalidated value reach `config()` and, from there, the delivery engine, the
 * retry planner and the inbound request path.
 */
class UpdateSettingsRequest extends FormRequest
{
    /**
     * The addon's existing settings permission. The same one gates the page
     * itself, so a user who can see this form can save it; the check is
     * repeated here because a permission enforced only by the screen that
     * happens to link to the endpoint is not enforced.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage webhook settings');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = ['settings' => ['required', 'array']];

        foreach (Settings::fields() as $key => $field) {
            // Dots are path separators to the validator, and these keys carry
            // real dots (`retry.max_attempts`). Escaped, the rule addresses the
            // one key rather than a nested structure that does not exist.
            $at = 'settings.'.str_replace('.', '\\.', $key);

            $rules[$at] = match ($field['type']) {
                'boolean' => ['present', 'boolean'],
                'integer' => array_values(array_filter([
                    'present',
                    $field['nullable'] ? 'nullable' : 'required',
                    'integer',
                    isset($field['min']) ? 'min:'.$field['min'] : null,
                ])),
                'select' => [
                    'present',
                    $field['nullable'] ? 'nullable' : 'required',
                    Rule::in(array_column($field['options'] ?? [], 'value')),
                ],
                'list' => ['present', 'array'],
                default => [
                    'present',
                    $field['nullable'] ? 'nullable' : 'required',
                    'string',
                    'max:255',
                ],
            };

            if ($field['type'] === 'list') {
                // `nullable`, because Laravel's ConvertEmptyStringsToNull has
                // already turned a blank line from the textarea into null by the
                // time the rules run. Rejecting it would mean the form refuses
                // to save over a trailing newline — and the blanks are dropped
                // in the controller anyway, so nothing empty is ever stored.
                $rules[$at.'.*'] = ($field['items'] ?? 'string') === 'integer'
                    ? ['nullable', 'integer']
                    : ['nullable', 'string', 'max:255'];
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $attributes = [];

        foreach (Settings::fields() as $key => $field) {
            $attributes['settings.'.$key] = $field['label'];
        }

        return $attributes;
    }
}
