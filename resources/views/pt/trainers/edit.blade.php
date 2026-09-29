@extends('layouts.app')

@section('title', 'Edit Trainer')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('pt.trainers.index') }}">Trainers</a>
    <span>Edit Trainer</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div><p class="eyebrow">Personal Training</p><h1>Edit Trainer</h1></div>
    </div>

    <x-panel title="Trainer Details">
        <form method="POST" action="{{ route('pt.trainers.update', $trainer) }}">
            @csrf
            @method('PUT')
            @include('pt.trainers.partials.form')
            <div class="form-actions">
                <a class="btn btn-light" href="{{ route('pt.trainers.index') }}">Cancel</a>
                <button class="btn btn-primary" type="submit">Save Changes</button>
            </div>
        </form>
    </x-panel>
@endsection
