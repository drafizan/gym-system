@props([
    'label',
    'for' => null,
])

<div {{ $attributes->class('form-row') }}>
    <label @if ($for) for="{{ $for }}" @endif>{{ $label }}</label>
    <div class="form-control-wrap">
        {{ $slot }}
    </div>
</div>
