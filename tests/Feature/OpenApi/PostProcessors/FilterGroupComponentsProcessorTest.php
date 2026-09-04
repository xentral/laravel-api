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

/**
 * Every `$ref` target reachable inside one schema, as component names.
 *
 * @return list<string>
 */
function referencedComponents(array $schema): array
{
    $names = [];
    array_walk_recursive($schema, function (mixed $value, mixed $key) use (&$names): void {
        if ($key === '$ref' && is_string($value)) {
            $names[] = substr($value, strlen('#/components/schemas/'));
        }
    });

    return $names;
}

function groupComponentName(int $level): string
{
    return $level === 1 ? 'InvoiceFilterGroup' : sprintf('InvoiceFilterGroupDepth%d', $level);
}

it('emits one group component per nesting level, each referring to the next', function () {
    $schemas = generatedSpec()['components']['schemas'];

    for ($level = 1; $level < QueryBuilderRequest::MAX_GROUP_DEPTH; $level++) {
        $group = $schemas[groupComponentName($level)];

        expect($group['required'])->toBe(['op', 'conditions'])
            ->and($group['additionalProperties'])->toBeFalse()
            ->and($group['properties']['op']['enum'])->toBe(['and', 'or'])
            ->and($group['properties']['conditions']['items']['oneOf'])->toBe([
                ['$ref' => '#/components/schemas/InvoiceFilterCondition'],
                ['$ref' => '#/components/schemas/'.groupComponentName($level + 1)],
            ]);
    }
});

it('accepts conditions only at the deepest level', function () {
    $schemas = generatedSpec()['components']['schemas'];
    $deepest = $schemas[groupComponentName(QueryBuilderRequest::MAX_GROUP_DEPTH)];

    expect($deepest['properties']['conditions']['items'])->toBe(['$ref' => '#/components/schemas/InvoiceFilterCondition'])
        ->and($schemas)->not->toHaveKey(groupComponentName(QueryBuilderRequest::MAX_GROUP_DEPTH + 1));
});

it('emits exactly as many group levels as the runtime cap allows', function () {
    $groups = array_filter(
        array_keys(generatedSpec()['components']['schemas']),
        fn (string $name) => str_starts_with($name, 'InvoiceFilterGroup'),
    );

    expect($groups)->toHaveCount(QueryBuilderRequest::MAX_GROUP_DEPTH);
});

it('emits no reference cycle below the parameter', function () {
    // The publish pipeline dereferences the spec into a plain object tree; a
    // cycle anywhere below the parameter makes that tree unserialisable. The
    // walk starts at the component the parameter refers to, so a cycle among
    // unrelated workbench resources does not enter into it.
    $schemas = generatedSpec()['components']['schemas'];
    $edges = array_map(referencedComponents(...), $schemas);

    $onPath = [];
    $done = [];
    $cycles = [];
    $walk = function (string $name, array $path) use (&$walk, &$onPath, &$done, &$cycles, $edges): void {
        if (isset($done[$name])) {
            return;
        }
        if (isset($onPath[$name])) {
            $cycles[] = implode(' -> ', [...$path, $name]);

            return;
        }

        $onPath[$name] = true;
        foreach ($edges[$name] ?? [] as $target) {
            $walk($target, [...$path, $name]);
        }
        unset($onPath[$name]);
        $done[$name] = true;
    };

    $walk('InvoiceFilterGroup', []);

    expect($cycles)->toBe([])
        ->and($done)->toHaveKeys([groupComponentName(QueryBuilderRequest::MAX_GROUP_DEPTH), 'InvoiceFilterCondition']);
});

it('keeps the condition component out of the enum map trap', function () {
    // unique() preserves keys, and a gapped array serialises as a map, which
    // is not a valid enum.
    $condition = generatedSpec()['components']['schemas']['InvoiceFilterCondition'];

    expect(array_is_list($condition['properties']['op']['enum']))->toBeTrue()
        ->and(array_is_list($condition['properties']['key']['enum']))->toBeTrue();
});

it('points the opted in parameter at the top level group component', function () {
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
