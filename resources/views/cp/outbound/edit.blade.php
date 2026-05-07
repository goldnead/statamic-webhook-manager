@extends('webhook-manager::cp.layout', ['title' => $isNew ? 'Create outbound webhook' : $webhook->name])

@section('webhook-content')
    @php
        $action = $isNew
            ? cp_route('webhook-manager.outbound.store')
            : cp_route('webhook-manager.outbound.update', $webhook);
    @endphp

    <form method="POST" action="{{ $action }}" class="space-y-6">
        @csrf
        @if (! $isNew)
            @method('PATCH')
        @endif

        <header class="flex items-center justify-between">
            <h1>{{ $isNew ? 'Create outbound webhook' : $webhook->name }}</h1>
            <div class="space-x-2">
                @unless ($isNew)
                    <button type="button"
                        onclick="webhookManagerTest('{{ $webhook->id }}')"
                        class="btn">Test</button>
                @endunless
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </header>

        @if ($errors->any())
            <div class="bg-red-100 text-red-900 px-4 py-2 rounded">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <fieldset class="card p-4 space-y-3">
            <legend class="font-semibold">Identity</legend>
            <label class="block">
                <span class="block text-sm font-semibold">Name</span>
                <input type="text" name="name" value="{{ old('name', $webhook->name) }}" class="input-text w-full" required />
            </label>
            <label class="block">
                <span class="block text-sm font-semibold">Handle</span>
                <input type="text" name="handle" value="{{ old('handle', $webhook->handle) }}" pattern="[a-z0-9_-]+" class="input-text w-full" required />
            </label>
            <label class="block">
                <span class="block text-sm font-semibold">Description</span>
                <textarea name="description" rows="2" class="input-text w-full">{{ old('description', $webhook->description) }}</textarea>
            </label>
            <label class="block">
                <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $webhook->enabled ?? true)) />
                <span>Enabled</span>
            </label>
        </fieldset>

        <fieldset class="card p-4 space-y-3">
            <legend class="font-semibold">Trigger</legend>
            <label class="block">
                <span class="block text-sm font-semibold">Trigger type</span>
                <select name="trigger_type" class="input-text w-full" required>
                    @foreach ($triggerOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('trigger_type', $webhook->trigger_type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </fieldset>

        <fieldset class="card p-4 space-y-3">
            <legend class="font-semibold">Destination</legend>
            <label class="block">
                <span class="block text-sm font-semibold">URL</span>
                <input type="url" name="url" value="{{ old('url', $webhook->url) }}" class="input-text w-full" required />
            </label>
            <label class="block">
                <span class="block text-sm font-semibold">Method</span>
                <select name="method" class="input-text w-full">
                    @foreach (['POST','GET','PUT','PATCH','DELETE'] as $m)
                        <option value="{{ $m }}" @selected(old('method', $webhook->method) === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="block text-sm font-semibold">Timeout (seconds)</span>
                <input type="number" min="1" max="120" name="timeout_seconds" value="{{ old('timeout_seconds', $webhook->timeout_seconds ?? 15) }}" class="input-text w-32" />
            </label>
            <label class="block">
                <input type="checkbox" name="follow_redirects" value="1" @checked(old('follow_redirects', $webhook->follow_redirects ?? true)) />
                <span>Follow redirects</span>
            </label>
        </fieldset>

        <fieldset class="card p-4 space-y-3">
            <legend class="font-semibold">Authentication</legend>
            <label class="block">
                <span class="block text-sm font-semibold">Type</span>
                <select name="auth_type" class="input-text w-full">
                    @foreach ($authOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('auth_type', $webhook->auth_type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="block text-sm font-semibold">Auth config (JSON)</span>
                <textarea name="auth_config_json" rows="3" class="input-text w-full font-mono text-sm" placeholder='{ "secret": "your-secret" }'>{{ old('auth_config_json', $webhook->auth_config ? json_encode($webhook->auth_config, JSON_PRETTY_PRINT) : '') }}</textarea>
                <small class="text-grey">Stored encrypted. Replace, don't reveal.</small>
            </label>
        </fieldset>

        <fieldset class="card p-4 space-y-3">
            <legend class="font-semibold">Payload</legend>
            <label class="block">
                <span class="block text-sm font-semibold">Type</span>
                <select name="payload_type" class="input-text w-full">
                    <option value="raw_json" @selected(old('payload_type', $webhook->payload_type) === 'raw_json')>Raw JSON template</option>
                    <option value="mapped" @selected(old('payload_type', $webhook->payload_type) === 'mapped')>Mapped object</option>
                    <option value="form" @selected(old('payload_type', $webhook->payload_type) === 'form')>Form encoded</option>
                </select>
            </label>
            <label class="block">
                <span class="block text-sm font-semibold">Template</span>
                <textarea name="payload_template" rows="10" class="input-text w-full font-mono text-sm">{{ old('payload_template', $webhook->payload_template) }}</textarea>
                <small class="text-grey">Use tokens like <code>{{ '{{' }} entry:title {{ '}}' }}</code>, <code>{{ '{{' }} system:timestamp_iso {{ '}}' }}</code>.</small>
            </label>
        </fieldset>

        <fieldset class="card p-4 space-y-3">
            <legend class="font-semibold">Delivery</legend>
            <label class="block">
                <input type="checkbox" name="queue_enabled" value="1" @checked(old('queue_enabled', $webhook->queue_enabled ?? true)) />
                <span>Send asynchronously via queue</span>
            </label>
            <label class="block">
                <span class="block text-sm font-semibold">Body logging</span>
                <select name="log_body_mode" class="input-text w-full">
                    @foreach (['full','partial','none'] as $mode)
                        <option value="{{ $mode }}" @selected(old('log_body_mode', $webhook->log_body_mode) === $mode)>{{ ucfirst($mode) }}</option>
                    @endforeach
                </select>
            </label>
        </fieldset>
    </form>

    @unless ($isNew)
        <hr class="my-6" />
        <form method="POST" action="{{ cp_route('webhook-manager.outbound.destroy', $webhook) }}"
              onsubmit="return confirm('Delete this webhook?')">
            @csrf @method('DELETE')
            <button class="btn-error">Delete</button>
        </form>
    @endunless

    <script>
        async function webhookManagerTest(id) {
            const res = await fetch('{{ cp_route('webhook-manager.actions.test-outbound', '__id__') }}'.replace('__id__', id), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
            });
            const data = await res.json();
            alert('Result: ' + (data.ok ? 'success' : 'failed') + ' (HTTP ' + (data.response_status ?? '—') + ')');
        }
    </script>
@endsection
