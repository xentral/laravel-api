<?php declare(strict_types=1);

use Workbench\App\Models\Invoice;

describe('Ends With Operator', function () {
    it('can filter by string ends with', function () {
        Invoice::factory()->create(['invoice_number' => 'INV-123']);
        Invoice::factory()->create(['invoice_number' => 'REC-123']);
        Invoice::factory()->create(['invoice_number' => 'INV-456']);

        $query = buildFilterQuery([[
            'key' => 'invoice_number',
            'op' => 'endsWith',
            'value' => '123',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by string ends with partial match at end only', function () {
        Invoice::factory()->create(['invoice_number' => '123-TEST']);
        Invoice::factory()->create(['invoice_number' => '456-TES']);
        Invoice::factory()->create(['invoice_number' => '789-TESTA']);

        $query = buildFilterQuery([[
            'key' => 'invoice_number',
            'op' => 'endsWith',
            'value' => 'TEST',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });
});
