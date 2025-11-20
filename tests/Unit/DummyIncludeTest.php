<?php declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedInclude;
use Xentral\LaravelApi\Query\DummyInclude;

it('returns an allowed include', function () {
    $include = DummyInclude::make('foo')->first();

    expect($include)
        ->toBeInstanceOf(AllowedInclude::class)
        ->and($include->getName())->toBe('foo')
        ->and($include->isForInclude('foo'))->toBeTrue();
});

it('is invokable and does nothing', function () {
    $include = new DummyInclude;

    expect(method_exists($include, '__invoke'))->toBeTrue();

    $include($this->createMock(Builder::class), 'foo');
});

it('returns a single include for simple names without dots', function () {
    $includes = DummyInclude::make('foo');

    expect($includes)->toHaveCount(1)
        ->and($includes->first())->toBeInstanceOf(AllowedInclude::class)
        ->and($includes->first()->getName())->toBe('foo');
});

it('returns parent relationship and dummy include for nested names', function () {
    $includes = DummyInclude::make('lineItems.customFields');

    $names = $includes->map(fn ($include) => $include->getName());

    // AllowedInclude::relationship() returns the relationship plus count and exists variants
    // So we expect at least 2 items (the parent relationship and the dummy include)
    expect($includes->count())->toBeGreaterThanOrEqual(2)
        ->and($names)->toContain('lineItems')
        ->and($names)->toContain('lineItems.customFields');
});

it('returns parent relationship and dummy include for multi-level nested names', function () {
    $includes = DummyInclude::make('customer.addresses.metadata');

    $names = $includes->map(fn ($include) => $include->getName());

    // Should include both the parent relationships and the dummy include
    expect($names)->toContain('customer')
        ->and($names)->toContain('customer.addresses')
        ->and($names)->toContain('customer.addresses.metadata');
});
