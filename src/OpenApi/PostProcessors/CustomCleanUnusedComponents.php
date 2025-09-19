<?php declare(strict_types=1);

namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Generator;
use OpenApi\Processors\Concerns\AnnotationTrait;

/**
 * Custom version of CleanUnusedComponents that preserves security schemes.
 *
 * This processor tracks the use of all Components and removes unused schemas,
 * but always preserves security schemes since they may be referenced globally.
 */
class CustomCleanUnusedComponents
{
    use AnnotationTrait;

    public function __invoke(Analysis $analysis): void
    {
        if (Generator::isDefault($analysis->openapi->components)) {
            return;
        }

        $analysis->annotations = $this->collectAnnotations($analysis->annotations);

        // Allow multiple runs to catch nested dependencies
        for ($ii = 0; $ii < 10; $ii++) {
            if (! $this->cleanup($analysis)) {
                break;
            }
        }
    }

    protected function cleanup(Analysis $analysis): bool
    {
        $usedRefs = [];
        foreach ($analysis->annotations as $annotation) {
            if (property_exists($annotation, 'ref') && ! Generator::isDefault($annotation->ref) && $annotation->ref !== null) {
                $usedRefs[$annotation->ref] = $annotation->ref;
            }

            foreach (['allOf', 'anyOf', 'oneOf'] as $sub) {
                if (property_exists($annotation, $sub) && ! Generator::isDefault($annotation->{$sub})) {
                    foreach ($annotation->{$sub} as $subElem) {
                        if (is_object($subElem) && property_exists($subElem, 'ref') && ! Generator::isDefault($subElem->ref) && $subElem->ref !== null) {
                            $usedRefs[$subElem->ref] = $subElem->ref;
                        }
                    }
                }
            }

            if ($annotation instanceof OA\OpenApi || $annotation instanceof OA\Operation) {
                if (! Generator::isDefault($annotation->security)) {
                    foreach ($annotation->security as $security) {
                        foreach (array_keys($security) as $securityName) {
                            $ref = OA\Components::COMPONENTS_PREFIX.'securitySchemes/'.$securityName;
                            $usedRefs[$ref] = $ref;
                        }
                    }
                }
            }
        }

        $unusedRefs = [];
        foreach (OA\Components::$_nested as $nested) {
            if (count($nested) == 2) {
                // $nested[1] is the name of the property that holds the component name
                [$componentType, $nameProperty] = $nested;

                // Skip security schemes - always preserve them
                if ($componentType === 'securitySchemes') {
                    continue;
                }

                if (! Generator::isDefault($analysis->openapi->components->{$componentType})) {
                    foreach ($analysis->openapi->components->{$componentType} as $component) {
                        $ref = OA\Components::ref($component);
                        if (! in_array($ref, $usedRefs)) {
                            $unusedRefs[$ref] = [$ref, $nameProperty];
                        }
                    }
                }
            }
        }

        // Remove unused components (but not security schemes)
        foreach ($unusedRefs as $refDetails) {
            [$ref, $nameProperty] = $refDetails;
            [$hash, $components, $componentType, $name] = explode('/', $ref);
            foreach ($analysis->openapi->components->{$componentType} as $ii => $component) {
                if ($component->{$nameProperty} == $name) {
                    $annotation = $analysis->openapi->components->{$componentType}[$ii];
                    $this->removeAnnotation($analysis->annotations, $annotation);
                    unset($analysis->openapi->components->{$componentType}[$ii]);

                    if (! $analysis->openapi->components->{$componentType}) {
                        $analysis->openapi->components->{$componentType} = Generator::UNDEFINED;
                    }
                }
            }
        }

        return count($unusedRefs) != 0;
    }
}
