<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Präsentation' }}</title>
    <style>
        html, body { margin: 0; padding: 0; background: #111418; color: #e8e6e1; font-family: system-ui, sans-serif; height: 100%; }
    </style>
</head>
<body>
    {{ $slot }}
</body>
</html>
