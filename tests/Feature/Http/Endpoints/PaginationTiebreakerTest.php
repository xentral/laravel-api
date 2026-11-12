<?php declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Workbench\App\Models\Invoice;
use Xentral\LaravelApi\OpenApi\PaginationType;
use Xentral\LaravelApi\Query\QueryBuilder;

/**
 * @return list<int>
 */
function walkInvoiceCursor(string $sort): array
{
    $ids = [];
    $cursor = null;

    do {
        $query = ['sort' => $sort, 'per_page' => 1] + ($cursor === null ? [] : ['cursor' => $cursor]);
        $response = test()->getJson('/api/v1/invoices?'.http_build_query($query), ['x-pagination' => 'cursor']);
        $response->assertOk();

        $ids = [...$ids, ...array_column($response->json('data'), 'id')];
        $cursor = $response->json('meta.next_cursor');
    } while ($cursor !== null && count($ids) < 20);

    return $ids;
}

/**
 * @return list<array{column: string, direction: string}>
 */
function paginatedInvoiceOrders(Closure $prepare, PaginationType $type = PaginationType::SIMPLE, ?Model $model = null): array
{
    $builder = QueryBuilder::for(($model ?? new Invoice)->newQuery(), new Request);
    $prepare($builder);
    $builder->apiPaginate(100, $type);

    return array_map(
        fn (array $order): array => ['column' => (string) $order['column'], 'direction' => $order['direction']],
        $builder->getEloquentBuilder()->getQuery()->orders ?? [],
    );
}

describe('Pagination tiebreaker', function () {
    beforeEach(function () {
        $this->cheap = Invoice::factory()->count(3)->create(['total_amount' => 100])->modelKeys();
        $this->pricey = Invoice::factory()->count(2)->create(['total_amount' => 200])->modelKeys();
    });

    it('returns every row exactly once when a cursor walks over equal sort values', function () {
        expect(walkInvoiceCursor('total_amount'))->toBe([...$this->cheap, ...$this->pricey]);
    });

    it('returns every row exactly once when a cursor walks over equal sort values descending', function () {
        expect(walkInvoiceCursor('-total_amount'))->toBe([...array_reverse($this->pricey), ...array_reverse($this->cheap)]);
    });

    it('walks back over equal sort values with the previous cursor', function () {
        $headers = ['x-pagination' => 'cursor'];
        $first = $this->getJson('/api/v1/invoices?sort=total_amount&per_page=2', $headers);
        $second = $this->getJson('/api/v1/invoices?sort=total_amount&per_page=2&cursor='.$first->json('meta.next_cursor'), $headers);
        $back = $this->getJson('/api/v1/invoices?sort=total_amount&per_page=2&cursor='.$second->json('meta.prev_cursor'), $headers);

        expect(array_column($second->json('data'), 'id'))->toBe([$this->cheap[2], $this->pricey[0]])
            ->and(array_column($back->json('data'), 'id'))->toBe([$this->cheap[0], $this->cheap[1]]);
    });

    it('walks back over equal sort values descending with the previous cursor', function () {
        $headers = ['x-pagination' => 'cursor'];
        $first = $this->getJson('/api/v1/invoices?sort=-total_amount&per_page=2', $headers);
        $second = $this->getJson('/api/v1/invoices?sort=-total_amount&per_page=2&cursor='.$first->json('meta.next_cursor'), $headers);
        $back = $this->getJson('/api/v1/invoices?sort=-total_amount&per_page=2&cursor='.$second->json('meta.prev_cursor'), $headers);

        expect(array_column($second->json('data'), 'id'))->toBe([$this->cheap[2], $this->cheap[1]])
            ->and(array_column($back->json('data'), 'id'))->toBe([$this->pricey[1], $this->pricey[0]]);
    });

    it('returns every row exactly once when offset pages walk over equal sort values', function (string $paginationType) {
        $ids = [];
        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = $query->sql;
        });

        foreach (range(1, 5) as $page) {
            $response = $this->getJson("/api/v1/invoices?sort=-total_amount&per_page=1&page={$page}", [
                'x-pagination' => $paginationType,
            ]);
            $response->assertOk();
            $ids = [...$ids, ...array_column($response->json('data'), 'id')];
        }

        expect($ids)->toBe([...array_reverse($this->pricey), ...array_reverse($this->cheap)])
            ->and(implode("\n", $statements))->toContain('order by "total_amount" desc, "invoices"."id" desc');
    })->with(['simple', 'table']);

    it('appends the qualified model key as the last order for every pagination type', function (PaginationType $type) {
        $orders = paginatedInvoiceOrders(fn (QueryBuilder $builder) => $builder->orderBy('status', 'desc'), $type);

        expect($orders)->toBe([
            ['column' => 'status', 'direction' => 'desc'],
            ['column' => 'invoices.id', 'direction' => 'desc'],
        ]);
    })->with([PaginationType::SIMPLE, PaginationType::TABLE, PaginationType::CURSOR]);

    it('orders the model key in the direction of the last order', function () {
        $orders = paginatedInvoiceOrders(
            fn (QueryBuilder $builder) => $builder->orderBy('status', 'desc')->orderBy('total_amount'),
        );

        expect($orders)->toBe([
            ['column' => 'status', 'direction' => 'desc'],
            ['column' => 'total_amount', 'direction' => 'asc'],
            ['column' => 'invoices.id', 'direction' => 'asc'],
        ]);
    });

    it('orders the model key ascending after a raw order', function () {
        $builder = QueryBuilder::for(Invoice::class, new Request);
        $builder->orderByRaw('total_amount desc');
        $builder->apiPaginate();

        expect($builder->getEloquentBuilder()->getQuery()->orders)->toBe([
            ['type' => 'Raw', 'sql' => 'total_amount desc'],
            ['column' => 'invoices.id', 'direction' => 'asc'],
        ]);
    });

    it('leaves a query alone whose model has no key', function () {
        $keyless = new class extends Model
        {
            protected $table = 'invoices';

            protected $primaryKey = null;

            public $incrementing = false;
        };

        $orders = paginatedInvoiceOrders(fn (QueryBuilder $builder) => $builder->orderBy('status'), model: $keyless);

        expect($orders)->toBe([['column' => 'status', 'direction' => 'asc']]);
    });

    it('orders by the model key alone when the query has no order', function () {
        expect(paginatedInvoiceOrders(fn () => null))->toBe([
            ['column' => 'invoices.id', 'direction' => 'asc'],
        ]);
    });

    it('leaves a query alone that already orders by the model key', function (string $column) {
        $orders = paginatedInvoiceOrders(
            fn (QueryBuilder $builder) => $builder->orderBy('status')->orderBy($column, 'desc'),
        );

        expect($orders)->toBe([
            ['column' => 'status', 'direction' => 'asc'],
            ['column' => $column, 'direction' => 'desc'],
        ]);
    })->with(['id', 'invoices.id']);

    it('leaves a query alone whose rows are not single model rows', function (Closure $prepare) {
        expect(paginatedInvoiceOrders($prepare))->toBe([]);
    })->with([
        'grouped' => [fn (QueryBuilder $builder) => $builder->select('status')->groupBy('status')],
        'distinct' => [fn (QueryBuilder $builder) => $builder->select('status')->distinct()],
        'union' => [fn (QueryBuilder $builder) => $builder->union(Invoice::query())],
    ]);
});
