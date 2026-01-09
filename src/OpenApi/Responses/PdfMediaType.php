<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Responses;

use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Schema;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class PdfMediaType extends MediaType
{
    public function __construct(?string $example = 'Binary PDF content')
    {
        parent::__construct(
            mediaType: 'application/pdf',
            schema: new Schema(type: 'string', format: 'binary', example: $example)
        );
    }
}
