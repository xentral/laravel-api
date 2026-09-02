<?php declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Workbench\App\Enum\InvoiceStatusEnum;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Invoice;
use Xentral\LaravelApi\Http\QueryBuilderRequest;
use Xentral\LaravelApi\Query\Filters\QueryFilter;
use Xentral\LaravelApi\Query\QueryBuilder;

function filterGroup(string $operator, array $conditions): string
{
    return urlencode(json_encode(['op' => $operator, 'conditions' => $conditions], JSON_THROW_ON_ERROR));
}

function orGroup(array $conditions): string
{
    return filterGroup('or', $conditions);
}

function responseIds(TestResponse $response): array
{
    $ids = array_column($response->json('data'), 'id');
    sort($ids);

    return $ids;
}

function sortedIds(array $models): array
{
    $ids = array_map(fn ($model) => $model->id, $models);
    sort($ids);

    return $ids;
}

describe('OR filter groups', function () {
    it('returns invoices matching any condition of an or group', function () {
        $first = Invoice::factory()->create(['invoice_number' => 'INV-100']);
        $second = Invoice::factory()->create(['invoice_number' => 'INV-200']);
        Invoice::factory()->create(['invoice_number' => 'INV-300']);

        $response = $this->getJson('/api/v1/invoices?filter='.orGroup([
            ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-100'],
            ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-200'],
        ]));

        $response->assertOk();
        expect(responseIds($response))->toBe(sortedIds([$first, $second]));
    });

    it('returns invoices matching any of three conditions', function () {
        $first = Invoice::factory()->create(['invoice_number' => 'INV-100']);
        $second = Invoice::factory()->create(['invoice_number' => 'INV-200']);
        $third = Invoice::factory()->create(['invoice_number' => 'INV-300']);
        Invoice::factory()->create(['invoice_number' => 'INV-400']);

        $response = $this->getJson('/api/v1/invoices?filter='.orGroup([
            ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-100'],
            ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-200'],
            ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-300'],
        ]));

        $response->assertOk();
        expect(responseIds($response))->toBe(sortedIds([$first, $second, $third]));
    });

    it('supports the deep object group form', function () {
        $wanted = Invoice::factory()->create(['invoice_number' => 'INV-100']);
        Invoice::factory()->create(['invoice_number' => 'INV-300']);

        $response = $this->getJson('/api/v1/invoices?filter[op]=or'
            .'&filter[conditions][0][key]=invoice_number&filter[conditions][0][op]=equals&filter[conditions][0][value]=INV-100'
            .'&filter[conditions][1][key]=invoice_number&filter[conditions][1][op]=equals&filter[conditions][1][value]=NOPE');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        expect($response->json('data.0.id'))->toBe($wanted->id);
    });

    it('branches a relation filter inside an or group', function () {
        $byCustomer = Invoice::factory()
            ->for(Customer::factory()->state(['name' => 'Acme GmbH']))
            ->create(['invoice_number' => 'INV-1']);
        $byNumber = Invoice::factory()->create(['invoice_number' => 'INV-2']);
        Invoice::factory()->create(['invoice_number' => 'INV-3']);

        $response = $this->getJson('/api/v1/invoices?filter='.orGroup([
            ['key' => 'customer.name', 'op' => 'equals', 'value' => 'Acme GmbH'],
            ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-2'],
        ]));

        $response->assertOk();
        expect(responseIds($response))->toBe(sortedIds([$byCustomer, $byNumber]));
    });

    it('counts a row matching several branches once in table mode', function () {
        // INV-100 matches BOTH branches - the total must stay de-duplicated.
        Invoice::factory()->create(['invoice_number' => 'INV-100', 'status' => InvoiceStatusEnum::Paid]);
        Invoice::factory()->create(['invoice_number' => 'INV-200', 'status' => InvoiceStatusEnum::Paid]);
        Invoice::factory()->create(['invoice_number' => 'INV-300', 'status' => InvoiceStatusEnum::Sent]);

        $response = $this->withHeader('x-pagination', 'table')
            ->getJson('/api/v1/invoices?filter='.orGroup([
                ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-100'],
                ['key' => 'status', 'op' => 'equals', 'value' => InvoiceStatusEnum::Paid->value],
            ]));

        $response->assertOk();
        expect($response->json('meta.total'))->toBe(2);
    });

    it('pages an or set with cursor pagination without gaps or duplicates', function () {
        $first = Invoice::factory()->create(['invoice_number' => 'INV-100']);
        Invoice::factory()->create(['invoice_number' => 'INV-200']);
        $third = Invoice::factory()->create(['invoice_number' => 'INV-300']);

        $filter = orGroup([
            ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-100'],
            ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-300'],
        ]);

        $ids = [];
        $url = '/api/v1/invoices?page[size]=1&filter='.$filter;
        while ($url !== null) {
            $response = $this->withHeader('x-pagination', 'cursor')->getJson($url);
            $response->assertOk();
            $ids = [...$ids, ...array_column($response->json('data'), 'id')];
            $url = $response->json('links.next');
        }

        sort($ids);
        expect($ids)->toBe(sortedIds([$first, $third]));
    });

    it('combines search and an or group conjunctively', function () {
        $match = Invoice::factory()->create(['invoice_number' => 'INV-100']);
        Invoice::factory()->create(['invoice_number' => 'XX-100']);

        $response = $this->getJson('/api/v1/invoices?search=INV-&filter='.orGroup([
            ['key' => 'invoice_number', 'op' => 'contains', 'value' => '100'],
            ['key' => 'invoice_number', 'op' => 'contains', 'value' => '999'],
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        expect($response->json('data.0.id'))->toBe($match->id);
    });

    it('keeps unnamed filter defaults outside the or group', function () {
        $paid = Invoice::factory()->create(['invoice_number' => 'INV-100', 'status' => InvoiceStatusEnum::Paid]);
        Invoice::factory()->create(['invoice_number' => 'INV-200', 'status' => InvoiceStatusEnum::Sent]);

        // Both branches match, but the status default the request never names
        // narrows the whole set afterwards.
        $request = Request::create('/invoices', 'GET', ['filter' => json_encode([
            'op' => 'or',
            'conditions' => [
                ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-100'],
                ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-200'],
            ],
        ], JSON_THROW_ON_ERROR)]);

        $result = QueryBuilder::for(Invoice::class, $request)
            ->allowOrFilterGroups()
            ->allowedFilters(
                QueryFilter::string('invoice_number'),
                QueryFilter::enum('status', InvoiceStatusEnum::class)
                    ->default(['operator' => 'equals', 'value' => InvoiceStatusEnum::Paid->value]),
            )
            ->get();

        expect($result->pluck('id')->all())->toBe([$paid->id]);
    });

    it('drops the default of a filter the or group names', function () {
        $paid = Invoice::factory()->create(['invoice_number' => 'INV-100', 'status' => InvoiceStatusEnum::Paid]);
        $sent = Invoice::factory()->create(['invoice_number' => 'INV-200', 'status' => InvoiceStatusEnum::Sent]);

        // The group names status itself, so the default must not narrow the
        // disjunction on top of the branches - that would silently filter away
        // every branch the default does not agree with.
        $request = Request::create('/invoices', 'GET', ['filter' => json_encode([
            'op' => 'or',
            'conditions' => [
                ['key' => 'status', 'op' => 'equals', 'value' => InvoiceStatusEnum::Sent->value],
                ['key' => 'status', 'op' => 'equals', 'value' => InvoiceStatusEnum::Paid->value],
            ],
        ], JSON_THROW_ON_ERROR)]);

        $result = QueryBuilder::for(Invoice::class, $request)
            ->allowOrFilterGroups()
            ->allowedFilters(
                QueryFilter::enum('status', InvoiceStatusEnum::class)
                    ->default(['operator' => 'equals', 'value' => InvoiceStatusEnum::Paid->value]),
            )
            ->get();

        expect($result->pluck('id')->sort()->values()->all())->toBe(sortedIds([$paid, $sent]));
    });
});

describe('AND filter groups', function () {
    it('treats an and group like the flat form', function () {
        $wanted = Invoice::factory()->create(['invoice_number' => 'INV-100', 'status' => InvoiceStatusEnum::Paid]);
        Invoice::factory()->create(['invoice_number' => 'INV-200', 'status' => InvoiceStatusEnum::Paid]);
        Invoice::factory()->create(['invoice_number' => 'INV-1000', 'status' => InvoiceStatusEnum::Sent]);

        $response = $this->getJson('/api/v1/invoices?filter='.filterGroup('and', [
            ['key' => 'invoice_number', 'op' => 'contains', 'value' => 'INV-100'],
            ['key' => 'status', 'op' => 'equals', 'value' => InvoiceStatusEnum::Paid->value],
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        expect($response->json('data.0.id'))->toBe($wanted->id);
    });

    it('still accepts an and group on an endpoint without the opt-in', function () {
        Customer::factory()->create(['name' => 'Acme GmbH']);
        Customer::factory()->create(['name' => 'Other GmbH']);

        $this->getJson('/api/v1/customers?filter='.filterGroup('and', [
            ['key' => 'name', 'op' => 'equals', 'value' => 'Acme GmbH'],
        ]))->assertOk()->assertJsonCount(1, 'data');
    });
});

describe('Nested filter groups', function () {
    it('expresses (A and B) or (C and D)', function () {
        $paidAcme = Invoice::factory()->for(Customer::factory()->state(['name' => 'Acme GmbH']))
            ->create(['status' => InvoiceStatusEnum::Paid, 'invoice_number' => 'INV-1']);
        $sentGlobex = Invoice::factory()->for(Customer::factory()->state(['name' => 'Globex AG']))
            ->create(['status' => InvoiceStatusEnum::Sent, 'invoice_number' => 'INV-2']);
        Invoice::factory()->for(Customer::factory()->state(['name' => 'Acme GmbH']))
            ->create(['status' => InvoiceStatusEnum::Sent, 'invoice_number' => 'INV-3']);

        $response = $this->getJson('/api/v1/invoices?filter='.orGroup([
            ['op' => 'and', 'conditions' => [
                ['key' => 'customer.name', 'op' => 'equals', 'value' => 'Acme GmbH'],
                ['key' => 'status', 'op' => 'equals', 'value' => InvoiceStatusEnum::Paid->value],
            ]],
            ['op' => 'and', 'conditions' => [
                ['key' => 'customer.name', 'op' => 'equals', 'value' => 'Globex AG'],
                ['key' => 'status', 'op' => 'equals', 'value' => InvoiceStatusEnum::Sent->value],
            ]],
        ]));

        $response->assertOk();
        expect(responseIds($response))->toBe(sortedIds([$paidAcme, $sentGlobex]));
    });

    it('applies an or subgroup under an and root conjunctively', function () {
        $wanted = Invoice::factory()->create(['status' => InvoiceStatusEnum::Paid, 'invoice_number' => 'INV-100']);
        // In the or branch but not paid, and paid but outside the or branch.
        Invoice::factory()->create(['status' => InvoiceStatusEnum::Sent, 'invoice_number' => 'INV-200']);
        Invoice::factory()->create(['status' => InvoiceStatusEnum::Paid, 'invoice_number' => 'INV-999']);

        // status = paid AND (number = INV-100 OR number = INV-200)
        $response = $this->getJson('/api/v1/invoices?filter='.filterGroup('and', [
            ['key' => 'status', 'op' => 'equals', 'value' => InvoiceStatusEnum::Paid->value],
            ['op' => 'or', 'conditions' => [
                ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-100'],
                ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-200'],
            ]],
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        expect($response->json('data.0.id'))->toBe($wanted->id);
    });

    it('applies an and subgroup under an or root as one branch', function () {
        // The subgroup's conditions must combine conjunctively inside the
        // branch; joining them with or instead would widen the whole set.
        // Both other rows satisfy one half of the subgroup, so an or there
        // would return all three.
        $wanted = Invoice::factory()->for(Customer::factory()->state(['name' => 'Acme GmbH']))
            ->create(['status' => InvoiceStatusEnum::Paid, 'invoice_number' => 'INV-1']);
        Invoice::factory()->for(Customer::factory()->state(['name' => 'Acme GmbH']))
            ->create(['status' => InvoiceStatusEnum::Sent, 'invoice_number' => 'INV-2']);
        Invoice::factory()->for(Customer::factory()->state(['name' => 'Globex AG']))
            ->create(['status' => InvoiceStatusEnum::Paid, 'invoice_number' => 'INV-3']);

        $response = $this->getJson('/api/v1/invoices?filter='.orGroup([
            ['op' => 'and', 'conditions' => [
                ['key' => 'customer.name', 'op' => 'equals', 'value' => 'Acme GmbH'],
                ['key' => 'status', 'op' => 'equals', 'value' => InvoiceStatusEnum::Paid->value],
            ]],
            ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-999'],
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        expect($response->json('data.0.id'))->toBe($wanted->id);
    });

    it('keeps a condition that follows a subgroup inside a nested and group', function () {
        // or[ and[ or[number = INV-1, number = INV-2], status = paid ] ]
        // The status condition sits AFTER the subgroup. Stopping the loop at
        // the subgroup would drop it and silently widen the result.
        $wanted = Invoice::factory()->create(['invoice_number' => 'INV-1', 'status' => InvoiceStatusEnum::Paid]);
        Invoice::factory()->create(['invoice_number' => 'INV-2', 'status' => InvoiceStatusEnum::Sent]);

        $response = $this->getJson('/api/v1/invoices?filter='.orGroup([
            ['op' => 'and', 'conditions' => [
                ['op' => 'or', 'conditions' => [
                    ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-1'],
                    ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-2'],
                ]],
                ['key' => 'status', 'op' => 'equals', 'value' => InvoiceStatusEnum::Paid->value],
            ]],
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        expect($response->json('data.0.id'))->toBe($wanted->id);
    });

    it('applies a group nested to the maximum depth', function () {
        $wanted = Invoice::factory()->create(['invoice_number' => 'INV-100']);
        Invoice::factory()->create(['invoice_number' => 'INV-200']);

        $node = ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-100'];

        for ($level = 0; $level < QueryBuilderRequest::MAX_GROUP_DEPTH; $level++) {
            $node = ['op' => $level % 2 === 0 ? 'or' : 'and', 'conditions' => [$node]];
        }

        $response = $this->getJson('/api/v1/invoices?filter='.urlencode(json_encode($node, JSON_THROW_ON_ERROR)));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        expect($response->json('data.0.id'))->toBe($wanted->id);
    });

    it('keeps an and subgroup conjunctive against its own sub-group', function () {
        // or[ and[ status = paid, or[number = INV-1, number = INV-2] ] ]
        // Joining the inner group to its sibling with or instead of and would
        // return all three rows.
        $wanted = Invoice::factory()->create(['status' => InvoiceStatusEnum::Paid, 'invoice_number' => 'INV-1']);
        Invoice::factory()->create(['status' => InvoiceStatusEnum::Sent, 'invoice_number' => 'INV-2']);
        Invoice::factory()->create(['status' => InvoiceStatusEnum::Paid, 'invoice_number' => 'INV-9']);

        $response = $this->getJson('/api/v1/invoices?filter='.orGroup([
            ['op' => 'and', 'conditions' => [
                ['key' => 'status', 'op' => 'equals', 'value' => InvoiceStatusEnum::Paid->value],
                ['op' => 'or', 'conditions' => [
                    ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-1'],
                    ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-2'],
                ]],
            ]],
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        expect($response->json('data.0.id'))->toBe($wanted->id);
    });

    it('applies the direct conditions of an and root exactly like the flat list', function () {
        // Decision behind the split path: only sub-groups go through closures,
        // because a filter that writes query wide state - lifting a global
        // scope, say - silently no-ops inside one. Pinning the SQL is what
        // keeps that guarantee from being refactored away; the behavioural
        // consequence is exercised on the endpoints that own such a filter.
        $conditions = [
            ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-1'],
            ['key' => 'status', 'op' => 'equals', 'value' => InvoiceStatusEnum::Paid->value],
        ];

        $build = fn (array $filter) => QueryBuilder::for(
            Invoice::class,
            Request::create('/invoices', 'GET', ['filter' => json_encode($filter, JSON_THROW_ON_ERROR)]),
        )
            ->allowOrFilterGroups()
            ->allowedFilters(
                QueryFilter::string('invoice_number'),
                QueryFilter::enum('status', InvoiceStatusEnum::class),
            );

        $flat = $build($conditions)->toSql();
        $grouped = $build(['op' => 'and', 'conditions' => [
            ...$conditions,
            ['op' => 'or', 'conditions' => [
                ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-2'],
                ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-3'],
            ]],
        ]])->toSql();

        expect($grouped)->toStartWith($flat);
    });

    it('counts a row matching several nested branches once in table mode', function () {
        // Matches both or-branches; the total must count it once.
        Invoice::factory()->create(['status' => InvoiceStatusEnum::Paid, 'invoice_number' => 'INV-100']);
        Invoice::factory()->create(['status' => InvoiceStatusEnum::Sent, 'invoice_number' => 'INV-200']);

        $response = $this->withHeader('x-pagination', 'table')
            ->getJson('/api/v1/invoices?filter='.orGroup([
                ['op' => 'and', 'conditions' => [['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-100']]],
                ['op' => 'and', 'conditions' => [['key' => 'status', 'op' => 'equals', 'value' => InvoiceStatusEnum::Paid->value]]],
            ]));

        $response->assertOk();
        expect($response->json('meta.total'))->toBe(1);
    });

    it('pages a nested or set with cursor pagination without gaps or duplicates', function () {
        $first = Invoice::factory()->create(['invoice_number' => 'INV-100', 'status' => InvoiceStatusEnum::Paid]);
        Invoice::factory()->create(['invoice_number' => 'INV-200', 'status' => InvoiceStatusEnum::Sent]);
        $third = Invoice::factory()->create(['invoice_number' => 'INV-300', 'status' => InvoiceStatusEnum::Paid]);

        $filter = orGroup([
            ['op' => 'and', 'conditions' => [
                ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-100'],
                ['key' => 'status', 'op' => 'equals', 'value' => InvoiceStatusEnum::Paid->value],
            ]],
            ['op' => 'and', 'conditions' => [
                ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-300'],
                ['key' => 'status', 'op' => 'equals', 'value' => InvoiceStatusEnum::Paid->value],
            ]],
        ]);

        $ids = [];
        $url = '/api/v1/invoices?page[size]=1&filter='.$filter;
        while ($url !== null) {
            $response = $this->withHeader('x-pagination', 'cursor')->getJson($url);
            $response->assertOk();
            $ids = [...$ids, ...array_column($response->json('data'), 'id')];
            $url = $response->json('links.next');
        }

        sort($ids);
        expect($ids)->toBe(sortedIds([$first, $third]));
    });

    it('still accepts a barred filter as a direct root and condition', function () {
        $invoice = Invoice::factory()->hasLineItems(1)->create(['invoice_number' => 'INV-1']);
        Invoice::factory()->hasLineItems(1)->create(['invoice_number' => 'INV-2']);

        $this->getJson('/api/v1/invoices?filter='.filterGroup('and', [
            ['key' => 'lineItem.id', 'op' => 'equals', 'value' => (string) $invoice->lineItems()->first()->id],
            ['op' => 'or', 'conditions' => [
                ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-1'],
                ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-2'],
            ]],
        ]))->assertOk()->assertJsonCount(1, 'data');
    });

    it('keeps defaults for filters named nowhere in the tree', function () {
        $paid = Invoice::factory()->create(['invoice_number' => 'INV-100', 'status' => InvoiceStatusEnum::Paid]);
        Invoice::factory()->create(['invoice_number' => 'INV-200', 'status' => InvoiceStatusEnum::Sent]);

        // Both branches match a row; the status default the tree never names
        // narrows the result afterwards.
        $request = Request::create('/invoices', 'GET', ['filter' => json_encode([
            'op' => 'or',
            'conditions' => [
                ['op' => 'and', 'conditions' => [['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-100']]],
                ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-200'],
            ],
        ], JSON_THROW_ON_ERROR)]);

        $result = QueryBuilder::for(Invoice::class, $request)
            ->allowOrFilterGroups()
            ->allowedFilters(
                QueryFilter::string('invoice_number'),
                QueryFilter::enum('status', InvoiceStatusEnum::class)
                    ->default(['operator' => 'equals', 'value' => InvoiceStatusEnum::Paid->value]),
            )
            ->get();

        expect($result->pluck('id')->all())->toBe([$paid->id]);
    });

    it('drops the default of a filter named only inside a subgroup', function () {
        // status is named one level down, so its default must not narrow the
        // tree on top of the branch that already constrains it.
        $paid = Invoice::factory()->create(['invoice_number' => 'INV-100', 'status' => InvoiceStatusEnum::Paid]);
        $sent = Invoice::factory()->create(['invoice_number' => 'INV-200', 'status' => InvoiceStatusEnum::Sent]);

        $request = Request::create('/invoices', 'GET', ['filter' => json_encode([
            'op' => 'or',
            'conditions' => [
                ['op' => 'and', 'conditions' => [['key' => 'status', 'op' => 'equals', 'value' => InvoiceStatusEnum::Sent->value]]],
                ['key' => 'status', 'op' => 'equals', 'value' => InvoiceStatusEnum::Paid->value],
            ],
        ], JSON_THROW_ON_ERROR)]);

        $result = QueryBuilder::for(Invoice::class, $request)
            ->allowOrFilterGroups()
            ->allowedFilters(
                QueryFilter::enum('status', InvoiceStatusEnum::class)
                    ->default(['operator' => 'equals', 'value' => InvoiceStatusEnum::Paid->value]),
            )
            ->get();

        expect($result->pluck('id')->sort()->values()->all())->toBe(sortedIds([$paid, $sent]));
    });
});

describe('Filter group rejections', function () {
    it('rejects an or group on an endpoint without the opt-in', function () {
        // The customers list does not call allowOrFilterGroups().
        $this->getJson('/api/v1/customers?filter='.orGroup([
            ['key' => 'name', 'op' => 'equals', 'value' => 'Acme'],
            ['key' => 'country', 'op' => 'equals', 'value' => 'DE'],
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.filter.0', 'OR filter groups are not supported on this endpoint.');
    });

    it('rejects an excluded filter inside an or group', function () {
        $this->getJson('/api/v1/invoices?filter='.orGroup([
            ['key' => 'lineItem.id', 'op' => 'equals', 'value' => '1'],
            ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-100'],
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.filter.0', 'The filter lineItem.id cannot be used inside an or group.');
    });

    it('still accepts an excluded filter outside an or group', function () {
        $invoice = Invoice::factory()->hasLineItems(1)->create();

        $this->getJson('/api/v1/invoices?filter='.urlencode(json_encode([
            ['key' => 'lineItem.id', 'op' => 'equals', 'value' => (string) $invoice->lineItems()->first()->id],
        ], JSON_THROW_ON_ERROR)))->assertOk()->assertJsonCount(1, 'data');
    });

    it('rejects nesting beyond the maximum depth', function () {
        $node = ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-1'];

        for ($level = 0; $level < QueryBuilderRequest::MAX_GROUP_DEPTH + 1; $level++) {
            $node = ['op' => 'and', 'conditions' => [$node]];
        }

        $this->getJson('/api/v1/invoices?filter='.urlencode(json_encode($node, JSON_THROW_ON_ERROR)))
            ->assertStatus(422)
            ->assertJsonPath('errors.filter.0', 'Filter groups nest to at most 5 levels.');
    });

    it('rejects nesting on an endpoint without the opt-in even when every operator is and', function () {
        $this->getJson('/api/v1/customers?filter='.filterGroup('and', [
            ['op' => 'and', 'conditions' => [['key' => 'name', 'op' => 'equals', 'value' => 'Acme']]],
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.filter.0', 'Nested filter groups are not supported on this endpoint.');
    });

    it('rejects a barred filter inside a nested subgroup', function () {
        $this->getJson('/api/v1/invoices?filter='.filterGroup('and', [
            ['op' => 'or', 'conditions' => [
                ['key' => 'lineItem.id', 'op' => 'equals', 'value' => '1'],
                ['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-1'],
            ]],
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.filter.0', 'The filter lineItem.id cannot be used inside a nested filter group.');
    });

    it('rejects a barred filter that follows a subgroup at an or root', function () {
        // The barred triple sits AFTER the subgroup; a guard that stopped
        // walking at the subgroup would let it through into a closure.
        $this->getJson('/api/v1/invoices?filter='.orGroup([
            ['op' => 'and', 'conditions' => [['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-1']]],
            ['key' => 'lineItem.id', 'op' => 'equals', 'value' => '1'],
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.filter.0', 'The filter lineItem.id cannot be used inside an or group.');
    });

    it('rejects a barred filter that follows a subgroup inside a nested group', function () {
        $this->getJson('/api/v1/invoices?filter='.filterGroup('and', [
            ['op' => 'or', 'conditions' => [
                ['op' => 'and', 'conditions' => [['key' => 'invoice_number', 'op' => 'equals', 'value' => 'INV-1']]],
                ['key' => 'lineItem.id', 'op' => 'equals', 'value' => '1'],
            ]],
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.filter.0', 'The filter lineItem.id cannot be used inside a nested filter group.');
    });

    it('rejects a barred filter buried several levels deep', function () {
        // Pins the recursion in the subtree walk: a guard that only inspected
        // the first level would let this through into a closure, where the
        // filter silently matches nothing instead of erroring.
        $this->getJson('/api/v1/invoices?filter='.filterGroup('and', [
            ['op' => 'or', 'conditions' => [
                ['op' => 'and', 'conditions' => [
                    ['key' => 'lineItem.id', 'op' => 'equals', 'value' => '1'],
                ]],
            ]],
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.filter.0', 'The filter lineItem.id cannot be used inside a nested filter group.');
    });

    it('rejects an unknown filter key inside an or group', function () {
        // spatie's ensureAllFiltersExist() runs on the collapsed filters and
        // rejects the key before the group is ever branched.
        $this->getJson('/api/v1/invoices?filter='.orGroup([
            ['key' => 'nope', 'op' => 'equals', 'value' => '1'],
        ]))->assertStatus(400);
    });

    it('throws when allowOrFilterGroups is called after allowedFilters', function () {
        QueryBuilder::for(Invoice::class, Request::create('/invoices'))
            ->allowedFilters(QueryFilter::string('invoice_number'))
            ->allowOrFilterGroups();
    })->throws(LogicException::class);
});
