@extends('webhook-manager::cp.layout', ['title' => __('webhook-manager::nav.debug')])

@section('webhook-content')
    <header class="mb-6">
        <h1>{{ __('webhook-manager::nav.debug') }}</h1>
    </header>

    <div class="card p-4 mb-6">
        <h2 class="font-semibold mb-2">Registered triggers</h2>
        <table class="data-table w-full">
            <thead><tr><th>Handle</th><th>Label</th><th>Source type</th></tr></thead>
            <tbody>
                @foreach ($triggers as $t)
                    <tr>
                        <td><code>{{ $t->handle() }}</code></td>
                        <td>{{ $t->label() }}</td>
                        <td>{{ $t->sourceType() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card p-4 mb-6">
        <h2 class="font-semibold mb-2">Variable resolvers</h2>
        <ul class="list-disc list-inside">
            @foreach ($resolvers as $r)
                <li><code>{{ '{{' }} {{ $r->namespace() }}:key {{ '}}' }}</code></li>
            @endforeach
        </ul>
    </div>

    <div class="card p-4">
        <h2 class="font-semibold mb-2">Template preview</h2>
        <textarea id="dbg-template" rows="6" class="input-text w-full font-mono">{ "title": "{{ '{{' }} entry:title {{ '}}' }}", "site": "{{ '{{' }} site:handle {{ '}}' }}" }</textarea>
        <textarea id="dbg-payload" rows="6" class="input-text w-full font-mono mt-2">{ "id": "1", "title": "Hello", "site": "default" }</textarea>
        <button class="btn mt-2" onclick="webhookDebugPreview()">Preview</button>
        <pre class="webhook-snapshot-pre" id="dbg-out"></pre>

        <script>
            async function webhookDebugPreview() {
                const template = document.getElementById('dbg-template').value;
                const payloadRaw = document.getElementById('dbg-payload').value;
                let payload = {};
                try { payload = JSON.parse(payloadRaw); } catch (e) { document.getElementById('dbg-out').innerText = 'Invalid JSON payload'; return; }
                const res = await fetch('{{ cp_route('webhook-manager.actions.preview-template') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ template, sample_payload: payload, source_type: 'entry' }),
                });
                const data = await res.json();
                document.getElementById('dbg-out').innerText =
                    (data.issues?.length ? 'Issues:\n - ' + data.issues.join('\n - ') + '\n\n' : '') +
                    'Rendered:\n' + (data.rendered ?? '');
            }
        </script>
    </div>
@endsection
