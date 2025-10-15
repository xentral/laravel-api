<?php declare(strict_types=1);

use Workbench\App\Models\Invoice;

describe('Less Than Operator', function () {
    it('can filter by number less than', function () {
        Invoice::factory()->create(['total_amount' => 1000]);
        Invoice::factory()->create(['total_amount' => 2000]);
        Invoice::factory()->create(['total_amount' => 3000]);

        $query = buildFilterQuery([[
            'key' => 'total_amount',
            'op' => 'lessThan',
            'value' => 2500,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by date less than', function () {
        $baseDate = today()->subDays(10);

        Invoice::factory()->create(['issued_at' => $baseDate->copy()->subDays(5)]);
        Invoice::factory()->create(['issued_at' => $baseDate->copy()->subDays(2)]);
        Invoice::factory()->create(['issued_at' => $baseDate->copy()->addDays(2)]);

        $query = buildFilterQuery([[
            'key' => 'issued_at',
            'op' => 'lessThan',
            'value' => $baseDate->toDateString(),
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('excludes boundary value in less than comparison', function () {
        Invoice::factory()->create(['total_amount' => 1000]);
        Invoice::factory()->create(['total_amount' => 1500]);
        Invoice::factory()->create(['total_amount' => 2000]);

        $query = buildFilterQuery([[
            'key' => 'total_amount',
            'op' => 'lessThan',
            'value' => 1500,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });
});
