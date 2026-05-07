@extends('webhook-manager::cp.layout', ['title' => __('webhook-manager::nav.outbound')])

@section('webhook-content')
    <header class="flex items-center justify-between mb-6">
        <h1>{{ __('webhook-manager::nav.outbound') }}</h1>
        <a href="{{ cp_route('webhook-manager.outbound.create') }}" class="btn-primary">
            Create outbound webhook
        </a>
    </header>

    <form method="GET" class="mb-4">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by name, handle or URL" class="input-text w-full md:w-1/3" />
    </form>

    @if ($webhooks->isEmpty())
        <x-webhook-manager::partials.empty-state
            title="No outbound webhooks yet"
            description="Outbound webhooks fire on internal Statamic events and POST to a destination URL."
        >
            <x-slot:cta>
                <a href="{{ cp_route('webhook-manager.outbound.create') }}" class="btn-primary">Create your first outbound webhook</a>
            </x-slot:cta>
        </x-webhook-manager::partials.empty-state>
    @else
        <table class="data-table w-full">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Trigger</th>
                    <th>URL</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach ($webhooks as $hook)
                <tr>
                    <td>
                        <a href="{{ cp_route('webhook-manager.outbound.edit', $hook) }}" class="font-semibold">{{ $hook->name }}</a>
                        <div class="text-xs text-grey">{{ $hook->handle }}</div>
                    </td>
                    <td><code>{{ $hook->trigger_type }}</code></td>
                    <td class="truncate max-w-md">{{ $hook->url }}</td>
                    <td>
                        <x-webhook-manager::partials.status-badge :status="$hook->enabled ? 'success' : 'cancelled'" />
                    </td>
                    <td class="text-right">
                        <form method="POST" action="{{ cp_route('webhook-manager.outbound.toggle', $hook) }}" class="inline">
                            @csrf @method('PATCH')
                            <button class="btn">{{ $hook->enabled ? 'Disable' : 'Enable' }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="mt-4">
            {{ $webhooks->links() }}
        </div>
    @endif
@endsection
