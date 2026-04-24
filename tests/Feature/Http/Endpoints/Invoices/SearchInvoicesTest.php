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

describe('Invoice Search — relation columns', function () {
    it('matches by customer name (single-level relation)', function () {
        $acme = Workbench\App\Models\Customer::factory()->create(['name' => 'Acme Corp']);
        $other = Workbench\App\Models\Customer::factory()->create(['name' => 'Contoso']);
        Invoice::factory()->for($acme)->create(['invoice_number' => 'INV-A']);
        Invoice::factory()->for($other)->create(['invoice_number' => 'INV-B']);

        $response = $this->getJson('/api/v1/invoices?search=Acme');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.invoice_number', 'INV-A');
    });

    it('matches by customer email (single-level relation)', function () {
        $a = Workbench\App\Models\Customer::factory()->create(['email' => 'alice@example.com']);
        $b = Workbench\App\Models\Customer::factory()->create(['email' => 'bob@example.com']);
        Invoice::factory()->for($a)->create(['invoice_number' => 'INV-A']);
        Invoice::factory()->for($b)->create(['invoice_number' => 'INV-B']);

        $response = $this->getJson('/api/v1/invoices?search=alice%40');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.invoice_number', 'INV-A');
    });

    it('matches by line item product_name (multi-level hasMany relation)', function () {
        $invoiceWithWidget = Invoice::factory()->create(['invoice_number' => 'INV-W']);
        $invoiceWithGadget = Invoice::factory()->create(['invoice_number' => 'INV-G']);
        Workbench\App\Models\LineItem::factory()->for($invoiceWithWidget)->create(['product_name' => 'Super Widget']);
        Workbench\App\Models\LineItem::factory()->for($invoiceWithGadget)->create(['product_name' => 'Mega Gadget']);

        $response = $this->getJson('/api/v1/invoices?search=Widget');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.invoice_number', 'INV-W');
    });

    it('unions matches from direct and relation columns', function () {
        // Direct-column match
        $other = Workbench\App\Models\Customer::factory()->create(['name' => 'Unrelated']);
        Invoice::factory()->for($other)->create(['invoice_number' => 'INV-ACME-123']);

        // Relation-column match (customer.name)
        $acme = Workbench\App\Models\Customer::factory()->create(['name' => 'Acme Corp']);
        Invoice::factory()->for($acme)->create(['invoice_number' => 'INV-000']);

        // Non-match
        $zzz = Workbench\App\Models\Customer::factory()->create(['name' => 'ZZZ']);
        Invoice::factory()->for($zzz)->create(['invoice_number' => 'INV-999']);

        $response = $this->getJson('/api/v1/invoices?search=Acme');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $returnedNumbers = collect($response->json('data'))->pluck('invoice_number')->sort()->values()->all();
        expect($returnedNumbers)->toBe(['INV-000', 'INV-ACME-123']);
    });
});
