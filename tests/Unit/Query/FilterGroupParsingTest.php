<?php declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Xentral\LaravelApi\Http\QueryBuilderRequest;
use Xentral\LaravelApi\Query\Filters\FilterGroupOperator;

function filterRequest(array|string $filter): QueryBuilderRequest
{
    return QueryBuilderRequest::fromRequest(Request::create('/products', 'GET', ['filter' => $filter]));
}

it('parses a json string or group', function () {
    $request = filterRequest(json_encode([
        'op' => 'or',
        'conditions' => [
            ['key' => 'name', 'op' => 'contains', 'value' => 'screw'],
            ['key' => 'manufacturer', 'op' => 'equals', 'value' => 'Acme'],
        ],
    ]));

    $group = $request->filterGroup();

    expect($group)->not->toBeNull()
        ->and($group->operator)->toBe(FilterGroupOperator::Or)
        ->and($group->conditions)->toHaveCount(2)
        ->and($group->conditions[0])->toBe(['key' => 'name', 'filter' => ['operator' => 'contains', 'value' => 'screw']]);
});

it('parses a deep object group', function () {
    $request = filterRequest([
        'op' => 'or',
        'conditions' => [
            ['key' => 'name', 'op' => 'contains', 'value' => 'screw'],
            ['key' => 'id', 'op' => 'in', 'value' => ['1', '2']],
        ],
    ]);

    expect($request->filterGroup())->not->toBeNull()
        ->and($request->filterGroup()->operator)->toBe(FilterGroupOperator::Or)
        ->and($request->filterGroup()->conditions[1])
        ->toBe(['key' => 'id', 'filter' => ['operator' => 'in', 'value' => ['1', '2']]]);
});

it('parses an and group', function () {
    expect(filterRequest(['op' => 'and', 'conditions' => [['key' => 'id', 'op' => 'equals', 'value' => '5']]])->filterGroup()->operator)
        ->toBe(FilterGroupOperator::And);
});

it('returns null for the flat triple form and for no filter at all', function () {
    expect(filterRequest([['key' => 'id', 'op' => 'equals', 'value' => '5']])->filterGroup())->toBeNull()
        ->and(QueryBuilderRequest::fromRequest(Request::create('/products'))->filterGroup())->toBeNull();
});

it('returns null for the single filter object shorthand', function () {
    expect(filterRequest(json_encode(['key' => 'id', 'op' => 'equals', 'value' => '5']))->filterGroup())->toBeNull()
        ->and(filterRequest(json_encode(['key' => 'id', 'op' => 'equals', 'value' => '5']))->filters()->toArray())
        ->toBe(['id' => ['operator' => 'equals', 'value' => '5']]);
});

it('keeps a string keyed flat filter list out of the group path', function () {
    // Degenerate but pre-existing: a flat list whose keys happen to be strings,
    // one of them `conditions`. It was a conjunction before groups existed and
    // has to stay one, which is what the `key` guard on the group shape is for.
    $request = filterRequest([
        'key' => ['key' => 'name', 'op' => 'contains', 'value' => 'a'],
        'conditions' => ['key' => 'id', 'op' => 'equals', 'value' => '5'],
    ]);

    expect($request->filterGroup())->toBeNull()
        ->and($request->filters()->toArray())->toBe([
            'name' => ['operator' => 'contains', 'value' => 'a'],
            'id' => ['operator' => 'equals', 'value' => '5'],
        ]);
});

it('collapses group conditions into the filters collection like the flat form', function () {
    // Three repeats of one key, so the second merge appends to an existing
    // list rather than creating one.
    $conditions = [
        ['key' => 'name', 'op' => 'contains', 'value' => 'a'],
        ['key' => 'name', 'op' => 'contains', 'value' => 'b'],
        ['key' => 'name', 'op' => 'contains', 'value' => 'c'],
        ['key' => 'id', 'op' => 'equals', 'value' => '5'],
    ];

    $flat = filterRequest($conditions);
    $group = filterRequest(['op' => 'and', 'conditions' => $conditions]);

    expect($group->filters()->toArray())
        ->toBe($flat->filters()->toArray())
        ->toBe([
            'name' => [
                ['operator' => 'contains', 'value' => 'a'],
                ['operator' => 'contains', 'value' => 'b'],
                ['operator' => 'contains', 'value' => 'c'],
            ],
            'id' => ['operator' => 'equals', 'value' => '5'],
        ]);
});

it('rejects a nested group', function () {
    filterRequest([
        'op' => 'or',
        'conditions' => [
            ['op' => 'and', 'conditions' => [['key' => 'id', 'op' => 'equals', 'value' => '1']]],
        ],
    ])->filterGroup();
})->throws(ValidationException::class, 'Nested filter groups are not supported.');

it('rejects an unknown group operator', function () {
    filterRequest(['op' => 'xor', 'conditions' => [['key' => 'id', 'op' => 'equals', 'value' => '1']]])->filterGroup();
})->throws(ValidationException::class, 'Invalid filter group operator: xor. Valid operators are and, or.');

it('rejects a missing group operator', function () {
    filterRequest(['conditions' => [['key' => 'id', 'op' => 'equals', 'value' => '1']]])->filterGroup();
})->throws(ValidationException::class, 'A filter group requires an operator. Valid operators are and, or.');

it('rejects a null group operator like a missing one', function () {
    filterRequest(['op' => null, 'conditions' => [['key' => 'id', 'op' => 'equals', 'value' => '1']]])->filterGroup();
})->throws(ValidationException::class, 'Invalid filter group operator: NULL. Valid operators are and, or.');

it('rejects a non scalar group operator', function () {
    filterRequest(['op' => ['or'], 'conditions' => [['key' => 'id', 'op' => 'equals', 'value' => '1']]])->filterGroup();
})->throws(ValidationException::class, 'Invalid filter group operator: array. Valid operators are and, or.');

it('rejects an empty conditions list', function () {
    filterRequest(['op' => 'or', 'conditions' => []])->filterGroup();
})->throws(ValidationException::class, 'A filter group requires at least one condition.');

it('rejects a non list conditions value', function () {
    filterRequest(['op' => 'or', 'conditions' => ['a' => ['key' => 'id', 'op' => 'equals', 'value' => '1']]])->filterGroup();
})->throws(ValidationException::class, 'Invalid filter format.');

it('rejects a condition without a key', function () {
    filterRequest(['op' => 'or', 'conditions' => [['op' => 'equals', 'value' => '1']]])->filterGroup();
})->throws(ValidationException::class, 'Invalid filter format.');

it('rejects a condition without an operator', function () {
    filterRequest(['op' => 'or', 'conditions' => [['key' => 'id', 'value' => '1']]])->filterGroup();
})->throws(ValidationException::class, 'Invalid filter format.');

it('rejects unknown keys on the group object', function () {
    filterRequest(['op' => 'or', 'conditions' => [['key' => 'id', 'op' => 'equals', 'value' => '1']], 'foo' => 'bar'])->filterGroup();
})->throws(ValidationException::class, 'Invalid filter format.');

it('reports an invalid filter format for a decoded scalar filter', function () {
    filterRequest('5')->filters();
})->throws(ValidationException::class, 'Invalid filter format.');

it('ignores an undecodable filter string', function () {
    expect(filterRequest('not json')->filters()->toArray())->toBe([])
        ->and(filterRequest('not json')->filterGroup())->toBeNull();
});
