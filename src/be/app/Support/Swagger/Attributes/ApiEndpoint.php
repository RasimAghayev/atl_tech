<?php

declare(strict_types=1);

namespace App\Support\Swagger\Attributes;

use Attribute;

/**
 * API Endpoint Attribute
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class ApiEndpoint
{
    public function __construct(
        public string $method,
        public string $path,
        public string $summary,
        public string $description = '',
        public array $parameters = [],
        public array $responses = [],
        public bool $authenticated = true,
        public array $permissions = []
    ) {}
}
