<?php declare(strict_types=1);

use Workbench\App\Models\Invoice;

describe('Not Equals Operator', function () {
    it('can filter by id not equals', function () {
        $invoice = Invoice::factory()->create();
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'id',
            'op' => 'notEquals',
            'value' => $invoice->id,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter by string not equals', function () {
        Invoice::factory()->create(['invoice_number' => 'INV-12345']);
        Invoice::factory()->count(2)->create();

        $query = buildFilterQuery([[
            'key' => 'invoice_number',
            'op' => 'notEquals',
            'value' => 'INV-12345',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by date not equals', function () {
        $date = now()->startOfDay();
        $otherDate = $date->copy()->addDays(5);

        Invoice::factory()->count(2)->create(['paid_at' => $date]);
        Invoice::factory()->count(3)->create(['paid_at' => $otherDate]);
        Invoice::factory()->count(1)->create(['paid_at' => null]); // NULL should NOT be included

        $query = buildFilterQuery([[
            'key' => 'paid_at',
            'op' => 'notEquals',
            'value' => $date->toDateString(),
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data'); // Only the 3 with $otherDate
    });
});
