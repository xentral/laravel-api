<?php declare(strict_types=1);

use Workbench\App\Models\Customer;
use Workbench\App\Models\Invoice;

describe('Multiple Filters on Same Property', function () {
    it('can filter by same property with multiple conditions', function () {
        $date1 = now()->startOfDay();
        $date2 = $date1->copy()->addDays(10);
        $date3 = $date1->copy()->addDays(20);

        Invoice::factory()->create(['issued_at' => $date1]);
        Invoice::factory()->create(['issued_at' => $date2->copy()->addDays(2)]);
        Invoice::factory()->create(['issued_at' => $date2->copy()->addDays(5)]);
        Invoice::factory()->create(['issued_at' => $date3]);

        $query = buildFilterQuery([[
            'key' => 'issued_at',
            'op' => 'greaterThanOrEquals',
            'value' => $date2->toDateString(),
        ], [
            'key' => 'issued_at',
            'op' => 'lessThan',
            'value' => $date3->toDateString(),
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by relation property with multiple conditions', function () {
        $customer1 = Customer::factory()->create(['name' => 'Acme Corporation']);
        $customer2 = Customer::factory()->create(['name' => 'Acme Industries']);
        $customer3 = Customer::factory()->create(['name' => 'Tech Corporation']);
        $customer4 = Customer::factory()->create(['name' => 'Beta Corp']);

        Invoice::factory()->for($customer1)->create();
        Invoice::factory()->for($customer2)->create();
        Invoice::factory()->for($customer3)->create();
        Invoice::factory()->for($customer4)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.name',
            'op' => 'contains',
            'value' => 'Acme',
        ], [
            'key' => 'customer.name',
            'op' => 'startsWith',
            'value' => 'Acme C',
        ]]);

        $response = $this->getJson("/api/v1/invoices?{$query}");
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });

    it('can filter by same property with 3+ conditions', function () {
        Invoice::factory()->create(['total_amount' => 500]);
        Invoice::factory()->create(['total_amount' => 1500]);
        Invoice::factory()->create(['total_amount' => 2500]);
        Invoice::factory()->create(['total_amount' => 3500]);
        Invoice::factory()->create(['total_amount' => 4500]);

        $query = buildFilterQuery([[
            'key' => 'total_amount',
            'op' => 'greaterThan',
            'value' => 1000,
        ], [
            'key' => 'total_amount',
            'op' => 'lessThan',
            'value' => 4000,
        ], [
            'key' => 'total_amount',
            'op' => 'notEquals',
            'value' => 2500,
        ]]);

        $response = $this->getJson("/api/v1/invoices?{$query}");
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by relation property with mixed operators', function () {
        $customer1 = Customer::factory()->create(['name' => 'Acme Corporation', 'country' => 'US']);
        $customer2 = Customer::factory()->create(['name' => 'Acme Industries', 'country' => 'CA']);
        $customer3 = Customer::factory()->create(['name' => 'Tech Corporation', 'country' => 'US']);

        Invoice::factory()->for($customer1)->create();
        Invoice::factory()->for($customer2)->create();
        Invoice::factory()->for($customer3)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.name',
            'op' => 'contains',
            'value' => 'Acme',
        ], [
            'key' => 'customer.country',
            'op' => 'equals',
            'value' => 'US',
        ]]);

        $response = $this->getJson("/api/v1/invoices?{$query}");
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });
});
