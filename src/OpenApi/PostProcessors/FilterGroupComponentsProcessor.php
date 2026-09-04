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
 * A filter group may contain groups, which an inline parameter schema cannot
 * express. FilterParameter therefore emits a `$ref` and registers the condition
 * schema it built here, and this processor turns each registration into
 * `{name}Condition` plus a chain of group components: `{name}Group` for the top
 * level, `{name}GroupDepth2` down to `{name}GroupDepth{MAX_GROUP_DEPTH}` below
 * it. Every level refers to the condition schema and to the next level only,
 * and the deepest level accepts conditions alone.
 *
 * The chain is deliberately acyclic. A group schema that referred to itself
 * would describe the wire format in one line, but tooling that dereferences a
 * spec into a plain object tree - the publish pipeline does - cannot serialise
 * a cycle. Unrolling to the runtime cap keeps the published spec a tree and
 * makes the depth limit visible in the schema itself; the runtime enforces the
 * same cap, from the same constant.
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
                // never sees the `$ref`s between the levels and drops them.
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
        $schemas = [
            new Schema(
                schema: $name.'Condition',
                title: 'Condition',
                properties: $conditionProperties,
                type: 'object',
                additionalProperties: false,
            ),
        ];

        for ($level = 1; $level <= QueryBuilderRequest::MAX_GROUP_DEPTH; $level++) {
            $schemas[] = $this->groupSchema($name, $level);
        }

        return $schemas;
    }

    /**
     * The group component for one nesting level; the outermost group is level one.
     */
    private function groupSchema(string $name, int $level): Schema
    {
        $conditionRef = new Schema(ref: Components::COMPONENTS_PREFIX.'schemas/'.$name.'Condition');

        if ($level === QueryBuilderRequest::MAX_GROUP_DEPTH) {
            $description = sprintf('A filter group at the deepest level, %d; its conditions cannot be groups.', $level);
            $items = new Items(ref: $conditionRef->ref);
        } else {
            $description = $level === 1
                ? sprintf(
                    'A boolean group of filter conditions. A condition may be a group itself, nesting to at most %d levels.',
                    QueryBuilderRequest::MAX_GROUP_DEPTH,
                )
                : sprintf('A filter group nested at level %d of at most %d.', $level, QueryBuilderRequest::MAX_GROUP_DEPTH);
            $items = new Items(oneOf: [
                $conditionRef,
                new Schema(ref: Components::COMPONENTS_PREFIX.'schemas/'.$this->groupComponentName($name, $level + 1)),
            ]);
        }

        return new Schema(
            schema: $this->groupComponentName($name, $level),
            title: $level === 1 ? 'Filter group' : sprintf('Filter group, level %d', $level),
            description: $description,
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
                    items: $items,
                ),
            ],
            type: 'object',
            additionalProperties: false,
        );
    }

    private function groupComponentName(string $name, int $level): string
    {
        return $level === 1 ? $name.'Group' : sprintf('%sGroupDepth%d', $name, $level);
    }
}
