<?php

declare(strict_types=1);

namespace App\Support\Swagger\Attributes;

use Attribute;

/**
 * Base API Resource Attribute
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ApiResource
{
    public function __construct(
        public string $name,
        public string $description = '',
        public string $model = '',
        public string $collection = '',
        public string $requestClass = '',
        public string $updateRequestClass = ''
    ) {}
}
