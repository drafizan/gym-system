@extends('layouts.app')

@section('title', 'Member Registration')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('members.index') }}">Members</a>
    <span>Member Registration</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Members</p>
            <h1>Member Registration</h1>
        </div>
    </div>

    <form class="member-registration-form" method="POST" action="{{ route('members.store') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="registration_checkout" value="1">
        @include('members.partials.form', [
            'cancelUrl' => route('members.index'),
            'submitLabel' => 'Save & Continue to POS',
            'showSaveAnother' => false,
        ])
    </form>
@endsection
