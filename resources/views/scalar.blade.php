<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
        }
    </style>
</head>

<body>
<script
    id="api-reference"
    data-url="{{ $url }}"
    data-configuration='{
        "theme": "default",
        "layout": "modern",
        "defaultHttpClient": {
            "targetKey": "javascript",
            "clientKey": "fetch"
        },
        "authentication": {
            "preferredSecurityScheme": "Authorization",
            "http": {
                "bearer": {
                    "token": "{{ csrf_token() }}"
                }
            }
        },
        "servers": [
            {
                "url": "{{ config('app.url') }}",
                "description": "Current Environment"
            }
        ]
    }'></script>
<script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference@latest"></script>
</body>
</html>