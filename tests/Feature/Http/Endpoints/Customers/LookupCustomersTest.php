<?php declare(strict_types=1);

use Workbench\App\Models\Customer;

describe('Customer Lookup — non-paginated endpoint', function () {
    it('returns all customers without pagination, even when count exceeds default page size', function () {
        Customer::factory()->count(30)->create();

        $response = $this->getJson('/api/v1/customers/lookup');

        $response->assertOk();
        $response->assertJsonCount(30, 'data');
    });

    it('response envelope is {data: [...]} with no meta and no links keys', function () {
        Customer::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/customers/lookup');

        $response->assertOk();
        expect($response->json())->toHaveKey('data');
        expect($response->json())->not->toHaveKey('meta');
        expect($response->json())->not->toHaveKey('links');
    });

    it('silently ignores per_page and page query parameters', function () {
        Customer::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/customers/lookup?per_page=5&page=2');

        $response->assertOk();
        $response->assertJsonCount(20, 'data');
        expect($response->json())->not->toHaveKey('meta');
    });

    it('still applies filters and returns the unwrapped {data: [...]} envelope', function () {
        Customer::factory()->count(3)->create(['country' => 'US']);
        Customer::factory()->count(2)->create(['country' => 'CA']);

        $filterQuery = buildFilterQuery([[
            'key' => 'country',
            'op' => 'equals',
            'value' => 'US',
        ]]);
        $response = $this->getJson("/api/v1/customers/lookup?{$filterQuery}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        expect($response->json())->not->toHaveKey('meta');
    });
});
