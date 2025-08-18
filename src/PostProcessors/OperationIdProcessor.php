<?php declare(strict_types=1);

namespace Xentral\LaravelApi\PostProcessors;

use Illuminate\Support\Str;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Generator;

class OperationIdProcessor
{
    public function __invoke(Analysis $analysis)
    {
        $allOperations = $analysis->getAnnotationsOfType(OA\Operation::class);

        /** @var OA\Operation $operation */
        foreach ($allOperations as $operation) {
            if ($operation->operationId === null) {
                $operation->operationId = Generator::UNDEFINED;
            }

            if (! Generator::isDefault($operation->operationId)) {
                continue;
            }

            $operation->operationId = Str::of($operation->path)
                ->replace('/', '-')
                ->slug(dictionary: ['{' => '-', '}' => '-'])
                ->prepend(strtoupper($operation->method).'::')
                ->toString();
        }
    }
}
