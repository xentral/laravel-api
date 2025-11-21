<?php declare(strict_types=1);

use Workbench\App\Models\Customer;

describe('Basic Show Operations', function () {
    it('can show single customer', function () {
        $customer = Customer::factory()->create([
            'name' => 'Acme Corp',
            'email' => 'contact@acme.com',
            'phone' => '+1-555-0100',
            'country' => 'US',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/customers/{$customer->id}");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'id' => $customer->id,
                'name' => 'Acme Corp',
                'email' => 'contact@acme.com',
                'phone' => '+1-555-0100',
                'country' => 'US',
                'is_active' => true,
            ],
        ]);
    });

    it('returns 404 when customer not found', function () {
        $response = $this->getJson('/api/v1/customers/99999');

        $response->assertNotFound();
    });

    it('can include invoices relationship', function () {
        $customer = Customer::factory()->hasInvoices(3)->create();

        $response = $this->getJson("/api/v1/customers/{$customer->id}?include=invoices");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'invoices' => [
                    '*' => [
                        'id',
                        'invoice_number',
                        'customer_id',
                        'status',
                        'total_amount',
                    ],
                ],
            ],
        ]);

        $invoices = $response->json('data.invoices');
        expect($invoices)->toHaveCount(3);
        expect($invoices[0]['customer_id'])->toBe($customer->id);
    });

    it('returns correct customer data structure', function () {
        $customer = Customer::factory()->create();

        $response = $this->getJson("/api/v1/customers/{$customer->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'phone',
                'country',
                'is_active',
                'created_at',
                'updated_at',
            ],
        ]);

        // Verify the structure doesn't include relationships by default
        expect($response->json('data'))->not->toHaveKey('invoices');
    });

    it('Includes Trait Functionality in Resource', function () {
        $customer = Customer::factory()->create();

        $response = $this->getJson("/api/v1/customers/{$customer->id}");

        $response->assertOk();
        $responseData = $response->json('data');

        // Assuming the trait adds a 'meta' field
        expect($responseData)->toHaveKey('meta');
        expect($responseData['meta'])->toBeArray();
    });
});
