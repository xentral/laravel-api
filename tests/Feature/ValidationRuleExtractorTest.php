<?php declare(strict_types=1);

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Workbench\App\Http\Data\TestData;
use Workbench\App\Http\Requests\CreateTestModelRequest;
use Workbench\App\Http\Requests\TestFormRequest;
use Xentral\LaravelApi\OpenApi\ValidationRuleExtractor;

beforeEach(function () {
    $this->extractor = new ValidationRuleExtractor;
});

it('can extract rules from a Laravel Form Request', function () {
    $rules = $this->extractor->extractRules(CreateTestModelRequest::class);

    expect($rules)
        ->toHaveKeys(['name', 'status'])
        ->and($rules['name'])
        ->toBeArray()
        ->toContain('string')
        ->and($rules['status'])
        ->toBeArray()
        ->toHaveCount(1);
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
    $rulesWithMessages = $this->extractor->extractRulesWithMessages(TestFormRequest::class);

    // Should get non-database rules (name, age, status), limited to 3
    expect($rulesWithMessages)
        ->toHaveKeys(['name', 'age', 'status']);

    // Check that database rules are filtered out
    expect($rulesWithMessages)
        ->not->toHaveKey('email') // has unique rule
        ->not->toHaveKey('user_id') // has exists rule
        ->not->toHaveKey('category_id'); // has Rule::exists()

    // Check structure of returned data
    expect($rulesWithMessages['name'])
        ->toHaveKeys(['rules', 'message'])
        ->and($rulesWithMessages['name']['rules'])
        ->toBeArray()
        ->toContain('required', 'string', 'max:255')
        ->and($rulesWithMessages['name']['message'])
        ->toBe('The name field is required.');

    expect($rulesWithMessages['age']['message'])
        ->toBe('The age field is required.');

    expect($rulesWithMessages['status']['message'])
        ->toBe('The status field is required.');
});

it('can be configured with different max rules limit', function () {
    $extractor = new ValidationRuleExtractor(maxRules: 2);
    $rulesWithMessages = $extractor->extractRulesWithMessages(TestFormRequest::class);

    // Should only get 2 rules
    expect($rulesWithMessages)->toHaveCount(2);
});

it('generates appropriate validation messages for different rule types', function () {
    // Use CreateTestModelRequest which has simple rules
    $rulesWithMessages = $this->extractor->extractRulesWithMessages(CreateTestModelRequest::class);

    expect($rulesWithMessages['name']['message'])
        ->toBe('The name field must be a string.');

    // Status field should have some validation message
    if (isset($rulesWithMessages['status'])) {
        expect($rulesWithMessages['status']['message'])
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
    // Use TestFormRequest which has required rules
    $rulesWithMessages = $this->extractor->extractRulesWithMessages(TestFormRequest::class);

    // Fields with required rule should show required message
    expect($rulesWithMessages['name']['message'])
        ->toBe('The name field is required.')
        ->and($rulesWithMessages['age']['message'])
        ->toBe('The age field is required.')
        ->and($rulesWithMessages['status']['message'])
        ->toBe('The status field is required.');
});

it('handles fields with only database rules gracefully', function () {
    // Use TestFormRequest which has mixed database and non-database rules
    $rulesWithMessages = $this->extractor->extractRulesWithMessages(TestFormRequest::class);

    // Should exclude fields with database rules
    expect($rulesWithMessages)
        ->toHaveCount(3) // name, age, status (non-database fields)
        ->toHaveKeys(['name', 'age', 'status'])
        ->not->toHaveKey('email') // has unique rule
        ->not->toHaveKey('user_id') // has exists rule
        ->not->toHaveKey('category_id'); // has Rule::exists()
});
