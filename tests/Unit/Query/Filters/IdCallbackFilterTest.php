<?php declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\LineItem;
use Xentral\LaravelApi\Query\Filters\FilterOperator;
use Xentral\LaravelApi\Query\Filters\IdCallbackFilter;
use Xentral\LaravelApi\Query\Filters\IdFilterTarget;
use Xentral\LaravelApi\Query\Filters\StringOperatorFilter;

function lineItemsIdFilter(?ArrayObject $received = null): IdCallbackFilter
{
    return new IdCallbackFilter(
        function (Builder $query, array $ids) use ($received): void {
            $received?->append($ids);
            $query->whereHas('lineItems', fn (Builder $lineItems) => $lineItems->whereIn('line_items.id', $ids));
        },
        IdFilterTarget::Relation,
    );
}

function customerColumnIdFilter(?ArrayObject $received = null): IdCallbackFilter
{
    return new IdCallbackFilter(
        function (Builder $query, array $ids) use ($received): void {
            $received?->append($ids);
            $query->whereIn('invoices.customer_id', $ids);
        },
        IdFilterTarget::Column,
    );
}

function invoiceWithLineItems(int $count): array
{
    $invoice = Invoice::factory()->create();
    $lineItems = LineItem::factory()->count($count)->for($invoice)->create();

    return [$invoice, $lineItems->pluck('id')->all()];
}

describe('IdCallbackFilter on a relation target', function () {
    it('requires all ids to match for equals with a list', function () {
        [$withBoth, $bothIds] = invoiceWithLineItems(2);
        $withFirstOnly = Invoice::factory()->create();
        LineItem::factory()->for($withFirstOnly)->create();

        $query = Invoice::query();
        lineItemsIdFilter()($query, ['operator' => 'equals', 'value' => $bothIds], 'lineItem.id');

        expect($query->pluck('id')->all())->toBe([$withBoth->id]);
    });

    it('invokes the callback once per id for equals with a list', function () {
        [, $bothIds] = invoiceWithLineItems(2);
        $received = new ArrayObject;

        $query = Invoice::query();
        lineItemsIdFilter($received)($query, ['operator' => 'equals', 'value' => $bothIds], 'lineItem.id');

        expect($received->getArrayCopy())->toBe([[$bothIds[0]], [$bothIds[1]]]);
    });

    it('matches any id for in', function () {
        [$first, $firstIds] = invoiceWithLineItems(1);
        [$second, $secondIds] = invoiceWithLineItems(1);
        invoiceWithLineItems(1);

        $query = Invoice::query();
        lineItemsIdFilter()($query, ['operator' => 'in', 'value' => [$firstIds[0], $secondIds[0]]], 'lineItem.id');

        expect($query->pluck('id')->all())->toEqualCanonicalizing([$first->id, $second->id]);
    });

    it('invokes the callback once with every id for in', function () {
        [, $bothIds] = invoiceWithLineItems(2);
        $received = new ArrayObject;

        $query = Invoice::query();
        lineItemsIdFilter($received)($query, ['operator' => 'in', 'value' => $bothIds], 'lineItem.id');

        expect($received->getArrayCopy())->toBe([$bothIds]);
    });

    it('excludes every listed id for notIn', function () {
        [, $excludedIds] = invoiceWithLineItems(2);
        [$kept] = invoiceWithLineItems(1);

        $query = Invoice::query();
        lineItemsIdFilter()($query, ['operator' => 'notIn', 'value' => $excludedIds], 'lineItem.id');

        expect($query->pluck('id')->all())->toBe([$kept->id]);
    });

    it('treats notEquals with a list like notIn, matching none of them', function () {
        [, $excludedIds] = invoiceWithLineItems(2);
        [$kept] = invoiceWithLineItems(1);

        $query = Invoice::query();
        lineItemsIdFilter()($query, ['operator' => 'notEquals', 'value' => $excludedIds], 'lineItem.id');

        expect($query->pluck('id')->all())->toBe([$kept->id]);
    });
});

describe('IdCallbackFilter on a column target', function () {
    it('keeps equals with a list satisfiable instead of anding it into nothing', function () {
        $first = Customer::factory()->create();
        $second = Customer::factory()->create();
        Invoice::factory()->create(['customer_id' => $first->id]);
        Invoice::factory()->create(['customer_id' => $second->id]);
        Invoice::factory()->create();

        $received = new ArrayObject;
        $query = Invoice::query();
        customerColumnIdFilter($received)($query, ['operator' => 'equals', 'value' => [$first->id, $second->id]], 'customer.id');

        expect($query->count())->toBe(2)
            ->and($received->getArrayCopy())->toBe([[$first->id, $second->id]]);
    });

    it('excludes null rows for notIn, exactly like the column based id filter does', function () {
        Customer::factory()->create(['is_archived' => 5]);
        $other = Customer::factory()->create(['is_archived' => 7]);
        Customer::factory()->create(['is_archived' => null]);

        $filter = new IdCallbackFilter(
            fn (Builder $query, array $ids) => $query->whereIn('customers.is_archived', $ids),
            IdFilterTarget::Column,
        );

        $viaCallback = Customer::query();
        $filter($viaCallback, ['operator' => 'notIn', 'value' => [5]], 'legacy.id');

        $viaColumn = Customer::query();
        (new StringOperatorFilter([FilterOperator::NOT_IN]))($viaColumn, ['operator' => 'notIn', 'value' => [5]], 'is_archived');

        expect($viaCallback->pluck('id')->all())->toBe([$other->id])
            ->and($viaCallback->pluck('id')->all())->toBe($viaColumn->pluck('id')->all());
    });

    it('excludes every listed id for notIn on a plain column', function () {
        $excluded = Customer::factory()->create();
        $kept = Customer::factory()->create();
        Invoice::factory()->create(['customer_id' => $excluded->id]);
        $keptInvoice = Invoice::factory()->create(['customer_id' => $kept->id]);

        $query = Invoice::query();
        customerColumnIdFilter()($query, ['operator' => 'notIn', 'value' => [$excluded->id]], 'customer.id');

        expect($query->pluck('id')->all())->toBe([$keptInvoice->id]);
    });
});

describe('IdCallbackFilter predicate grouping', function () {
    it('keeps a callback using orWhere from widening a surrounding constraint', function () {
        Invoice::factory()->create(['status' => 'open']);
        $outOfScope = Invoice::factory()->create(['status' => 'paid']);

        $filter = new IdCallbackFilter(
            fn (Builder $query, array $ids) => $query
                ->whereIn('invoices.customer_id', $ids)
                ->orWhereIn('invoices.id', $ids),
            IdFilterTarget::Column,
        );

        $query = Invoice::query()->where('status', 'open');
        $filter($query, ['operator' => 'in', 'value' => [$outOfScope->id]], 'legacy.id');

        expect($query->pluck('id')->all())->not->toContain($outOfScope->id);
    });

    it('groups every predicate of an anded equals list as well', function () {
        Invoice::factory()->create(['status' => 'open']);
        $outOfScope = Invoice::factory()->create(['status' => 'paid']);
        $secondOutOfScope = Invoice::factory()->create(['status' => 'paid']);

        $filter = new IdCallbackFilter(
            fn (Builder $query, array $ids) => $query
                ->whereIn('invoices.customer_id', $ids)
                ->orWhereIn('invoices.id', $ids),
            IdFilterTarget::Relation,
        );

        $query = Invoice::query()->where('status', 'open');
        $filter($query, ['operator' => 'equals', 'value' => [$outOfScope->id, $secondOutOfScope->id]], 'legacy.id');

        expect($query->pluck('id')->all())
            ->not->toContain($outOfScope->id)
            ->not->toContain($secondOutOfScope->id);
    });
});

describe('IdCallbackFilter validation', function () {
    it('rejects an unsupported operator with the documented message', function () {
        try {
            lineItemsIdFilter()(Invoice::query(), ['operator' => 'contains', 'value' => [1]], 'lineItem.id');
        } catch (ValidationException $e) {
            expect($e->errors()['lineItem.id'][0])->toBe("Unsupported operator: contains. Use 'equals', 'notEquals', 'in' or 'notIn'.");

            return;
        }

        $this->fail('Expected a ValidationException');
    });

    it('rejects a non-numeric id', function () {
        try {
            lineItemsIdFilter()(Invoice::query(), ['operator' => 'in', 'value' => ['1', 'abc']], 'lineItem.id');
        } catch (ValidationException $e) {
            expect($e->errors()['lineItem.id'][0])->toBe('Invalid value: abc. Expected one or more numeric ids.');

            return;
        }

        $this->fail('Expected a ValidationException');
    });

    it('rejects an empty id list instead of letting notIn match everything', function () {
        $received = new ArrayObject;

        try {
            lineItemsIdFilter($received)(Invoice::query(), ['operator' => 'notIn', 'value' => []], 'lineItem.id');
        } catch (ValidationException $e) {
            expect($e->errors()['lineItem.id'][0])->toBe('Invalid value: []. Expected one or more numeric ids.')
                ->and($received->count())->toBe(0);

            return;
        }

        $this->fail('Expected a ValidationException');
    });

    it('rejects a null value the same way, without relying on spatie normalising an empty list', function () {
        $received = new ArrayObject;

        try {
            lineItemsIdFilter($received)(Invoice::query(), ['operator' => 'notIn', 'value' => null], 'lineItem.id');
        } catch (ValidationException $e) {
            expect($e->errors()['lineItem.id'][0])->toBe('Invalid value: []. Expected one or more numeric ids.')
                ->and($received->count())->toBe(0);

            return;
        }

        $this->fail('Expected a ValidationException');
    });

    it('hands the callback ints even when the request carried numeric strings', function () {
        [, $ids] = invoiceWithLineItems(2);
        $received = new ArrayObject;

        $query = Invoice::query();
        lineItemsIdFilter($received)($query, ['operator' => 'in', 'value' => array_map(strval(...), $ids)], 'lineItem.id');

        expect($received->getArrayCopy())->toBe([$ids]);
    });
});

describe('IdCallbackFilter repeated filter keys', function () {
    it('applies every entry of a repeated key conjunctively', function () {
        [$withBoth, $bothIds] = invoiceWithLineItems(2);
        invoiceWithLineItems(1);

        $query = Invoice::query();
        lineItemsIdFilter()($query, [
            ['operator' => 'in', 'value' => [$bothIds[0]]],
            ['operator' => 'in', 'value' => [$bothIds[1]]],
        ], 'lineItem.id');

        expect($query->pluck('id')->all())->toBe([$withBoth->id]);
    });
});
