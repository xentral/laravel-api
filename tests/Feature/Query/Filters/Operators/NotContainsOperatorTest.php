<?php declare(strict_types=1);

use Workbench\App\Models\Customer;
use Workbench\App\Models\Invoice;

describe('Not Contains Operator', function () {
    it('can filter by string not contains', function () {
        Invoice::factory()->create(['invoice_number' => 'INV-12345']);
        Invoice::factory()->create(['invoice_number' => 'INV-67890']);
        Invoice::factory()->create(['invoice_number' => 'REC-11111']);
        Invoice::factory()->create(['invoice_number' => 'REC-22222']);

        $query = buildFilterQuery([[
            'key' => 'invoice_number',
            'op' => 'notContains',
            'value' => 'INV',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by relation string not contains', function () {
        $customer1 = Customer::factory()->create(['name' => 'Acme Corporation']);
        $customer2 = Customer::factory()->create(['name' => 'Acme Industries']);
        $customer3 = Customer::factory()->create(['name' => 'Tech Corp']);
        $customer4 = Customer::factory()->create(['name' => 'Global Systems']);

        Invoice::factory()->for($customer1)->create();
        Invoice::factory()->for($customer2)->create();
        Invoice::factory()->for($customer3)->create();
        Invoice::factory()->for($customer4)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.name',
            'op' => 'notContains',
            'value' => 'Acme',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });
});
