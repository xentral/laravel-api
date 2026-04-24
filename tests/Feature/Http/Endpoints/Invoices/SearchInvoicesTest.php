<?php declare(strict_types=1);

use Workbench\App\Models\Customer;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\LineItem;

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

    it('treats a literal backslash in the search term as a character, not an escape', function () {
        Invoice::factory()->create(['invoice_number' => 'INV\\001']);
        Invoice::factory()->create(['invoice_number' => 'INVX001']);

        $response = $this->getJson('/api/v1/invoices?search=INV%5C');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.invoice_number', 'INV\\001');
    });
});

describe('Invoice Search — relation columns', function () {
    it('matches by customer name (single-level relation)', function () {
        $acme = Customer::factory()->create(['name' => 'Acme Corp']);
        $other = Customer::factory()->create(['name' => 'Contoso']);
        Invoice::factory()->for($acme)->create(['invoice_number' => 'INV-A']);
        Invoice::factory()->for($other)->create(['invoice_number' => 'INV-B']);

        $response = $this->getJson('/api/v1/invoices?search=Acme');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.invoice_number', 'INV-A');
    });

    it('matches by customer email (single-level relation)', function () {
        $a = Customer::factory()->create(['email' => 'alice@example.com']);
        $b = Customer::factory()->create(['email' => 'bob@example.com']);
        Invoice::factory()->for($a)->create(['invoice_number' => 'INV-A']);
        Invoice::factory()->for($b)->create(['invoice_number' => 'INV-B']);

        $response = $this->getJson('/api/v1/invoices?search=alice%40');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.invoice_number', 'INV-A');
    });

    it('matches by line item product_name (hasMany relation)', function () {
        $invoiceWithWidget = Invoice::factory()->create(['invoice_number' => 'INV-W']);
        $invoiceWithGadget = Invoice::factory()->create(['invoice_number' => 'INV-G']);
        LineItem::factory()->for($invoiceWithWidget)->create(['product_name' => 'Super Widget']);
        LineItem::factory()->for($invoiceWithGadget)->create(['product_name' => 'Mega Gadget']);

        $response = $this->getJson('/api/v1/invoices?search=Widget');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.invoice_number', 'INV-W');
    });

    it('unions matches from direct and relation columns', function () {
        // Direct-column match
        $other = Customer::factory()->create(['name' => 'Unrelated']);
        Invoice::factory()->for($other)->create(['invoice_number' => 'INV-ACME-123']);

        // Relation-column match (customer.name)
        $acme = Customer::factory()->create(['name' => 'Acme Corp']);
        Invoice::factory()->for($acme)->create(['invoice_number' => 'INV-000']);

        // Non-match
        $zzz = Customer::factory()->create(['name' => 'ZZZ']);
        Invoice::factory()->for($zzz)->create(['invoice_number' => 'INV-999']);

        $response = $this->getJson('/api/v1/invoices?search=Acme');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $returnedNumbers = collect($response->json('data'))->pluck('invoice_number')->sort()->values()->all();
        expect($returnedNumbers)->toBe(['INV-000', 'INV-ACME-123']);
    });
});

describe('Invoice Search — integration with filters, sort, pagination', function () {
    it('ANDs the search group with a filter', function () {
        $acme = Customer::factory()->create(['name' => 'Acme Corp']);

        // Matches search AND matches filter
        Invoice::factory()->paid()->for($acme)->create(['invoice_number' => 'INV-A']);

        // Matches search but NOT filter (wrong status)
        Invoice::factory()->draft()->for($acme)->create(['invoice_number' => 'INV-B']);

        // Matches filter but NOT search (different customer, no "Acme" anywhere)
        $other = Customer::factory()->create(['name' => 'Contoso']);
        Invoice::factory()->paid()->for($other)->create(['invoice_number' => 'INV-C']);

        $filterQuery = buildFilterQuery([[
            'key' => 'status',
            'op' => 'equals',
            'value' => 'paid',
        ]]);
        $response = $this->getJson("/api/v1/invoices?search=Acme&{$filterQuery}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.invoice_number', 'INV-A');
    });

    it('composes with sort and pagination', function () {
        $acme = Customer::factory()->create(['name' => 'Acme Corp']);
        Invoice::factory()->for($acme)->create(['invoice_number' => 'INV-1', 'total_amount' => 100]);
        Invoice::factory()->for($acme)->create(['invoice_number' => 'INV-2', 'total_amount' => 300]);
        Invoice::factory()->for($acme)->create(['invoice_number' => 'INV-3', 'total_amount' => 200]);

        $other = Customer::factory()->create(['name' => 'Contoso']);
        Invoice::factory()->for($other)->create(['invoice_number' => 'INV-NO', 'total_amount' => 999]);

        $response = $this->getJson('/api/v1/invoices?search=Acme&sort=-total_amount&per_page=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.invoice_number', 'INV-2'); // 300
        $response->assertJsonPath('data.1.invoice_number', 'INV-3'); // 200
    });
});
