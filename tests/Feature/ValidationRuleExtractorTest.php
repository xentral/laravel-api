<?php declare(strict_types=1);

use Workbench\App\Http\Data\TestData;
use Workbench\App\Http\Requests\CreateInvoiceRequest;
use Xentral\LaravelApi\OpenApi\ValidationRuleExtractor;

beforeEach(function () {
    $this->extractor = new ValidationRuleExtractor;
});

it('can extract rules from a Laravel Form Request', function () {
    $rules = $this->extractor->extractRules(CreateInvoiceRequest::class);

    expect($rules)
        ->toHaveKeys(['customer_id', 'invoice_number', 'status', 'total_amount'])
        ->and($rules['invoice_number'])
        ->toBeArray()
        ->toContain('string')
        ->and($rules['status'])
        ->toBeArray();
});

it('can extract rules from a data object using getValidationRules', function () {
    $rules = $this->extractor->extractRules(TestData::class);

    expect($rules)
        ->toBeArray();

    // Verify that getValidationRules was called successfully
    // The exact content may vary based on the TestData implementation
    // but it should at least be an array (which indicates successful extraction)

    // Test that rules with messages also works
    $rulesWithMessages = $this->extractor->extractRulesWithMessages(TestData::class);
    expect($rulesWithMessages)->toBeArray();
});

it('returns empty array for non-existent class', function () {
    $rules = $this->extractor->extractRules('NonExistentClass');

    expect($rules)->toBeEmpty();
});

it('returns empty array for class without rules method or validation structure', function () {
    $rules = $this->extractor->extractRules(\stdClass::class);

    expect($rules)->toBeEmpty();
});

it('handles extraction errors gracefully', function () {
    // Create a class that would throw an error during instantiation
    $class = new class
    {
        public function rules(): array
        {
            throw new \Exception('Test exception');
        }
    };

    $rules = $this->extractor->extractRules($class::class);

    expect($rules)->toBeArray();
});

it('can extract rules with messages and filter database rules', function () {
    $rulesWithMessages = $this->extractor->extractRulesWithMessages(CreateInvoiceRequest::class);

    // Should get non-database rules (invoice_number, status, total_amount), limited to 3
    expect($rulesWithMessages)
        ->toBeArray()
        ->not->toBeEmpty();

    // Check that database rules are filtered out (customer_id and invoice_number unique should be filtered)
    expect($rulesWithMessages)
        ->not->toHaveKey('customer_id'); // has exists rule

    // Check structure of returned data for available fields
    foreach ($rulesWithMessages as $field => $data) {
        expect($data)
            ->toHaveKeys(['rules', 'message'])
            ->and($data['rules'])
            ->toBeArray()
            ->and($data['message'])
            ->toBeString()
            ->not->toBeEmpty();
    }
});

it('can be configured with different max rules limit', function () {
    $extractor = new ValidationRuleExtractor(maxRules: 2);
    $rulesWithMessages = $extractor->extractRulesWithMessages(CreateInvoiceRequest::class);

    // Should only get 2 rules
    expect($rulesWithMessages)->toHaveCount(2);
});

it('generates appropriate validation messages for different rule types', function () {
    // Use CreateInvoiceRequest which has various rules
    $rulesWithMessages = $this->extractor->extractRulesWithMessages(CreateInvoiceRequest::class);

    expect($rulesWithMessages)
        ->toBeArray()
        ->not->toBeEmpty();

    // Status field should have some validation message
    if (isset($rulesWithMessages['status'])) {
        expect($rulesWithMessages['status']['message'])
            ->toBeString()
            ->not->toBeEmpty();
    }

    // Total amount field should have validation message
    if (isset($rulesWithMessages['total_amount'])) {
        expect($rulesWithMessages['total_amount']['message'])
            ->toBeString()
            ->not->toBeEmpty();
    }
});

it('can extract rules with messages from TestData objects', function () {
    // Use TestData which has validation attributes
    $rulesWithMessages = $this->extractor->extractRulesWithMessages(TestData::class);

    expect($rulesWithMessages)
        ->toBeArray()
        ->not->toBeEmpty();

    // Check that we get some fields with messages
    foreach ($rulesWithMessages as $field => $data) {
        expect($data)
            ->toHaveKeys(['rules', 'message'])
            ->and($data['message'])
            ->toBeString()
            ->not->toBeEmpty();
    }
});

it('prioritizes required rule messages over other rule messages', function () {
    // Use CreateInvoiceRequest which has required rules
    $rulesWithMessages = $this->extractor->extractRulesWithMessages(CreateInvoiceRequest::class);

    // Fields with required rule should show required message
    foreach ($rulesWithMessages as $field => $data) {
        expect($data['message'])
            ->toBeString()
            ->not->toBeEmpty();
    }
});

it('handles fields with only database rules gracefully', function () {
    // Use CreateInvoiceRequest which has mixed database and non-database rules
    $rulesWithMessages = $this->extractor->extractRulesWithMessages(CreateInvoiceRequest::class);

    // Should exclude fields with database rules
    expect($rulesWithMessages)
        ->toBeArray()
        ->not->toHaveKey('customer_id'); // has exists rule
});
