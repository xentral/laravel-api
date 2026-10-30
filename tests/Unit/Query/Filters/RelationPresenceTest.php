<?php declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\LineItem;
use Xentral\LaravelApi\Query\Filters\DateOperatorFilter;
use Xentral\LaravelApi\Query\Filters\DateTimeOperatorFilter;
use Xentral\LaravelApi\Query\Filters\FilterOperator;
use Xentral\LaravelApi\Query\Filters\NumberOperatorFilter;
use Xentral\LaravelApi\Query\Filters\StringOperatorFilter;

/**
 * On a relation key isNull and isNotNull describe the parent: isNotNull holds when some related
 * row carries a value, isNull when none does. So every parent answers exactly one of the two, a
 * parent with one set and one unset row answers isNotNull only, and a parent without related
 * rows answers isNull.
 */
dataset('presence operators', [
    'isNull: no related row carries a value' => ['isNull', ['unset', 'none']],
    'isNotNull: some related row carries a value' => ['isNotNull', ['mixed', 'set']],
]);

/**
 * A legacy zero date is not a date, so a parent whose related rows hold only zero dates answers
 * isNull like one whose rows are NULL.
 */
dataset('date presence operators', [
    'isNull: no related row carries a date' => ['isNull', ['unset', 'zero', 'none']],
    'isNotNull: some related row carries a date' => ['isNotNull', ['mixed', 'set']],
]);

/**
 * @param  array<string, list<string|null>>  $paidAt  per customer name, one invoice per entry
 * @return array<string, int> customer id per name
 */
function customersWithPaidInvoices(array $paidAt): array
{
    $customers = [];
    foreach ($paidAt as $name => $moments) {
        $customer = Customer::factory()->create();
        foreach ($moments as $moment) {
            $invoice = Invoice::factory()->for($customer)->create();
            // the model's datetime cast cannot hold a legacy zero date, so it is written raw
            DB::table('invoices')->where('id', $invoice->id)->update(['paid_at' => $moment]);
        }
        $customers[$name] = $customer->id;
    }

    return $customers;
}

describe('String relation presence', function () {
    it('asks whether any line item carries a value', function (string $operator, array $expected) {
        $invoices = [];
        foreach (['mixed' => ['Gift wrap', null], 'set' => ['Gift wrap', 'Engraving'], 'unset' => [null, ''], 'none' => []] as $name => $descriptions) {
            $invoice = Invoice::factory()->create();
            foreach ($descriptions as $description) {
                LineItem::factory()->for($invoice)->create(['description' => $description]);
            }
            $invoices[$name] = $invoice->id;
        }

        $query = Invoice::query();
        (new StringOperatorFilter(FilterOperator::TEXT))($query, ['operator' => $operator], 'lineItems.description');

        expect($query->pluck('id')->sort()->values()->all())
            ->toBe(collect($expected)->map(fn (string $name) => $invoices[$name])->sort()->values()->all());
    })->with('presence operators');
});

describe('Number relation presence', function () {
    it('asks whether any line item carries a value', function (string $operator, array $expected) {
        $invoices = [];
        foreach (['mixed' => [10, null], 'set' => [10, 5], 'unset' => [null, null], 'none' => []] as $name => $discounts) {
            $invoice = Invoice::factory()->create();
            foreach ($discounts as $discount) {
                LineItem::factory()->for($invoice)->create(['discount_percent' => $discount]);
            }
            $invoices[$name] = $invoice->id;
        }

        $query = Invoice::query();
        (new NumberOperatorFilter('lineItems.discountPercent'))($query, ['operator' => $operator], 'lineItems.discount_percent');

        expect($query->pluck('id')->sort()->values()->all())
            ->toBe(collect($expected)->map(fn (string $name) => $invoices[$name])->sort()->values()->all());
    })->with('presence operators');
});

describe('Date relation presence', function () {
    it('asks whether any invoice carries a date', function (string $operator, array $expected) {
        $customers = customersWithPaidInvoices(['mixed' => ['2026-01-15 10:00:00', null], 'set' => ['2026-01-15 10:00:00', '2026-01-16 10:00:00'], 'unset' => [null, null], 'zero' => ['0000-00-00 00:00:00', '0000-00-00'], 'none' => []]);

        $query = Customer::query();
        (new DateOperatorFilter('invoices.paidAt'))($query, ['operator' => $operator], 'invoices.paid_at');

        expect($query->pluck('id')->sort()->values()->all())
            ->toBe(collect($expected)->map(fn (string $name) => $customers[$name])->sort()->values()->all());
    })->with('date presence operators');
});

describe('DateTime relation presence', function () {
    it('asks whether any invoice carries a moment', function (string $operator, array $expected) {
        $customers = customersWithPaidInvoices(['mixed' => ['2026-01-15 10:00:00', null], 'set' => ['2026-01-15 10:00:00', '2026-01-16 10:00:00'], 'unset' => [null, null], 'zero' => ['0000-00-00 00:00:00', '0000-00-00'], 'none' => []]);

        $query = Customer::query();
        (new DateTimeOperatorFilter('invoices.paidAt'))($query, ['operator' => $operator], 'invoices.paid_at');

        expect($query->pluck('id')->sort()->values()->all())
            ->toBe(collect($expected)->map(fn (string $name) => $customers[$name])->sort()->values()->all());
    })->with('date presence operators');
});
