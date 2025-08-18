<?php declare(strict_types=1);

return [
    'oas_version' => '3.1.0',
    'ruleset' => null,
    'folders' => [__DIR__.'/../../workbench'],
    'deprecation_filter' => [
        'enabled' => true,
        'months_before_removal' => 6,
    ],
    'name' => 'My API',
    'version' => '1.0.0',
    'description' => 'Developer API',
    'contact' => [
        'name' => 'API Support',
        'url' => 'https://.example.com',
        'email' => 'api@example.com',
    ],
    'servers' => [
        [
            'url' => 'https://.example.com',
            'description' => 'Your API environment',
        ],
    ],
    'exclude_money' => true,
];
