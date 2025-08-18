<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <link rel="stylesheet" type="text/css" href="/api-docs/assets/swagger-ui.css">
</head>

<body>
<div id="swagger-ui"></div>

<script src="/api-docs/assets/swagger-ui-bundle.js"></script>
<script src="/api-docs/assets/swagger-ui-standalone-preset.js"></script>
<script>
    window.onload = function () {
        // Build a system
        const ui = SwaggerUIBundle({
            dom_id: '#swagger-ui',
            url:  '{{$url}}',

            requestInterceptor: function (request) {
                request.headers['X-CSRF-TOKEN'] = '{{ csrf_token() }}';
                return request;
            },

            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset
            ],

            plugins: [
                SwaggerUIBundle.plugins.DownloadUrl
            ],

            // layout: "StandaloneLayout",
            deepLinking: true,
        })

        window.ui = ui
    }
</script>
</body>
</html>
