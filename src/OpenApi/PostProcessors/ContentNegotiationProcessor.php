<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Generator;

class ContentNegotiationProcessor
{
    public function __invoke(Analysis $analysis): void
    {
        $allOperations = $analysis->getAnnotationsOfType(OA\Operation::class);

        /** @var OA\Operation $operation */
        foreach ($allOperations as $operation) {
            $additionalMediaTypes = $this->getAdditionalMediaTypes($operation);

            if (empty($additionalMediaTypes)) {
                continue;
            }

            $description = $operation->description !== Generator::UNDEFINED ? $operation->description : '';
            $operation->description = $description.$this->buildContentNegotiationNote($additionalMediaTypes);
        }
    }

    /**
     * @return string[]
     */
    private function getAdditionalMediaTypes(OA\Operation $operation): array
    {
        if ($operation->responses === Generator::UNDEFINED) {
            return [];
        }

        foreach ($operation->responses as $response) {
            if ((string) $response->response !== '200') {
                continue;
            }

            if ($response->content === Generator::UNDEFINED || ! is_array($response->content)) {
                return [];
            }

            if (count($response->content) <= 1) {
                return [];
            }

            $mediaTypes = [];
            foreach ($response->content as $content) {
                if ($content->mediaType !== 'application/json') {
                    $mediaTypes[] = $content->mediaType;
                }
            }

            return $mediaTypes;
        }

        return [];
    }

    /**
     * @param  string[]  $mediaTypes
     */
    private function buildContentNegotiationNote(array $mediaTypes): string
    {
        $formatted = array_map(fn (string $type) => "`{$type}`", $mediaTypes);
        $list = implode(', ', $formatted);

        return "\n\n**Content Negotiation:** This endpoint supports additional response formats: {$list}. Use the `Accept` header to request a specific format.";
    }
}
