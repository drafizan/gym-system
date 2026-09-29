@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Please check the form.</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
