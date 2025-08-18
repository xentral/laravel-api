<?php declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Workbench\App\Enum\StatusEnum;

/**
 * @property int $id
 * @property string $name
 * @property StatusEnum $status
 * @property Carbon $updated_at
 * @property Carbon $created_at
 */
class TestModel extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => StatusEnum::class,
        ];
    }
}
