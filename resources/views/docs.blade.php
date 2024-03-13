<!doctype html>
<html lang="en" data-theme="{{ config('scramble.theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ config('app.name') }} - API Docs</title>

    <script src="https://unpkg.com/@stoplight/elements/web-components.min.js"></script>

    <link rel="stylesheet" href="https://unpkg.com/@stoplight/elements/styles.min.css">
    <script>
        const originalFetch = window.fetch;

        // intercept TryIt requests and add the XSRF-TOKEN header,
        // which is necessary for Sanctum cookie-based authentication to work correctly
        window.fetch = (url, options) => {
            const CSRF_TOKEN_COOKIE_KEY = "XSRF-TOKEN";
            const CSRF_TOKEN_HEADER_KEY = "X-XSRF-TOKEN";
            const getCookieValue = (key) => {
                const cookie = document.cookie.split(';').find((cookie) => cookie.trim().startsWith(key));
                return cookie?.split("=")[1];
            };

            const updateFetchHeaders = (
                headers,
                headerKey,
                headerValue,
            ) => {
                if (headers instanceof Headers) {
                    headers.set(headerKey, headerValue);
                } else if (Array.isArray(headers)) {
                    headers.push([headerKey, headerValue]);
                } else if (headers) {
                    headers[headerKey] = headerValue;
                }
            };
            const csrfToken = getCookieValue(CSRF_TOKEN_COOKIE_KEY);
            if (csrfToken) {
                const { headers = new Headers() } = options || {};
                updateFetchHeaders(headers, CSRF_TOKEN_HEADER_KEY, unescape(csrfToken));
                return originalFetch(url, {
                    ...options,
                    headers,
                });
            }

            return originalFetch(url, options);
        };
    </script>
</head>
<body style="height: 100vh; overflow-y: hidden">
<elements-api
    apiDescriptionUrl="{{ route('scramble.docs.index') }}"
    tryItCredentialsPolicy="{{ config('scramble.ui.try_it_credentials_policy', 'include') }}"
    router="hash"
    @if(config('scramble.ui.hide_try_it')) hideTryIt="true" @endif
    logo="{{ config('scramble.ui.logo') }}"
/>
</body>
</html>
