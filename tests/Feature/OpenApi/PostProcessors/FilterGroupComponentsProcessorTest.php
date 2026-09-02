<?php declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Xentral\LaravelApi\Http\QueryBuilderRequest;
use Xentral\LaravelApi\OpenApi\Filters\FilterParameter;
use Xentral\LaravelApi\OpenApi\Filters\StringFilter;
use Xentral\LaravelApi\OpenApi\OpenApiGeneratorFactory;

function generatedSpec(): array
{
    $generator = (new OpenApiGeneratorFactory)->create(config('openapi.schemas.default'));

    return Yaml::parse($generator->generate([workbench_dir()])->toYaml());
}

it('emits a group component that refers to itself', function () {
    $schemas = generatedSpec()['components']['schemas'];

    expect($schemas)->toHaveKeys(['InvoiceFilterCondition', 'InvoiceFilterGroup']);

    $group = $schemas['InvoiceFilterGroup'];

    expect($group['required'])->toBe(['op', 'conditions'])
        ->and($group['additionalProperties'])->toBeFalse()
        ->and($group['properties']['op']['enum'])->toBe(['and', 'or'])
        ->and($group['properties']['conditions']['items']['oneOf'])->toBe([
            ['$ref' => '#/components/schemas/InvoiceFilterCondition'],
            ['$ref' => '#/components/schemas/InvoiceFilterGroup'],
        ]);
});

it('keeps the condition component out of the enum map trap', function () {
    // unique() preserves keys, and a gapped array serialises as a map, which
    // is not a valid enum.
    $condition = generatedSpec()['components']['schemas']['InvoiceFilterCondition'];

    expect(array_is_list($condition['properties']['op']['enum']))->toBeTrue()
        ->and(array_is_list($condition['properties']['key']['enum']))->toBeTrue();
});

it('points the opted in parameter at the group component', function () {
    $parameters = generatedSpec()['paths']['/api/v1/invoices']['get']['parameters'];
    $filter = collect($parameters)->firstWhere('name', 'filter');

    expect($filter['schema']['oneOf'][1])->toBe(['$ref' => '#/components/schemas/InvoiceFilterGroup'])
        ->and($filter['description'])->toContain(
            sprintf('nesting to at most %d levels', QueryBuilderRequest::MAX_GROUP_DEPTH)
        );
});

it('leaves a parameter without a group schema name inline', function () {
    // The customers list opts into neither, so its filter stays a plain array.
    $parameters = generatedSpec()['paths']['/api/v1/customers']['get']['parameters'];
    $filter = collect($parameters)->firstWhere('name', 'filter');

    expect($filter['schema']['type'])->toBe('array')
        ->and($filter['schema'])->not->toHaveKey('oneOf')
        ->and($filter['description'])->not->toContain('group');
});

it('rejects a group schema name without the or group opt in', function () {
    new FilterParameter([new StringFilter(name: 'name')], groupSchemaName: 'Nope');
})->throws(InvalidArgumentException::class, 'groupSchemaName is only meaningful together with supportsOrGroups: true.');
