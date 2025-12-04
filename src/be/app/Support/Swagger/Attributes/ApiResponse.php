<?php

declare(strict_types=1);

namespace App\Support\Swagger\Attributes;

use Attribute;

/**
 * API Response Attribute
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ApiResponse
{
    public function __construct(
        public int $code,
        public string $description,
        public string $schema = '',
        public array $example = []
    ) {}
}
