@extends('webhook-manager::cp.layout', ['title' => __('webhook-manager::nav.deliveries')])

@section('webhook-content')
    <header class="flex items-center justify-between mb-6">
        <h1>{{ __('webhook-manager::nav.deliveries') }}</h1>
    </header>

    <form method="GET" class="flex flex-wrap gap-3 mb-4">
        <select name="status" class="input-text">
            <option value="">All statuses</option>
            @foreach (['pending','processing','success','failed','cancelled'] as $s)
                <option value="{{ $s }}" @selected(($filters['status'] ?? null) === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <input type="text" name="trigger" placeholder="Trigger" value="{{ $filters['trigger'] ?? '' }}" class="input-text" />
        <input type="text" name="error_type" placeholder="Error type" value="{{ $filters['error_type'] ?? '' }}" class="input-text" />
        <button class="btn">Filter</button>
    </form>

    @if ($deliveries->isEmpty())
        <x-webhook-manager::partials.empty-state
            title="No deliveries match these filters"
            description="Trigger a webhook or relax the filters to see results." />
    @else
        <table class="data-table w-full">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Trigger</th>
                    <th>URL</th>
                    <th>HTTP</th>
                    <th>Attempts</th>
                    <th>When</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach ($deliveries as $delivery)
                <tr>
                    <td><x-webhook-manager::partials.status-badge :status="$delivery->statusBadge()" /></td>
                    <td><code>{{ $delivery->trigger_type }}</code></td>
                    <td class="truncate max-w-md">{{ $delivery->request_url }}</td>
                    <td>{{ $delivery->response_status ?? '—' }}</td>
                    <td>{{ $delivery->attempts }}</td>
                    <td>{{ $delivery->created_at?->diffForHumans() }}</td>
                    <td>
                        <a href="{{ cp_route('webhook-manager.deliveries.show', $delivery) }}" class="text-blue">View</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $deliveries->links() }}</div>
    @endif
@endsection
