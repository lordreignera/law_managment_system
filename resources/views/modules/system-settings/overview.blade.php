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

        <form class="kfms-settings-search" method="GET" action="{{ route('settings.system.overview') }}" data-settings-search>
            <label class="kfms-search-box" for="settings-search">
                <i class="mdi mdi-magnify"></i>
                <input id="settings-search" type="search" name="search" value="{{ $search }}" placeholder="Start typing, such as B for banks" autocomplete="off" aria-controls="settings-results" aria-describedby="settings-search-status">
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-btn" type="submit">Search</button>
                <a class="kfms-link-btn" href="{{ route('settings.system.overview') }}" data-settings-clear>Clear</a>
            </div>
        </form>

        <p class="kfms-muted-text" id="settings-search-status" role="status" aria-live="polite">{{ $settings->count() }} settings found</p>
        <div class="kfms-empty" data-settings-empty @if ($settings->isNotEmpty()) hidden @endif>No settings match your search. Try another name or clear the search.</div>

        <div class="kfms-settings-grid" id="settings-results">
            @foreach ($allSettings as $setting)
                <a class="kfms-settings-card" href="{{ route('settings.system.index', $setting['slug']) }}" data-live-setting data-setting-title="{{ mb_strtolower($setting['title']) }}" data-setting-search="{{ mb_strtolower($setting['title'].' '.$setting['description']) }}" @if (! $settings->contains('slug', $setting['slug'])) hidden @endif>
                    <span>{{ $setting['title'] }}</span>
                    <strong>{{ $setting['count'] }}</strong>
                    <small>{{ $setting['description'] }}</small>
                </a>
            @endforeach
        </div>
    </section>
@endsection
