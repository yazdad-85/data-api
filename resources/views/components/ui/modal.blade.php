@props([
    'id',
    'title',
    'open' => false,
])

<dialog
    id="{{ $id }}"
    class="modal"
    @if ($open) open @endif
    {{ $attributes }}
>
    <div class="modal__panel">
        <header class="modal__header">
            <h2 class="modal__title font-display" id="{{ $id }}-title">{{ $title }}</h2>
            <form method="dialog" class="modal__close-form">
                <button type="submit" class="modal__close" aria-label="Tutup">×</button>
            </form>
        </header>

        <div class="modal__body" aria-labelledby="{{ $id }}-title">
            {{ $slot }}
        </div>

        @isset($actions)
            <footer class="modal__actions">
                {{ $actions }}
            </footer>
        @endisset
    </div>
</dialog>
