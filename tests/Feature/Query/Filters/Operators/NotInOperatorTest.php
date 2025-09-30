<?php declare(strict_types=1);

use Workbench\App\Models\Invoice;

describe('Not In Operator', function () {
    it('can filter by id not in array', function () {
        $invoice1 = Invoice::factory()->create();
        $invoice2 = Invoice::factory()->create();
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'id',
            'op' => 'notIn',
            'value' => [$invoice1->id, $invoice2->id],
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter by string not in array', function () {
        Invoice::factory()->create(['status' => 'paid']);
        Invoice::factory()->create(['status' => 'sent']);
        Invoice::factory()->count(2)->create(['status' => 'draft']);

        $query = buildFilterQuery([[
            'key' => 'status',
            'op' => 'notIn',
            'value' => ['paid', 'sent'],
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });
});
