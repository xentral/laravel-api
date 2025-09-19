<?php declare(strict_types=1);

namespace Workbench\App\Http\Data;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UnusedData',
    properties: [
        new OA\Property(property: 'name', type: 'string'),
    ],
    type: 'object',
    additionalProperties: false,
)]
class UnusedData {}
