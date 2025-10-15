<?php declare(strict_types=1);

use Xentral\LaravelApi\OpenApi\Filters\EnumFilter;
use Xentral\LaravelApi\Query\Filters\FilterOperator;

enum TestStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
}

it('creates enum filter with BackedEnum class', function () {
    $filter = new EnumFilter(
        name: 'status',
        enumSource: TestStatus::class,
    );

    expect($filter->name)->toBe('status')
        ->and($filter->type)->toBe('string')
        ->and($filter->enum)->toBe(['active', 'inactive', 'pending'])
        ->and($filter->operators)->toBe([
            FilterOperator::EQUALS,
            FilterOperator::NOT_EQUALS,
            FilterOperator::IN,
            FilterOperator::NOT_IN,
        ]);
});

it('creates enum filter with array of values', function () {
    $filter = new EnumFilter(
        name: 'category',
        enumSource: ['electronics', 'clothing', 'food'],
    );

    expect($filter->name)->toBe('category')
        ->and($filter->type)->toBe('string')
        ->and($filter->enum)->toBe(['electronics', 'clothing', 'food'])
        ->and($filter->operators)->toBe([
            FilterOperator::EQUALS,
            FilterOperator::NOT_EQUALS,
            FilterOperator::IN,
            FilterOperator::NOT_IN,
        ]);
});

it('allows custom operators', function () {
    $filter = new EnumFilter(
        name: 'status',
        enumSource: TestStatus::class,
        operators: [FilterOperator::EQUALS],
    );

    expect($filter->operators)->toBe([FilterOperator::EQUALS]);
});

it('always uses string type', function () {
    $filter = new EnumFilter(
        name: 'status',
        enumSource: ['1', '2', '3'],
    );

    expect($filter->type)->toBe('string');
});

it('throws exception for invalid enum class', function () {
    new EnumFilter(
        name: 'invalid',
        enumSource: 'NonExistentClass',
    );
})->throws(\InvalidArgumentException::class);

it('generates correct OpenAPI property for multiple values', function () {
    $filter = new EnumFilter(
        name: 'status',
        enumSource: TestStatus::class,
    );

    $property = $filter->toProperty();

    expect($property->property)->toBe('status')
        ->and($property->type)->toBe('array')
        ->and($property->items->type)->toBe('string')
        ->and($property->items->enum)->toBe(['active', 'inactive', 'pending']);
});

it('generates correct OpenAPI property for single value', function () {
    $filter = new EnumFilter(
        name: 'status',
        enumSource: TestStatus::class,
    );

    // Set multiple to false via reflection since it's a parent property
    $reflection = new \ReflectionProperty($filter, 'multiple');
    $reflection->setValue($filter, false);

    $property = $filter->toProperty();

    expect($property->property)->toBe('status')
        ->and($property->type)->toBe('string')
        ->and($property->enum)->toBe(['active', 'inactive', 'pending']);
});
