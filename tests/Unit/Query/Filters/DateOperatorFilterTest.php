<?php declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Workbench\App\Models\Invoice;
use Xentral\LaravelApi\Query\Filters\DateOperatorFilter;

describe('DateOperatorFilter', function () {
    describe('valid date values', function () {
        it('accepts a valid date value', function () {
            Invoice::factory()->create(['issued_at' => '2026-02-16 10:00:00']);

            $filter = new DateOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '2026-02-16'], 'issued_at');

            expect($query->count())->toBe(1);
        });

        it('accepts valid date array values', function () {
            Invoice::factory()->create(['issued_at' => '2026-02-16 10:00:00']);
            Invoice::factory()->create(['issued_at' => '2026-02-17 10:00:00']);
            Invoice::factory()->create(['issued_at' => '2026-02-18 10:00:00']);

            $filter = new DateOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => ['2026-02-16', '2026-02-18']], 'issued_at');

            expect($query->count())->toBe(2);
        });
    });

    describe('invalid date values', function () {
        it('skips filter when value is empty', function () {
            Invoice::factory()->create(['issued_at' => '2026-02-16 10:00:00']);

            $filter = new DateOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => ''], 'issued_at');

            expect($query->count())->toBe(1);
        });

        it('throws ValidationException for non-date string', function () {
            $filter = new DateOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => 'not-a-date'], 'issued_at');
        })->throws(ValidationException::class);

        it('throws ValidationException for invalid month', function () {
            $filter = new DateOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '2025-13-01'], 'issued_at');
        })->throws(ValidationException::class);

        it('throws ValidationException for invalid day', function () {
            $filter = new DateOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '2025-02-30'], 'issued_at');
        })->throws(ValidationException::class);

        it('throws ValidationException for invalid value in array', function () {
            $filter = new DateOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => ['2026-02-16', 'bad', '2026-02-18']], 'issued_at');
        })->throws(ValidationException::class);

        it('includes filter name and value in error message', function () {
            $filter = new DateOperatorFilter('issuedAt');
            $query = Invoice::query();

            try {
                $filter($query, ['operator' => 'equals', 'value' => 'bad-date'], 'issued_at');
            } catch (ValidationException $e) {
                expect($e->errors()['issuedAt'][0])
                    ->toContain('bad-date')
                    ->toContain('issuedAt');

                return;
            }

            $this->fail('Expected ValidationException was not thrown');
        });
    });

    describe('isNull and isNotNull operators', function () {
        it('does not require value validation for isNull', function () {
            Invoice::factory()->create(['issued_at' => '2026-02-16 10:00:00']);

            $filter = new DateOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'isNull'], 'issued_at');

            expect($query->count())->toBe(0);
        });

        it('does not require value validation for isNotNull', function () {
            Invoice::factory()->create(['issued_at' => '2026-02-16 10:00:00']);

            $filter = new DateOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'isNotNull'], 'issued_at');

            expect($query->count())->toBe(1);
        });
    });
});
