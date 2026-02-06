<?php declare(strict_types=1);

use Workbench\App\Models\Customer;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\LineItem;
use Xentral\LaravelApi\Query\Filters\FilterOperator;
use Xentral\LaravelApi\Query\Filters\StringOperatorFilter;

describe('StringOperatorFilter Relation Filtering', function () {
    describe('notIn operator on relations', function () {
        it('includes records with no related records when using notIn filter', function () {
            // Create invoice WITH lineItems that have product_name = 'Widget' (should be EXCLUDED)
            $invoiceWithWidget = Invoice::factory()->create();
            LineItem::factory()->for($invoiceWithWidget)->create(['product_name' => 'Widget']);

            // Create invoice WITH lineItems that have product_name = 'Gadget' (should be INCLUDED)
            $invoiceWithGadget = Invoice::factory()->create();
            LineItem::factory()->for($invoiceWithGadget)->create(['product_name' => 'Gadget']);

            // Create invoice WITHOUT any lineItems (should be INCLUDED - this is the key test case)
            $invoiceWithoutLineItems = Invoice::factory()->create();

            $filter = new StringOperatorFilter([FilterOperator::NOT_IN]);

            $query = Invoice::query();
            $filter($query, ['operator' => 'notIn', 'value' => ['Widget']], 'lineItems.product_name');

            $results = $query->get();

            // Should include invoice with Gadget and invoice without line items
            // Should exclude invoice with Widget
            expect($results->pluck('id')->toArray())
                ->toContain($invoiceWithGadget->id)
                ->toContain($invoiceWithoutLineItems->id)
                ->not->toContain($invoiceWithWidget->id);

            expect($results)->toHaveCount(2);
        });

        it('excludes records with matching related records using notIn filter', function () {
            $customer1 = Customer::factory()->create(['name' => 'Acme Corp']);
            $customer2 = Customer::factory()->create(['name' => 'Beta Inc']);

            $invoiceAcme = Invoice::factory()->for($customer1)->create();
            $invoiceBeta = Invoice::factory()->for($customer2)->create();

            $filter = new StringOperatorFilter([FilterOperator::NOT_IN]);

            $query = Invoice::query();
            $filter($query, ['operator' => 'notIn', 'value' => ['Acme Corp']], 'customer.name');

            $results = $query->get();

            expect($results->pluck('id')->toArray())
                ->toContain($invoiceBeta->id)
                ->not->toContain($invoiceAcme->id);
        });

        it('includes records with no related records when using notEquals filter', function () {
            // Create invoice WITH lineItems that have product_name = 'Widget' (should be EXCLUDED)
            $invoiceWithWidget = Invoice::factory()->create();
            LineItem::factory()->for($invoiceWithWidget)->create(['product_name' => 'Widget']);

            // Create invoice WITH lineItems that have product_name = 'Gadget' (should be INCLUDED)
            $invoiceWithGadget = Invoice::factory()->create();
            LineItem::factory()->for($invoiceWithGadget)->create(['product_name' => 'Gadget']);

            // Create invoice WITHOUT any lineItems (should be INCLUDED)
            $invoiceWithoutLineItems = Invoice::factory()->create();

            $filter = new StringOperatorFilter([FilterOperator::NOT_EQUALS]);

            $query = Invoice::query();
            $filter($query, ['operator' => 'notEquals', 'value' => 'Widget'], 'lineItems.product_name');

            $results = $query->get();

            expect($results->pluck('id')->toArray())
                ->toContain($invoiceWithGadget->id)
                ->toContain($invoiceWithoutLineItems->id)
                ->not->toContain($invoiceWithWidget->id);
        });

        it('includes records with no related records when using notContains filter', function () {
            // Create invoice WITH lineItems that have product_name containing 'Widget' (should be EXCLUDED)
            $invoiceWithWidget = Invoice::factory()->create();
            LineItem::factory()->for($invoiceWithWidget)->create(['product_name' => 'Super Widget Pro']);

            // Create invoice WITH lineItems that have product_name = 'Gadget' (should be INCLUDED)
            $invoiceWithGadget = Invoice::factory()->create();
            LineItem::factory()->for($invoiceWithGadget)->create(['product_name' => 'Gadget']);

            // Create invoice WITHOUT any lineItems (should be INCLUDED)
            $invoiceWithoutLineItems = Invoice::factory()->create();

            $filter = new StringOperatorFilter([FilterOperator::NOT_CONTAINS]);

            $query = Invoice::query();
            $filter($query, ['operator' => 'notContains', 'value' => 'Widget'], 'lineItems.product_name');

            $results = $query->get();

            expect($results->pluck('id')->toArray())
                ->toContain($invoiceWithGadget->id)
                ->toContain($invoiceWithoutLineItems->id)
                ->not->toContain($invoiceWithWidget->id);
        });
    });

    describe('equals operator with array values on relations (AND logic)', function () {
        it('requires ALL values to match when using equals with array on relations', function () {
            // Create invoice with two line items (Widget AND Gadget) - should be INCLUDED
            $invoiceWithBoth = Invoice::factory()->create();
            LineItem::factory()->for($invoiceWithBoth)->create(['product_name' => 'Widget']);
            LineItem::factory()->for($invoiceWithBoth)->create(['product_name' => 'Gadget']);

            // Create invoice with only Widget - should be EXCLUDED
            $invoiceWithOnlyWidget = Invoice::factory()->create();
            LineItem::factory()->for($invoiceWithOnlyWidget)->create(['product_name' => 'Widget']);

            // Create invoice with only Gadget - should be EXCLUDED
            $invoiceWithOnlyGadget = Invoice::factory()->create();
            LineItem::factory()->for($invoiceWithOnlyGadget)->create(['product_name' => 'Gadget']);

            // Create invoice without any line items - should be EXCLUDED
            $invoiceWithoutLineItems = Invoice::factory()->create();

            $filter = new StringOperatorFilter([FilterOperator::EQUALS]);

            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => ['Widget', 'Gadget']], 'lineItems.product_name');

            $results = $query->get();

            // Should only include invoice that has BOTH Widget AND Gadget
            expect($results->pluck('id')->toArray())
                ->toContain($invoiceWithBoth->id)
                ->not->toContain($invoiceWithOnlyWidget->id)
                ->not->toContain($invoiceWithOnlyGadget->id)
                ->not->toContain($invoiceWithoutLineItems->id);

            expect($results)->toHaveCount(1);
        });

        it('still works with single value for equals on relations', function () {
            $invoiceWithWidget = Invoice::factory()->create();
            LineItem::factory()->for($invoiceWithWidget)->create(['product_name' => 'Widget']);

            $invoiceWithGadget = Invoice::factory()->create();
            LineItem::factory()->for($invoiceWithGadget)->create(['product_name' => 'Gadget']);

            $filter = new StringOperatorFilter([FilterOperator::EQUALS]);

            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => 'Widget'], 'lineItems.product_name');

            $results = $query->get();

            expect($results->pluck('id')->toArray())
                ->toContain($invoiceWithWidget->id)
                ->not->toContain($invoiceWithGadget->id);

            expect($results)->toHaveCount(1);
        });
    });

    describe('positive operators on relations (should still work)', function () {
        it('uses whereHas for in operator on relations', function () {
            $invoiceWithWidget = Invoice::factory()->create();
            LineItem::factory()->for($invoiceWithWidget)->create(['product_name' => 'Widget']);

            $invoiceWithGadget = Invoice::factory()->create();
            LineItem::factory()->for($invoiceWithGadget)->create(['product_name' => 'Gadget']);

            $invoiceWithoutLineItems = Invoice::factory()->create();

            $filter = new StringOperatorFilter([FilterOperator::IN]);

            $query = Invoice::query();
            $filter($query, ['operator' => 'in', 'value' => ['Widget']], 'lineItems.product_name');

            $results = $query->get();

            // Should only include invoice with Widget
            expect($results->pluck('id')->toArray())
                ->toContain($invoiceWithWidget->id)
                ->not->toContain($invoiceWithGadget->id)
                ->not->toContain($invoiceWithoutLineItems->id);

            expect($results)->toHaveCount(1);
        });

        it('uses whereHas for equals operator on relations', function () {
            $customer1 = Customer::factory()->create(['name' => 'Acme Corp']);
            $customer2 = Customer::factory()->create(['name' => 'Beta Inc']);

            $invoiceAcme = Invoice::factory()->for($customer1)->create();
            $invoiceBeta = Invoice::factory()->for($customer2)->create();

            $filter = new StringOperatorFilter([FilterOperator::EQUALS]);

            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => 'Acme Corp'], 'customer.name');

            $results = $query->get();

            expect($results->pluck('id')->toArray())
                ->toContain($invoiceAcme->id)
                ->not->toContain($invoiceBeta->id);
        });
    });
});
