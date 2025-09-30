<?php declare(strict_types=1);

use Workbench\App\Models\Customer;
use Workbench\App\Models\Invoice;

describe('Equals Operator', function () {
    it('can filter by id equals', function () {
        $invoice = Invoice::factory()->create();
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'id',
            'op' => 'equals',
            'value' => $invoice->id,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $invoice->id);
    });

    it('can filter by string equals', function () {
        $invoice = Invoice::factory()->create(['invoice_number' => 'INV-12345']);
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'invoice_number',
            'op' => 'equals',
            'value' => 'INV-12345',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.invoice_number', 'INV-12345');
    });

    it('can filter by number equals', function () {
        $invoice = Invoice::factory()->create(['total_amount' => 1234.56]);
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'total_amount',
            'op' => 'equals',
            'value' => 1234.56,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });

    it('can filter by date equals', function () {
        $date = now()->startOfDay();
        $invoice = Invoice::factory()->create(['issued_at' => $date]);
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'issued_at',
            'op' => 'equals',
            'value' => $date->toDateString(),
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });

    it('can filter by boolean equals', function () {
        $customer = Customer::factory()->create(['is_active' => true]);
        Customer::factory()->count(2)->create(['is_active' => false]);
        Invoice::factory()->for($customer)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.is_active',
            'op' => 'equals',
            'value' => true,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });

    it('can filter by relation equals', function () {
        $customer = Customer::factory()->create(['name' => 'Acme Corp']);
        $invoice = Invoice::factory()->for($customer)->create();
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.name',
            'op' => 'equals',
            'value' => 'Acme Corp',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });
});
