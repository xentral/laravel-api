<?php declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\LineItem;

describe('Basic List Operations', function () {
    it('can list invoices', function () {
        Invoice::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/invoices');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'invoice_number',
                    'customer_id',
                    'status',
                    'total_amount',
                    'issued_at',
                    'due_at',
                    'paid_at',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    });

    it('can include customer relationship', function () {
        Invoice::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/invoices?include=customer');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'invoice_number',
                    'customer_id',
                    'customer' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'country',
                        'is_active',
                    ],
                ],
            ],
        ]);

        $firstInvoice = $response->json('data.0');
        expect($firstInvoice['customer'])->toHaveKey('id');
        expect($firstInvoice['customer']['id'])->toBe($firstInvoice['customer_id']);
    });

    it('can include lineItems relationship', function () {
        $invoice = Invoice::factory()->hasLineItems(3)->create();
        Invoice::factory()->count(1)->create();

        $response = $this->getJson('/api/v1/invoices?include=lineItems');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'invoice_number',
                    'line_items' => [
                        '*' => [
                            'id',
                            'invoice_id',
                            'product_name',
                            'quantity',
                            'unit_price',
                            'total_price',
                        ],
                    ],
                ],
            ],
        ]);

        $invoiceData = collect($response->json('data'))->firstWhere('id', $invoice->id);
        expect($invoiceData['line_items'])->toHaveCount(3);
    });

    it('can include both customer and lineItems', function () {
        $invoice = Invoice::factory()->hasLineItems(2)->create();

        $response = $this->getJson('/api/v1/invoices?include=customer,lineItems');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'invoice_number',
                    'customer_id',
                    'customer' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'line_items' => [
                        '*' => [
                            'id',
                            'product_name',
                        ],
                    ],
                ],
            ],
        ]);

        $firstInvoice = $response->json('data.0');
        expect($firstInvoice)->toHaveKey('customer');
        expect($firstInvoice)->toHaveKey('line_items');
        expect($firstInvoice['customer']['id'])->toBe($firstInvoice['customer_id']);
    });

    it('pagination works correctly', function () {
        Invoice::factory()->count(15)->create();

        $response = $this->getJson('/api/v1/invoices?per_page=5');

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

    it('returns empty array when no invoices exist', function () {
        $response = $this->getJson('/api/v1/invoices');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    });
});
describe('Invoice ID Filters', function () {
    it('can filter invoices by id equals', function () {
        $invoice = Invoice::factory()->create();
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'id',
            'op' => 'equals',
            'value' => $invoice->id,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $invoice->id);
    });

    it('can filter invoices by id not equals', function () {
        $invoice = Invoice::factory()->create();
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'id',
            'op' => 'notEquals',
            'value' => $invoice->id,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter invoices by id in', function () {
        $invoices = Invoice::factory()->count(3)->create();
        Invoice::factory()->count(2)->create();

        $ids = $invoices->pluck('id')->implode(',');
        $query = buildFilterQuery([[
            'key' => 'id',
            'op' => 'in',
            'value' => $ids,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter invoices by id not in', function () {
        $invoices = Invoice::factory()->count(2)->create();
        Invoice::factory()->count(3)->create();

        $ids = $invoices->pluck('id')->implode(',');
        $query = buildFilterQuery([[
            'key' => 'id',
            'op' => 'notIn',
            'value' => $ids,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });
});

describe('Invoice String Filters', function () {
    it('can filter invoices by invoice_number equals', function () {
        $invoice = Invoice::factory()->create(['invoice_number' => 'INV-001']);
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'invoice_number',
            'op' => 'equals',
            'value' => 'INV-001',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.invoice_number', 'INV-001');
    });

    it('can filter invoices by invoice_number not equals', function () {
        Invoice::factory()->create(['invoice_number' => 'INV-001']);
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'invoice_number',
            'op' => 'notEquals',
            'value' => 'INV-001',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter invoices by invoice_number contains', function () {
        Invoice::factory()->create(['invoice_number' => 'INV-001']);
        Invoice::factory()->create(['invoice_number' => 'INV-002']);
        Invoice::factory()->create(['invoice_number' => 'PO-001']);

        $query = buildFilterQuery([[
            'key' => 'invoice_number',
            'op' => 'contains',
            'value' => 'INV',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter invoices by invoice_number starts_with', function () {
        Invoice::factory()->create(['invoice_number' => 'INV-001']);
        Invoice::factory()->create(['invoice_number' => 'INV-002']);
        Invoice::factory()->create(['invoice_number' => 'PO-001']);

        $query = buildFilterQuery([[
            'key' => 'invoice_number',
            'op' => 'startsWith',
            'value' => 'INV',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter invoices by invoice_number ends_with', function () {
        Invoice::factory()->create(['invoice_number' => 'INV-001']);
        Invoice::factory()->create(['invoice_number' => 'INV-002']);
        Invoice::factory()->create(['invoice_number' => 'PO-001']);

        $query = buildFilterQuery([[
            'key' => 'invoice_number',
            'op' => 'endsWith',
            'value' => '001',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter invoices by status equals', function () {
        Invoice::factory()->count(2)->paid()->create();
        Invoice::factory()->count(3)->draft()->create();

        $query = buildFilterQuery([[
            'key' => 'status',
            'op' => 'equals',
            'value' => 'paid',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter invoices by status in', function () {
        Invoice::factory()->count(2)->paid()->create();
        Invoice::factory()->count(3)->draft()->create();
        Invoice::factory()->count(1)->cancelled()->create();

        $query = buildFilterQuery([[
            'key' => 'status',
            'op' => 'in',
            'value' => 'paid,draft',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    });

    it('can filter invoices by status not in', function () {
        Invoice::factory()->count(2)->paid()->create();
        Invoice::factory()->count(3)->draft()->create();

        $query = buildFilterQuery([[
            'key' => 'status',
            'op' => 'notIn',
            'value' => 'cancelled,overdue',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    });

    it('returns validation error for invalid status value', function () {
        Invoice::factory()->create();

        $query = buildFilterQuery([[
            'key' => 'status',
            'op' => 'equals',
            'value' => 'invalid_status',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    });

    it('returns validation error for invalid status value in array', function () {
        Invoice::factory()->create();

        $query = buildFilterQuery([[
            'key' => 'status',
            'op' => 'in',
            'value' => 'paid,invalid_status',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    });
});

describe('Invoice Number Filters', function () {
    it('can filter invoices by total_amount equals', function () {
        Invoice::factory()->create(['total_amount' => 1000.00]);
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'total_amount',
            'op' => 'equals',
            'value' => 1000.00,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });

    it('can filter invoices by total_amount greater than', function () {
        Invoice::factory()->create(['total_amount' => 1000.00]);
        Invoice::factory()->create(['total_amount' => 2000.00]);
        Invoice::factory()->create(['total_amount' => 500.00]);

        $query = buildFilterQuery([[
            'key' => 'total_amount',
            'op' => 'greaterThan',
            'value' => 1500,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        expect($response->json('data.0.total_amount'))->toBeGreaterThan(1500);
    });

    it('can filter invoices by total_amount less than', function () {
        Invoice::factory()->create(['total_amount' => 1000.00]);
        Invoice::factory()->create(['total_amount' => 2000.00]);
        Invoice::factory()->create(['total_amount' => 500.00]);

        $query = buildFilterQuery([[
            'key' => 'total_amount',
            'op' => 'lessThan',
            'value' => 1500,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter invoices by total_amount greater than or equals', function () {
        Invoice::factory()->create(['total_amount' => 1000.00]);
        Invoice::factory()->create(['total_amount' => 1500.00]);
        Invoice::factory()->create(['total_amount' => 2000.00]);

        $query = buildFilterQuery([[
            'key' => 'total_amount',
            'op' => 'greaterThanOrEquals',
            'value' => 1500,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter invoices by total_amount less than or equals', function () {
        Invoice::factory()->create(['total_amount' => 1000.00]);
        Invoice::factory()->create(['total_amount' => 1500.00]);
        Invoice::factory()->create(['total_amount' => 2000.00]);

        $query = buildFilterQuery([[
            'key' => 'total_amount',
            'op' => 'lessThanOrEquals',
            'value' => 1500,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });
});

describe('Invoice Date Filters', function () {
    it('can filter invoices by issued_at equals', function () {
        $date = today();
        Invoice::factory()->create(['issued_at' => $date]);
        Invoice::factory()->count(3)->create(['issued_at' => $date->copy()->addDays(5)]);

        $query = buildFilterQuery([[
            'key' => 'issued_at',
            'op' => 'equals',
            'value' => $date->toDateString(),
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });

    it('can filter invoices by issued_at greater than', function () {
        $date = today();
        Invoice::factory()->create(['issued_at' => $date->copy()->addDays(10)]);
        Invoice::factory()->create(['issued_at' => $date->copy()->addDays(15)]);
        Invoice::factory()->create(['issued_at' => $date->copy()->subDays(5)]);

        $query = buildFilterQuery([[
            'key' => 'issued_at',
            'op' => 'greaterThan',
            'value' => $date->toDateString(),
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter invoices by issued_at less than', function () {
        $date = today();
        Invoice::factory()->create(['issued_at' => $date->copy()->subDays(10)]);
        Invoice::factory()->create(['issued_at' => $date->copy()->subDays(5)]);
        Invoice::factory()->create(['issued_at' => $date->copy()->addDays(5)]);

        $query = buildFilterQuery([[
            'key' => 'issued_at',
            'op' => 'lessThan',
            'value' => $date->toDateString(),
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter invoices by due_at greater than or equals', function () {
        $date = today();
        Invoice::factory()->create(['due_at' => $date]);
        Invoice::factory()->create(['due_at' => $date->copy()->addDays(5)]);
        Invoice::factory()->create(['due_at' => $date->copy()->subDays(5)]);

        $query = buildFilterQuery([[
            'key' => 'due_at',
            'op' => 'greaterThanOrEquals',
            'value' => $date->toDateString(),
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter invoices by paid_at not equals', function () {
        $date = today();
        Invoice::factory()->paid()->create(['paid_at' => $date]);
        Invoice::factory()->paid()->count(2)->create(['paid_at' => $date->copy()->addDays(5)]);
        Invoice::factory()->draft()->count(1)->create(['paid_at' => null]);

        $query = buildFilterQuery([[
            'key' => 'paid_at',
            'op' => 'notEquals',
            'value' => $date->toDateString(),
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });
});

describe('Invoice Date Range Filters (Between)', function () {
    it('can filter invoices by issued_at between two dates', function () {
        $startDate = today()->subDays(20);
        $endDate = $startDate->copy()->addDays(10);

        // Inside the range (inclusive)
        Invoice::factory()->create(['issued_at' => $startDate->copy()]); // On start boundary
        Invoice::factory()->create(['issued_at' => $startDate->copy()->addDays(5)]);
        Invoice::factory()->create(['issued_at' => $endDate->copy()]); // On end boundary

        // Outside the range
        Invoice::factory()->create(['issued_at' => $startDate->copy()->subDays(1)]);
        Invoice::factory()->create(['issued_at' => $endDate->copy()->addDays(1)]);

        $query = buildFilterQuery([[
            'key' => 'issued_at',
            'op' => 'greaterThanOrEquals',
            'value' => $startDate->toDateString(),
        ], [
            'key' => 'issued_at',
            'op' => 'lessThanOrEquals',
            'value' => $endDate->toDateString(),
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter invoices by due_at between two dates with greater than and less than', function () {
        $startDate = today()->subDays(50);
        $endDate = $startDate->copy()->addDays(30);

        // Inside the range (strictly between start and end, excluding boundaries)
        Invoice::factory()->create(['due_at' => $startDate->copy()->addDays(5)]);
        Invoice::factory()->create(['due_at' => $startDate->copy()->addDays(15)]);
        Invoice::factory()->create(['due_at' => $startDate->copy()->addDays(25)]);

        // Outside the range (including boundaries)
        Invoice::factory()->create(['due_at' => $startDate->copy()]);
        Invoice::factory()->create(['due_at' => $endDate->copy()]);

        $query = buildFilterQuery([[
            'key' => 'due_at',
            'op' => 'greaterThan',
            'value' => $startDate->toDateString(),
        ], [
            'key' => 'due_at',
            'op' => 'lessThan',
            'value' => $endDate->toDateString(),
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter invoices by created_at in the last 7 days', function () {
        $sevenDaysAgo = now()->subDays(7)->startOfDay();

        Invoice::factory()->create(['created_at' => now()->subDays(3)]);
        Invoice::factory()->create(['created_at' => now()->subDays(5)]);
        Invoice::factory()->create(['created_at' => now()->subDays(1)]);
        Invoice::factory()->create(['created_at' => now()->subDays(10)]);

        $query = buildFilterQuery([[
            'key' => 'created_at',
            'op' => 'greaterThanOrEquals',
            'value' => $sevenDaysAgo->toDateString(),
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can combine date range with other filters', function () {
        $startDate = today();
        $endDate = $startDate->copy()->addDays(10);
        $customer = Customer::factory()->create(['country' => 'US']);

        Invoice::factory()->paid()->for($customer)->create([
            'issued_at' => $startDate->copy()->addDays(5),
            'total_amount' => 2000,
        ]);
        Invoice::factory()->paid()->for($customer)->create([
            'issued_at' => $startDate->copy()->addDays(7),
            'total_amount' => 3000,
        ]);
        Invoice::factory()->draft()->for($customer)->create([
            'issued_at' => $startDate->copy()->addDays(6),
            'total_amount' => 2500,
        ]);
        Invoice::factory()->paid()->create([
            'issued_at' => $startDate->copy()->addDays(5),
            'total_amount' => 2500,
        ]);

        $query = buildFilterQuery([[
            'key' => 'status',
            'op' => 'equals',
            'value' => 'paid',
        ], [
            'key' => 'issued_at',
            'op' => 'greaterThanOrEquals',
            'value' => $startDate->toDateString(),
        ], [
            'key' => 'issued_at',
            'op' => 'lessThanOrEquals',
            'value' => $endDate->toDateString(),
        ], [
            'key' => 'customer.country',
            'op' => 'equals',
            'value' => 'US',
        ], [
            'key' => 'total_amount',
            'op' => 'greaterThan',
            'value' => 2000,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        expect($response->json('data.0.total_amount'))->toBe(3000);
    });
});

describe('Invoice Relation Filters', function () {
    it('can filter invoices by customer_id equals', function () {
        $customer = Customer::factory()->create();
        Invoice::factory()->count(2)->for($customer)->create();
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'customer_id',
            'op' => 'equals',
            'value' => $customer->id,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter invoices by customer name equals', function () {
        $customer = Customer::factory()->create(['name' => 'Acme Corp']);
        Invoice::factory()->count(2)->for($customer)->create();
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.name',
            'op' => 'equals',
            'value' => 'Acme Corp',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter invoices by customer name contains', function () {
        $customer1 = Customer::factory()->create(['name' => 'Acme Corp']);
        $customer2 = Customer::factory()->create(['name' => 'Acme Industries']);
        Invoice::factory()->for($customer1)->create();
        Invoice::factory()->for($customer2)->create();
        Invoice::factory()->count(2)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.name',
            'op' => 'contains',
            'value' => 'Acme',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter invoices by customer email equals', function () {
        $customer = Customer::factory()->create(['email' => 'john@example.com']);
        Invoice::factory()->count(2)->for($customer)->create();
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.email',
            'op' => 'equals',
            'value' => 'john@example.com',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter invoices by customer country equals', function () {
        $usCustomer = Customer::factory()->create(['country' => 'US']);
        $caCustomer = Customer::factory()->create(['country' => 'CA']);
        Invoice::factory()->count(3)->for($usCustomer)->create();
        Invoice::factory()->count(2)->for($caCustomer)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.country',
            'op' => 'equals',
            'value' => 'US',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter invoices by customer country in', function () {
        $customer1 = Customer::factory()->create(['country' => 'US']);
        $customer2 = Customer::factory()->create(['country' => 'CA']);
        Invoice::factory()->count(2)->for($customer1)->create();
        Invoice::factory()->count(2)->for($customer2)->create();
        Invoice::factory()->count(1)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.country',
            'op' => 'in',
            'value' => 'US,CA',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(4, 'data');
    });

    it('can filter invoices by customer is_active equals true', function () {
        $activeCustomer = Customer::factory()->active()->create();
        $inactiveCustomer = Customer::factory()->inactive()->create();
        Invoice::factory()->count(3)->for($activeCustomer)->create();
        Invoice::factory()->count(2)->for($inactiveCustomer)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.is_active',
            'op' => 'equals',
            'value' => true,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter invoices by customer is_active equals false', function () {
        $activeCustomer = Customer::factory()->active()->create();
        $inactiveCustomer = Customer::factory()->inactive()->create();
        Invoice::factory()->count(3)->for($activeCustomer)->create();
        Invoice::factory()->count(2)->for($inactiveCustomer)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.is_active',
            'op' => 'equals',
            'value' => false,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });
});

describe('Invoice LineItem Relation Filters', function () {
    it('can filter invoices by line item product_name equals', function () {
        $invoice1 = Invoice::factory()->create();
        $invoice2 = Invoice::factory()->create();
        LineItem::factory()->for($invoice1)->create(['product_name' => 'Widget']);
        LineItem::factory()->for($invoice2)->create(['product_name' => 'Gadget']);
        Invoice::factory()->count(2)->create();

        $query = buildFilterQuery([[
            'key' => 'lineItems.product_name',
            'op' => 'equals',
            'value' => 'Widget',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });

    it('can filter invoices by line item product_name contains', function () {
        $invoice1 = Invoice::factory()->create();
        $invoice2 = Invoice::factory()->create();
        LineItem::factory()->for($invoice1)->create(['product_name' => 'Super Widget']);
        LineItem::factory()->for($invoice2)->create(['product_name' => 'Widget Pro']);
        Invoice::factory()->count(1)->create();

        $query = buildFilterQuery([[
            'key' => 'lineItems.product_name',
            'op' => 'contains',
            'value' => 'Widget',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter invoices by line item quantity greater than', function () {
        $invoice1 = Invoice::factory()->create();
        $invoice2 = Invoice::factory()->create();
        LineItem::factory()->for($invoice1)->create(['quantity' => 100]);
        LineItem::factory()->for($invoice2)->create(['quantity' => 50]);

        $query = buildFilterQuery([[
            'key' => 'lineItems.quantity',
            'op' => 'greaterThan',
            'value' => 75,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });

    it('can filter invoices by line item unit_price less than', function () {
        $invoice1 = Invoice::factory()->create();
        $invoice2 = Invoice::factory()->create();
        LineItem::factory()->for($invoice1)->create(['unit_price' => 50.00]);
        LineItem::factory()->for($invoice2)->create(['unit_price' => 150.00]);

        $query = buildFilterQuery([[
            'key' => 'lineItems.unit_price',
            'op' => 'lessThan',
            'value' => 100,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });

    it('can filter invoices by line item total_price greater than or equals', function () {
        $invoice1 = Invoice::factory()->create();
        $invoice2 = Invoice::factory()->create();
        $invoice3 = Invoice::factory()->create();
        LineItem::factory()->for($invoice1)->create(['total_price' => 1000.00]);
        LineItem::factory()->for($invoice2)->create(['total_price' => 500.00]);
        LineItem::factory()->for($invoice3)->create(['total_price' => 1500.00]);

        $query = buildFilterQuery([[
            'key' => 'lineItems.total_price',
            'op' => 'greaterThanOrEquals',
            'value' => 1000,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });
});

describe('Invoice Multiple Filter Combinations', function () {
    it('can filter by multiple fields combined', function () {
        $customer = Customer::factory()->create(['country' => 'US']);
        Invoice::factory()->paid()->for($customer)->create(['total_amount' => 1000.00]);
        Invoice::factory()->paid()->for($customer)->create(['total_amount' => 2000.00]);
        Invoice::factory()->draft()->for($customer)->create(['total_amount' => 1500.00]);
        Invoice::factory()->paid()->create(['total_amount' => 1500.00]);

        $query = buildFilterQuery([[
            'key' => 'status',
            'op' => 'equals',
            'value' => 'paid',
        ], [
            'key' => 'customer.country',
            'op' => 'equals',
            'value' => 'US',
        ], [
            'key' => 'total_amount',
            'op' => 'greaterThan',
            'value' => 1500,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });

    it('can combine customer and line item filters', function () {
        $customer = Customer::factory()->create(['name' => 'Acme Corp']);
        $invoice1 = Invoice::factory()->for($customer)->create();
        $invoice2 = Invoice::factory()->for($customer)->create();
        LineItem::factory()->for($invoice1)->create(['product_name' => 'Widget']);
        LineItem::factory()->for($invoice2)->create(['product_name' => 'Gadget']);
        Invoice::factory()->count(2)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.name',
            'op' => 'contains',
            'value' => 'Acme',
        ], [
            'key' => 'lineItems.product_name',
            'op' => 'equals',
            'value' => 'Widget',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });

    it('can filter by status, date range and customer', function () {
        $date = today();
        $customer = Customer::factory()->create(['country' => 'US']);
        Invoice::factory()->paid()->for($customer)->create(['issued_at' => $date->copy()->addDays(5)]);
        Invoice::factory()->paid()->for($customer)->create(['issued_at' => $date->copy()->addDays(10)]);
        Invoice::factory()->paid()->for($customer)->create(['issued_at' => $date->copy()->addDays(20)]);
        Invoice::factory()->draft()->for($customer)->create(['issued_at' => $date->copy()->addDays(7)]);

        $query = buildFilterQuery([[
            'key' => 'status',
            'op' => 'equals',
            'value' => 'paid',
        ], [
            'key' => 'issued_at',
            'op' => 'greaterThan',
            'value' => $date->toDateString(),
        ], [
            'key' => 'issued_at',
            'op' => 'lessThan',
            'value' => $date->copy()->addDays(15)->toDateString(),
        ], [
            'key' => 'customer.country',
            'op' => 'equals',
            'value' => 'US',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });
});

describe('Invoice Date Null Filters (Legacy 0000-00-00 Support)', function () {
    it('can filter invoices by paid_at isNull (includes NULL)', function () {
        // Create invoices with null paid_at
        Invoice::factory()->count(2)->create(['paid_at' => null]);

        // Create invoices with actual paid_at dates
        Invoice::factory()->create(['paid_at' => today()]);
        Invoice::factory()->create(['paid_at' => today()->subDays(5)]);

        $query = buildFilterQuery([[
            'key' => 'paid_at',
            'op' => 'isNull',
            'value' => null,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        // Verify all returned invoices have null paid_at
        $paidAtValues = collect($response->json('data'))->pluck('paid_at')->toArray();
        foreach ($paidAtValues as $paidAt) {
            expect($paidAt)->toBeNull();
        }
    });

    it('can filter invoices by paid_at isNull (includes legacy 0000-00-00)', function () {
        // Create invoice with null paid_at
        $invoice1 = Invoice::factory()->create(['paid_at' => null]);

        // Create invoices with legacy 0000-00-00 dates using raw DB insert to bypass Laravel date casting
        $invoice2 = Invoice::factory()->create(['paid_at' => now()]);
        $invoice3 = Invoice::factory()->create(['paid_at' => now()]);
        DB::table('invoices')->where('id', $invoice2->id)->update(['paid_at' => '0000-00-00']);
        DB::table('invoices')->where('id', $invoice3->id)->update(['paid_at' => '0000-00-00']);

        // Create invoice with actual paid_at date
        Invoice::factory()->create(['paid_at' => today()]);

        $query = buildFilterQuery([[
            'key' => 'paid_at',
            'op' => 'isNull',
            'value' => null,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        // Verify all returned invoices have null or 0000-00-00 paid_at
        // Note: The JSON resource might format 0000-00-00 differently (e.g., as null or -0001-11-30)
        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->toArray();
        expect($returnedIds)->toContain($invoice1->id);
        expect($returnedIds)->toContain($invoice2->id);
        expect($returnedIds)->toContain($invoice3->id);
    });

    it('can filter invoices by paid_at isNotNull (excludes NULL)', function () {
        // Create invoices with null paid_at
        Invoice::factory()->count(2)->create(['paid_at' => null]);

        // Create invoices with actual paid_at dates
        Invoice::factory()->create(['paid_at' => today()]);
        Invoice::factory()->create(['paid_at' => today()->subDays(5)]);

        $query = buildFilterQuery([[
            'key' => 'paid_at',
            'op' => 'isNotNull',
            'value' => null,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        // Verify all returned invoices have non-null paid_at
        $paidAtValues = collect($response->json('data'))->pluck('paid_at')->toArray();
        foreach ($paidAtValues as $paidAt) {
            expect($paidAt)->not->toBeNull();
        }
    });

    it('can filter invoices by paid_at isNotNull (excludes legacy 0000-00-00)', function () {
        // Create invoice with null paid_at
        Invoice::factory()->create(['paid_at' => null]);

        // Create invoices with legacy 0000-00-00 dates using raw DB insert
        $legacyInvoice1 = Invoice::factory()->create(['paid_at' => now()]);
        $legacyInvoice2 = Invoice::factory()->create(['paid_at' => now()]);
        DB::table('invoices')->where('id', $legacyInvoice1->id)->update(['paid_at' => '0000-00-00']);
        DB::table('invoices')->where('id', $legacyInvoice2->id)->update(['paid_at' => '0000-00-00']);

        // Create invoices with actual paid_at dates
        Invoice::factory()->count(3)->create(['paid_at' => today()]);

        $query = buildFilterQuery([[
            'key' => 'paid_at',
            'op' => 'isNotNull',
            'value' => null,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        // Verify all returned invoices have valid paid_at (not null or 0000-00-00)
        $paidAtValues = collect($response->json('data'))->pluck('paid_at')->toArray();
        foreach ($paidAtValues as $paidAt) {
            expect($paidAt)->not->toBeNull();
            expect($paidAt)->not->toBe('0000-00-00');
        }
    });

    it('returns all invoices when all have null or 0000-00-00 paid_at and filtering by isNull', function () {
        // Create invoices with null paid_at
        Invoice::factory()->count(2)->create(['paid_at' => null]);

        // Create invoices with legacy 0000-00-00 dates using raw DB insert
        $legacy1 = Invoice::factory()->create(['paid_at' => now()]);
        $legacy2 = Invoice::factory()->create(['paid_at' => now()]);
        DB::table('invoices')->where('id', $legacy1->id)->update(['paid_at' => '0000-00-00']);
        DB::table('invoices')->where('id', $legacy2->id)->update(['paid_at' => '0000-00-00']);

        $query = buildFilterQuery([[
            'key' => 'paid_at',
            'op' => 'isNull',
            'value' => null,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(4, 'data');
    });

    it('returns empty result when all have null or 0000-00-00 paid_at and filtering by isNotNull', function () {
        // Create invoices with null paid_at
        Invoice::factory()->count(2)->create(['paid_at' => null]);

        // Create invoices with legacy 0000-00-00 dates using raw DB insert
        $legacy1 = Invoice::factory()->create(['paid_at' => now()]);
        $legacy2 = Invoice::factory()->create(['paid_at' => now()]);
        DB::table('invoices')->where('id', $legacy1->id)->update(['paid_at' => '0000-00-00']);
        DB::table('invoices')->where('id', $legacy2->id)->update(['paid_at' => '0000-00-00']);

        $query = buildFilterQuery([[
            'key' => 'paid_at',
            'op' => 'isNotNull',
            'value' => null,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    });

    it('can combine paid_at isNull filter with other filters', function () {
        // Create invoices with varying data
        Invoice::factory()->paid()->create(['paid_at' => null, 'total_amount' => 1000]);

        // Create legacy date invoice
        $legacyInvoice = Invoice::factory()->paid()->create(['paid_at' => now(), 'total_amount' => 2000]);
        DB::table('invoices')->where('id', $legacyInvoice->id)->update(['paid_at' => '0000-00-00']);

        Invoice::factory()->paid()->create(['paid_at' => today(), 'total_amount' => 1500]);
        Invoice::factory()->draft()->create(['paid_at' => null, 'total_amount' => 1500]);

        $query = buildFilterQuery([
            [
                'key' => 'paid_at',
                'op' => 'isNull',
                'value' => null,
            ],
            [
                'key' => 'status',
                'op' => 'equals',
                'value' => 'paid',
            ],
        ]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        // Verify all are paid status - checking that the filter logic works correctly
        foreach ($response->json('data') as $invoice) {
            expect($invoice['status'])->toBe('paid');
            // The paid_at should be null or a legacy date (JSON formatting may vary)
        }
    });

    it('can combine paid_at isNotNull filter with other filters', function () {
        // Create invoices with varying data
        Invoice::factory()->paid()->create(['paid_at' => today(), 'total_amount' => 1000]);
        Invoice::factory()->paid()->create(['paid_at' => today(), 'total_amount' => 2000]);
        Invoice::factory()->paid()->create(['paid_at' => null, 'total_amount' => 1500]);
        Invoice::factory()->paid()->create(['paid_at' => '0000-00-00', 'total_amount' => 1500]);

        $query = buildFilterQuery([
            [
                'key' => 'paid_at',
                'op' => 'isNotNull',
                'value' => null,
            ],
            [
                'key' => 'total_amount',
                'op' => 'greaterThan',
                'value' => 1500,
            ],
        ]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $invoice = $response->json('data.0');
        expect($invoice['paid_at'])->not->toBeNull();
        expect($invoice['paid_at'])->not->toBe('0000-00-00');
        expect($invoice['total_amount'])->toBeGreaterThan(1500);
    });

    it('can filter by paid_at isNull without passing value key', function () {
        // Create invoices with null and legacy dates
        Invoice::factory()->create(['paid_at' => null]);

        $legacyInvoice = Invoice::factory()->create(['paid_at' => now()]);
        DB::table('invoices')->where('id', $legacyInvoice->id)->update(['paid_at' => '0000-00-00']);

        Invoice::factory()->create(['paid_at' => today()]);

        // Build filter without 'value' key
        $filters = [[
            'key' => 'paid_at',
            'op' => 'isNull',
        ]];

        $query = http_build_query(['filter' => json_encode($filters)]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });
});

describe('Invoice String Null Filters (Legacy Empty String Support)', function () {
    it('can filter invoices by customer phone isNull (includes empty string)', function () {
        // Create customer with null phone
        $customer1 = Customer::factory()->create(['phone' => null]);

        // Create customers with empty string phone (legacy data)
        $customer2 = Customer::factory()->create(['phone' => '']);
        $customer3 = Customer::factory()->create(['phone' => '']);

        // Create customer with actual phone
        $customer4 = Customer::factory()->create(['phone' => '+1234567890']);

        Invoice::factory()->for($customer1)->create();
        Invoice::factory()->for($customer2)->create();
        Invoice::factory()->for($customer3)->create();
        Invoice::factory()->for($customer4)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.phone',
            'op' => 'isNull',
            'value' => null,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });

    it('can filter invoices by customer phone isNotNull (excludes empty string)', function () {
        // Create customer with null phone
        $customer1 = Customer::factory()->create(['phone' => null]);

        // Create customers with empty string phone
        $customer2 = Customer::factory()->create(['phone' => '']);

        // Create customers with actual phone
        $customer3 = Customer::factory()->create(['phone' => '+1234567890']);
        $customer4 = Customer::factory()->create(['phone' => '+0987654321']);

        Invoice::factory()->for($customer1)->create();
        Invoice::factory()->for($customer2)->create();
        Invoice::factory()->for($customer3)->create();
        Invoice::factory()->for($customer4)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.phone',
            'op' => 'isNotNull',
            'value' => null,
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can combine customer phone isNull filter with other filters', function () {
        // Create customers with varying data
        $customer1 = Customer::factory()->create(['phone' => null, 'country' => 'US']);
        $customer2 = Customer::factory()->create(['phone' => '', 'country' => 'US']);
        $customer3 = Customer::factory()->create(['phone' => '+1234567890', 'country' => 'US']);
        $customer4 = Customer::factory()->create(['phone' => null, 'country' => 'CA']);

        Invoice::factory()->for($customer1)->create();
        Invoice::factory()->for($customer2)->create();
        Invoice::factory()->for($customer3)->create();
        Invoice::factory()->for($customer4)->create();

        $query = buildFilterQuery([
            [
                'key' => 'customer.phone',
                'op' => 'isNull',
                'value' => null,
            ],
            [
                'key' => 'customer.country',
                'op' => 'equals',
                'value' => 'US',
            ],
        ]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by customer phone isNull without passing value key', function () {
        // Create customers with null and empty phone
        $customer1 = Customer::factory()->create(['phone' => null]);
        $customer2 = Customer::factory()->create(['phone' => '']);
        $customer3 = Customer::factory()->create(['phone' => '+1234567890']);

        Invoice::factory()->for($customer1)->create();
        Invoice::factory()->for($customer2)->create();
        Invoice::factory()->for($customer3)->create();

        // Build filter without 'value' key
        $filters = [[
            'key' => 'customer.phone',
            'op' => 'isNull',
        ]];

        $query = http_build_query(['filter' => json_encode($filters)]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });
});

describe('Invoice Filter Edge Cases', function () {
    it('returns empty result when no matches found', function () {
        Invoice::factory()->count(3)->create();

        $query = buildFilterQuery([[
            'key' => 'invoice_number',
            'op' => 'equals',
            'value' => 'NONEXISTENT',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    });

    it('handles null date filters correctly', function () {
        Invoice::factory()->count(2)->draft()->create(['paid_at' => null]);
        Invoice::factory()->count(1)->paid()->create();

        $query = buildFilterQuery([[
            'key' => 'paid_at',
            'op' => 'equals',
            'value' => '',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
    });

    it('can include relations while filtering', function () {
        $customer = Customer::factory()->create(['name' => 'Acme Corp']);
        $invoice = Invoice::factory()->for($customer)->create();
        LineItem::factory()->count(3)->for($invoice)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.name',
            'op' => 'equals',
            'value' => 'Acme Corp',
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}&include=customer,lineItems");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'customer' => ['id', 'name', 'email'],
                    'line_items' => [
                        '*' => ['id', 'product_name', 'quantity'],
                    ],
                ],
            ],
        ]);
    });
});

describe('Invoice Multiple Filters on Same Property', function () {
    it('can filter by same property with multiple conditions', function () {
        $date1 = today();
        $date2 = $date1->copy()->addDays(10);
        $date3 = $date1->copy()->addDays(20);

        Invoice::factory()->create(['issued_at' => $date1]);
        Invoice::factory()->create(['issued_at' => $date2->copy()->addDays(2)]);
        Invoice::factory()->create(['issued_at' => $date2->copy()->addDays(5)]);
        Invoice::factory()->create(['issued_at' => $date3]);

        $query = buildFilterQuery([[
            'key' => 'issued_at',
            'op' => 'greaterThanOrEquals',
            'value' => $date2->toDateString(),
        ], [
            'key' => 'issued_at',
            'op' => 'lessThan',
            'value' => $date3->toDateString(),
        ]]);
        $response = $this->getJson("/api/v1/invoices?{$query}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by relation property with multiple conditions', function () {
        $customer1 = Customer::factory()->create(['name' => 'Acme Corporation']);
        $customer2 = Customer::factory()->create(['name' => 'Acme Industries']);
        $customer3 = Customer::factory()->create(['name' => 'Tech Corporation']);
        $customer4 = Customer::factory()->create(['name' => 'Beta Corp']);

        Invoice::factory()->for($customer1)->create();
        Invoice::factory()->for($customer2)->create();
        Invoice::factory()->for($customer3)->create();
        Invoice::factory()->for($customer4)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.name',
            'op' => 'contains',
            'value' => 'Acme',
        ], [
            'key' => 'customer.name',
            'op' => 'startsWith',
            'value' => 'Acme C',
        ]]);

        $response = $this->getJson("/api/v1/invoices?{$query}");
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });

    it('can filter by same property with 3+ conditions', function () {
        Invoice::factory()->create(['total_amount' => 500]);
        Invoice::factory()->create(['total_amount' => 1500]);
        Invoice::factory()->create(['total_amount' => 2500]);
        Invoice::factory()->create(['total_amount' => 3500]);
        Invoice::factory()->create(['total_amount' => 4500]);

        $query = buildFilterQuery([[
            'key' => 'total_amount',
            'op' => 'greaterThan',
            'value' => 1000,
        ], [
            'key' => 'total_amount',
            'op' => 'lessThan',
            'value' => 4000,
        ], [
            'key' => 'total_amount',
            'op' => 'notEquals',
            'value' => 2500,
        ]]);

        $response = $this->getJson("/api/v1/invoices?{$query}");
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    });

    it('can filter by relation property with mixed operators', function () {
        $customer1 = Customer::factory()->create(['name' => 'Acme Corporation', 'country' => 'US']);
        $customer2 = Customer::factory()->create(['name' => 'Acme Industries', 'country' => 'CA']);
        $customer3 = Customer::factory()->create(['name' => 'Tech Corporation', 'country' => 'US']);

        Invoice::factory()->for($customer1)->create();
        Invoice::factory()->for($customer2)->create();
        Invoice::factory()->for($customer3)->create();

        $query = buildFilterQuery([[
            'key' => 'customer.name',
            'op' => 'contains',
            'value' => 'Acme',
        ], [
            'key' => 'customer.country',
            'op' => 'equals',
            'value' => 'US',
        ]]);

        $response = $this->getJson("/api/v1/invoices?{$query}");
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });
});
