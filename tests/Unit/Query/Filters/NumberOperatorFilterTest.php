<?php declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Workbench\App\Models\Invoice;
use Xentral\LaravelApi\Query\Filters\FilterOperator;
use Xentral\LaravelApi\Query\Filters\NumberOperatorFilter;

describe('NumberOperatorFilter', function () {
    describe('valid numeric values', function () {
        it('accepts integer values', function () {
            Invoice::factory()->create(['total_amount' => 42]);

            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '42'], 'total_amount');

            expect($query->count())->toBe(1);
        });

        it('accepts float values', function () {
            Invoice::factory()->create(['total_amount' => 3.14]);

            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '3.14'], 'total_amount');

            expect($query->count())->toBe(1);
        });

        it('accepts negative values', function () {
            Invoice::factory()->create(['total_amount' => -5]);

            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '-5'], 'total_amount');

            expect($query->count())->toBe(1);
        });

        it('accepts numeric string values', function () {
            Invoice::factory()->create(['total_amount' => 100]);

            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '100'], 'total_amount');

            expect($query->count())->toBe(1);
        });

        it('accepts zero numeric string values', function () {
            Invoice::factory()->create(['total_amount' => 0]);

            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '0'], 'total_amount');

            expect($query->count())->toBe(1);
        });
    });

    describe('invalid numeric values', function () {
        it('throws ValidationException for alphabetic value', function () {
            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => 'abc'], 'total_amount');
        })->throws(ValidationException::class);

        it('throws ValidationException for empty string value', function () {
            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => ''], 'total_amount');
        })->throws(ValidationException::class);

        it('throws ValidationException for alphanumeric value', function () {
            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '12abc'], 'total_amount');
        })->throws(ValidationException::class);

        it('includes filter name and value in error message', function () {
            $filter = new NumberOperatorFilter;
            $query = Invoice::query();

            try {
                $filter($query, ['operator' => 'equals', 'value' => 'abc'], 'total_amount');
            } catch (ValidationException $e) {
                expect($e->errors()['total_amount'][0])
                    ->toContain('abc')
                    ->toContain('total_amount');

                return;
            }

            $this->fail('Expected ValidationException was not thrown');
        });
    });

    describe('isNull and isNotNull operators', function () {
        it('does not require value validation for isNull', function () {
            Invoice::factory()->create(['total_amount' => 30]);

            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'isNull'], 'total_amount');

            expect($query->count())->toBe(0);
        });

        it('does not require value validation for isNotNull', function () {
            Invoice::factory()->create(['total_amount' => 30]);

            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'isNotNull'], 'total_amount');

            expect($query->count())->toBe(1);
        });
    });

    describe('array values validation', function () {
        it('validates all values in an array for equals operator', function () {
            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => ['1', 'abc', '3']], 'total_amount');
        })->throws(ValidationException::class);

        it('accepts valid numeric array values', function () {
            Invoice::factory()->create(['total_amount' => 10]);
            Invoice::factory()->create(['total_amount' => 20]);
            Invoice::factory()->create(['total_amount' => 99]);

            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => ['10', '20']], 'total_amount');

            expect($query->count())->toBe(2);
        });
    });

    describe('comparison operators', function () {
        it('filters with greaterThan operator', function () {
            Invoice::factory()->create(['total_amount' => 10]);
            Invoice::factory()->create(['total_amount' => 30]);

            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'greaterThan', 'value' => '20'], 'total_amount');

            expect($query->count())->toBe(1);
        });

        it('filters with lessThan operator', function () {
            Invoice::factory()->create(['total_amount' => 10]);
            Invoice::factory()->create(['total_amount' => 30]);

            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'lessThan', 'value' => '20'], 'total_amount');

            expect($query->count())->toBe(1);
        });

        it('rejects non-numeric value for greaterThan', function () {
            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'greaterThan', 'value' => 'abc'], 'total_amount');
        })->throws(ValidationException::class);
    });

    describe('unsupported operators', function () {
        it('throws ValidationException for contains operator', function () {
            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'contains', 'value' => '5'], 'total_amount');
        })->throws(ValidationException::class);

        it('throws ValidationException for invalid operator', function () {
            $filter = new NumberOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'invalidOp', 'value' => '5'], 'total_amount');
        })->throws(ValidationException::class);
    });
});
