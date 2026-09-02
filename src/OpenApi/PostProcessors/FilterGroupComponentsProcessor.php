<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use OpenApi\Analysis;
use OpenApi\Annotations\Components;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use OpenApi\Generator;
use Xentral\LaravelApi\Http\QueryBuilderRequest;

/**
 * Writes the component schemas a nesting filter parameter refers to.
 *
 * A filter group may contain groups, so its schema has to refer to itself -
 * which an inline parameter schema cannot do. FilterParameter therefore emits a
 * `$ref` and registers the condition schema it built here, and this processor
 * turns each registration into the `{name}Condition` / `{name}Group` pair the
 * `$ref` resolves against.
 *
 * The registry is static because attributes are constructed while the analyser
 * scans, long before any processor runs; it is emptied once written so a second
 * generation in the same process starts from its own scan.
 */
class FilterGroupComponentsProcessor
{
    /** @var array<string, list<Property>> */
    private static array $registered = [];

    /**
     * @param  list<Property>  $conditionProperties  the condition schema of the parameter that asked for the name
     */
    public static function register(string $name, array $conditionProperties): void
    {
        self::$registered[$name] = $conditionProperties;
    }

    public function __invoke(Analysis $analysis): void
    {
        $registered = self::$registered;
        self::$registered = [];

        $context = $analysis->context ?? $analysis->openapi?->_context;

        if ($registered === [] || $context === null || ! $analysis->openapi->components instanceof Components) {
            return;
        }

        $schemas = Generator::isDefault($analysis->openapi->components->schemas)
            ? []
            : $analysis->openapi->components->schemas;

        $existing = array_map(fn ($schema) => (string) $schema->schema, $schemas);

        foreach ($registered as $name => $conditionProperties) {
            foreach ($this->componentsFor($name, $conditionProperties) as $schema) {
                if (in_array((string) $schema->schema, $existing, true)) {
                    continue;
                }

                // Registering puts the schema and everything under it into the
                // annotation tree. Without that the unused component cleanup
                // never sees the `$ref` the group makes to its condition and
                // drops that component again.
                $analysis->addAnnotation($schema, $context);
                $schemas[] = $schema;
            }
        }

        $analysis->openapi->components->schemas = $schemas;
    }

    /**
     * @param  list<Property>  $conditionProperties
     * @return list<Schema>
     */
    private function componentsFor(string $name, array $conditionProperties): array
    {
        return [
            new Schema(
                schema: $name.'Condition',
                title: 'Condition',
                properties: $conditionProperties,
                type: 'object',
                additionalProperties: false,
            ),
            new Schema(
                schema: $name.'Group',
                title: 'Filter group',
                description: sprintf(
                    'A boolean group of filter conditions. A condition may be a group itself, nesting to at most %d levels.',
                    QueryBuilderRequest::MAX_GROUP_DEPTH,
                ),
                required: ['op', 'conditions'],
                properties: [
                    new Property(
                        property: 'op',
                        description: 'Boolean operator combining the conditions.',
                        type: 'string',
                        enum: ['and', 'or'],
                    ),
                    new Property(
                        property: 'conditions',
                        type: 'array',
                        items: new Items(
                            oneOf: [
                                new Schema(ref: Components::COMPONENTS_PREFIX.'schemas/'.$name.'Condition'),
                                new Schema(ref: Components::COMPONENTS_PREFIX.'schemas/'.$name.'Group'),
                            ],
                        ),
                    ),
                ],
                type: 'object',
                additionalProperties: false,
            ),
        ];
    }
}
