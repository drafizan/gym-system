@foreach (['success', 'status', 'warning', 'error'] as $type)
    @if (session($type))
        <div class="alert alert-{{ $type === 'error' ? 'danger' : $type }}" role="alert">
            {{ session($type) }}
        </div>
    @endif
@endforeach
