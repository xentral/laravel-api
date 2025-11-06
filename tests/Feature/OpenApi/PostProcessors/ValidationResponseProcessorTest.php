<?php declare(strict_types=1);

use Illuminate\Validation\Rules\Enum;
use Workbench\App\Http\Requests\CreateTestModelRequest;
use Xentral\LaravelApi\OpenApi\PostProcessors\ValidationResponseProcessor;
use Xentral\LaravelApi\OpenApi\SchemaConfig;

it('can extract validation rules from a form request', function () {
    $request = CreateTestModelRequest::class;

    $config = SchemaConfig::fromArray([
        'oas_version' => '3.1.0',
        'folders' => [base_path('app')],
        'output' => base_path('openapi.yml'),
        'validation_response' => [
            'status_code' => 422,
            'content_type' => 'application/json',
            'max_errors' => 3,
            'content' => [
                'message' => 'The given data was invalid.',
                'errors' => '{{errors}}',
            ],
        ],
        'pagination_response' => [
            'casing' => 'camel',
        ],
        'deprecation_filter' => [
            'enabled' => true,
            'months_before_removal' => 6,
        ],
        'feature_flags' => [
            'description_prefix' => "This endpoint is only available if the feature flag `{flag}` is enabled.\n\n",
        ],
        'validation_commands' => [],
    ]);

    $processor = new ValidationResponseProcessor($config);
    $rules = $processor->extractValidationInfo($request);

    expect($rules)
        ->toHaveKeys(['name', 'status'])
        ->and($rules['status'])
        ->toBeArray()
        ->and($rules['status'][0])
        ->toBeInstanceOf(Enum::class);
});
