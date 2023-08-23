<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ config('app.name') }} - API Docs</title>

    @if(config('scramble.ui.sri.enabled'))
        <script
            src="https://unpkg.com/@stoplight/elements{{ config('scramble.ui.sri.version') }}/web-components.min.js"
            integrity="{{ config('scramble.ui.sri.hash.script') }}"
            crossorigin="anonymous"></script>
        <link
            rel="stylesheet"
            href="https://unpkg.com/@stoplight/elements{{ config('scramble.ui.sri.version') }}/styles.min.css"
            integrity="{{ config('scramble.ui.sri.hash.style') }}"
            crossorigin="anonymous" />
    @else
        <script src="https://unpkg.com/@stoplight/elements/web-components.min.js"></script>
        <link rel="stylesheet" href="https://unpkg.com/@stoplight/elements/styles.min.css">
    @endif
</head>
<body style="height: 100vh; overflow-y: hidden">
<elements-api
    apiDescriptionUrl="{{ route('scramble.docs.index') }}"
    router="hash"
    @if(config('scramble.ui.hide_try_it')) hideTryIt="true" @endif
    logo="{{ config('scramble.ui.logo') }}"
/>
</body>
</html>
