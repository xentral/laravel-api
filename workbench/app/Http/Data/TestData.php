<?php declare(strict_types=1);
namespace Workbench\App\Http\Data;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class TestData extends Data
{
    public function __construct(
        #[Max(255)]
        public string $name,
        #[Email]
        public string $email,
        #[Min(18)]
        public int $age,
        public ?string $description = null,
    ) {}
}
