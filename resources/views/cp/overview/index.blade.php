@extends('webhook-manager::cp.layout', ['title' => __('webhook-manager::nav.overview')])

@section('webhook-content')
    <header class="flex items-center justify-between mb-6">
        <h1>{{ __('webhook-manager::nav.overview') }}</h1>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="card p-4">
            <div class="text-xs uppercase text-grey">Outbound (active)</div>
            <div class="text-2xl font-semibold">{{ $activeOutbound }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase text-grey">Inbound (active)</div>
            <div class="text-2xl font-semibold">{{ $activeInbound }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase text-grey">Success rate 24h</div>
            <div class="text-2xl font-semibold">{{ $successRate24h }}%</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase text-grey">Success rate 7d</div>
            <div class="text-2xl font-semibold">{{ $successRate7d }}%</div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="card p-4">
            <div class="text-xs uppercase text-grey">Successful deliveries</div>
            <div class="text-xl">{{ $counts['success'] }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase text-grey">Failed deliveries</div>
            <div class="text-xl">{{ $counts['failed'] }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase text-grey">Pending / processing</div>
            <div class="text-xl">{{ $counts['pending'] }}</div>
        </div>
    </div>

    <h2 class="text-lg font-semibold mb-2">Recent failures</h2>
    @if ($recentFailures->isEmpty())
        <x-webhook-manager::partials.empty-state
            title="No recent failures"
            description="All recent deliveries succeeded — nothing to investigate." />
    @else
        <table class="w-full data-table">
            <thead>
                <tr>
                    <th>Trigger</th>
                    <th>URL</th>
                    <th>Error</th>
                    <th>When</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach ($recentFailures as $delivery)
                <tr>
                    <td>{{ $delivery->trigger_type }}</td>
                    <td class="truncate max-w-xs">{{ $delivery->request_url }}</td>
                    <td>{{ $delivery->error_type }}</td>
                    <td>{{ $delivery->created_at?->diffForHumans() }}</td>
                    <td>
                        <a href="{{ cp_route('webhook-manager.deliveries.show', $delivery) }}" class="text-blue">View</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endsection
