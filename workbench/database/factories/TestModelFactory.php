<?php declare(strict_types=1);
namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Enum\StatusEnum;
use Workbench\App\Models\TestModel;

class TestModelFactory extends Factory
{
    protected $model = TestModel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'status' => StatusEnum::PENDING,
        ];
    }
}
