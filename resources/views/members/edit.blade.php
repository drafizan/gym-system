@extends('layouts.app')

@section('title', 'Edit Member')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('members.index') }}">Members</a>
    <span>Edit</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Gym Operations</p>
            <h1>Edit Member</h1>
        </div>
    </div>

    <form class="member-registration-form" method="POST" action="{{ route('members.update', $member) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('members.partials.form', [
            'cancelUrl' => route('members.show', $member),
            'submitLabel' => 'Save Changes',
            'showSaveAnother' => false,
        ])
    </form>
@endsection
