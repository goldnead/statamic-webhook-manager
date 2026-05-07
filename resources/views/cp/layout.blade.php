@extends('statamic::layout')

@section('title', $title ?? __('webhook-manager::nav.webhooks'))

@section('content')
    <div class="webhook-manager">
        @if (session('success'))
            <div class="bg-green-100 text-green-900 px-4 py-2 mb-4 rounded">
                {{ session('success') }}
            </div>
        @endif

        @yield('webhook-content')
    </div>
@endsection
