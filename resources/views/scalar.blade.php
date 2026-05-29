<!doctype html>
<html lang="en" data-theme="{{ $config->get('ui.theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="color-scheme" content="{{ $config->get('ui.theme', 'light') }}">
    <title>{{ $config->get('ui.title') ?? config('app.name') . ' - API Docs' }}</title>
</head>
<body>
<div id="app"></div>
<!-- Load the Script -->
<script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>

<!-- Initialize the API Reference -->
<script>
    Scalar.createApiReference('#app', {
        content: @json($spec),
        proxyUrl: 'https://proxy.scalar.com',
        showDeveloperTools: 'never',
        agent: {
            disabled: true,
        }
    })
</script>
</body>
</html>
