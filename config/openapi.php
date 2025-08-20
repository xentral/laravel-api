<?php declare(strict_types=1);

return [
    'docs' => [
        'enabled' => env('APP_ENV') !== 'production',
        'prefix' => 'api-docs',
        'middleware' => ['web', 'auth'],
    ],
    'schemas' => [
        'default' => [
            'config' => [
                'oas_version' => '3.1.0',
                'folders' => [base_path('app')],
                'output' => base_path('openapi.yml'),
                'pagination_response' => [
                    'casing' => 'snake',
                ],
                'validation_response' => [
                    'status_code' => 422,
                    'content_type' => 'application/json',
                    'max_errors' => 3,
                    'content' => [
                        'message' => 'The given data was invalid.',
                        'errors' => '{{errors}}',
                    ],
                ],
                'deprecation_filter' => [
                    'enabled' => true,
                    'months_before_removal' => 6,
                ],
                'feature_flags' => [
                    'description_prefix' => "This endpoint is only available if the feature flag `{flag}` is enabled.\n\n",
                ],
                'validation_commands' => [],
            ],
            'info' => [
                'name' => 'My API',
                'version' => '1.0.0',
                'description' => 'Developer API',
                'contact' => [
                    'name' => 'API Support',
                    'url' => env('APP_URL', 'https://.example.com'),
                    'email' => env('MAIL_FROM_ADDRESS', 'api@example.com'),
                ],
                'servers' => [
                    [
                        'url' => env('APP_URL', 'https://.example.com'),
                        'description' => 'Your API environment',
                    ],
                ],
            ],
        ],
    ],
];
