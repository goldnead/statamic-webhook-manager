@extends('webhook-manager::cp.layout', ['title' => __('webhook-manager::nav.logs')])

@section('webhook-content')
    <header class="flex items-center justify-between mb-6">
        <h1>{{ __('webhook-manager::nav.logs') }}</h1>
    </header>

    <form method="GET" class="flex gap-3 mb-4">
        <select name="level" class="input-text">
            <option value="">All levels</option>
            @foreach (['debug','info','warning','error'] as $l)
                <option value="{{ $l }}" @selected(($filters['level'] ?? null) === $l)>{{ ucfirst($l) }}</option>
            @endforeach
        </select>
        <input type="text" name="type" placeholder="Type" value="{{ $filters['type'] ?? '' }}" class="input-text" />
        <input type="text" name="correlation_id" placeholder="Correlation ID" value="{{ $filters['correlation_id'] ?? '' }}" class="input-text" />
        <button class="btn">Filter</button>
    </form>

    @if ($logs->isEmpty())
        <x-webhook-manager::partials.empty-state title="No log entries" />
    @else
        <table class="data-table w-full">
            <thead><tr><th>Level</th><th>Type</th><th>Message</th><th>When</th></tr></thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ ucfirst($log->level) }}</td>
                        <td><code>{{ $log->type }}</code></td>
                        <td>{{ $log->message }}</td>
                        <td>{{ $log->created_at?->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $logs->links() }}</div>
    @endif
@endsection
