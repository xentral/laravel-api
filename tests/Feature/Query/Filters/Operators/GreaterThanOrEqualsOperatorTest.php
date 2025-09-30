<?php declare(strict_types=1);

use Workbench\App\Models\Invoice;

describe('Greater Than Or Equals Operator', function () {
    it('can filter by number greater than or equals', function () {
        Invoice::factory()->create(['total_amount' => 1000]);
        Invoice::factory()->create(['total_amount' => 2000]);
        Invoice::factory()->create(['total_amount' => 3000]);

        $query = buildFilterQuery([[
            'key' => 'total_amount',
            'op' => 'greaterThanOrEquals',
            'value' => 2000,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by date greater than or equals', function () {
        $baseDate = now()->startOfDay()->subDays(10);

        Invoice::factory()->create(['due_at' => $baseDate->copy()->subDays(2)]);
        Invoice::factory()->create(['due_at' => $baseDate->copy()]);
        Invoice::factory()->create(['due_at' => $baseDate->copy()->addDays(2)]);

        $query = buildFilterQuery([[
            'key' => 'due_at',
            'op' => 'greaterThanOrEquals',
            'value' => $baseDate->toDateString(),
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('includes boundary value in greater than or equals comparison', function () {
        Invoice::factory()->create(['total_amount' => 1000]);
        Invoice::factory()->create(['total_amount' => 1500]);
        Invoice::factory()->create(['total_amount' => 2000]);

        $query = buildFilterQuery([[
            'key' => 'total_amount',
            'op' => 'greaterThanOrEquals',
            'value' => 1500,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });
});
