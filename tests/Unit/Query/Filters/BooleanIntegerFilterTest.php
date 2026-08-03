<?php declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Workbench\App\Models\Customer;
use Xentral\LaravelApi\Query\Filters\BooleanIntegerFilter;
use Xentral\LaravelApi\Query\Filters\QueryFilter;

function applyBooleanIntegerFilter(mixed $value, string $property = 'is_verified'): int
{
    $filter = new BooleanIntegerFilter($property);
    $query = Customer::query();
    $filter($query, $value, $property);

    return $query->count();
}

describe('BooleanIntegerFilter truthy semantics', function () {
    it('matches every value greater than zero for true, not just one', function () {
        Customer::factory()->create(['is_verified' => 2]);
        Customer::factory()->create(['is_verified' => 1]);
        Customer::factory()->create(['is_verified' => 0]);

        expect(applyBooleanIntegerFilter(['operator' => 'equals', 'value' => true]))->toBe(2);
    });

    it('does not match a value greater than one for false', function () {
        Customer::factory()->create(['is_verified' => 2]);
        Customer::factory()->create(['is_verified' => 0]);

        expect(applyBooleanIntegerFilter(['operator' => 'equals', 'value' => false]))->toBe(1);
    });

    it('matches null for false, mirroring what the (bool) cast shows in the payload', function () {
        Customer::factory()->create(['is_archived' => null]);
        Customer::factory()->create(['is_archived' => 0]);
        Customer::factory()->create(['is_archived' => 1]);

        expect(applyBooleanIntegerFilter(['operator' => 'equals', 'value' => false], 'is_archived'))->toBe(2);
    });

    it('does not match null for true', function () {
        Customer::factory()->create(['is_archived' => null]);
        Customer::factory()->create(['is_archived' => 3]);

        expect(applyBooleanIntegerFilter(['operator' => 'equals', 'value' => true], 'is_archived'))->toBe(1);
    });

    it('inverts notEquals true to the falsy predicate, including null', function () {
        Customer::factory()->create(['is_archived' => null]);
        Customer::factory()->create(['is_archived' => 0]);
        Customer::factory()->create(['is_archived' => 2]);

        expect(applyBooleanIntegerFilter(['operator' => 'notEquals', 'value' => true], 'is_archived'))->toBe(2);
    });

    it('inverts notEquals false to the truthy predicate', function () {
        Customer::factory()->create(['is_archived' => null]);
        Customer::factory()->create(['is_archived' => 0]);
        Customer::factory()->create(['is_archived' => 2]);

        expect(applyBooleanIntegerFilter(['operator' => 'notEquals', 'value' => false], 'is_archived'))->toBe(1);
    });

    it('keeps the null branch grouped so it cannot widen a surrounding constraint', function () {
        Customer::factory()->create(['country' => 'DE', 'is_archived' => null]);
        Customer::factory()->create(['country' => 'AT', 'is_archived' => null]);

        $filter = new BooleanIntegerFilter('is_archived');
        $query = Customer::query()->where('country', 'DE');
        $filter($query, ['operator' => 'equals', 'value' => false], 'is_archived');

        expect($query->count())->toBe(1);
    });
});

describe('BooleanIntegerFilter value coercion', function () {
    it('still accepts every documented spelling', function (mixed $value, int $expected) {
        Customer::factory()->create(['is_verified' => 2]);
        Customer::factory()->create(['is_verified' => 0]);

        expect(applyBooleanIntegerFilter(['operator' => 'equals', 'value' => $value]))->toBe($expected);
    })->with([
        [true, 1],
        [1, 1],
        ['1', 1],
        ['true', 1],
        [false, 1],
        [0, 1],
        ['0', 1],
        ['false', 1],
    ]);

    it('keeps the documented message for an invalid value', function () {
        try {
            applyBooleanIntegerFilter(['operator' => 'equals', 'value' => 'maybe']);
        } catch (ValidationException $e) {
            expect($e->errors()['is_verified'][0])->toBe('Invalid value: maybe. Valid values are: true, false.');

            return;
        }

        $this->fail('Expected a ValidationException');
    });

    it('keeps the documented message for an unsupported operator', function () {
        try {
            applyBooleanIntegerFilter(['operator' => 'in', 'value' => true]);
        } catch (ValidationException $e) {
            expect($e->errors()['is_verified'][0])->toBe("Unsupported operator: in. Use 'equals' or 'notEquals'.");

            return;
        }

        $this->fail('Expected a ValidationException');
    });
});

describe('BooleanIntegerFilter error naming', function () {
    it('keys an invalid value error by the API filter name, not the internal column', function () {
        $filter = new BooleanIntegerFilter('isVariant');
        $query = Customer::query();

        try {
            $filter($query, ['operator' => 'equals', 'value' => 'maybe'], 'variante');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('isVariant')
                ->and($e->errors())->not->toHaveKey('variante');

            return;
        }

        $this->fail('Expected a ValidationException');
    });

    it('keys an unsupported operator error by the API filter name, not the internal column', function () {
        $filter = new BooleanIntegerFilter('isVariant');
        $query = Customer::query();

        try {
            $filter($query, ['operator' => 'in', 'value' => true], 'variante');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('isVariant')
                ->and($e->errors())->not->toHaveKey('variante');

            return;
        }

        $this->fail('Expected a ValidationException');
    });

    it('keys errors by the API name when wired through QueryFilter::booleanInteger', function () {
        $filter = QueryFilter::booleanInteger('isVariant', 'variante')->getFilterClass();
        $query = Customer::query();

        try {
            $filter($query, ['operator' => 'equals', 'value' => 'maybe'], 'variante');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('isVariant')
                ->and($e->errors())->not->toHaveKey('variante');

            return;
        }

        $this->fail('Expected a ValidationException');
    });
});

describe('BooleanIntegerFilter repeated filter keys', function () {
    it('applies every entry of a repeated key conjunctively', function () {
        Customer::factory()->create(['is_verified' => 2]);
        Customer::factory()->create(['is_verified' => 0]);

        $contradicting = [
            ['operator' => 'equals', 'value' => true],
            ['operator' => 'equals', 'value' => false],
        ];

        expect(applyBooleanIntegerFilter($contradicting))->toBe(0);
    });

    it('applies a repeated key that agrees with itself', function () {
        Customer::factory()->create(['is_verified' => 2]);
        Customer::factory()->create(['is_verified' => 0]);

        $agreeing = [
            ['operator' => 'equals', 'value' => true],
            ['operator' => 'notEquals', 'value' => false],
        ];

        expect(applyBooleanIntegerFilter($agreeing))->toBe(1);
    });
});

describe('BooleanIntegerFilter missing value key', function () {
    it('rejects a filter without a value instead of raising an undefined key warning', function () {
        $filter = new BooleanIntegerFilter('isVerified');
        $query = Customer::query();

        try {
            $filter($query, ['operator' => 'equals'], 'is_verified');
        } catch (ValidationException $e) {
            expect($e->errors()['isVerified'][0])->toBe('Invalid value: NULL. Valid values are: true, false.');

            return;
        }

        $this->fail('Expected a ValidationException');
    });
});
