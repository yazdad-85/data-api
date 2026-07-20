@php
    use App\Support\Ui\AppEnvironmentLabel;
    $envLabel = AppEnvironmentLabel::fromEnv();
    $requestId = function_exists('request_id') ? request_id() : null;
@endphp
<footer class="app-footer" @if($requestId) title="request_id: {{ $requestId }}" @endif>
    <div>
        <strong class="font-display" style="color: var(--ink); font-weight: 600;">Pusat Data</strong>
        <span aria-hidden="true"> · </span>
        <span>&copy; {{ now()->year }}</span>
    </div>
    <span class="env-badge">{{ $envLabel }}</span>
</footer>
