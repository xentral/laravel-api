<?php declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $updated_at
 * @property Carbon $created_at
 */
class Project extends Model
{
    protected $guarded = [];
}
