<?php declare(strict_types=1);

use Workbench\App\Models\Customer;

describe('Basic List Operations', function () {
    it('can list customers', function () {
        Customer::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/customers');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'country',
                    'is_active',
                    'is_verified',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    });

    it('can include invoices relationship', function () {
        $customer = Customer::factory()->hasInvoices(2)->create();
        Customer::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/customers?include=invoices');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                    'invoices',
                ],
            ],
        ]);

        $customerData = collect($response->json('data'))->firstWhere('id', $customer->id);
        expect($customerData['invoices'])->toHaveCount(2);
    });

    it('pagination works correctly', function () {
        Customer::factory()->count(15)->create();

        $response = $this->getJson('/api/v1/customers?per_page=5');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
        $response->assertJsonStructure([
            'data',
            'links' => [
                'first',
                'next',
            ],
            'meta' => [
                'current_page',
                'per_page',
            ],
        ]);
    });

    it('returns empty array when no customers exist', function () {
        $response = $this->getJson('/api/v1/customers');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    });
});

describe('Customer ID Filters', function () {
    it('can filter by id equals', function () {
        $customer = Customer::factory()->create();
        Customer::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'id',
            'op' => 'equals',
            'value' => $customer->id,
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $customer->id);
    });

    it('can filter by id notEquals', function () {
        $customer = Customer::factory()->create();
        Customer::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'id',
            'op' => 'notEquals',
            'value' => $customer->id,
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter by id in', function () {
        $customers = Customer::factory()->count(3)->create();
        Customer::factory()->count(2)->create();

        $ids = $customers->pluck('id')->implode(',');
        $query = buildFilterQuery([[
            'key' => 'id',
            'op' => 'in',
            'value' => $ids,
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter by id notIn', function () {
        $customers = Customer::factory()->count(2)->create();
        Customer::factory()->count(3)->create();

        $ids = $customers->pluck('id')->implode(',');
        $query = buildFilterQuery([[
            'key' => 'id',
            'op' => 'notIn',
            'value' => $ids,
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });
});

describe('Customer String Filters', function () {
    it('can filter by name equals', function () {
        Customer::factory()->create(['name' => 'Acme Corp']);
        Customer::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'name',
            'op' => 'equals',
            'value' => 'Acme Corp',
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Acme Corp');
    });

    it('can filter by name notEquals', function () {
        Customer::factory()->create(['name' => 'Acme Corp']);
        Customer::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'name',
            'op' => 'notEquals',
            'value' => 'Acme Corp',
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter by name contains', function () {
        Customer::factory()->create(['name' => 'Acme Corp']);
        Customer::factory()->create(['name' => 'Acme Industries']);
        Customer::factory()->create(['name' => 'TechStart Inc']);

        $query = buildFilterQuery([[
            'key' => 'name',
            'op' => 'contains',
            'value' => 'Acme',
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by name startsWith', function () {
        Customer::factory()->create(['name' => 'Acme Corp']);
        Customer::factory()->create(['name' => 'Acme Industries']);
        Customer::factory()->create(['name' => 'TechStart Acme']);

        $query = buildFilterQuery([[
            'key' => 'name',
            'op' => 'startsWith',
            'value' => 'Acme',
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by name endsWith', function () {
        Customer::factory()->create(['name' => 'Corp Acme']);
        Customer::factory()->create(['name' => 'Industries Acme']);
        Customer::factory()->create(['name' => 'Acme TechStart']);

        $query = buildFilterQuery([[
            'key' => 'name',
            'op' => 'endsWith',
            'value' => 'Acme',
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by email equals', function () {
        Customer::factory()->create(['email' => 'john@example.com']);
        Customer::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'email',
            'op' => 'equals',
            'value' => 'john@example.com',
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.email', 'john@example.com');
    });

    it('can filter by email notEquals', function () {
        Customer::factory()->create(['email' => 'john@example.com']);
        Customer::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'email',
            'op' => 'notEquals',
            'value' => 'john@example.com',
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter by email contains', function () {
        Customer::factory()->create(['email' => 'john@example.com']);
        Customer::factory()->create(['email' => 'jane@example.com']);
        Customer::factory()->create(['email' => 'bob@other.com']);

        $query = buildFilterQuery([[
            'key' => 'email',
            'op' => 'contains',
            'value' => 'example.com',
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by country equals', function () {
        Customer::factory()->count(3)->create(['country' => 'US']);
        Customer::factory()->count(2)->create(['country' => 'CA']);

        $query = buildFilterQuery([[
            'key' => 'country',
            'op' => 'equals',
            'value' => 'US',
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter by country in', function () {
        Customer::factory()->count(2)->create(['country' => 'US']);
        Customer::factory()->count(2)->create(['country' => 'CA']);
        Customer::factory()->count(1)->create(['country' => 'MX']);

        $query = buildFilterQuery([[
            'key' => 'country',
            'op' => 'in',
            'value' => 'US,CA',
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(4, 'data');
    });

    it('can filter by country notIn', function () {
        Customer::factory()->count(2)->create(['country' => 'US']);
        Customer::factory()->count(2)->create(['country' => 'CA']);
        Customer::factory()->count(1)->create(['country' => 'MX']);

        $query = buildFilterQuery([[
            'key' => 'country',
            'op' => 'notIn',
            'value' => 'US,CA',
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });
});

describe('Customer Boolean Filters', function () {
    it('can filter by is_active equals true', function () {
        Customer::factory()->count(3)->create(['is_active' => true]);
        Customer::factory()->count(2)->create(['is_active' => false]);

        $query = buildFilterQuery([[
            'key' => 'is_active',
            'op' => 'equals',
            'value' => true,
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter by is_active equals false', function () {
        Customer::factory()->count(3)->create(['is_active' => true]);
        Customer::factory()->count(2)->create(['is_active' => false]);

        $query = buildFilterQuery([[
            'key' => 'is_active',
            'op' => 'equals',
            'value' => false,
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });
});

describe('Customer BooleanInteger Filters', function () {
    it('can filter by is_verified equals true', function () {
        Customer::factory()->count(3)->verified()->create();
        Customer::factory()->count(2)->unverified()->create();

        $query = buildFilterQuery([[
            'key' => 'is_verified',
            'op' => 'equals',
            'value' => true,
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter by is_verified equals false', function () {
        Customer::factory()->count(3)->verified()->create();
        Customer::factory()->count(2)->unverified()->create();

        $query = buildFilterQuery([[
            'key' => 'is_verified',
            'op' => 'equals',
            'value' => false,
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by is_verified notEquals true', function () {
        Customer::factory()->count(3)->verified()->create();
        Customer::factory()->count(2)->unverified()->create();

        $query = buildFilterQuery([[
            'key' => 'is_verified',
            'op' => 'notEquals',
            'value' => true,
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by is_verified using string true', function () {
        Customer::factory()->count(3)->verified()->create();
        Customer::factory()->count(2)->unverified()->create();

        $query = buildFilterQuery([[
            'key' => 'is_verified',
            'op' => 'equals',
            'value' => 'true',
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter by is_verified using string false', function () {
        Customer::factory()->count(3)->verified()->create();
        Customer::factory()->count(2)->unverified()->create();

        $query = buildFilterQuery([[
            'key' => 'is_verified',
            'op' => 'equals',
            'value' => 'false',
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('returns validation error for invalid is_verified value', function () {
        Customer::factory()->create();

        $query = buildFilterQuery([[
            'key' => 'is_verified',
            'op' => 'equals',
            'value' => 'invalid',
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['is_verified']);
    });
});

describe('Customer Multiple Filter Combinations', function () {
    it('can combine name and country filters', function () {
        Customer::factory()->create(['name' => 'Acme Corp', 'country' => 'US']);
        Customer::factory()->create(['name' => 'Acme Industries', 'country' => 'US']);
        Customer::factory()->create(['name' => 'Acme Global', 'country' => 'CA']);
        Customer::factory()->create(['name' => 'TechStart Inc', 'country' => 'US']);

        $query = buildFilterQuery([[
            'key' => 'name',
            'op' => 'contains',
            'value' => 'Acme',
        ], [
            'key' => 'country',
            'op' => 'equals',
            'value' => 'US',
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can combine email and is_active filters', function () {
        Customer::factory()->create(['email' => 'john@example.com', 'is_active' => true]);
        Customer::factory()->create(['email' => 'jane@example.com', 'is_active' => true]);
        Customer::factory()->create(['email' => 'bob@example.com', 'is_active' => false]);
        Customer::factory()->create(['email' => 'alice@other.com', 'is_active' => true]);

        $query = buildFilterQuery([[
            'key' => 'email',
            'op' => 'contains',
            'value' => 'example.com',
        ], [
            'key' => 'is_active',
            'op' => 'equals',
            'value' => true,
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can combine multiple filters (name contains + country in + is_active)', function () {
        Customer::factory()->create(['name' => 'Acme Corp', 'country' => 'US', 'is_active' => true]);
        Customer::factory()->create(['name' => 'Acme Industries', 'country' => 'CA', 'is_active' => true]);
        Customer::factory()->create(['name' => 'Acme Global', 'country' => 'US', 'is_active' => false]);
        Customer::factory()->create(['name' => 'TechStart Acme', 'country' => 'MX', 'is_active' => true]);

        $query = buildFilterQuery([[
            'key' => 'name',
            'op' => 'contains',
            'value' => 'Acme',
        ], [
            'key' => 'country',
            'op' => 'in',
            'value' => 'US,CA',
        ], [
            'key' => 'is_active',
            'op' => 'equals',
            'value' => true,
        ]]);
        $response = $this->getJson("/api/v1/customers?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });
});
