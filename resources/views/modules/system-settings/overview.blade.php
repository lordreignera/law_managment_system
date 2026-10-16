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
            @can('settings.master-data.index')
                <a class="kfms-btn" href="{{ route('settings.master-data.index') }}">Import Master Data</a>
            @endcan
        </div>

        <form class="kfms-settings-search" method="GET" action="{{ route('settings.system.overview') }}">
            <label class="kfms-search-box" for="settings-search">
                <i class="mdi mdi-magnify"></i>
                <input id="settings-search" type="search" name="search" value="{{ $search }}" placeholder="Search settings, such as courts or tax" autocomplete="off">
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-btn" type="submit">Search</button>
                @if ($search !== '')
                    <a class="kfms-link-btn" href="{{ route('settings.system.overview') }}">Clear</a>
                @endif
            </div>
        </form>

        @if ($settings->isEmpty())
            <div class="kfms-empty">No settings match “{{ $search }}”.</div>
        @endif

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
