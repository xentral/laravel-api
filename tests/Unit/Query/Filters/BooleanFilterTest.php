<?php declare(strict_types=1);

use Workbench\App\Models\Customer;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\LineItem;
use Xentral\LaravelApi\Query\Filters\BooleanIntegerFilter;
use Xentral\LaravelApi\Query\Filters\QueryFilter;

/**
 * A flag is matched the way the payload shows it, which casts NULL to false.
 */
describe('Boolean filter', function () {
    it('reads a NULL flag as false, the way the payload casts it', function (string $operator, string $value, array $expected) {
        $rows = [
            'unset' => Customer::factory()->create(['is_archived' => null]),
            'off' => Customer::factory()->create(['is_archived' => 0]),
            'on' => Customer::factory()->create(['is_archived' => 1]),
            'legacy on' => Customer::factory()->create(['is_archived' => 2]),
        ];

        $query = Customer::query();
        QueryFilter::boolean('isArchived', 'is_archived')->getFilterClass()($query, ['operator' => $operator, 'value' => $value], 'is_archived');

        expect($query->pluck('id')->sort()->values()->all())
            ->toBe(collect($expected)->map(fn (string $key) => $rows[$key]->id)->sort()->values()->all());
    })->with([
        'equals false' => ['equals', 'false', ['unset', 'off']],
        'notEquals true' => ['notEquals', 'true', ['unset', 'off']],
        'equals true' => ['equals', 'true', ['on', 'legacy on']],
        'notEquals false' => ['notEquals', 'false', ['on', 'legacy on']],
    ]);

    it('filters the related row for a dotted key on a relation', function () {
        $active = Customer::factory()->create(['is_active' => true]);
        $inactive = Customer::factory()->create(['is_active' => false]);
        $activeInvoice = Invoice::factory()->for($active)->create();
        Invoice::factory()->for($inactive)->create();

        $query = Invoice::query();
        (new BooleanIntegerFilter('customer.isActive'))($query, ['operator' => 'equals', 'value' => 'true'], 'customer.is_active');

        expect($query->pluck('id')->all())->toBe([$activeInvoice->id]);
    });

    it('matches notEquals through a relation only when no related row is truthy', function () {
        $invoices = [];
        foreach (['mixed' => [3, 0], 'off' => [0], 'none' => []] as $name => $quantities) {
            $invoice = Invoice::factory()->create();
            foreach ($quantities as $quantity) {
                LineItem::factory()->for($invoice)->create(['quantity' => $quantity]);
            }
            $invoices[$name] = $invoice->id;
        }

        $query = Invoice::query();
        (new BooleanIntegerFilter('lineItems.quantity'))($query, ['operator' => 'notEquals', 'value' => 'true'], 'lineItems.quantity');

        expect($query->pluck('id')->sort()->values()->all())->toBe([$invoices['off'], $invoices['none']]);
    });

    it('keeps the internal name the boolean helper is given', function () {
        expect(QueryFilter::boolean('isArchived', 'is_archived')->getInternalName())->toBe('is_archived');
    });
});
