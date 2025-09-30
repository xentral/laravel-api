<?php declare(strict_types=1);

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Invoice;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $issuedAt = $this->faker->dateTimeBetween('-1 year', 'now');
        $dueAt = $this->faker->dateTimeBetween($issuedAt, '+30 days');

        return [
            'invoice_number' => 'INV-'.$this->faker->unique()->numerify('######'),
            'customer_id' => Customer::factory(),
            'status' => $this->faker->randomElement(['draft', 'sent', 'paid', 'overdue', 'cancelled']),
            'total_amount' => $this->faker->randomFloat(2, 100, 50000),
            'issued_at' => $issuedAt,
            'due_at' => $dueAt,
            'paid_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'issued_at' => null,
            'due_at' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => $this->faker->dateTimeBetween($attributes['issued_at'] ?? '-30 days', 'now'),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'overdue',
            'due_at' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
