<?php declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Workbench\App\Enum\InvoiceStatusEnum;
use Workbench\App\Models\Invoice;
use Xentral\LaravelApi\Query\Filters\DateOperatorFilter;
use Xentral\LaravelApi\Query\Filters\DateTimeOperatorFilter;
use Xentral\LaravelApi\Query\Filters\FilterOperator;
use Xentral\LaravelApi\Query\Filters\IdCallbackFilter;
use Xentral\LaravelApi\Query\Filters\IdFilterTarget;
use Xentral\LaravelApi\Query\Filters\QueryFilter;
use Xentral\LaravelApi\Query\Filters\StringOperatorFilter;

/**
 * A violation names the key the client sent, not the column the filter reads. spatie hands a
 * filter its internal name as $property, so a filter carries its public name to key on it.
 */
function violationKeysOf(callable $filter, array $value, string $property): array
{
    try {
        $filter(Invoice::query(), $value, $property);
    } catch (ValidationException $e) {
        return array_keys($e->errors());
    }

    test()->fail('Expected a ValidationException');
}

function lineItemIdCallback(): Closure
{
    return fn (Builder $query, array $ids) => $query->whereHas('lineItems', fn (Builder $lineItems) => $lineItems->whereIn('line_items.id', $ids));
}

describe('StringOperatorFilter violation keys', function () {
    it('keys an unknown operator on the public filter name', function () {
        $filter = new StringOperatorFilter(FilterOperator::TEXT, null, 'documentNumber');

        expect(violationKeysOf($filter, ['operator' => 'bogus', 'value' => 'x'], 'belegnr'))->toBe(['documentNumber']);
    });

    it('keys an operator outside the allowed set on the public filter name', function () {
        $filter = new StringOperatorFilter(FilterOperator::TEXT, null, 'documentNumber');

        expect(violationKeysOf($filter, ['operator' => 'lessThan', 'value' => 'x'], 'belegnr'))->toBe(['documentNumber']);
    });

    it('keys an invalid enum value on the public filter name', function () {
        $filter = new StringOperatorFilter(FilterOperator::ENUM, InvoiceStatusEnum::class, 'status');

        expect(violationKeysOf($filter, ['operator' => 'equals', 'value' => 'bogus'], 'status_code'))->toBe(['status']);
    });

    it('keys on the public filter name when the key filters through a relation', function () {
        $filter = new StringOperatorFilter(FilterOperator::TEXT, null, 'customerName');

        expect(violationKeysOf($filter, ['operator' => 'bogus', 'value' => 'x'], 'customer.name'))->toBe(['customerName']);
    });

    it('falls back to the column when no public name is given', function () {
        $filter = new StringOperatorFilter(FilterOperator::TEXT);

        expect(violationKeysOf($filter, ['operator' => 'bogus', 'value' => 'x'], 'belegnr'))->toBe(['belegnr']);
    });
});

describe('DateOperatorFilter violation keys', function () {
    it('keys an unsupported operator on the public filter name', function () {
        $filter = new DateOperatorFilter('issuedAt');

        expect(violationKeysOf($filter, ['operator' => 'contains', 'value' => '2026-01-01'], 'issued_at'))->toBe(['issuedAt']);
    });
});

describe('DateTimeOperatorFilter violation keys', function () {
    it('keys an unsupported operator on the public filter name', function () {
        $filter = new DateTimeOperatorFilter('paidAt');

        expect(violationKeysOf($filter, ['operator' => 'contains', 'value' => '2026-01-01T00:00:00+00:00'], 'paid_at'))->toBe(['paidAt']);
    });
});

describe('IdCallbackFilter violation keys', function () {
    it('keys an unsupported operator on the public filter name', function () {
        $filter = new IdCallbackFilter(lineItemIdCallback(), IdFilterTarget::Relation, 'lineItemId');

        expect(violationKeysOf($filter, ['operator' => 'contains', 'value' => [1]], 'lineItem.id'))->toBe(['lineItemId']);
    });

    it('keys an invalid value on the public filter name', function () {
        $filter = new IdCallbackFilter(lineItemIdCallback(), IdFilterTarget::Relation, 'lineItemId');

        expect(violationKeysOf($filter, ['operator' => 'equals', 'value' => 'abc'], 'lineItem.id'))->toBe(['lineItemId']);
    });
});

describe('QueryFilter hands the public name to the filter', function () {
    it('keys a violation on the public name of a factory-built filter', function (callable $factory, array $value) {
        $filter = $factory()->getFilterClass();

        expect(violationKeysOf($filter, $value, 'belegnr'))->toBe(['documentNumber']);
    })->with([
        'string' => [fn () => QueryFilter::string('documentNumber', 'belegnr'), ['operator' => 'bogus', 'value' => 'x']],
        'identifier' => [fn () => QueryFilter::identifier('documentNumber', 'belegnr'), ['operator' => 'bogus', 'value' => '1']],
        'enum' => [fn () => QueryFilter::enum('documentNumber', InvoiceStatusEnum::class, 'belegnr'), ['operator' => 'equals', 'value' => 'bogus']],
        'make' => [fn () => (new QueryFilter('documentNumber'))->make('documentNumber', FilterOperator::TEXT, 'belegnr'), ['operator' => 'bogus', 'value' => 'x']],
        'identifierCallback' => [fn () => QueryFilter::identifierCallback('documentNumber', lineItemIdCallback(), IdFilterTarget::Relation), ['operator' => 'contains', 'value' => [1]]],
    ]);
});
