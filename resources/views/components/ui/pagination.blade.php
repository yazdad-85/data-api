@props([
    'paginator',
])

@if ($paginator->hasPages())
    <nav {{ $attributes->class(['pagination']) }} aria-label="Navigasi halaman">
        {{ $paginator->links() }}
    </nav>
@endif
