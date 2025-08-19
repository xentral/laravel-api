<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Endpoints;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class ActionEndpoint extends PatchEndpoint {}
