<?php declare(strict_types=1);

use Illuminate\Validation\Rules\Enum;
use Workbench\App\Http\Requests\CreateTestModelRequest;
use Xentral\LaravelApi\OpenApi\PostProcessors\ValidationResponseProcessor;

it('can extract validation rules from a form request', function () {
    $request = CreateTestModelRequest::class;

    $processor = new ValidationResponseProcessor;
    $rules = $processor->extractValidationInfo($request);

    expect($rules)
        ->toHaveKeys(['name', 'status'])
        ->and($rules['status'])
        ->toBeArray()
        ->and($rules['status'][0])
        ->toBeInstanceOf(Enum::class);
});
