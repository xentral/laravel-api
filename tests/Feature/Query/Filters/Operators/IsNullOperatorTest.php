<?php declare(strict_types=1);

use Workbench\App\Models\Customer;
use Workbench\App\Models\Invoice;

describe('Is Null Operator', function () {
    it('can filter by nullable date field is null', function () {
        Invoice::factory()->count(3)->create(['paid_at' => null]);
        Invoice::factory()->count(2)->paid()->create();

        $query = buildFilterQuery([[
            'key' => 'paid_at',
            'op' => 'isNull',
            'value' => true,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter by nullable string field is null', function () {
        $customerWithNullPhone = Customer::factory()->create(['phone' => null]);
        $customerWithPhone = Customer::factory()->create(['phone' => '123-456-7890']);

        Invoice::factory()->count(2)->create(['customer_id' => $customerWithNullPhone->id]);
        Invoice::factory()->count(3)->create(['customer_id' => $customerWithPhone->id]);

        $query = buildFilterQuery([[
            'key' => 'customer.phone',
            'op' => 'isNull',
            'value' => true,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });
});
