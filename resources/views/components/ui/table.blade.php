@props([])

<div {{ $attributes->class(['table-wrap']) }}>
    @isset($empty)
        <div class="table-empty">
            {{ $empty }}
        </div>
    @else
        <table class="data-table">
            @isset($thead)
                <thead>{{ $thead }}</thead>
            @endisset
            <tbody>
                {{ $tbody ?? $slot }}
            </tbody>
        </table>
    @endif
</div>
