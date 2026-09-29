@props([
    'eyebrow' => null,
    'title',
])

<article {{ $attributes->class('panel') }}>
    <div class="panel-header">
        <div>
            @if ($eyebrow)
                <p class="eyebrow">{{ $eyebrow }}</p>
            @endif
            <h2>{{ $title }}</h2>
        </div>

        @isset($actions)
            {{ $actions }}
        @endisset
    </div>

    {{ $slot }}
</article>
