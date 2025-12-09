<?php declare(strict_types=1);

use Workbench\App\Models\Invoice;

beforeEach(function () {
    Invoice::factory()->create(['invoice_number' => 'INV-001', 'total_amount' => 100]);
    Invoice::factory()->create(['invoice_number' => 'INV-002', 'total_amount' => 300]);
    Invoice::factory()->create(['invoice_number' => 'INV-003', 'total_amount' => 200]);
});

describe('Standard Sort Parameter', function () {
    it('sorts ascending by field name', function () {
        $response = $this->getJson('/api/v1/invoices?sort=invoice_number');

        $response->assertOk();
        $data = $response->json('data');

        expect($data[0]['invoice_number'])->toBe('INV-001')
            ->and($data[1]['invoice_number'])->toBe('INV-002')
            ->and($data[2]['invoice_number'])->toBe('INV-003');
    });

    it('sorts descending with minus prefix', function () {
        $response = $this->getJson('/api/v1/invoices?sort=-invoice_number');

        $response->assertOk();
        $data = $response->json('data');

        expect($data[0]['invoice_number'])->toBe('INV-003')
            ->and($data[1]['invoice_number'])->toBe('INV-002')
            ->and($data[2]['invoice_number'])->toBe('INV-001');
    });

    it('supports multiple sort fields', function () {
        Invoice::factory()->create(['invoice_number' => 'INV-004', 'total_amount' => 100]);

        $response = $this->getJson('/api/v1/invoices?sort=total_amount,invoice_number');

        $response->assertOk();
        $data = $response->json('data');

        // First sort by total_amount ascending, then by invoice_number
        expect($data[0]['total_amount'])->toBe(100)
            ->and($data[0]['invoice_number'])->toBe('INV-001')
            ->and($data[1]['total_amount'])->toBe(100)
            ->and($data[1]['invoice_number'])->toBe('INV-004');
    });
});

describe('Legacy Order Parameter', function () {
    it('sorts ascending using order array with dir asc', function () {
        $response = $this->getJson('/api/v1/invoices?order[0][field]=invoice_number&order[0][dir]=asc');

        $response->assertOk();
        $data = $response->json('data');

        expect($data[0]['invoice_number'])->toBe('INV-001')
            ->and($data[1]['invoice_number'])->toBe('INV-002')
            ->and($data[2]['invoice_number'])->toBe('INV-003');
    });

    it('sorts descending using order array with dir desc', function () {
        $response = $this->getJson('/api/v1/invoices?order[0][field]=invoice_number&order[0][dir]=desc');

        $response->assertOk();
        $data = $response->json('data');

        expect($data[0]['invoice_number'])->toBe('INV-003')
            ->and($data[1]['invoice_number'])->toBe('INV-002')
            ->and($data[2]['invoice_number'])->toBe('INV-001');
    });

    it('supports multiple order fields', function () {
        Invoice::factory()->create(['invoice_number' => 'INV-004', 'total_amount' => 100]);

        $response = $this->getJson('/api/v1/invoices?order[0][field]=total_amount&order[0][dir]=asc&order[1][field]=invoice_number&order[1][dir]=asc');

        $response->assertOk();
        $data = $response->json('data');

        // First sort by total_amount ascending, then by invoice_number
        expect($data[0]['total_amount'])->toBe(100)
            ->and($data[0]['invoice_number'])->toBe('INV-001')
            ->and($data[1]['total_amount'])->toBe(100)
            ->and($data[1]['invoice_number'])->toBe('INV-004');
    });

    it('supports mixed directions in order array', function () {
        $response = $this->getJson('/api/v1/invoices?order[0][field]=total_amount&order[0][dir]=desc&order[1][field]=invoice_number&order[1][dir]=asc');

        $response->assertOk();
        $data = $response->json('data');

        expect($data[0]['total_amount'])->toBe(300)
            ->and($data[1]['total_amount'])->toBe(200)
            ->and($data[2]['total_amount'])->toBe(100);
    });

    it('ignores order parameter when sort parameter is provided', function () {
        $response = $this->getJson('/api/v1/invoices?sort=-invoice_number&order[0][field]=invoice_number&order[0][dir]=asc');

        $response->assertOk();
        $data = $response->json('data');

        // sort parameter takes precedence, so it should be descending
        expect($data[0]['invoice_number'])->toBe('INV-003')
            ->and($data[1]['invoice_number'])->toBe('INV-002')
            ->and($data[2]['invoice_number'])->toBe('INV-001');
    });
});
