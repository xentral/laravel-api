<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

/**
 * What an id filter built from a callback points at.
 *
 * A relation can hold many rows per record, so `equals` with several ids means
 * "all of them" there. A column holds one value per record, where the same
 * reading would be unsatisfiable, so `equals` stays "any of them".
 */
enum IdFilterTarget: string
{
    case Relation = 'relation';
    case Column = 'column';
}
