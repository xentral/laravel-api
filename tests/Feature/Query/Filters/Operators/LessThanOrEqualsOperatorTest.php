<?php declare(strict_types=1);

use Workbench\App\Models\Invoice;

describe('Less Than Or Equals Operator', function () {
    it('can filter by number less than or equals', function () {
        Invoice::factory()->create(['total_amount' => 1000]);
        Invoice::factory()->create(['total_amount' => 2000]);
        Invoice::factory()->create(['total_amount' => 3000]);

        $query = buildFilterQuery([[
            'key' => 'total_amount',
            'op' => 'lessThanOrEquals',
            'value' => 2000,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by date less than or equals', function () {
        $baseDate = now()->startOfDay()->subDays(10);

        Invoice::factory()->create(['issued_at' => $baseDate->copy()->subDays(2)]);
        Invoice::factory()->create(['issued_at' => $baseDate->copy()]);
        Invoice::factory()->create(['issued_at' => $baseDate->copy()->addDays(2)]);

        $query = buildFilterQuery([[
            'key' => 'issued_at',
            'op' => 'lessThanOrEquals',
            'value' => $baseDate->toDateString(),
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('includes boundary value in less than or equals comparison', function () {
        Invoice::factory()->create(['total_amount' => 1000]);
        Invoice::factory()->create(['total_amount' => 1500]);
        Invoice::factory()->create(['total_amount' => 2000]);

        $query = buildFilterQuery([[
            'key' => 'total_amount',
            'op' => 'lessThanOrEquals',
            'value' => 1500,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });
});
