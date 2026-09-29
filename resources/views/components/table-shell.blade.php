@props([
    'title' => null,
])

<section {{ $attributes->class('table-shell') }}>
    @if ($title || isset($actions))
        <div class="table-shell-header">
            @if ($title)
                <h2>{{ $title }}</h2>
            @endif
            @isset($actions)
                <div class="table-actions">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="table-responsive">
        {{ $slot }}
    </div>
</section>
