<?php declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Workbench\App\Models\Document;
use Workbench\App\Models\Project;
use Xentral\LaravelApi\OpenApi\Filters\IdFilter;
use Xentral\LaravelApi\Query\Filters\QueryFilter;
use Xentral\LaravelApi\Query\Filters\ReferenceFilter;

/**
 * A reference key answers for the reference the payload resolves, so a stored id whose row is
 * gone is no reference: it sits under isNull together with the rows holding no id at all.
 *
 * @return array<string, int> document id per row state
 */
function documentsReferencingAProject(): array
{
    $project = Project::query()->create(['name' => 'Resolvable']);

    $documents = [];
    foreach (['null' => null, 'empty' => '', 'zero' => '0', 'resolvable' => (string) $project->id, 'dangling' => '999'] as $name => $projectRef) {
        $documents[$name] = Document::query()->create(['project_ref' => $projectRef])->id;
    }

    return $documents;
}

/**
 * @return array<string, int> document id per row state
 */
function documentsReferencingAnOrigin(): array
{
    $origin = Document::query()->create();

    $documents = ['origin' => $origin->id];
    foreach (['null' => null, 'zero' => 0, 'resolvable' => $origin->id, 'dangling' => 999] as $name => $originId) {
        $documents[$name] = Document::query()->create(['origin_id' => $originId])->id;
    }

    return $documents;
}

function documentIdsReferencing(string $name, string $relation, array $value): array
{
    $query = Document::query();
    $filter = QueryFilter::reference($name, $relation);
    $filter->getFilterClass()($query, $value, $filter->getInternalName());

    return $query->pluck('id')->sort()->values()->all();
}

describe('reference presence', function () {
    it('splits a text column into resolvable references and everything else', function (string $operator, array $expected) {
        $documents = documentsReferencingAProject();

        expect(documentIdsReferencing('project.id', 'project', ['operator' => $operator]))
            ->toBe(collect($expected)->map(fn (string $name) => $documents[$name])->sort()->values()->all());
    })->with([
        'isNull' => ['isNull', ['null', 'empty', 'zero', 'dangling']],
        'isNotNull' => ['isNotNull', ['resolvable']],
    ]);

    it('splits an integer column into resolvable references and everything else', function (string $operator, array $expected) {
        $documents = documentsReferencingAnOrigin();

        expect(documentIdsReferencing('origin.id', 'origin', ['operator' => $operator]))
            ->toBe(collect($expected)->map(fn (string $name) => $documents[$name])->sort()->values()->all());
    })->with([
        'isNull' => ['isNull', ['origin', 'null', 'zero', 'dangling']],
        'isNotNull' => ['isNotNull', ['resolvable']],
    ]);
});

describe('reference membership', function () {
    it('matches on the stored id, whether or not it resolves', function (string $operator, mixed $value, array $expected) {
        $documents = documentsReferencingAnOrigin();
        $value = $value === 'the origin' ? (string) $documents['origin'] : $value;

        expect(documentIdsReferencing('origin.id', 'origin', ['operator' => $operator, 'value' => $value]))
            ->toBe(collect($expected)->map(fn (string $name) => $documents[$name])->sort()->values()->all());
    })->with([
        'equals a resolvable id' => ['equals', 'the origin', ['resolvable']],
        'equals a dangling id' => ['equals', '999', ['dangling']],
        'notEquals a dangling id' => ['notEquals', '999', ['origin', 'null', 'zero', 'resolvable']],
        'in' => ['in', ['999', '998'], ['dangling']],
        'notIn' => ['notIn', ['999', '998'], ['origin', 'null', 'zero', 'resolvable']],
    ]);

    it('matches a text column on the stored id', function () {
        $documents = documentsReferencingAProject();

        expect(documentIdsReferencing('project.id', 'project', ['operator' => 'in', 'value' => ['999']]))
            ->toBe([$documents['dangling']]);
    });
});

describe('reference filter contract', function () {
    it('applies every entry of a repeated key', function () {
        $documents = documentsReferencingAnOrigin();

        $matching = documentIdsReferencing('origin.id', 'origin', [
            ['operator' => 'isNull'],
            ['operator' => 'notEquals', 'value' => '999'],
        ]);

        expect($matching)->toBe(collect(['origin', 'null', 'zero'])->map(fn (string $name) => $documents[$name])->sort()->values()->all());
    });

    it('rejects an operator outside the id set on the filter name', function (array $value) {
        try {
            documentIdsReferencing('partialDelivery.splitFrom.id', 'origin', $value);
        } catch (ValidationException $e) {
            expect(array_keys($e->errors()))->toBe(['partialDelivery.splitFrom.id']);

            return;
        }

        test()->fail('Expected a ValidationException');
    })->with([
        'an operator of another family' => [['operator' => 'contains', 'value' => '1']],
        'an unknown operator' => [['operator' => 'bogus', 'value' => '1']],
    ]);

    it('refuses a relation that holds no foreign key on the filtered model', function (string $relation, array $value) {
        expect(fn () => documentIdsReferencing('partials.id', $relation, $value))
            ->toThrow(LogicException::class, "Document::{$relation}");
    })->with([
        'a has-many relation, presence' => ['partials', ['operator' => 'isNull']],
        'a has-many relation, membership' => ['partials', ['operator' => 'equals', 'value' => '1']],
        'no relation at all' => ['missing', ['operator' => 'isNotNull']],
    ]);

    it('accepts exactly the operators IdFilter documents by default', function () {
        $filter = QueryFilter::reference('origin.id', 'origin')->getFilterClass();

        expect($filter)->toBeInstanceOf(ReferenceFilter::class)
            ->and($filter->allowedOperators())->toBe((new IdFilter)->operators);
    });
});
