@extends('webhook-manager::cp.layout', ['title' => __('webhook-manager::nav.settings')])

@section('webhook-content')
    <header class="flex items-center justify-between mb-6">
        <h1>{{ __('webhook-manager::nav.settings') }}</h1>
    </header>

    <p class="text-sm text-grey mb-4">
        Settings are configured in <code>config/webhook-manager.php</code>. Publish the config file with:
        <code>php please vendor:publish --tag=webhook-manager-config</code>.
    </p>

    <pre class="webhook-snapshot-pre">{{ json_encode($config, JSON_PRETTY_PRINT) }}</pre>
@endsection
