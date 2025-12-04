<?php

declare(strict_types=1);

namespace App\Support\Swagger\Attributes;

use Attribute;

/**
 * API Parameter Attribute
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ApiParameter
{
    public function __construct(
        public string $name,
        public string $in, // query, path, header, body
        public string $type = 'string',
        public bool $required = false,
        public string $description = '',
        public mixed $example = null,
        public ?array $schema = null
    ) {}
}
