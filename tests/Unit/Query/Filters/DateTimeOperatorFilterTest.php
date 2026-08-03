<?php declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Workbench\App\Models\Invoice;
use Xentral\LaravelApi\Query\Filters\DateTimeOperatorFilter;

describe('DateTimeOperatorFilter', function () {
    describe('time precision is preserved', function () {
        it('filters with greaterThan preserving time precision', function () {
            Invoice::factory()->create(['issued_at' => '2026-02-16 09:00:00']);
            Invoice::factory()->create(['issued_at' => '2026-02-16 11:00:00']);

            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'greaterThan', 'value' => '2026-02-16 10:00:00'], 'issued_at');

            expect($query->count())->toBe(1)
                ->and($query->first()->issued_at->format('H:i:s'))->toBe('11:00:00');
        });

        it('filters with lessThan preserving time precision', function () {
            Invoice::factory()->create(['issued_at' => '2026-02-16 09:00:00']);
            Invoice::factory()->create(['issued_at' => '2026-02-16 11:00:00']);

            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'lessThan', 'value' => '2026-02-16 10:00:00'], 'issued_at');

            expect($query->count())->toBe(1)
                ->and($query->first()->issued_at->format('H:i:s'))->toBe('09:00:00');
        });

        it('filters with equals preserving time precision', function () {
            Invoice::factory()->create(['issued_at' => '2026-02-16 10:00:00']);
            Invoice::factory()->create(['issued_at' => '2026-02-16 10:30:00']);

            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '2026-02-16 10:00:00'], 'issued_at');

            expect($query->count())->toBe(1)
                ->and($query->first()->issued_at->format('H:i:s'))->toBe('10:00:00');
        });

        it('filters with notEquals preserving time precision', function () {
            Invoice::factory()->create(['issued_at' => '2026-02-16 10:00:00']);
            Invoice::factory()->create(['issued_at' => '2026-02-16 10:30:00']);

            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'notEquals', 'value' => '2026-02-16 10:00:00'], 'issued_at');

            expect($query->count())->toBe(1)
                ->and($query->first()->issued_at->format('H:i:s'))->toBe('10:30:00');
        });

        it('filters with greaterThanOrEquals preserving time precision', function () {
            Invoice::factory()->create(['issued_at' => '2026-02-16 09:00:00']);
            Invoice::factory()->create(['issued_at' => '2026-02-16 10:00:00']);
            Invoice::factory()->create(['issued_at' => '2026-02-16 11:00:00']);

            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'greaterThanOrEquals', 'value' => '2026-02-16 10:00:00'], 'issued_at');

            expect($query->count())->toBe(2);
        });

        it('filters with lessThanOrEquals preserving time precision', function () {
            Invoice::factory()->create(['issued_at' => '2026-02-16 09:00:00']);
            Invoice::factory()->create(['issued_at' => '2026-02-16 10:00:00']);
            Invoice::factory()->create(['issued_at' => '2026-02-16 11:00:00']);

            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'lessThanOrEquals', 'value' => '2026-02-16 10:00:00'], 'issued_at');

            expect($query->count())->toBe(2);
        });
    });

    describe('isNull and isNotNull operators', function () {
        it('filters isNull for datetime columns', function () {
            Invoice::factory()->create(['paid_at' => null]);
            Invoice::factory()->create(['paid_at' => '2026-02-16 10:00:00']);

            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'isNull'], 'paid_at');

            expect($query->count())->toBe(1);
        });

        it('filters isNotNull for datetime columns', function () {
            Invoice::factory()->create(['paid_at' => null]);
            Invoice::factory()->create(['paid_at' => '2026-02-16 10:00:00']);

            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'isNotNull'], 'paid_at');

            expect($query->count())->toBe(1);
        });

        it('treats legacy 0000-00-00 00:00:00 as null with isNull', function () {
            Invoice::factory()->create(['paid_at' => null]);
            Invoice::factory()->create(['paid_at' => '2026-02-16 10:00:00']);

            DB::table('invoices')->insert([
                'invoice_number' => 'LEGACY-DT',
                'customer_id' => Invoice::first()->customer_id,
                'status' => 'draft',
                'total_amount' => 0,
                'paid_at' => '0000-00-00 00:00:00',
                'issued_at' => now(),
            ]);

            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'isNull'], 'paid_at');

            expect($query->count())->toBe(2);
        });

        it('treats legacy 0000-00-00 as null with isNull', function () {
            Invoice::factory()->create(['paid_at' => null]);
            Invoice::factory()->create(['paid_at' => '2026-02-16 10:00:00']);

            DB::table('invoices')->insert([
                'invoice_number' => 'LEGACY-D',
                'customer_id' => Invoice::first()->customer_id,
                'status' => 'draft',
                'total_amount' => 0,
                'paid_at' => '0000-00-00',
                'issued_at' => now(),
            ]);

            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'isNull'], 'paid_at');

            expect($query->count())->toBe(2);
        });

        it('excludes legacy 0000-00-00 00:00:00 with isNotNull', function () {
            Invoice::factory()->create(['paid_at' => '2026-02-16 10:00:00']);

            DB::table('invoices')->insert([
                'invoice_number' => 'LEGACY-DT2',
                'customer_id' => Invoice::first()->customer_id,
                'status' => 'draft',
                'total_amount' => 0,
                'paid_at' => '0000-00-00 00:00:00',
                'issued_at' => now(),
            ]);

            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'isNotNull'], 'paid_at');

            expect($query->count())->toBe(1);
        });

        it('excludes legacy 0000-00-00 with isNotNull', function () {
            Invoice::factory()->create(['paid_at' => '2026-02-16 10:00:00']);

            DB::table('invoices')->insert([
                'invoice_number' => 'LEGACY-D2',
                'customer_id' => Invoice::first()->customer_id,
                'status' => 'draft',
                'total_amount' => 0,
                'paid_at' => '0000-00-00',
                'issued_at' => now(),
            ]);

            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'isNotNull'], 'paid_at');

            expect($query->count())->toBe(1);
        });
    });

    describe('array values', function () {
        it('filters equals with multiple datetime values', function () {
            Invoice::factory()->create(['issued_at' => '2026-02-16 09:00:00']);
            Invoice::factory()->create(['issued_at' => '2026-02-16 10:00:00']);
            Invoice::factory()->create(['issued_at' => '2026-02-16 11:00:00']);

            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => ['2026-02-16 09:00:00', '2026-02-16 11:00:00']], 'issued_at');

            expect($query->count())->toBe(2);
        });
    });

    describe('unsupported operators', function () {
        it('throws ValidationException for contains operator', function () {
            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'contains', 'value' => '2026-02-16'], 'issued_at');
        })->throws(ValidationException::class);

        it('throws ValidationException for invalid operator', function () {
            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'invalidOp', 'value' => '2026-02-16'], 'issued_at');
        })->throws(ValidationException::class);
    });

    describe('empty value handling', function () {
        it('skips filter when value is empty', function () {
            Invoice::factory()->create(['issued_at' => '2026-02-16 10:00:00']);

            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => ''], 'issued_at');

            expect($query->count())->toBe(1);
        });
    });

    describe('invalid datetime values', function () {
        it('throws ValidationException for non-datetime string', function () {
            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => 'not-a-datetime'], 'issued_at');
        })->throws(ValidationException::class);

        it('throws ValidationException for invalid month in plain date', function () {
            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '2024-13-01'], 'issued_at');
        })->throws(ValidationException::class);

        it('throws ValidationException for invalid month in datetime', function () {
            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '2025-13-01 10:00:00'], 'issued_at');
        })->throws(ValidationException::class);

        it('throws ValidationException for invalid leap year', function () {
            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '2026-02-29 10:00:00'], 'issued_at');
        })->throws(ValidationException::class);

        it('throws ValidationException for invalid value in array', function () {
            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => ['2026-02-16 10:00:00', 'bad']], 'issued_at');
        })->throws(ValidationException::class);

        it('accepts ISO 8601 format', function () {
            Invoice::factory()->create(['issued_at' => '2026-02-16 10:00:00']);

            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'greaterThan', 'value' => '2026-02-15T10:00:00+00:00'], 'issued_at');

            expect($query->count())->toBe(1);
        });

        it('converts ISO 8601 format to MySQL format for equals comparison', function () {
            Invoice::factory()->create(['issued_at' => '2024-01-02 11:00:00']);
            Invoice::factory()->create(['issued_at' => '2024-01-02 12:00:00']);

            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '2024-01-02T11:00:00+00:00'], 'issued_at');

            expect($query->count())->toBe(1)
                ->and($query->first()->issued_at->format('Y-m-d H:i:s'))->toBe('2024-01-02 11:00:00');
        });

        it('converts ISO 8601 format with timezone offset correctly', function () {
            Invoice::factory()->create(['issued_at' => '2024-01-02 10:00:00']);
            Invoice::factory()->create(['issued_at' => '2024-01-02 12:00:00']);

            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            // +02:00 offset means 12:00:00+02:00 = 10:00:00 UTC
            $filter($query, ['operator' => 'equals', 'value' => '2024-01-02T12:00:00+02:00'], 'issued_at');

            expect($query->count())->toBe(1)
                ->and($query->first()->issued_at->format('Y-m-d H:i:s'))->toBe('2024-01-02 10:00:00');
        });

        it('includes filter name and value in error message', function () {
            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();

            try {
                $filter($query, ['operator' => 'equals', 'value' => 'bad'], 'issued_at');
            } catch (ValidationException $e) {
                expect($e->errors()['issuedAt'][0])
                    ->toContain('bad')
                    ->toContain('issuedAt');

                return;
            }

            $this->fail('Expected ValidationException was not thrown');
        });
    });

    describe('app timezone handling', function () {
        beforeEach(function () {
            config(['app.timezone' => 'Europe/Berlin']);
            date_default_timezone_set('Europe/Berlin');
        });

        afterEach(function () {
            date_default_timezone_set('UTC');
        });

        it('matches a row when filtering equals with the value the API returns', function () {
            Invoice::factory()->create(['issued_at' => '2026-01-15 10:30:00']);

            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '2026-01-15T10:30:00+01:00'], 'issued_at');

            expect($query->count())->toBe(1);
        });

        it('matches a row when filtering equals with the documented zoneless format', function () {
            Invoice::factory()->create(['issued_at' => '2026-01-15 10:30:00']);

            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '2026-01-15 10:30:00'], 'issued_at');

            expect($query->count())->toBe(1);
        });

        it('does not match a row when filtering equals with the same wall clock at a different offset', function () {
            Invoice::factory()->create(['issued_at' => '2026-01-15 10:30:00']);

            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '2026-01-15T10:30:00+00:00'], 'issued_at');

            expect($query->count())->toBe(0);
        });

        it('includes the creation instant when filtering lessThanOrEquals with the value the API returns', function () {
            Invoice::factory()->create(['issued_at' => '2026-01-15 10:30:00']);

            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'lessThanOrEquals', 'value' => '2026-01-15T10:30:00+01:00'], 'issued_at');

            expect($query->count())->toBe(1);
        });

        it('converts offsets to app timezone wall clock for comparisons', function () {
            Invoice::factory()->create(['issued_at' => '2026-01-15 12:00:00']);

            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '2026-01-15T11:00:00+00:00'], 'issued_at');

            expect($query->count())->toBe(1);
        });
    });

    describe('zoneless ISO datetime values', function () {
        it('accepts ISO datetime without offset and interprets it in app timezone', function () {
            Invoice::factory()->create(['issued_at' => '2026-01-15 10:30:00']);

            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '2026-01-15T10:30:00'], 'issued_at');

            expect($query->count())->toBe(1);
        });
    });

    describe('plain date values with day interval semantics', function () {
        beforeEach(function () {
            Invoice::factory()->create(['issued_at' => '2024-11-20 23:59:59']);
            Invoice::factory()->create(['issued_at' => '2024-11-21 00:00:00']);
            Invoice::factory()->create(['issued_at' => '2024-11-21 15:00:00']);
            Invoice::factory()->create(['issued_at' => '2024-11-22 00:00:00']);
        });

        it('matches the whole day with equals', function () {
            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => '2024-11-21'], 'issued_at');

            expect($query->count())->toBe(2);
        });

        it('excludes the whole day with notEquals', function () {
            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'notEquals', 'value' => '2024-11-21'], 'issued_at');

            expect($query->count())->toBe(2);
        });

        it('starts at the day start with greaterThanOrEquals', function () {
            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'greaterThanOrEquals', 'value' => '2024-11-21'], 'issued_at');

            expect($query->count())->toBe(3);
        });

        it('starts at the next day start with greaterThan', function () {
            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'greaterThan', 'value' => '2024-11-21'], 'issued_at');

            expect($query->count())->toBe(1);
        });

        it('ends before the day start with lessThan', function () {
            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'lessThan', 'value' => '2024-11-21'], 'issued_at');

            expect($query->count())->toBe(1);
        });

        it('ends before the next day start with lessThanOrEquals', function () {
            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'lessThanOrEquals', 'value' => '2024-11-21'], 'issued_at');

            expect($query->count())->toBe(3);
        });

        it('matches mixed plain date and datetime values with equals', function () {
            $filter = new DateTimeOperatorFilter('issuedAt');
            $query = Invoice::query();
            $filter($query, ['operator' => 'equals', 'value' => ['2024-11-21', '2024-11-22 00:00:00']], 'issued_at');

            expect($query->count())->toBe(3);
        });
    });

    describe('multiple filters', function () {
        it('applies multiple filters via nested arrays', function () {
            Invoice::factory()->create(['issued_at' => '2026-02-16 08:00:00']);
            Invoice::factory()->create(['issued_at' => '2026-02-16 10:00:00']);
            Invoice::factory()->create(['issued_at' => '2026-02-16 12:00:00']);

            $filter = new DateTimeOperatorFilter;
            $query = Invoice::query();
            $filter($query, [
                ['operator' => 'greaterThan', 'value' => '2026-02-16 09:00:00'],
                ['operator' => 'lessThan', 'value' => '2026-02-16 11:00:00'],
            ], 'issued_at');

            expect($query->count())->toBe(1)
                ->and($query->first()->issued_at->format('H:i:s'))->toBe('10:00:00');
        });
    });
});
