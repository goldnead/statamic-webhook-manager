@extends('webhook-manager::cp.layout', ['title' => __('webhook-manager::nav.templates')])

@section('webhook-content')
    <header class="flex items-center justify-between mb-6">
        <h1>{{ __('webhook-manager::nav.templates') }}</h1>
    </header>

    @if ($templates->isEmpty())
        <x-webhook-manager::partials.empty-state
            title="No templates yet"
            description="Templates let you reuse payload bodies across hooks. CP-side editing arrives in the next iteration; the renderer is already used by outbound webhooks."
        />
    @else
        <table class="data-table w-full">
            <thead><tr><th>Name</th><th>Handle</th><th>Type</th></tr></thead>
            <tbody>
                @foreach ($templates as $tpl)
                    <tr>
                        <td>{{ $tpl->name }}</td>
                        <td><code>{{ $tpl->handle }}</code></td>
                        <td>{{ $tpl->type }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
