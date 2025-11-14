<?php declare(strict_types=1);

use Workbench\App\Enum\InvoiceStatusEnum;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Invoice;

describe('Invoice Create Operations', function () {
    it('can create invoice with valid data', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2025-001',
            'status' => InvoiceStatusEnum::Draft->value,
            'total_amount' => 1500.00,
            'issued_at' => '2025-01-15',
            'due_at' => '2025-02-15',
        ];

        $response = $this->postJson('/api/v1/invoices', $data);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'invoice_number',
                'status',
                'total_amount',
                'issued_at',
                'due_at',
                'created_at',
                'updated_at',
            ],
        ]);

        expect($response->json('data.invoice_number'))->toBe('INV-2025-001')
            ->and($response->json('data.status'))->toBe(InvoiceStatusEnum::Draft->value)
            ->and((float) $response->json('data.total_amount'))->toBe(1500.00);

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2025-001',
            'status' => InvoiceStatusEnum::Draft->value,
            'total_amount' => 1500.00,
        ]);
    });

    it('returns created invoice with correct structure', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2025-002',
            'status' => InvoiceStatusEnum::Sent->value,
            'total_amount' => 2500.00,
        ];

        $response = $this->postJson('/api/v1/invoices', $data);

        $response->assertStatus(201);
        expect($response->json('data'))->toHaveKeys([
            'id',
            'invoice_number',
            'status',
            'total_amount',
            'issued_at',
            'due_at',
            'paid_at',
            'created_at',
            'updated_at',
        ]);
    });
});

describe('Invoice Create Validation - Required Fields', function () {
    it('fails when customer_id is missing', function () {
        $data = [
            'invoice_number' => 'INV-2025-003',
            'status' => InvoiceStatusEnum::Draft->value,
            'total_amount' => 1000.00,
        ];

        $response = $this->postJson('/api/v1/invoices', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_id']);
    });

    it('fails when customer_id does not exist', function () {
        $data = [
            'customer_id' => 99999,
            'invoice_number' => 'INV-2025-004',
            'status' => InvoiceStatusEnum::Draft->value,
            'total_amount' => 1000.00,
        ];

        $response = $this->postJson('/api/v1/invoices', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_id']);
    });

    it('fails when invoice_number is missing', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'status' => InvoiceStatusEnum::Draft->value,
            'total_amount' => 1000.00,
        ];

        $response = $this->postJson('/api/v1/invoices', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['invoice_number']);
    });

    it('fails when invoice_number is duplicate', function () {
        $customer = Customer::factory()->create();
        Invoice::factory()->for($customer)->create(['invoice_number' => 'INV-DUPLICATE']);

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-DUPLICATE',
            'status' => InvoiceStatusEnum::Draft->value,
            'total_amount' => 1000.00,
        ];

        $response = $this->postJson('/api/v1/invoices', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['invoice_number']);
    });

    it('fails when status is missing', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2025-005',
            'total_amount' => 1000.00,
        ];

        $response = $this->postJson('/api/v1/invoices', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    });

    it('fails when status is not a valid enum value', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2025-006',
            'status' => 'invalid-status',
            'total_amount' => 1000.00,
        ];

        $response = $this->postJson('/api/v1/invoices', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    });

    it('fails when total_amount is missing', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2025-007',
            'status' => InvoiceStatusEnum::Draft->value,
        ];

        $response = $this->postJson('/api/v1/invoices', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['total_amount']);
    });

    it('fails when total_amount is negative', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2025-008',
            'status' => InvoiceStatusEnum::Draft->value,
            'total_amount' => -500.00,
        ];

        $response = $this->postJson('/api/v1/invoices', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['total_amount']);
    });
});

describe('Invoice Create Validation - Optional Date Fields', function () {
    it('accepts null for optional date fields', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2025-009',
            'status' => InvoiceStatusEnum::Draft->value,
            'total_amount' => 1000.00,
            'issued_at' => null,
            'due_at' => null,
            'paid_at' => null,
        ];

        $response = $this->postJson('/api/v1/invoices', $data);

        $response->assertStatus(201);
    });

    it('fails when issued_at has invalid date format', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2025-010',
            'status' => InvoiceStatusEnum::Draft->value,
            'total_amount' => 1000.00,
            'issued_at' => 'invalid-date',
        ];

        $response = $this->postJson('/api/v1/invoices', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['issued_at']);
    });

    it('fails when due_at is before issued_at', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2025-011',
            'status' => InvoiceStatusEnum::Draft->value,
            'total_amount' => 1000.00,
            'issued_at' => '2025-02-15',
            'due_at' => '2025-01-15',
        ];

        $response = $this->postJson('/api/v1/invoices', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['due_at']);
    });

    it('accepts due_at after issued_at', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2025-012',
            'status' => InvoiceStatusEnum::Draft->value,
            'total_amount' => 1000.00,
            'issued_at' => '2025-01-15',
            'due_at' => '2025-02-15',
        ];

        $response = $this->postJson('/api/v1/invoices', $data);

        $response->assertStatus(201);
    });

    it('accepts paid_at with valid date', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2025-013',
            'status' => InvoiceStatusEnum::Paid->value,
            'total_amount' => 1000.00,
            'paid_at' => '2025-01-20',
        ];

        $response = $this->postJson('/api/v1/invoices', $data);

        $response->assertStatus(201);
    });
});

describe('Invoice Create Validation - All Status Values', function () {
    it('can create invoice with Draft status', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-DRAFT-001',
            'status' => InvoiceStatusEnum::Draft->value,
            'total_amount' => 1000.00,
        ];

        $response = $this->postJson('/api/v1/invoices', $data);
        $response->assertStatus(201);
    });

    it('can create invoice with Sent status', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-SENT-001',
            'status' => InvoiceStatusEnum::Sent->value,
            'total_amount' => 1000.00,
        ];

        $response = $this->postJson('/api/v1/invoices', $data);
        $response->assertStatus(201);
    });

    it('can create invoice with Paid status', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-PAID-001',
            'status' => InvoiceStatusEnum::Paid->value,
            'total_amount' => 1000.00,
        ];

        $response = $this->postJson('/api/v1/invoices', $data);
        $response->assertStatus(201);
    });

    it('can create invoice with Overdue status', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-OVERDUE-001',
            'status' => InvoiceStatusEnum::Overdue->value,
            'total_amount' => 1000.00,
        ];

        $response = $this->postJson('/api/v1/invoices', $data);
        $response->assertStatus(201);
    });

    it('can create invoice with Cancelled status', function () {
        $customer = Customer::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-CANCELLED-001',
            'status' => InvoiceStatusEnum::Cancelled->value,
            'total_amount' => 1000.00,
        ];

        $response = $this->postJson('/api/v1/invoices', $data);
        $response->assertStatus(201);
    });
});
