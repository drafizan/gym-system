@props([
    'icon' => 'eye',
    'label',
    'variant' => 'light',
    'type' => 'button',
])

@php
    $icons = [
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle>',
        'edit' => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path>',
        'ban' => '<circle cx="12" cy="12" r="9"></circle><path d="M5.6 5.6l12.8 12.8"></path>',
        'restore' => '<path d="M3 12a9 9 0 1 0 3-6.7"></path><path d="M3 4v6h6"></path>',
        'search' => '<circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path>',
        'filter' => '<path d="M22 3H2l8 9.5V20l4 2v-9.5Z"></path>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="M7 10l5 5 5-5"></path><path d="M12 15V3"></path>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="M17 8l-5-5-5 5"></path><path d="M12 3v12"></path>',
        'refresh' => '<path d="M21 12a9 9 0 0 1-15.5 6.2"></path><path d="M3 12A9 9 0 0 1 18.5 5.8"></path><path d="M18 2v4h4"></path><path d="M6 22v-4H2"></path>',
        'back' => '<path d="M19 12H5"></path><path d="M12 19l-7-7 7-7"></path>',
        'trash' => '<path d="M3 6h18"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path>',
        'check' => '<path d="M20 6 9 17l-5-5"></path>',
    ];

    $tooltip = $attributes->get('data-tooltip', $label);
    $classes = ['btn', 'btn-' . $variant, 'icon-action'];
@endphp

@if ($attributes->has('href'))
    <a {{ $attributes->class($classes)->merge(['aria-label' => $label, 'title' => $tooltip, 'data-tooltip' => $tooltip]) }}>
        <svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons[$icon] ?? $icons['eye'] !!}</svg>
        <span class="sr-only">{{ $label }}</span>
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes)->merge(['aria-label' => $label, 'title' => $tooltip, 'data-tooltip' => $tooltip]) }}>
        <svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons[$icon] ?? $icons['eye'] !!}</svg>
        <span class="sr-only">{{ $label }}</span>
    </button>
@endif
