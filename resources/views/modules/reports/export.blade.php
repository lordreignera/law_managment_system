<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #071426; font-size: 10px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        p { color: #526173; margin: 0 0 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #071426; color: #fff; text-align: left; }
        th, td { border: 1px solid #cbd7e5; padding: 6px 7px; }
        tr:nth-child(even) td { background: #f4f7fa; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>{{ $periodLabel }} · Generated {{ $generatedAt->format('d M Y H:i') }}</p>
    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ is_numeric($cell) ? number_format((float) $cell, 2) : $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">No records matched the selected period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
