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
            <a class="kfms-link-btn" href="{{ $dashboardUrl }}">
                <i class="mdi mdi-view-dashboard-outline"></i>
                My Dashboard
            </a>
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

    @if ($guideModules->contains(fn (array $module) => ! empty($module['screen'])))
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Dashboard Screenshots</h2>
                    <span>Visual reference for the workspace linked to your role.</span>
                </div>
            </div>
            <div class="kfms-guide-preview-grid">
                @foreach ($guideModules as $module)
                    @continue(empty($module['screen']))
                    <article class="kfms-guide-preview-card">
                        <figure class="kfms-guide-preview-image">
                            <img
                                src="{{ asset($module['screen']['image']) }}"
                                alt="{{ $module['screen']['title'] }} screenshot"
                                loading="lazy"
                            >
                        </figure>
                        <div>
                            <h3>{{ $module['screen']['title'] }}</h3>
                            <p>{{ $module['screen']['description'] }}</p>
                        </div>
                        <div class="kfms-guide-preview-kpis">
                            @foreach ($module['screen']['stats'] as $stat)
                                <span>{{ $stat }}</span>
                            @endforeach
                        </div>
                        @if (isset($module['route']) && auth()->user()?->can($module['route_permission'] ?? $module['route']))
                            <a class="kfms-link-btn" href="{{ route($module['route']) }}">
                                Open {{ $module['screen']['title'] }}
                                <i class="mdi mdi-arrow-right"></i>
                            </a>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @foreach ($guideSections as $section)
        <section class="kfms-panel" id="{{ $section['id'] }}">
            <div class="kfms-panel-header">
                <div>
                    <h2>{{ $section['title'] }}</h2>
                    <span>{{ $section['subtitle'] }}</span>
                </div>
            </div>
            <ol class="kfms-guide-list">
                @foreach ($section['steps'] as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
        </section>
    @endforeach
@endsection
