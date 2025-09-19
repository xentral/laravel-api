<?php declare(strict_types=1);

return [
    'docs' => [
        'enabled' => true,
        'prefix' => 'api-docs',
        'middleware' => ['web'],
        'client' => 'scalar',
    ],
    'schemas' => [
        'default' => [
            'config' => [
                'oas_version' => '3.1.0',
                'folders' => [dirname(__DIR__).'/app'],
                'output' => dirname(__DIR__).'/openapi.yml',
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
                'rate_limit_response' => [
                    'enabled' => true,
                    'message' => 'Too Many Requests',
                ],
                'validation_commands' => [],
            ],
            'info' => [
                'name' => 'My API',
                'version' => '1.0.0',
                'description' => 'Developer API',
                'contact' => [
                    'name' => 'API Support',
                    'url' => 'https://example.com',
                    'email' => 'api@example.com',
                ],
                'servers' => [
                    [
                        'url' => 'https://example.com',
                        'description' => 'Your API environment',
                    ],
                ],
            ],
        ],
    ],
];
