<?php declare(strict_types=1);

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\LineItem;

class LineItemFactory extends Factory
{
    protected $model = LineItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 100);
        $unitPrice = $this->faker->randomFloat(2, 10, 1000);
        $discountPercent = $this->faker->optional(0.3)->randomFloat(2, 0, 25);
        $subtotal = $quantity * $unitPrice;
        $totalPrice = $discountPercent
            ? $subtotal * (1 - $discountPercent / 100)
            : $subtotal;

        return [
            'invoice_id' => Invoice::factory(),
            'product_name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'discount_percent' => $discountPercent,
        ];
    }

    public function withDiscount(float $percent = 10.0): static
    {
        return $this->state(function (array $attributes) use ($percent) {
            $subtotal = $attributes['quantity'] * $attributes['unit_price'];

            return [
                'discount_percent' => $percent,
                'total_price' => $subtotal * (1 - $percent / 100),
            ];
        });
    }

    public function withoutDiscount(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'discount_percent' => null,
                'total_price' => $attributes['quantity'] * $attributes['unit_price'],
            ];
        });
    }
}
