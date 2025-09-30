<?php declare(strict_types=1);

use Workbench\App\Models\Invoice;

describe('In Operator', function () {
    it('can filter by id in array', function () {
        $invoice1 = Invoice::factory()->create();
        $invoice2 = Invoice::factory()->create();
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'id',
            'op' => 'in',
            'value' => [$invoice1->id, $invoice2->id],
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by string in array', function () {
        Invoice::factory()->create(['status' => 'paid']);
        Invoice::factory()->create(['status' => 'sent']);
        Invoice::factory()->create(['status' => 'draft']);

        $query = buildFilterQuery([[
            'key' => 'status',
            'op' => 'in',
            'value' => ['paid', 'sent'],
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });
});
