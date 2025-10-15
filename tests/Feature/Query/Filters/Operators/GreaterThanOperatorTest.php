<?php declare(strict_types=1);

use Workbench\App\Models\Invoice;

describe('Greater Than Operator', function () {
    it('can filter by number greater than', function () {
        Invoice::factory()->create(['total_amount' => 1000]);
        Invoice::factory()->create(['total_amount' => 2000]);
        Invoice::factory()->create(['total_amount' => 3000]);

        $query = buildFilterQuery([[
            'key' => 'total_amount',
            'op' => 'greaterThan',
            'value' => 1500,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by date greater than', function () {
        $baseDate = today()->subDays(10);

        Invoice::factory()->create(['issued_at' => $baseDate->copy()->subDays(2)]);
        Invoice::factory()->create(['issued_at' => $baseDate->copy()->addDays(2)]);
        Invoice::factory()->create(['issued_at' => $baseDate->copy()->addDays(5)]);

        $query = buildFilterQuery([[
            'key' => 'issued_at',
            'op' => 'greaterThan',
            'value' => $baseDate->toDateString(),
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('excludes boundary value in greater than comparison', function () {
        Invoice::factory()->create(['total_amount' => 1000]);
        Invoice::factory()->create(['total_amount' => 1500]);
        Invoice::factory()->create(['total_amount' => 2000]);

        $query = buildFilterQuery([[
            'key' => 'total_amount',
            'op' => 'greaterThan',
            'value' => 1500,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });
});
