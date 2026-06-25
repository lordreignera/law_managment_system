@extends('layouts.admin')

@section('title', 'Edit '.$definition['singular'])
@section('page-title', 'Edit '.$definition['singular'])

@section('content')
    <section class="kfms-panel kfms-settings-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Edit {{ $definition['singular'] }}</h2>
                <span>{{ $record->code }}</span>
            </div>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('settings.system.update', [$setting, $record]) }}">
            @csrf
            @method('PUT')
            @include('modules.system-settings.partials.form')
        </form>
    </section>
@endsection
