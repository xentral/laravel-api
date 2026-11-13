<?php declare(strict_types=1);

use Illuminate\Http\Request;
use Workbench\App\Models\Document;
use Workbench\App\Models\Project;
use Xentral\LaravelApi\Http\ApiResource;
use Xentral\LaravelApi\Query\Filters\IdentifierFilter;
use Xentral\LaravelApi\Query\Filters\QueryFilter;

/**
 * An id key is unset exactly where ApiResource::reference() renders null: the column holds NULL,
 * '' or a zero. isNull returns those rows and isNotNull every other one, so the two split the
 * table whatever type the column has.
 */
function documentsWith(string $column, array $values): array
{
    $ids = [];
    foreach ($values as $name => $value) {
        $ids[$name] = Document::query()->create([$column => $value])->id;
    }

    return $ids;
}

function documentIdsMatching(callable $filter, array $value, string $property): array
{
    $query = Document::query();
    $filter($query, $value, $property);

    return $query->pluck('id')->sort()->values()->all();
}

describe('identifier presence on a text column', function () {
    it('reads NULL, an empty string and a zero as unset', function (string $operator, array $expected) {
        $documents = documentsWith('project_ref', ['null' => null, 'empty' => '', 'zero' => '0', 'set' => '5']);

        $filter = QueryFilter::identifier('project.id', 'project_ref')->getFilterClass();

        expect(documentIdsMatching($filter, ['operator' => $operator], 'project_ref'))
            ->toBe(collect($expected)->map(fn (string $name) => $documents[$name])->sort()->values()->all());
    })->with([
        'isNull' => ['isNull', ['null', 'empty', 'zero']],
        'isNotNull' => ['isNotNull', ['set']],
    ]);

    it('keeps a zero a text key holds as a value', function (string $operator, array $expected) {
        $documents = documentsWith('project_ref', ['null' => null, 'empty' => '', 'zero' => '0', 'set' => '5']);

        $filter = QueryFilter::string('reference', 'project_ref')->getFilterClass();

        expect(documentIdsMatching($filter, ['operator' => $operator], 'project_ref'))
            ->toBe(collect($expected)->map(fn (string $name) => $documents[$name])->sort()->values()->all());
    })->with([
        'isNull' => ['isNull', ['null', 'empty']],
        'isNotNull' => ['isNotNull', ['zero', 'set']],
    ]);
});

describe('identifier presence on an integer column', function () {
    it('reads NULL and zero as unset', function (string $operator, array $expected) {
        $documents = documentsWith('origin_id', ['null' => null, 'zero' => 0, 'set' => 5]);

        $filter = QueryFilter::identifier('origin.id', 'origin_id')->getFilterClass();

        expect(documentIdsMatching($filter, ['operator' => $operator], 'origin_id'))
            ->toBe(collect($expected)->map(fn (string $name) => $documents[$name])->sort()->values()->all());
    })->with([
        'isNull' => ['isNull', ['null', 'zero']],
        'isNotNull' => ['isNotNull', ['set']],
    ]);
});

describe('identifier presence through a relation', function () {
    it('asks whether any related row carries an id', function (string $operator, array $expected) {
        $documents = [];
        foreach (['unset' => [null, '', '0'], 'mixed' => ['0', '5'], 'set' => ['5', '6'], 'none' => []] as $name => $projectRefs) {
            $document = Document::query()->create();
            foreach ($projectRefs as $projectRef) {
                Document::query()->create(['origin_id' => $document->id, 'project_ref' => $projectRef]);
            }
            $documents[$name] = $document->id;
        }

        $query = Document::query()->whereKey($documents);
        QueryFilter::identifier('partials.project.id', 'partials.project_ref')->getFilterClass()($query, ['operator' => $operator], 'partials.project_ref');

        expect($query->pluck('id')->sort()->values()->all())
            ->toBe(collect($expected)->map(fn (string $name) => $documents[$name])->sort()->values()->all());
    })->with([
        'isNull' => ['isNull', ['unset', 'none']],
        'isNotNull' => ['isNotNull', ['mixed', 'set']],
    ]);
});

describe('identifier presence follows the rendered reference', function () {
    it('answers isNull exactly where reference() renders null', function (string $column, string $relation, mixed $stored) {
        Project::query()->create(['name' => 'Resolvable']);
        $document = Document::query()->create([$column => $stored]);

        $resource = new class($document->refresh()) extends ApiResource
        {
            public function rendered(string $relation): ?array
            {
                return $this->reference($relation);
            }

            public function toArray(Request $request): array
            {
                return [];
            }
        };

        $filter = new IdentifierFilter;

        expect(documentIdsMatching($filter, ['operator' => 'isNull'], $column) === [$document->id])
            ->toBe($resource->rendered($relation) === null)
            ->and(documentIdsMatching($filter, ['operator' => 'isNotNull'], $column) === [$document->id])
            ->toBe($resource->rendered($relation) !== null);
    })->with([
        'text NULL' => ['project_ref', 'project', null],
        'text empty' => ['project_ref', 'project', ''],
        'text zero' => ['project_ref', 'project', '0'],
        'text id' => ['project_ref', 'project', '1'],
        'text dangling id' => ['project_ref', 'project', '999'],
        'integer NULL' => ['origin_id', 'origin', null],
        'integer zero' => ['origin_id', 'origin', 0],
        'integer dangling id' => ['origin_id', 'origin', 999],
    ]);
});
