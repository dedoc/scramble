<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Elements in HTML</title>

    <script src="https://unpkg.com/@stoplight/elements/web-components.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@stoplight/elements/styles.min.css">
</head>
<body style="height: 100vh; overflow-y: hidden">
<elements-api
    apiDescriptionUrl="{{ route('documentor.docs.index') }}"
{{--    apiDescriptionUrl="https://gist.githubusercontent.com/romalytvynenko/0536cc3e96ff51347c7b713c60bd0889/raw/14f9313c2aa954751df337aea59021fa53db5cd4/openapi2.yaml"--}}
    router="hash"
/>
</body>
</html>
