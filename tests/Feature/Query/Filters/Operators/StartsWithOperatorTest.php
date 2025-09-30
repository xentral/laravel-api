<?php declare(strict_types=1);

use Workbench\App\Models\Invoice;

describe('Starts With Operator', function () {
    it('can filter by string starts with', function () {
        Invoice::factory()->create(['invoice_number' => 'INV-12345']);
        Invoice::factory()->create(['invoice_number' => 'INV-67890']);
        Invoice::factory()->create(['invoice_number' => 'REC-11111']);

        $query = buildFilterQuery([[
            'key' => 'invoice_number',
            'op' => 'startsWith',
            'value' => 'INV',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by string starts with partial match at beginning only', function () {
        Invoice::factory()->create(['invoice_number' => 'TEST-123']);
        Invoice::factory()->create(['invoice_number' => 'TES-456']);
        Invoice::factory()->create(['invoice_number' => 'ATEST-789']);

        $query = buildFilterQuery([[
            'key' => 'invoice_number',
            'op' => 'startsWith',
            'value' => 'TEST',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });
});
