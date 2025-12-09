<?php declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Workbench\App\Models\Invoice;
use Xentral\LaravelApi\OpenApi\OpenApiGeneratorFactory;

beforeEach(function () {
    Invoice::factory()->count(50)->create();
});

describe('Pagination Types (X-Pagination Header)', function () {
    it('defaults to simple pagination when no header is provided', function () {
        $response = $this->getJson('/api/v1/invoices');

        $response->assertOk();
        $data = $response->json();

        // Simple pagination should have 'links' with prev/next and 'meta' without last_page
        expect($data)->toHaveKey('meta')
            ->and($data['meta'])->toHaveKey('current_page')
            ->and($data['meta'])->toHaveKey('per_page')
            ->and($data['meta'])->not->toHaveKey('last_page') // Simple pagination doesn't have last_page
            ->and($data)->toHaveKey('links')
            ->and($data['links'])->toHaveKey('first')
            ->and($data['links'])->toHaveKey('next');
    });

    it('uses simple pagination when x-pagination header is set to simple', function () {
        $response = $this->getJson('/api/v1/invoices', [
            'x-pagination' => 'simple',
        ]);

        $response->assertOk();
        $data = $response->json();

        // Simple pagination should have 'links' with prev/next and 'meta' without last_page
        expect($data)->toHaveKey('meta')
            ->and($data['meta'])->toHaveKey('current_page')
            ->and($data['meta'])->toHaveKey('per_page')
            ->and($data['meta'])->not->toHaveKey('last_page'); // Simple pagination doesn't have last_page
    });

    it('uses table pagination when x-pagination header is set to table', function () {
        $response = $this->getJson('/api/v1/invoices', [
            'x-pagination' => 'table',
        ]);

        $response->assertOk();
        $data = $response->json();

        // Table pagination should have 'links' and 'meta' with last_page
        expect($data)->toHaveKey('meta')
            ->and($data['meta'])->toHaveKey('current_page')
            ->and($data['meta'])->toHaveKey('per_page')
            ->and($data['meta'])->toHaveKey('last_page') // Table pagination has last_page
            ->and($data['meta'])->toHaveKey('total') // Table pagination has total
            ->and($data)->toHaveKey('links')
            ->and($data['links'])->toHaveKey('first')
            ->and($data['links'])->toHaveKey('last');
    });

    it('uses cursor pagination when x-pagination header is set to cursor', function () {
        $response = $this->getJson('/api/v1/invoices', [
            'x-pagination' => 'cursor',
        ]);

        $response->assertOk();
        $data = $response->json();

        // Cursor pagination should have different structure
        expect($data)->toHaveKey('meta')
            ->and($data['meta'])->toHaveKey('per_page')
            ->and($data['meta'])->toHaveKey('next_cursor') // Cursor pagination has next_cursor
            ->and($data['meta'])->toHaveKey('prev_cursor') // Cursor pagination has prev_cursor
            ->and($data['meta'])->not->toHaveKey('current_page') // Cursor pagination doesn't have current_page
            ->and($data['meta'])->not->toHaveKey('total'); // Cursor pagination doesn't have total
    });

    it('falls back to first allowed type when requesting unsupported pagination type', function () {
        // Customer endpoint only supports SIMPLE pagination (default)
        $response = $this->getJson('/api/v1/customers', [
            'x-pagination' => 'table', // This should fall back to simple
        ]);

        $response->assertOk();
        $data = $response->json();

        // Should fall back to simple pagination (first allowed type)
        expect($data)->toHaveKey('meta')
            ->and($data['meta'])->toHaveKey('current_page')
            ->and($data['meta'])->toHaveKey('per_page')
            ->and($data['meta'])->not->toHaveKey('last_page'); // Simple pagination doesn't have last_page
    });

    it('is case insensitive for pagination header values', function () {
        $response = $this->getJson('/api/v1/invoices', [
            'x-pagination' => 'TABLE', // Uppercase should work
        ]);

        $response->assertOk();
        $data = $response->json();

        // Should use table pagination
        expect($data)->toHaveKey('meta')
            ->and($data['meta'])->toHaveKey('last_page'); // Table pagination has last_page
    });
});

describe('Pagination Response Casing', function () {
    it('returns snake_case pagination by default', function () {
        config(['openapi.schemas.default.config.pagination_response.casing' => 'snake']);

        $response = $this->getJson('/api/v1/invoices');

        $response->assertOk();
        $data = $response->json();

        // Should have snake_case pagination keys
        expect($data)->toHaveKey('meta')
            ->and($data['meta'])->toHaveKey('current_page')
            ->and($data['meta'])->toHaveKey('per_page')
            ->and($data['meta'])->toHaveKey('path')
            ->and($data['meta'])->toHaveKey('from')
            ->and($data['meta'])->toHaveKey('to');
    });

    it('returns camelCase pagination when configured', function () {
        config(['openapi.schemas.default.config.pagination_response.casing' => 'camel']);

        $response = $this->getJson('/api/v1/invoices');

        $response->assertOk();
        $data = $response->json();

        // Should have camelCase pagination keys
        expect($data)->toHaveKey('meta')
            ->and($data['meta'])->toHaveKey('currentPage')
            ->and($data['meta'])->toHaveKey('perPage')
            ->and($data['meta'])->toHaveKey('path')
            ->and($data['meta'])->toHaveKey('from')
            ->and($data['meta'])->toHaveKey('to')
            ->and($data['meta'])->not->toHaveKey('current_page')
            ->and($data['meta'])->not->toHaveKey('per_page');
    });
});

describe('Legacy Page Object Pagination (page[number] and page[size])', function () {
    it('supports page[size] for setting items per page', function () {
        $response = $this->getJson('/api/v1/invoices?page[size]=25');

        $response->assertOk();
        $data = $response->json();

        expect($data['meta']['per_page'])->toBe(25)
            ->and(count($data['data']))->toBeLessThanOrEqual(25);
    });

    it('supports page[number] for setting current page', function () {
        $response = $this->getJson('/api/v1/invoices?page[number]=2&page[size]=10');

        $response->assertOk();
        $data = $response->json();

        expect($data['meta']['current_page'])->toBe(2)
            ->and($data['meta']['per_page'])->toBe(10);
    });

    it('defaults to page 1 when only page[size] is provided', function () {
        $response = $this->getJson('/api/v1/invoices?page[size]=20');

        $response->assertOk();
        $data = $response->json();

        expect($data['meta']['current_page'])->toBe(1)
            ->and($data['meta']['per_page'])->toBe(20);
    });

    it('caps page[size] at maximum of 100 items per page', function () {
        $response = $this->getJson('/api/v1/invoices?page[size]=150');

        $response->assertOk();
        $data = $response->json();

        expect($data['meta']['per_page'])->toBe(100)
            ->and(count($data['data']))->toBeLessThanOrEqual(100);
    });

    it('works with table pagination type using page object', function () {
        $response = $this->getJson('/api/v1/invoices?page[number]=2&page[size]=10', [
            'x-pagination' => 'table',
        ]);

        $response->assertOk();
        $data = $response->json();

        expect($data['meta']['current_page'])->toBe(2)
            ->and($data['meta']['per_page'])->toBe(10)
            ->and($data['meta'])->toHaveKey('last_page')
            ->and($data['meta'])->toHaveKey('total');
    });
});

describe('Pagination Parameters (per_page vs perPage)', function () {
    it('uses snake_case per_page parameter for pagination', function () {
        $response = $this->getJson('/api/v1/invoices?per_page=25');

        $response->assertOk();
        $data = $response->json();

        expect($data['meta']['per_page'])->toBe(25)
            ->and(count($data['data']))->toBeLessThanOrEqual(25);
    });

    it('uses camelCase perPage parameter for pagination', function () {
        $response = $this->getJson('/api/v1/invoices?perPage=30');

        $response->assertOk();
        $data = $response->json();

        expect($data['meta']['per_page'])->toBe(30)
            ->and(count($data['data']))->toBeLessThanOrEqual(30);
    });

    it('prioritizes per_page over perPage when both are provided', function () {
        $response = $this->getJson('/api/v1/invoices?per_page=20&perPage=35');

        $response->assertOk();
        $data = $response->json();

        expect($data['meta']['per_page'])->toBe(20)
            ->and(count($data['data']))->toBeLessThanOrEqual(20);
    });

    it('uses default pagination size when neither parameter is provided', function () {
        $response = $this->getJson('/api/v1/invoices');

        $response->assertOk();
        $data = $response->json();

        expect($data['meta']['per_page'])->toBe(15)
            ->and(count($data['data']))->toBeLessThanOrEqual(15);
    });

    it('caps pagination at maximum of 100 items per page with per_page', function () {
        $response = $this->getJson('/api/v1/invoices?per_page=150');

        $response->assertOk();
        $data = $response->json();

        expect($data['meta']['per_page'])->toBe(100)
            ->and(count($data['data']))->toBeLessThanOrEqual(100);
    });

    it('caps pagination at maximum of 100 items per page with perPage', function () {
        $response = $this->getJson('/api/v1/invoices?perPage=150');

        $response->assertOk();
        $data = $response->json();

        expect($data['meta']['per_page'])->toBe(100)
            ->and(count($data['data']))->toBeLessThanOrEqual(100);
    });
});

describe('OpenAPI Pagination Parameters', function () {
    it('generates correct parameters for multiple pagination types', function () {
        $factory = new OpenApiGeneratorFactory;
        $generator = $factory->create(config('openapi.schemas.default'));

        $spec = $generator->generate([workbench_dir()]);
        $yaml = $spec->toYaml();

        // Parse YAML to check parameters
        $data = Yaml::parse($yaml);

        // Check invoices endpoint (supports simple, table, cursor)
        $invoicesEndpoint = $data['paths']['/api/v1/invoices']['get'];

        // Should have all parameters since it supports all types
        $paramNames = array_column($invoicesEndpoint['parameters'], 'name');

        expect($paramNames)->toContain('per_page')
            ->and($paramNames)->toContain('page')
            ->and($paramNames)->toContain('cursor'); // Should have all parameters
    });

    it('parameter descriptions explain when they are used', function () {
        $factory = new OpenApiGeneratorFactory;
        $generator = $factory->create(config('openapi.schemas.default'));

        $spec = $generator->generate([workbench_dir()]);
        $yaml = $spec->toYaml();

        // Parse YAML to check parameter descriptions
        $data = Yaml::parse($yaml);

        // Check invoices endpoint descriptions
        $invoicesEndpoint = $data['paths']['/api/v1/invoices']['get'];
        $parameters = $invoicesEndpoint['parameters'];

        // Find cursor parameter
        $cursorParam = collect($parameters)->firstWhere('name', 'cursor');
        expect($cursorParam['description'])->toContain('cursor pagination');

        // Find page parameter
        $pageParam = collect($parameters)->firstWhere('name', 'page');
        expect($pageParam['description'])->toContain('simple and table pagination');
    });

    it('includes x-pagination header for multiple pagination types', function () {
        $factory = new OpenApiGeneratorFactory;
        $generator = $factory->create(config('openapi.schemas.default'));

        $spec = $generator->generate([workbench_dir()]);
        $yaml = $spec->toYaml();

        // Parse YAML to check parameters
        $data = Yaml::parse($yaml);

        // Check invoices endpoint has x-pagination header
        $invoicesEndpoint = $data['paths']['/api/v1/invoices']['get'];
        $parameters = $invoicesEndpoint['parameters'];

        $headerParam = collect($parameters)->firstWhere('name', 'x-pagination');

        expect($headerParam)->not->toBeNull()
            ->and($headerParam['in'])->toBe('header')
            ->and($headerParam['required'])->toBeFalse()
            ->and($headerParam['schema']['type'])->toBe('string')
            ->and($headerParam['schema']['enum'])->toContain('simple')
            ->and($headerParam['schema']['enum'])->toContain('table')
            ->and($headerParam['schema']['enum'])->toContain('cursor')
            ->and($headerParam['schema']['example'])->toBe('simple');
    });

    it('does not include x-pagination header for single pagination type', function () {
        $factory = new OpenApiGeneratorFactory;
        $generator = $factory->create(config('openapi.schemas.default'));

        $spec = $generator->generate([workbench_dir()]);
        $yaml = $spec->toYaml();

        // Parse YAML to check parameters
        $data = Yaml::parse($yaml);

        // Check customers endpoint does NOT have x-pagination header (only supports simple)
        $customersEndpoint = $data['paths']['/api/v1/customers']['get'];
        $parameters = $customersEndpoint['parameters'];

        $headerParam = collect($parameters)->firstWhere('name', 'x-pagination');

        expect($headerParam)->toBeNull(); // Should not have x-pagination header for single type
    });

    it('x-pagination header description includes available types', function () {
        $factory = new OpenApiGeneratorFactory;
        $generator = $factory->create(config('openapi.schemas.default'));

        $spec = $generator->generate([workbench_dir()]);
        $yaml = $spec->toYaml();

        // Parse YAML to check parameter descriptions
        $data = Yaml::parse($yaml);

        // Check invoices endpoint header description
        $invoicesEndpoint = $data['paths']['/api/v1/invoices']['get'];
        $parameters = $invoicesEndpoint['parameters'];

        $headerParam = collect($parameters)->firstWhere('name', 'x-pagination');

        expect($headerParam['description'])
            ->toContain('simple, table, cursor')
            ->and($headerParam['description'])->toContain('Defaults to \'simple\'');
    });
});

describe('Pagination Links with Query String', function () {
    it('preserves per_page in pagination links', function () {
        $response = $this->getJson('/api/v1/invoices?per_page=10');

        $response->assertOk();
        $links = $response->json('links');

        expect($links['first'])->toContain('per_page=10')
            ->and($links['next'])->toContain('per_page=10');
    });

    it('preserves filter parameters in pagination links', function () {
        $response = $this->getJson('/api/v1/invoices?filter[0][key]=invoice_number&filter[0][op]=equals&filter[0][value]=INV-001');

        $response->assertOk();
        $links = $response->json('links');

        // The filter should be preserved in the pagination links
        expect($links['first'])->toContain('filter');
    });

    it('preserves sort parameter in pagination links', function () {
        $response = $this->getJson('/api/v1/invoices?sort=-invoice_number');

        $response->assertOk();
        $links = $response->json('links');

        expect($links['first'])->toContain('sort=-invoice_number');
    });

    it('preserves include parameter in pagination links', function () {
        $response = $this->getJson('/api/v1/invoices?include=customer');

        $response->assertOk();
        $links = $response->json('links');

        expect($links['first'])->toContain('include=customer');
    });

    it('preserves multiple query parameters in pagination links', function () {
        $response = $this->getJson('/api/v1/invoices?per_page=5&sort=-invoice_number&include=customer');

        $response->assertOk();
        $links = $response->json('links');

        expect($links['first'])->toContain('per_page=5')
            ->and($links['first'])->toContain('sort=-invoice_number')
            ->and($links['first'])->toContain('include=customer');
    });

    it('preserves query string in table pagination links', function () {
        $response = $this->getJson('/api/v1/invoices?per_page=10&sort=invoice_number', [
            'x-pagination' => 'table',
        ]);

        $response->assertOk();
        $links = $response->json('links');

        expect($links['first'])->toContain('per_page=10')
            ->and($links['first'])->toContain('sort=invoice_number')
            ->and($links['last'])->toContain('per_page=10')
            ->and($links['last'])->toContain('sort=invoice_number');
    });

    it('preserves query string in cursor pagination links', function () {
        $response = $this->getJson('/api/v1/invoices?per_page=10&include=customer', [
            'x-pagination' => 'cursor',
        ]);

        $response->assertOk();
        $links = $response->json('links');

        // Cursor pagination should preserve query parameters in next link
        if ($links['next']) {
            expect($links['next'])->toContain('per_page=10')
                ->and($links['next'])->toContain('include=customer');
        }
    });

    it('preserves legacy page object parameters in pagination links', function () {
        $response = $this->getJson('/api/v1/invoices?page[size]=10&include=customer');

        $response->assertOk();
        $links = $response->json('links');

        expect($links['first'])->toContain('include=customer');
    });

    it('has null last link for simple pagination', function () {
        $response = $this->getJson('/api/v1/invoices?per_page=10');

        $response->assertOk();
        $links = $response->json('links');

        expect($links['last'])->toBeNull()
            ->and($links['first'])->not->toBeNull();
    });

    it('has populated last link for table pagination', function () {
        $response = $this->getJson('/api/v1/invoices?per_page=10', [
            'x-pagination' => 'table',
        ]);

        $response->assertOk();
        $links = $response->json('links');

        expect($links['last'])->not->toBeNull()
            ->and($links['last'])->toContain('page=')
            ->and($links['first'])->not->toBeNull();
    });
});
