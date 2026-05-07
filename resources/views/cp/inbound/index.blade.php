@extends('webhook-manager::cp.layout', ['title' => __('webhook-manager::nav.inbound')])

@section('webhook-content')
    <header class="flex items-center justify-between mb-6">
        <h1>{{ __('webhook-manager::nav.inbound') }}</h1>
    </header>

    <x-webhook-manager::partials.empty-state
        title="Inbound endpoints — coming in the next iteration"
        description="The schema, controller and verifier services are scaffolded; full functionality ships in v0.2. Existing endpoints are listed below for reference."
    />

    @if ($endpoints->isNotEmpty())
        <table class="data-table w-full mt-4">
            <thead><tr><th>Name</th><th>Path</th><th>Auth</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($endpoints as $endpoint)
                    <tr>
                        <td>{{ $endpoint->name }}</td>
                        <td><code>{{ $endpoint->path }}</code></td>
                        <td>{{ $endpoint->auth_type }}</td>
                        <td>
                            <x-webhook-manager::partials.status-badge :status="$endpoint->enabled ? 'success' : 'cancelled'" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
