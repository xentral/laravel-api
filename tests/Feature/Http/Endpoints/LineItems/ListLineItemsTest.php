<?php declare(strict_types=1);

use Workbench\App\Models\Invoice;
use Workbench\App\Models\LineItem;

describe('Basic List Operations', function () {
    it('can list line items for an invoice', function () {
        $invoice = Invoice::factory()->hasLineItems(3)->create();

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'invoice_id',
                    'product_name',
                    'description',
                    'quantity',
                    'unit_price',
                    'discount_percent',
                    'total_price',
                ],
            ],
        ]);

        // Verify all line items belong to the invoice
        $lineItems = $response->json('data');
        foreach ($lineItems as $lineItem) {
            expect($lineItem['invoice_id'])->toBe($invoice->id);
        }
    });

    it('returns 404 when invoice not found', function () {
        $response = $this->getJson('/api/v1/invoices/99999/line-items');

        $response->assertNotFound();
    });

    it('only returns line items for specified invoice (isolation test)', function () {
        $invoice1 = Invoice::factory()->hasLineItems(2)->create();
        $invoice2 = Invoice::factory()->hasLineItems(3)->create();

        $response = $this->getJson("/api/v1/invoices/{$invoice1->id}/line-items");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        // Verify all returned line items belong to invoice1
        $lineItems = $response->json('data');
        foreach ($lineItems as $lineItem) {
            expect($lineItem['invoice_id'])->toBe($invoice1->id);
        }
    });

    it('pagination works correctly', function () {
        $invoice = Invoice::factory()->hasLineItems(15)->create();

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items");

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'from', 'path', 'per_page', 'to'],
        ]);
    });

    it('returns empty array when invoice has no line items', function () {
        $invoice = Invoice::factory()->create();

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items");

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    });
});

describe('LineItem ID Filters', function () {
    it('can filter line items by id equals', function () {
        $invoice = Invoice::factory()->hasLineItems(3)->create();
        $targetLineItem = $invoice->lineItems->first();

        $query = buildFilterQuery([[
            'key' => 'id',
            'op' => 'equals',
            'value' => $targetLineItem->id,
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $targetLineItem->id);
    });

    it('can filter line items by id not equals', function () {
        $invoice = Invoice::factory()->hasLineItems(3)->create();
        $excludedLineItem = $invoice->lineItems->first();

        $query = buildFilterQuery([[
            'key' => 'id',
            'op' => 'notEquals',
            'value' => $excludedLineItem->id,
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $lineItemIds = collect($response->json('data'))->pluck('id')->toArray();
        expect($lineItemIds)->not->toContain($excludedLineItem->id);
    });

    it('can filter line items by id in', function () {
        $invoice = Invoice::factory()->hasLineItems(5)->create();
        $targetIds = $invoice->lineItems->take(2)->pluck('id')->toArray();

        $query = buildFilterQuery([[
            'key' => 'id',
            'op' => 'in',
            'value' => $targetIds,
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $lineItemIds = collect($response->json('data'))->pluck('id')->toArray();
        expect($lineItemIds)->toBe($targetIds);
    });

    it('can filter line items by id not in', function () {
        $invoice = Invoice::factory()->hasLineItems(5)->create();
        $excludedIds = $invoice->lineItems->take(2)->pluck('id')->toArray();

        $query = buildFilterQuery([[
            'key' => 'id',
            'op' => 'notIn',
            'value' => $excludedIds,
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        $lineItemIds = collect($response->json('data'))->pluck('id')->toArray();
        foreach ($excludedIds as $excludedId) {
            expect($lineItemIds)->not->toContain($excludedId);
        }
    });
});

describe('LineItem String Filters', function () {
    it('can filter line items by product_name equals', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['product_name' => 'Premium Widget']);
        LineItem::factory()->for($invoice)->count(2)->create();

        $query = buildFilterQuery([[
            'key' => 'product_name',
            'op' => 'equals',
            'value' => 'Premium Widget',
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.product_name', 'Premium Widget');
    });

    it('can filter line items by product_name not equals', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['product_name' => 'Premium Widget']);
        LineItem::factory()->for($invoice)->create(['product_name' => 'Basic Widget']);
        LineItem::factory()->for($invoice)->create(['product_name' => 'Super Widget']);

        $query = buildFilterQuery([[
            'key' => 'product_name',
            'op' => 'notEquals',
            'value' => 'Premium Widget',
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $productNames = collect($response->json('data'))->pluck('product_name')->toArray();
        expect($productNames)->not->toContain('Premium Widget');
    });

    it('can filter line items by product_name contains', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['product_name' => 'Premium Widget']);
        LineItem::factory()->for($invoice)->create(['product_name' => 'Super Premium Item']);
        LineItem::factory()->for($invoice)->create(['product_name' => 'Basic Widget']);

        $query = buildFilterQuery([[
            'key' => 'product_name',
            'op' => 'contains',
            'value' => 'Premium',
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $productNames = collect($response->json('data'))->pluck('product_name')->toArray();
        foreach ($productNames as $name) {
            expect($name)->toContain('Premium');
        }
    });

    it('can filter line items by product_name starts with', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['product_name' => 'Widget Premium']);
        LineItem::factory()->for($invoice)->create(['product_name' => 'Widget Super']);
        LineItem::factory()->for($invoice)->create(['product_name' => 'Item Basic']);

        $query = buildFilterQuery([[
            'key' => 'product_name',
            'op' => 'startsWith',
            'value' => 'Widget',
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $productNames = collect($response->json('data'))->pluck('product_name')->toArray();
        foreach ($productNames as $name) {
            expect($name)->toStartWith('Widget');
        }
    });

    it('can filter line items by product_name ends with', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['product_name' => 'Premium Widget']);
        LineItem::factory()->for($invoice)->create(['product_name' => 'Basic Widget']);
        LineItem::factory()->for($invoice)->create(['product_name' => 'Super Item']);

        $query = buildFilterQuery([[
            'key' => 'product_name',
            'op' => 'endsWith',
            'value' => 'Widget',
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $productNames = collect($response->json('data'))->pluck('product_name')->toArray();
        foreach ($productNames as $name) {
            expect($name)->toEndWith('Widget');
        }
    });
});

describe('LineItem Number Filters', function () {
    it('can filter line items by quantity equals', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['quantity' => 5]);
        LineItem::factory()->for($invoice)->create(['quantity' => 10]);
        LineItem::factory()->for($invoice)->create(['quantity' => 15]);

        $query = buildFilterQuery([[
            'key' => 'quantity',
            'op' => 'equals',
            'value' => 10,
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.quantity', 10);
    });

    it('can filter line items by quantity greater than', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['quantity' => 5]);
        LineItem::factory()->for($invoice)->create(['quantity' => 10]);
        LineItem::factory()->for($invoice)->create(['quantity' => 15]);

        $query = buildFilterQuery([[
            'key' => 'quantity',
            'op' => 'greaterThan',
            'value' => 7,
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $quantities = collect($response->json('data'))->pluck('quantity')->toArray();
        foreach ($quantities as $quantity) {
            expect($quantity)->toBeGreaterThan(7);
        }
    });

    it('can filter line items by quantity less than', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['quantity' => 5]);
        LineItem::factory()->for($invoice)->create(['quantity' => 10]);
        LineItem::factory()->for($invoice)->create(['quantity' => 15]);

        $query = buildFilterQuery([[
            'key' => 'quantity',
            'op' => 'lessThan',
            'value' => 12,
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $quantities = collect($response->json('data'))->pluck('quantity')->toArray();
        foreach ($quantities as $quantity) {
            expect($quantity)->toBeLessThan(12);
        }
    });

    it('can filter line items by unit_price equals', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['unit_price' => 10.50]);
        LineItem::factory()->for($invoice)->create(['unit_price' => 20.00]);
        LineItem::factory()->for($invoice)->create(['unit_price' => 30.75]);

        $query = buildFilterQuery([[
            'key' => 'unit_price',
            'op' => 'equals',
            'value' => 20.00,
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        expect($response->json('data.0.unit_price'))->toBe(20);
    });

    it('can filter line items by unit_price greater than', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['unit_price' => 10.50]);
        LineItem::factory()->for($invoice)->create(['unit_price' => 20.00]);
        LineItem::factory()->for($invoice)->create(['unit_price' => 30.75]);

        $query = buildFilterQuery([[
            'key' => 'unit_price',
            'op' => 'greaterThan',
            'value' => 15.00,
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $prices = collect($response->json('data'))->pluck('unit_price')->toArray();
        foreach ($prices as $price) {
            expect($price)->toBeGreaterThan(15.00);
        }
    });

    it('can filter line items by unit_price less than', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['unit_price' => 10.50]);
        LineItem::factory()->for($invoice)->create(['unit_price' => 20.00]);
        LineItem::factory()->for($invoice)->create(['unit_price' => 30.75]);

        $query = buildFilterQuery([[
            'key' => 'unit_price',
            'op' => 'lessThan',
            'value' => 25.00,
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $prices = collect($response->json('data'))->pluck('unit_price')->toArray();
        foreach ($prices as $price) {
            expect($price)->toBeLessThan(25.00);
        }
    });

    it('can filter line items by total_price equals', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['total_price' => 100.00]);
        LineItem::factory()->for($invoice)->create(['total_price' => 200.00]);
        LineItem::factory()->for($invoice)->create(['total_price' => 300.00]);

        $query = buildFilterQuery([[
            'key' => 'total_price',
            'op' => 'equals',
            'value' => 200.00,
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        expect($response->json('data.0.total_price'))->toBe(200);
    });

    it('can filter line items by total_price greater than', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['total_price' => 100.00]);
        LineItem::factory()->for($invoice)->create(['total_price' => 200.00]);
        LineItem::factory()->for($invoice)->create(['total_price' => 300.00]);

        $query = buildFilterQuery([[
            'key' => 'total_price',
            'op' => 'greaterThan',
            'value' => 150.00,
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $prices = collect($response->json('data'))->pluck('total_price')->toArray();
        foreach ($prices as $price) {
            expect($price)->toBeGreaterThan(150.00);
        }
    });

    it('can filter line items by total_price less than', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['total_price' => 100.00]);
        LineItem::factory()->for($invoice)->create(['total_price' => 200.00]);
        LineItem::factory()->for($invoice)->create(['total_price' => 300.00]);

        $query = buildFilterQuery([[
            'key' => 'total_price',
            'op' => 'lessThan',
            'value' => 250.00,
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $prices = collect($response->json('data'))->pluck('total_price')->toArray();
        foreach ($prices as $price) {
            expect($price)->toBeLessThan(250.00);
        }
    });
});

describe('LineItem Multiple Filter Combinations', function () {
    it('can combine product_name and quantity filters', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['product_name' => 'Widget', 'quantity' => 10]);
        LineItem::factory()->for($invoice)->create(['product_name' => 'Widget', 'quantity' => 5]);
        LineItem::factory()->for($invoice)->create(['product_name' => 'Gadget', 'quantity' => 10]);

        $query = buildFilterQuery([
            [
                'key' => 'product_name',
                'op' => 'equals',
                'value' => 'Widget',
            ],
            [
                'key' => 'quantity',
                'op' => 'equals',
                'value' => 10,
            ],
        ]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.product_name', 'Widget');
        $response->assertJsonPath('data.0.quantity', 10);
    });

    it('can combine multiple numeric filters (quantity + unit_price)', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['quantity' => 10, 'unit_price' => 50.00]);
        LineItem::factory()->for($invoice)->create(['quantity' => 5, 'unit_price' => 50.00]);
        LineItem::factory()->for($invoice)->create(['quantity' => 10, 'unit_price' => 30.00]);

        $query = buildFilterQuery([
            [
                'key' => 'quantity',
                'op' => 'greaterThanOrEquals',
                'value' => 10,
            ],
            [
                'key' => 'unit_price',
                'op' => 'greaterThan',
                'value' => 40.00,
            ],
        ]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.quantity', 10);
        expect($response->json('data.0.unit_price'))->toBe(50);
    });

    it('can combine string and numeric filters', function () {
        $invoice = Invoice::factory()->create();
        LineItem::factory()->for($invoice)->create(['product_name' => 'Premium Widget', 'total_price' => 500.00]);
        LineItem::factory()->for($invoice)->create(['product_name' => 'Basic Widget', 'total_price' => 100.00]);
        LineItem::factory()->for($invoice)->create(['product_name' => 'Premium Gadget', 'total_price' => 200.00]);

        $query = buildFilterQuery([
            [
                'key' => 'product_name',
                'op' => 'contains',
                'value' => 'Premium',
            ],
            [
                'key' => 'total_price',
                'op' => 'greaterThan',
                'value' => 300.00,
            ],
        ]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.product_name', 'Premium Widget');
        expect($response->json('data.0.total_price'))->toBe(500);
    });
});

describe('LineItem Edge Cases', function () {
    it('returns empty result when no line items match filter', function () {
        $invoice = Invoice::factory()->hasLineItems(3)->create();

        $query = buildFilterQuery([[
            'key' => 'product_name',
            'op' => 'equals',
            'value' => 'Non-existent Product',
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    });

    it('filters only apply to line items of specified invoice', function () {
        $invoice1 = Invoice::factory()->create();
        $invoice2 = Invoice::factory()->create();

        LineItem::factory()->for($invoice1)->create(['product_name' => 'Widget']);
        LineItem::factory()->for($invoice2)->create(['product_name' => 'Widget']);
        LineItem::factory()->for($invoice2)->create(['product_name' => 'Gadget']);

        $query = buildFilterQuery([[
            'key' => 'product_name',
            'op' => 'equals',
            'value' => 'Widget',
        ]]);
        $response = $this->getJson("/api/v1/invoices/{$invoice1->id}/line-items?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.invoice_id', $invoice1->id);
    });
});
