<?php declare(strict_types=1);

use Workbench\App\Models\TestModel;

beforeEach(function () {
    $this->models = TestModel::factory()->count(5)->create();
});

it('can filter models via equals operator', function () {
    $models = createQueryFromFilterRequest([
        [
            'key' => 'name',
            'op' => 'equal',
            'value' => $this->models->first()->name,
        ],
    ])
        ->allowedFilters('name')
        ->get();

    expect($models)->toHaveCount(1);
});
