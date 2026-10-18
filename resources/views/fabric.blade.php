<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ $config->get('ui.title') ?? config('app.name') . ' - API Docs' }}</title>
    <link rel="stylesheet" href="https://main.fabric-preview.pages.dev/fabric.css">
    <style>
        body {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
    </style>
    @include('scramble::dev-tools', ['renderer' => 'fabric'])
</head>
<body>
<div id="fabric"></div>
<script src="https://main.fabric-preview.pages.dev/fabric.js"></script>

<script>
    const specificationString = @json(json_encode($spec));

    const viewer = Fabric.init('#fabric', {
        ...@json($config->renderer()->all()),
        openApiDocument: specificationString,
    });
</script>
</body>
</html>
