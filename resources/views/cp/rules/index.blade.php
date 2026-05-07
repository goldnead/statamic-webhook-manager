@extends('webhook-manager::cp.layout', ['title' => __('webhook-manager::nav.rules')])

@section('webhook-content')
    <header class="flex items-center justify-between mb-6">
        <h1>{{ __('webhook-manager::nav.rules') }}</h1>
    </header>

    <x-webhook-manager::partials.empty-state
        title="Rules — coming in the next iteration"
        description="Rules let you compose triggers, conditions and multiple actions. Schema and contracts are in place; engine evaluation ships next."
    />

    @if ($rules->isNotEmpty())
        <table class="data-table w-full mt-4">
            <thead><tr><th>Name</th><th>Trigger</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($rules as $rule)
                    <tr>
                        <td>{{ $rule->name }}</td>
                        <td><code>{{ $rule->trigger_type }}</code></td>
                        <td>
                            <x-webhook-manager::partials.status-badge :status="$rule->enabled ? 'success' : 'cancelled'" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
