<?php declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Invoice;
use Xentral\LaravelApi\Query\Filters\DateOperatorFilter;
use Xentral\LaravelApi\Query\Filters\DateTimeOperatorFilter;

/**
 * An empty value skips the filter, but only once the operator has passed: a request that names an
 * operator the key does not support is rejected whether or not it carries a value, on a column and
 * through a relation alike.
 */
dataset('date filters', [
    'date' => [DateOperatorFilter::class],
    'datetime' => [DateTimeOperatorFilter::class],
]);

dataset('rejected operators', [
    'unknown operator' => ['bogus'],
    'operator outside the allowed set' => ['contains'],
]);

describe('Empty value with a rejected operator', function () {
    it('rejects it on a column, keyed on the filter name', function (string $filter, string $operator) {
        Invoice::factory()->create();

        try {
            (new $filter('issuedAt'))(Invoice::query(), ['operator' => $operator, 'value' => ''], 'issued_at');
        } catch (ValidationException $e) {
            expect(array_keys($e->errors()))->toBe(['issuedAt'])
                ->and($e->errors()['issuedAt'][0])->toStartWith("Unsupported filter operator: {$operator}.");

            return;
        }

        $this->fail('Expected a ValidationException');
    })->with('date filters')->with('rejected operators');

    it('rejects it through a relation, keyed on the filter name', function (string $filter, string $operator) {
        Invoice::factory()->for(Customer::factory())->create();

        try {
            (new $filter('invoices.paidAt'))(Customer::query(), ['operator' => $operator, 'value' => ''], 'invoices.paid_at');
        } catch (ValidationException $e) {
            expect(array_keys($e->errors()))->toBe(['invoices.paidAt'])
                ->and($e->errors()['invoices.paidAt'][0])->toStartWith("Unsupported filter operator: {$operator}.");

            return;
        }

        $this->fail('Expected a ValidationException');
    })->with('date filters')->with('rejected operators');
});

describe('Empty value with an allowed operator', function () {
    it('still skips the filter on a column', function (string $filter) {
        Invoice::factory()->count(2)->create();

        $query = Invoice::query();
        (new $filter('issuedAt'))($query, ['operator' => 'notEquals', 'value' => ''], 'issued_at');

        expect($query->count())->toBe(2);
    })->with('date filters');

    it('still skips the filter through a relation', function (string $filter) {
        Invoice::factory()->for(Customer::factory())->create();
        Customer::factory()->create();

        $query = Customer::query();
        (new $filter('invoices.paidAt'))($query, ['operator' => 'notEquals', 'value' => ''], 'invoices.paid_at');

        expect($query->count())->toBe(2);
    })->with('date filters');
});
