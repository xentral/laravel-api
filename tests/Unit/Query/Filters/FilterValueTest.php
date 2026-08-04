<?php declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Xentral\LaravelApi\Query\Filters\FilterValue;

describe('FilterValue::toBool', function () {
    it('coerces every accepted truthy spelling', function (mixed $value) {
        expect(FilterValue::toBool($value, 'isActive'))->toBeTrue();
    })->with([true, 1, '1', 'true']);

    it('coerces every accepted falsy spelling', function (mixed $value) {
        expect(FilterValue::toBool($value, 'isActive'))->toBeFalse();
    })->with([false, 0, '0', 'false']);

    it('rejects any other value with the documented message', function () {
        try {
            FilterValue::toBool('yes', 'isActive');
        } catch (ValidationException $e) {
            expect($e->errors()['isActive'][0])->toBe('Invalid value: yes. Valid values are: true, false.');

            return;
        }

        $this->fail('Expected a ValidationException');
    });

    it('prints a non-scalar value as its type instead of interpolating it', function () {
        try {
            FilterValue::toBool(['true'], 'isActive');
        } catch (ValidationException $e) {
            expect($e->errors()['isActive'][0])->toBe('Invalid value: array. Valid values are: true, false.');

            return;
        }

        $this->fail('Expected a ValidationException');
    });
});

describe('FilterValue::toIds', function () {
    it('wraps a single numeric value into a list of ints', function () {
        expect(FilterValue::toIds('7', 'categories.id'))->toBe([7]);
    });

    it('casts a numeric list to ints', function () {
        expect(FilterValue::toIds(['3', 4], 'categories.id'))->toBe([3, 4]);
    });

    it('reindexes a sparse list so the callback always receives a list', function () {
        expect(FilterValue::toIds([1 => '3', 5 => '4'], 'categories.id'))->toBe([3, 4]);
    });

    it('rejects a non-numeric id with the documented message', function () {
        try {
            FilterValue::toIds(['3', 'abc'], 'categories.id');
        } catch (ValidationException $e) {
            expect($e->errors()['categories.id'][0])->toBe('Invalid value: abc. Expected one or more numeric ids.');

            return;
        }

        $this->fail('Expected a ValidationException');
    });

    it('prints a non-scalar id as its type instead of interpolating it', function () {
        try {
            FilterValue::toIds([[1]], 'categories.id');
        } catch (ValidationException $e) {
            expect($e->errors()['categories.id'][0])->toBe('Invalid value: array. Expected one or more numeric ids.');

            return;
        }

        $this->fail('Expected a ValidationException');
    });

    it('rejects an empty list, so no caller can ever receive zero ids', function () {
        try {
            FilterValue::toIds([], 'categories.id');
        } catch (ValidationException $e) {
            expect($e->errors()['categories.id'][0])->toBe('Invalid value: []. Expected one or more numeric ids.');

            return;
        }

        $this->fail('Expected a ValidationException');
    });

    it('rejects null the same way, independently of spatie normalising an empty list to null', function () {
        try {
            FilterValue::toIds(null, 'categories.id');
        } catch (ValidationException $e) {
            expect($e->errors()['categories.id'][0])->toBe('Invalid value: []. Expected one or more numeric ids.');

            return;
        }

        $this->fail('Expected a ValidationException');
    });
});
