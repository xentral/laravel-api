<?php declare(strict_types=1);

use Workbench\App\Models\Invoice;

describe('Invoice Search — direct columns', function () {
    it('matches by invoice_number', function () {
        Invoice::factory()->create(['invoice_number' => 'INV-001']);
        Invoice::factory()->create(['invoice_number' => 'INV-002']);
        Invoice::factory()->create(['invoice_number' => 'PO-999']);

        $response = $this->getJson('/api/v1/invoices?search=INV-001');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.invoice_number', 'INV-001');
    });

    it('returns empty when nothing matches', function () {
        Invoice::factory()->create(['invoice_number' => 'INV-001']);
        Invoice::factory()->create(['invoice_number' => 'INV-002']);
        Invoice::factory()->create(['invoice_number' => 'INV-003']);

        $response = $this->getJson('/api/v1/invoices?search=ZZZ-does-not-exist');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    });

    it('is a no-op when search parameter is absent', function () {
        Invoice::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/invoices');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('is a no-op when search parameter is empty string', function () {
        Invoice::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/invoices?search=');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('is a no-op when search parameter is whitespace only', function () {
        Invoice::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/invoices?search=%20%20%20');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('matches substring (LIKE contains) on direct column', function () {
        Invoice::factory()->create(['invoice_number' => 'INV-001']);
        Invoice::factory()->create(['invoice_number' => 'INV-002']);
        Invoice::factory()->create(['invoice_number' => 'PO-001']);

        $response = $this->getJson('/api/v1/invoices?search=INV');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });
});

describe('Invoice Search — LIKE wildcard escaping', function () {
    it('treats a literal percent in the search term as a character, not a wildcard', function () {
        Invoice::factory()->create(['invoice_number' => 'INV-50%-OFF']);
        Invoice::factory()->create(['invoice_number' => 'INV-50-OFF']);

        $response = $this->getJson('/api/v1/invoices?search=50%25');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.invoice_number', 'INV-50%-OFF');
    });

    it('treats a literal underscore in the search term as a character, not a wildcard', function () {
        Invoice::factory()->create(['invoice_number' => 'INV_001']);
        Invoice::factory()->create(['invoice_number' => 'INVX001']);

        $response = $this->getJson('/api/v1/invoices?search=INV_');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.invoice_number', 'INV_001');
    });
});
