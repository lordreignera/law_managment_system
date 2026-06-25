@extends('layouts.admin')

@section('title', 'System Settings')
@section('page-title', 'System Settings')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>System Settings</h2>
                <span>Master data used before matters, finance, HR, billing, and documents are created.</span>
            </div>
        </div>

        <div class="kfms-settings-grid">
            @foreach ($settings as $setting)
                <a class="kfms-settings-card" href="{{ route('settings.system.index', $setting['slug']) }}">
                    <span>{{ $setting['title'] }}</span>
                    <strong>{{ $setting['count'] }}</strong>
                    <small>{{ $setting['description'] }}</small>
                </a>
            @endforeach
        </div>
    </section>
@endsection
