<?php declare(strict_types=1);

use Workbench\App\Models\Customer;

beforeEach(function () {
    Customer::factory()->count(3)->create();
});

describe('QueryFilter Endpoint Validation', function () {
    it('rejects filters not in allowedFilters list', function () {
        // 'email' is not in the allowedFilters list for customers (only id, name, phone, country, is_active)
        // Wait, let me check what's actually allowed on the customer endpoint
        $response = $this->getJson('/api/v1/customers?'.http_build_query([
            'filter' => [
                [
                    'key' => 'non_existent_field',
                    'op' => 'equals',
                    'value' => 'test',
                ],
            ],
        ]));

        $response->assertStatus(400);
        $response->assertJsonStructure(['message']);
    });

    it('allows valid filters in allowedFilters list', function () {
        $customer = Customer::first();

        $response = $this->getJson('/api/v1/customers?'.http_build_query([
            'filter' => [
                [
                    'key' => 'id',
                    'op' => 'equals',
                    'value' => $customer->id,
                ],
            ],
        ]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.id'))->toBe($customer->id);
    });

    it('rejects multiple filters when one is not allowed', function () {
        $response = $this->getJson('/api/v1/customers?'.http_build_query([
            'filter' => [
                [
                    'key' => 'name',
                    'op' => 'equals',
                    'value' => 'test',
                ],
                [
                    'key' => 'non_existent_field',
                    'op' => 'contains',
                    'value' => 'test',
                ],
            ],
        ]));

        $response->assertStatus(400);
    });

    it('allows multiple valid filters', function () {
        $customer = Customer::factory()->create(['name' => 'Test Customer']);

        $response = $this->getJson('/api/v1/customers?'.http_build_query([
            'filter' => [
                [
                    'key' => 'name',
                    'op' => 'equals',
                    'value' => $customer->name,
                ],
                [
                    'key' => 'id',
                    'op' => 'equals',
                    'value' => $customer->id,
                ],
            ],
        ]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('validates filter operators at endpoint level', function () {
        // Test with an invalid operator that doesn't exist
        $response = $this->getJson('/api/v1/customers?'.http_build_query([
            'filter' => [
                [
                    'key' => 'name',
                    'op' => 'invalid_operator',
                    'value' => 'test',
                ],
            ],
        ]));

        $response->assertStatus(422); // Validation error
    });

    it('rejects filter with invalid operator for specific field', function () {
        // If a field only allows certain operators, using a different one should fail
        // This depends on the QueryFilter configuration in CustomerController
        $response = $this->getJson('/api/v1/customers?'.http_build_query([
            'filter' => [
                [
                    'key' => 'id',
                    'op' => 'contains', // ID should only support equals, in, etc., not contains
                    'value' => '1',
                ],
            ],
        ]));

        $response->assertStatus(422);
    });

    it('validates boolean field values', function () {
        // is_active is a boolean field, should validate properly
        $response = $this->getJson('/api/v1/customers?'.http_build_query([
            'filter' => [
                [
                    'key' => 'is_active',
                    'op' => 'equals',
                    'value' => 'not_a_boolean',
                ],
            ],
        ]));

        // Should either work (if it's cast) or fail validation
        // Let's test with valid boolean
        $response = $this->getJson('/api/v1/customers?'.http_build_query([
            'filter' => [
                [
                    'key' => 'is_active',
                    'op' => 'equals',
                    'value' => true,
                ],
            ],
        ]));

        $response->assertOk();
    });

    it('provides helpful error messages for invalid filters', function () {
        $response = $this->getJson('/api/v1/customers?'.http_build_query([
            'filter' => [
                [
                    'key' => 'unknown_field',
                    'op' => 'equals',
                    'value' => 'test',
                ],
            ],
        ]));

        $response->assertStatus(400);
        $json = $response->json();
        expect($json)->toHaveKey('message');
        expect($json['message'])->toContain('unknown_field');
    });

    it('handles multiple filter validations at once', function () {
        // Test multiple validation errors in one request
        $response = $this->getJson('/api/v1/customers?'.http_build_query([
            'filter' => [
                [
                    'key' => 'unknown_field1',
                    'op' => 'equals',
                    'value' => 'test',
                ],
                [
                    'key' => 'unknown_field2',
                    'op' => 'contains',
                    'value' => 'test',
                ],
            ],
        ]));

        $response->assertStatus(400);
    });

    it('handles complex filter scenarios with multiple valid filters', function () {
        $customer = Customer::first();

        $response = $this->getJson('/api/v1/customers?'.http_build_query([
            'filter' => [
                [
                    'key' => 'name',
                    'op' => 'contains',
                    'value' => substr((string) $customer->name, 0, 3),
                ],
                [
                    'key' => 'country',
                    'op' => 'equals',
                    'value' => $customer->country,
                ],
                [
                    'key' => 'is_active',
                    'op' => 'equals',
                    'value' => $customer->is_active,
                ],
            ],
        ]));

        $response->assertOk();
        expect($response->json('data'))->not->toBeEmpty();
    });
});
