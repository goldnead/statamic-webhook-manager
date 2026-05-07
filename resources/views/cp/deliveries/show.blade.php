@extends('webhook-manager::cp.layout', ['title' => 'Delivery #'.$delivery->id])

@section('webhook-content')
    <header class="flex items-center justify-between mb-6">
        <h1>Delivery #{{ $delivery->id }}
            <x-webhook-manager::partials.status-badge :status="$delivery->statusBadge()" />
        </h1>
        @if ($canReplay && $delivery->isReplayable())
            <button type="button" onclick="webhookManagerReplay({{ $delivery->id }})" class="btn-primary">Replay</button>
        @endif
    </header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="card p-4">
            <h3 class="font-semibold mb-2">Metadata</h3>
            <dl class="text-sm space-y-1">
                <div><dt class="inline text-grey">Trigger:</dt> <dd class="inline"><code>{{ $delivery->trigger_type }}</code></dd></div>
                <div><dt class="inline text-grey">Reference:</dt> <dd class="inline">{{ $delivery->trigger_reference ?? '—' }}</dd></div>
                <div><dt class="inline text-grey">Correlation:</dt> <dd class="inline">{{ $delivery->correlation_id ?? '—' }}</dd></div>
                <div><dt class="inline text-grey">Attempts:</dt> <dd class="inline">{{ $delivery->attempts }}</dd></div>
                <div><dt class="inline text-grey">Duration:</dt> <dd class="inline">{{ $delivery->duration_ms ?? '—' }} ms</dd></div>
                <div><dt class="inline text-grey">First attempt:</dt> <dd class="inline">{{ $delivery->first_attempted_at?->diffForHumans() ?? '—' }}</dd></div>
                <div><dt class="inline text-grey">Last attempt:</dt> <dd class="inline">{{ $delivery->last_attempted_at?->diffForHumans() ?? '—' }}</dd></div>
                <div><dt class="inline text-grey">Next retry:</dt> <dd class="inline">{{ $delivery->next_retry_at?->diffForHumans() ?? '—' }}</dd></div>
                <div><dt class="inline text-grey">Error:</dt> <dd class="inline">{{ $delivery->error_type ?? '—' }} {{ $delivery->error_message ? '— '.$delivery->error_message : '' }}</dd></div>
            </dl>
        </div>

        <div class="card p-4">
            <h3 class="font-semibold mb-2">Request</h3>
            <dl class="text-sm space-y-1">
                <div><dt class="inline text-grey">Method:</dt> <dd class="inline"><code>{{ $delivery->request_method }}</code></dd></div>
                <div><dt class="inline text-grey">URL:</dt> <dd class="inline break-all">{{ $delivery->request_url }}</dd></div>
            </dl>
            <h4 class="font-semibold mt-3">Headers</h4>
            <pre class="webhook-snapshot-pre">{{ json_encode($delivery->request_headers, JSON_PRETTY_PRINT) }}</pre>
            <h4 class="font-semibold mt-3">Body</h4>
            <pre class="webhook-snapshot-pre">{{ $delivery->request_body }}</pre>
        </div>
    </div>

    <div class="card p-4 mb-6">
        <h3 class="font-semibold mb-2">Response — HTTP {{ $delivery->response_status ?? '—' }}</h3>
        <h4 class="font-semibold mt-3">Headers</h4>
        <pre class="webhook-snapshot-pre">{{ json_encode($delivery->response_headers, JSON_PRETTY_PRINT) }}</pre>
        <h4 class="font-semibold mt-3">Body</h4>
        <pre class="webhook-snapshot-pre">{{ $delivery->response_body }}</pre>
    </div>

    <div class="card p-4">
        <h3 class="font-semibold mb-2">Copy as cURL</h3>
        @if (! $canViewSensitive)
            <p class="text-sm text-grey mb-2">Sensitive headers and payload keys are masked. Ask an administrator for the
                <em>view sensitive payloads</em> permission to see the original snapshot.</p>
        @endif
        <pre class="webhook-snapshot-pre" id="curl-snippet">{{ $curl }}</pre>
        <button class="btn mt-2" onclick="navigator.clipboard.writeText(document.getElementById('curl-snippet').innerText)">Copy</button>
    </div>

    <script>
        async function webhookManagerReplay(id) {
            const res = await fetch('{{ cp_route('webhook-manager.actions.replay-delivery', $delivery) }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
            });
            const data = await res.json();
            alert(data.message ?? 'Replay queued.');
            window.location.reload();
        }
    </script>
@endsection
