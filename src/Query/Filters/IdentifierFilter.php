<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

use Xentral\LaravelApi\Http\ApiResource;

/**
 * An id key, which holds no value exactly where the payload renders the reference as null.
 *
 * ApiResource::reference() renders a foreign key by its PHP truthiness, so NULL, '', '0' and 0 all
 * come out as null. UNSET_VALUES is that set in SQL, next to NULL: isNull matches it and isNotNull
 * is its complement. Both entries are strings on purpose. An integer column compares them as
 * numbers, where both read as 0, while a text column compares them as text and so matches the two
 * spellings a legacy column stores for "no reference".
 *
 * Only id keys read '0' this way. On a text or enum key it is a value like any other.
 *
 * @see ApiResource::reference()
 */
class IdentifierFilter extends StringOperatorFilter
{
    /** @var list<string> */
    public const UNSET_VALUES = ['', '0'];

    public function __construct(?string $filterName = null)
    {
        parent::__construct(FilterOperator::ID, filterName: $filterName);
    }

    protected function unsetValues(): array
    {
        return self::UNSET_VALUES;
    }
}
