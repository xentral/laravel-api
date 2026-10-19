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
