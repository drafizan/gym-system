@props([
    'label',
    'value',
    'tone' => 'muted',
])

<article {{ $attributes->class('stat-card') }}>
    <span class="stat-label">{{ $label }}</span>
    <strong>{{ $value }}</strong>
    <small @class([
        'text-success' => $tone === 'success',
        'text-info' => $tone === 'info',
        'text-warning' => $tone === 'warning',
    ])>{{ $slot }}</small>
</article>
