<?php declare(strict_types=1);

namespace Xentral\LaravelApi;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;

class ValidationResponseStatusCodeProcessor
{
    public function __construct(private readonly int $validationStatusCode = 422) {}

    public function __invoke(Analysis $analysis): void
    {
        /** @var OA\Operation[] $allOperations */
        $allOperations = $analysis->getAnnotationsOfType(OA\Operation::class);

        foreach ($allOperations as $operation) {
            foreach ($operation->responses as $response) {
                $status = (int) $response->response;
                if ($status === 422 || $status === 400) {
                    $response->response = $this->validationStatusCode;
                }
            }
        }
    }
}
