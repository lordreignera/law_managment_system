@extends('layouts.admin')

@section('title', 'Add '.$definition['singular'])
@section('page-title', 'Add '.$definition['singular'])

@section('content')
    <section class="kfms-panel kfms-settings-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Add {{ $definition['singular'] }}</h2>
                <span>{{ $definition['description'] }}</span>
            </div>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('settings.system.store', $setting) }}">
            @csrf
            @include('modules.system-settings.partials.form')
        </form>
    </section>
@endsection
