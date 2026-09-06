<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $guideTitle }}</title>
    <style>
        body {
            color: #071426;
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #d5a33a;
            display: table;
            margin-bottom: 22px;
            padding-bottom: 16px;
            width: 100%;
        }

        .brand,
        .meta {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }

        .brand img {
            max-height: 74px;
            max-width: 190px;
        }

        .brand strong {
            display: block;
            font-size: 18px;
            font-weight: 800;
            margin-top: 8px;
        }

        .brand span,
        .meta {
            color: #51627a;
        }

        .meta {
            font-size: 11px;
            text-align: right;
        }

        h1 {
            font-size: 25px;
            margin: 0 0 6px;
        }

        h2 {
            border-bottom: 1px solid #d8e2ef;
            font-size: 17px;
            margin: 24px 0 10px;
            padding-bottom: 7px;
        }

        h3 {
            font-size: 14px;
            margin: 0 0 6px;
        }

        p {
            color: #51627a;
            line-height: 1.5;
            margin: 0 0 10px;
        }

        .module {
            border: 1px solid #d8e2ef;
            margin-bottom: 14px;
            padding: 12px;
            page-break-inside: avoid;
        }

        .module img {
            border: 1px solid #d8e2ef;
            display: block;
            margin: 10px 0 12px;
            max-width: 100%;
            width: 100%;
        }

        .stats {
            display: table;
            margin-top: 8px;
            table-layout: fixed;
            width: 100%;
        }

        .stats span {
            background: #eef5fb;
            border: 1px solid #d8e2ef;
            display: table-cell;
            font-size: 11px;
            font-weight: 800;
            padding: 8px;
        }

        .workspace-list {
            margin-bottom: 18px;
        }

        .workspace-list span {
            border: 1px solid #d8e2ef;
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            margin: 0 6px 7px 0;
            padding: 7px 9px;
        }

        ol {
            margin: 0;
            padding-left: 20px;
        }

        li {
            line-height: 1.55;
            margin-bottom: 7px;
        }

        .footer {
            border-top: 1px solid #d8e2ef;
            color: #64748b;
            font-size: 10px;
            margin-top: 28px;
            padding-top: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="brand">
            @if ($logoSource)
                <img src="{{ $logoSource }}" alt="{{ $company->company_name }} logo">
            @endif
            <strong>{{ $company->company_name }}</strong>
            <span>{{ $company->tagline ?: $company->short_name }}</span>
        </div>
        <div class="meta">
            Generated {{ $generatedAt->format('d M Y, H:i') }}<br>
            Prepared for {{ auth()->user()?->name ?: auth()->user()?->email }}<br>
            {{ $company->short_name }}
        </div>
    </header>

    <h1>{{ $guideTitle }}</h1>
    <p>{{ $guideSubtitle }}</p>

    <h2>Available Workspaces</h2>
    <div class="workspace-list">
        @foreach ($guideModules as $module)
            <span>{{ $module['title'] }}</span>
        @endforeach
    </div>

    <h2>How It Works</h2>
    @foreach ($guideSections as $section)
        <section class="module">
            <h3>{{ $section['title'] }}</h3>
            <p>{{ $section['subtitle'] }}</p>
            @if (! empty($section['screen']) && file_exists($section['screen']['image_path']))
                <img src="{{ $section['screen']['image_path'] }}" alt="{{ $section['screen']['title'] }} screenshot">
                <p>{{ $section['screen']['description'] }}</p>
                <div class="stats">
                    @foreach ($section['screen']['stats'] as $stat)
                        <span>{{ $stat }}</span>
                    @endforeach
                </div>
            @endif
            <ol>
                @foreach ($section['steps'] as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
        </section>
    @endforeach

    <div class="footer">
        Copyright &copy; {{ $generatedAt->format('Y') }} {{ $company->company_name }}. All rights reserved.
    </div>
</body>
</html>
