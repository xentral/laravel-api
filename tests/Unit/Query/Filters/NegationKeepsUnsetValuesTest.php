<?php declare(strict_types=1);

use Workbench\App\Models\Customer;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\LineItem;
use Xentral\LaravelApi\Query\Filters\DateOperatorFilter;
use Xentral\LaravelApi\Query\Filters\DateTimeOperatorFilter;
use Xentral\LaravelApi\Query\Filters\FilterOperator;
use Xentral\LaravelApi\Query\Filters\NumberOperatorFilter;
use Xentral\LaravelApi\Query\Filters\StringOperatorFilter;

/**
 * A row whose column is NULL is not equal to any given value, so a negation keeps it, while a
 * positive operator or a comparison never matches it.
 */
describe('Number negation', function () {
    it('keeps a row without a value under notEquals', function (int|array $value) {
        $invoice = Invoice::factory()->create();
        $unset = LineItem::factory()->for($invoice)->create(['discount_percent' => null]);
        LineItem::factory()->for($invoice)->create(['discount_percent' => 10]);
        $other = LineItem::factory()->for($invoice)->create(['discount_percent' => 5]);

        $query = LineItem::query();
        (new NumberOperatorFilter('discountPercent'))($query, ['operator' => 'notEquals', 'value' => $value], 'discount_percent');

        expect($query->pluck('id')->sort()->values()->all())->toBe([$unset->id, $other->id]);
    })->with([
        'one value' => [10],
        'a list' => [[10, 15]],
    ]);

    it('does not match a row without a value with a positive operator or a comparison', function (string $operator) {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['discount_percent' => null]);
        $ten = LineItem::factory()->for($invoice)->create(['discount_percent' => 10]);

        $query = LineItem::query();
        (new NumberOperatorFilter('discountPercent'))($query, ['operator' => $operator, 'value' => 10], 'discount_percent');

        expect($query->pluck('id')->all())->toBe([$ten->id]);
    })->with(['equals', 'lessThanOrEquals', 'greaterThanOrEquals']);
});

describe('Date negation', function () {
    it('keeps a row without a date under notEquals', function (string|array $value) {
        $unset = Invoice::factory()->create(['paid_at' => null]);
        Invoice::factory()->create(['paid_at' => '2026-01-15 10:00:00']);
        $other = Invoice::factory()->create(['paid_at' => '2026-01-16 10:00:00']);

        $query = Invoice::query();
        (new DateOperatorFilter('paidAt'))($query, ['operator' => 'notEquals', 'value' => $value], 'paid_at');

        expect($query->pluck('id')->sort()->values()->all())->toBe([$unset->id, $other->id]);
    })->with([
        'one date' => ['2026-01-15'],
        'a list' => [['2026-01-15', '2026-01-20']],
    ]);

    it('does not match a row without a date with equals', function () {
        Invoice::factory()->create(['paid_at' => null]);
        $paid = Invoice::factory()->create(['paid_at' => '2026-01-15 10:00:00']);

        $query = Invoice::query();
        (new DateOperatorFilter('paidAt'))($query, ['operator' => 'equals', 'value' => '2026-01-15'], 'paid_at');

        expect($query->pluck('id')->all())->toBe([$paid->id]);
    });
});

describe('Datetime negation', function () {
    it('keeps a row without a value under notEquals', function (string $value) {
        $unset = Invoice::factory()->create(['issued_at' => null]);
        Invoice::factory()->create(['issued_at' => '2026-02-16 10:00:00']);
        $other = Invoice::factory()->create(['issued_at' => '2026-02-17 10:00:00']);

        $query = Invoice::query();
        (new DateTimeOperatorFilter('issuedAt'))($query, ['operator' => 'notEquals', 'value' => $value], 'issued_at');

        expect($query->pluck('id')->sort()->values()->all())->toBe([$unset->id, $other->id]);
    })->with([
        'a moment' => ['2026-02-16 10:00:00'],
        'a whole day' => ['2026-02-16'],
    ]);

    it('does not match a row without a value with equals', function () {
        Invoice::factory()->create(['issued_at' => null]);
        $issued = Invoice::factory()->create(['issued_at' => '2026-02-16 10:00:00']);

        $query = Invoice::query();
        (new DateTimeOperatorFilter('issuedAt'))($query, ['operator' => 'equals', 'value' => '2026-02-16'], 'issued_at');

        expect($query->pluck('id')->all())->toBe([$issued->id]);
    });
});

describe('Id negation', function () {
    it('keeps a row without a reference under notEquals and notIn', function (string $operator, int|array $value) {
        Customer::factory()->create(['is_archived' => 5]);
        $other = Customer::factory()->create(['is_archived' => 7]);
        $unset = Customer::factory()->create(['is_archived' => null]);

        $query = Customer::query();
        (new StringOperatorFilter(FilterOperator::ID))($query, ['operator' => $operator, 'value' => $value], 'is_archived');

        expect($query->pluck('id')->sort()->values()->all())->toBe([$other->id, $unset->id]);
    })->with([
        'notEquals' => ['notEquals', 5],
        'notIn' => ['notIn', [5]],
    ]);
});

describe('Date negation through a relation', function () {
    it('matches only a parent none of whose related rows is on the date', function (string $filter) {
        $customers = [];
        foreach (['matching' => ['2026-01-15 10:00:00', null], 'other' => ['2026-01-16 10:00:00'], 'unset' => [null], 'none' => []] as $name => $paidAt) {
            $customer = Customer::factory()->create();
            foreach ($paidAt as $moment) {
                Invoice::factory()->for($customer)->create(['paid_at' => $moment]);
            }
            $customers[$name] = $customer->id;
        }

        $query = Customer::query();
        (new $filter('invoices.paidAt'))($query, ['operator' => 'notEquals', 'value' => '2026-01-15'], 'invoices.paid_at');

        expect($query->pluck('id')->sort()->values()->all())
            ->toBe(collect(['other', 'unset', 'none'])->map(fn (string $name) => $customers[$name])->sort()->values()->all());
    })->with([
        'date' => [DateOperatorFilter::class],
        'datetime' => [DateTimeOperatorFilter::class],
    ]);

    it('skips the filter on an empty value, as it does on a column', function (string $filter, string $value) {
        $withInvoice = Customer::factory()->create();
        Invoice::factory()->for($withInvoice)->create(['paid_at' => '2026-01-15 10:00:00']);
        $withoutInvoice = Customer::factory()->create();

        $query = Customer::query();
        (new $filter('invoices.paidAt'))($query, ['operator' => 'notEquals', 'value' => $value], 'invoices.paid_at');

        expect($query->pluck('id')->sort()->values()->all())->toBe([$withInvoice->id, $withoutInvoice->id]);
    })->with([
        'date' => [DateOperatorFilter::class],
        'datetime' => [DateTimeOperatorFilter::class],
    ])->with([
        'empty string' => [''],
        'zero' => ['0'],
    ]);
});

/**
 * The NULL a negation keeps belongs to the condition, not to the whole query: a query already
 * narrowed to one parent must not pick up another parent's unset rows.
 */
describe('Negation on a narrowed query', function () {
    it('keeps only the unset text of the narrowed rows', function () {
        $narrowed = Invoice::factory()->create();
        $unset = LineItem::factory()->for($narrowed)->create(['description' => null]);
        LineItem::factory()->for($narrowed)->create(['description' => 'Gift wrap']);
        LineItem::factory()->for(Invoice::factory()->create())->create(['description' => null]);

        $query = LineItem::query()->where('invoice_id', $narrowed->id);
        (new StringOperatorFilter(FilterOperator::TEXT))($query, ['operator' => 'notEquals', 'value' => 'Gift wrap'], 'description');

        expect($query->pluck('id')->all())->toBe([$unset->id]);
    });

    it('keeps only the unset number of the narrowed rows', function () {
        $narrowed = Invoice::factory()->create();
        $unset = LineItem::factory()->for($narrowed)->create(['discount_percent' => null]);
        LineItem::factory()->for($narrowed)->create(['discount_percent' => 10]);
        LineItem::factory()->for(Invoice::factory()->create())->create(['discount_percent' => null]);

        $query = LineItem::query()->where('invoice_id', $narrowed->id);
        (new NumberOperatorFilter('discountPercent'))($query, ['operator' => 'notEquals', 'value' => 10], 'discount_percent');

        expect($query->pluck('id')->all())->toBe([$unset->id]);
    });

    it('keeps only the unset date of the narrowed rows', function (string $filter) {
        $narrowed = Customer::factory()->create();
        $unset = Invoice::factory()->for($narrowed)->create(['paid_at' => null]);
        Invoice::factory()->for($narrowed)->create(['paid_at' => '2026-01-15 10:00:00']);
        Invoice::factory()->for(Customer::factory()->create())->create(['paid_at' => null]);

        $query = Invoice::query()->where('customer_id', $narrowed->id);
        (new $filter('paidAt'))($query, ['operator' => 'notEquals', 'value' => '2026-01-15'], 'paid_at');

        expect($query->pluck('id')->all())->toBe([$unset->id]);
    })->with([
        'date' => [DateOperatorFilter::class],
        'datetime' => [DateTimeOperatorFilter::class],
    ]);
});

/**
 * An unset text is stored as NULL or as '', and isNull reads both as unset. So every operator reads
 * them alike too: a NULL row matches exactly when the '' row does.
 */
describe('Empty text and NULL alike', function () {
    it('matches a NULL text exactly when it matches an empty one', function (string $operator, string|array $value, array $expected) {
        $invoice = Invoice::factory()->create();
        $rows = [
            'set' => LineItem::factory()->for($invoice)->create(['description' => 'Gift wrap']),
            'empty' => LineItem::factory()->for($invoice)->create(['description' => '']),
            'null' => LineItem::factory()->for($invoice)->create(['description' => null]),
        ];

        $query = LineItem::query();
        (new StringOperatorFilter(FilterOperator::TEXT))($query, ['operator' => $operator, 'value' => $value], 'description');

        expect($query->pluck('id')->sort()->values()->all())
            ->toBe(collect($expected)->map(fn (string $name) => $rows[$name]->id)->sort()->values()->all());
    })->with([
        'equals a value' => ['equals', 'Gift wrap', ['set']],
        'equals the empty text' => ['equals', '', ['empty', 'null']],
        'notEquals a value' => ['notEquals', 'Gift wrap', ['empty', 'null']],
        'notEquals the empty text' => ['notEquals', '', ['set']],
        'in, the empty text among the values' => ['in', ['', 'Gift wrap'], ['set', 'empty', 'null']],
        'notIn, the empty text among the values' => ['notIn', ['', 'Engraving'], ['set']],
        'notIn without the empty text' => ['notIn', ['Engraving'], ['set', 'empty', 'null']],
        'contains the empty text' => ['contains', '', ['set', 'empty', 'null']],
        'notContains a value' => ['notContains', 'wrap', ['empty', 'null']],
        'notContains the empty text' => ['notContains', '', []],
        'startsWith the empty text' => ['startsWith', '', ['set', 'empty', 'null']],
        'endsWith a value' => ['endsWith', 'wrap', ['set']],
        'endsWith the empty text' => ['endsWith', '', ['set', 'empty', 'null']],
    ]);
});
