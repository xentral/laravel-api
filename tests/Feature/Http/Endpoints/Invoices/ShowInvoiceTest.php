<?php declare(strict_types=1);

use Workbench\App\Models\Invoice;

describe('Basic Show Operations', function () {
    it('can show single invoice', function () {
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-2024-001',
            'status' => 'sent',
            'total_amount' => 1500.00,
        ]);

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'id' => $invoice->id,
                'invoice_number' => 'INV-2024-001',
                'customer_id' => $invoice->customer_id,
                'status' => 'sent',
                'total_amount' => 1500.00,
            ],
        ]);
    });

    it('returns 404 when invoice not found', function () {
        $response = $this->getJson('/api/v1/invoices/99999');

        $response->assertNotFound();
    });

    it('can include customer relationship', function () {
        $invoice = Invoice::factory()->create();

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}?include=customer");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'invoice_number',
                'customer_id',
                'status',
                'total_amount',
                'customer' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'country',
                    'is_active',
                ],
            ],
        ]);

        $customer = $response->json('data.customer');
        expect($customer['id'])->toBe($invoice->customer_id);
    });

    it('can include lineItems relationship', function () {
        $invoice = Invoice::factory()->hasLineItems(3)->create();

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}?include=lineItems");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'invoice_number',
                'customer_id',
                'status',
                'total_amount',
                'line_items' => [
                    '*' => [
                        'id',
                        'invoice_id',
                        'product_name',
                        'quantity',
                        'unit_price',
                        'total_price',
                    ],
                ],
            ],
        ]);

        $lineItems = $response->json('data.line_items');
        expect($lineItems)->toHaveCount(3);
        expect($lineItems[0]['invoice_id'])->toBe($invoice->id);
    });

    it('can include both customer and lineItems', function () {
        $invoice = Invoice::factory()->hasLineItems(2)->create();

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}?include=customer,lineItems");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'invoice_number',
                'customer_id',
                'status',
                'total_amount',
                'customer' => [
                    'id',
                    'name',
                    'email',
                ],
                'line_items' => [
                    '*' => [
                        'id',
                        'product_name',
                        'quantity',
                    ],
                ],
            ],
        ]);

        $customer = $response->json('data.customer');
        $lineItems = $response->json('data.line_items');
        expect($customer['id'])->toBe($invoice->customer_id);
        expect($lineItems)->toHaveCount(2);
        expect($lineItems[0]['invoice_id'])->toBe($invoice->id);
    });

    it('returns correct invoice data structure', function () {
        $invoice = Invoice::factory()->create();

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'invoice_number',
                'customer_id',
                'status',
                'total_amount',
                'issued_at',
                'due_at',
                'paid_at',
                'created_at',
                'updated_at',
            ],
        ]);

        // Verify the structure doesn't include relationships by default
        expect($response->json('data'))->not->toHaveKey('customer');
        expect($response->json('data'))->not->toHaveKey('line_items');
    });
});

describe('DummyInclude with nested includes', function () {
    it('can include lineItems.customFields without explicitly including lineItems', function () {
        $invoice = Invoice::factory()->hasLineItems(2)->create();

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}?include=lineItems.customFields");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'invoice_number',
                'customer_id',
                'status',
                'total_amount',
                'line_items' => [
                    '*' => [
                        'id',
                        'product_name',
                        'quantity',
                        'unit_price',
                        'total_price',
                        'customFields',
                    ],
                ],
            ],
        ]);

        $lineItems = $response->json('data.line_items');
        expect($lineItems)->toHaveCount(2);
        expect($lineItems[0])->toHaveKey('customFields');
        expect($lineItems[0]['customFields'])->toBe(['foo' => 'bar']);
    });

    it('includes customFields when both lineItems and lineItems.customFields are requested', function () {
        $invoice = Invoice::factory()->hasLineItems(2)->create();

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}?include=lineItems,lineItems.customFields");

        $response->assertOk();
        $lineItems = $response->json('data.line_items');
        expect($lineItems)->toHaveCount(2);
        expect($lineItems[0])->toHaveKey('customFields');
        expect($lineItems[0]['customFields'])->toBe(['foo' => 'bar']);
    });

    it('does not include customFields when only lineItems is requested', function () {
        $invoice = Invoice::factory()->hasLineItems(2)->create();

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}?include=lineItems");

        $response->assertOk();
        $lineItems = $response->json('data.line_items');
        expect($lineItems)->toHaveCount(2);
        expect($lineItems[0])->not->toHaveKey('customFields');
    });
});
