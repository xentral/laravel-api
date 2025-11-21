<?php declare(strict_types=1);

namespace Workbench\App\Http\Resources;

/**
 * @method void addAdditionalFields(array $fields)
 */
trait IsMetaFieldResource
{
    protected function initializeIsMetaFieldResource(): void
    {
        $this->addAdditionalFields(['meta' => [
            'foo' => 'bar',
            'baz' => 123,
        ]]);
    }
}
