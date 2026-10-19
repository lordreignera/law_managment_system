@php
    $module = \Illuminate\Support\Str::before(request()->route()?->getName() ?? '', '.');
    $candidates = array_merge(match ($module) {
        'settings' => ['settings.system.overview'],
        'access' => ['access.users.index'],
        default => [$module.'.dashboard', $module.'.index'],
    }, ['dashboard']);
    $homeRoute = collect($candidates)->first(fn ($name) => Route::has($name) && ! request()->routeIs($name) && auth()->user()?->can($name));
    $backUrl = $homeRoute ? route($homeRoute) : route('dashboard');
    $previousUrl = url()->previous();
    $previousPath = parse_url($previousUrl, PHP_URL_PATH) ?: '/';
    if (str_starts_with($previousUrl, request()->getSchemeAndHttpHost().'/')
        && $previousUrl !== request()->fullUrl() && $previousPath !== '/'
        && ! \Illuminate\Support\Str::startsWith($previousPath, ['/login', '/register', '/logout', '/forgot-password', '/reset-password', '/two-factor', '/client-portal', '/access-pending'])) {
        $backUrl = $previousUrl;
    }
@endphp
@unless (request()->routeIs('dashboard', '*.dashboard'))
    <nav class="kfms-workspace-navigation" aria-label="Workspace navigation">
        <a class="kfms-link-btn" href="{{ $backUrl }}" data-workspace-back><i class="mdi mdi-arrow-left" aria-hidden="true"></i> Back</a>
        <div><a href="{{ route('dashboard') }}">Dashboard</a><span aria-hidden="true">/</span><span>@yield('title', 'Workspace')</span></div>
    </nav>
@endunless
