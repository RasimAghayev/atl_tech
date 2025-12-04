<?php

declare(strict_types=1);

namespace App\Http\Controllers;

/**
 * SwaggerController
 *
 * This controller serves as the entry point for API documentation.
 * Swagger documentation is auto-generated from PHP Attributes using the command:
 * php artisan swagger:generate-from-attributes
 *
 * Documentation approach:
 * - Use #[ApiResource] on controller classes to define resource metadata
 * - Use #[ApiEndpoint] on methods to define endpoint metadata
 * - Use #[ApiResponse] on methods to define response schemas
 * - Use #[ApiParameter] on methods to define custom parameters
 *
 * The generated OpenAPI 3.0 spec is saved to storage/api-docs/swagger.json
 */
class SwaggerController {}
