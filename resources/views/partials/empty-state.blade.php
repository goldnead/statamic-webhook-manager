@props(['title', 'description' => null, 'cta' => null])
<div class="webhook-empty-state border border-dashed rounded-lg">
    <h2 class="text-lg font-semibold mb-2">{{ $title }}</h2>
    @if ($description)
        <p class="mb-3">{{ $description }}</p>
    @endif
    {{ $cta }}
</div>
