@props([
    'variant' => 'light',
])

<span {{ $attributes->class(['status-pill', $variant === 'success' ? 'online' : 'badge-light']) }}>
    {{ $slot }}
</span>
