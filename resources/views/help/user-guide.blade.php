@extends('layouts.admin')

@section('title', 'User Guide')
@section('page-title', 'User Guide')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $guideTitle }}</h2>
                <span>{{ $guideSubtitle }}</span>
            </div>
            <div class="kfms-button-row">
                <a class="kfms-link-btn" href="{{ route('help.user-guide.download') }}">
                    <i class="mdi mdi-download"></i>
                    Download PDF
                </a>
                <a class="kfms-link-btn" href="{{ $dashboardUrl }}">
                    <i class="mdi mdi-view-dashboard-outline"></i>
                    My Dashboard
                </a>
            </div>
        </div>

        <div class="kfms-guide-grid">
            @foreach ($guideModules as $module)
                <a href="#{{ $module['sections'][0]['id'] ?? 'first-steps' }}">
                    <i class="mdi {{ $module['icon'] }}"></i>
                    <strong>{{ $module['title'] }}</strong>
                    <span>{{ $module['subtitle'] }}</span>
                </a>
            @endforeach
        </div>
    </section>

    @foreach ($guideSections as $section)
        <section class="kfms-panel" id="{{ $section['id'] }}">
            <div class="kfms-panel-header">
                <div>
                    <h2>{{ $section['title'] }}</h2>
                    <span>{{ $section['subtitle'] }}</span>
                </div>
            </div>
            @if (! empty($section['screen']))
                <div class="kfms-guide-section-shot">
                    <figure class="kfms-guide-preview-image">
                        <img
                            src="{{ asset($section['screen']['image']) }}"
                            alt="{{ $section['screen']['title'] }} screenshot"
                            loading="lazy"
                        >
                    </figure>
                    <div>
                        <h3>{{ $section['screen']['title'] }}</h3>
                        <p>{{ $section['screen']['description'] }}</p>
                    </div>
                    <div class="kfms-guide-preview-kpis">
                        @foreach ($section['screen']['stats'] as $stat)
                            <span>{{ $stat }}</span>
                        @endforeach
                    </div>
                    @if (isset($section['route']) && auth()->user()?->can($section['route_permission'] ?? $section['route']))
                        <a class="kfms-link-btn" href="{{ route($section['route']) }}">
                            Open {{ $section['screen']['title'] }}
                            <i class="mdi mdi-arrow-right"></i>
                        </a>
                    @endif
                </div>
            @endif
            <ol class="kfms-guide-list">
                @foreach ($section['steps'] as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
        </section>
    @endforeach
@endsection
