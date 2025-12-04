<?php

declare(strict_types=1);

namespace App\Support\Swagger\Services;

use App\Support\Swagger\Attributes\{ApiEndpoint,ApiParameter,ApiResource,ApiResponse};
use Illuminate\Support\Facades\Route;

class SwaggerGenerator
{
    protected array $documentation = [];

    protected array $schemas = [];

    protected array $tags = [];

    /**
     * @throws \ReflectionException
     */
    public function generateFromController(string $controllerClass): array
    {
        $reflection = new \ReflectionClass($controllerClass);

        // Get class-level attributes
        $resourceAttr = $this->getClassAttribute($reflection, ApiResource::class);

        if ($resourceAttr) {
            $this->processResource($reflection, $resourceAttr);
        }

        // Process each method
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $this->processMethod($method, $resourceAttr);
        }

        return [
            'paths' => $this->documentation['paths'] ?? [],
            'tags' => $this->tags,
            'schemas' => $this->schemas,
        ];
    }

    protected function processResource(\ReflectionClass $class, ApiResource $resource): void
    {
        // Add tag only if not already exists
        if (!$this->hasTag($resource->name)) {
            $this->tags[] = [
                'name' => $resource->name,
                'description' => $resource->description,
            ];
        }

        // Generate schemas based on a model
        if ($resource->model) {
            $this->generateModelSchema($resource->model, $resource->name);
        }

        // Generate request schemas
        if ($resource->requestClass) {
            $this->generateRequestSchema($resource->requestClass, "Store{$resource->name}Request");
        }

        if ($resource->updateRequestClass) {
            $this->generateRequestSchema($resource->updateRequestClass, "Update{$resource->name}Request");
        }
    }

    protected function processMethod(\ReflectionMethod $method, ?ApiResource $resource): void
    {
        $endpoint = $this->getMethodAttribute($method, ApiEndpoint::class);

        if (!$endpoint) {
            // Auto-generate based on the method name
            $endpoint = $this->autoGenerateEndpoint($method, $resource);
        }

        if ($endpoint) {
            $path = $this->buildPath($endpoint, $resource, $method->getDeclaringClass()->getName(), $method->getName());
            $operation = $this->buildOperation($method, $endpoint, $resource);

            $this->documentation['paths'][$path][strtolower($endpoint->method)] = $operation;
        }
    }

    protected function autoGenerateEndpoint(\ReflectionMethod $method, ?ApiResource $resource): ?ApiEndpoint
    {
        $methodName = $method->getName();
        $resourceName = $resource?->name ?? 'Resource';

        // Standard CRUD mappings
        $mappings = [
            'index' => [
                'method' => 'get',
                'path' => '/',
                'summary' => "Get list of {$resourceName}",
                'responses' => [200, 401, 403],
            ],
            'store' => [
                'method' => 'post',
                'path' => '/',
                'summary' => "Create new {$resourceName}",
                'responses' => [201, 401, 403, 422],
            ],
            'show' => [
                'method' => 'get',
                'path' => '/{id}',
                'summary' => "Get {$resourceName} by ID",
                'responses' => [200, 401, 403, 404],
            ],
            'update' => [
                'method' => 'put',
                'path' => '/{id}',
                'summary' => "Update {$resourceName}",
                'responses' => [200, 401, 403, 404, 422],
            ],
            'updatePatch' => [
                'method' => 'patch',
                'path' => '/{id}',
                'summary' => "Partially update {$resourceName}",
                'responses' => [200, 401, 403, 404, 422],
            ],
            'destroy' => [
                'method' => 'delete',
                'path' => '/{id}',
                'summary' => "Delete {$resourceName}",
                'responses' => [204, 401, 403, 404],
            ],
        ];

        if (isset($mappings[$methodName])) {
            $mapping = $mappings[$methodName];

            return new ApiEndpoint(
                method: $mapping['method'],
                path: $mapping['path'],
                summary: $mapping['summary'],
                description: "Auto-generated endpoint for {$methodName}",
                responses: $mapping['responses']
            );
        }

        return null;
    }

    protected function buildOperation(\ReflectionMethod $method, ApiEndpoint $endpoint, ?ApiResource $resource): array
    {
        $operation = [
            'summary' => $endpoint->summary,
            'description' => $endpoint->description,
            'operationId' => $method->getName().($resource?->name ?? ''),
            'tags' => [$resource?->name ?? 'Default'],
        ];

        // Add security if authenticated
        if ($endpoint->authenticated) {
            $operation['security'] = [['bearerAuth' => []]];
        }

        // Add parameters
        $operation['parameters'] = $this->buildParameters($method, $endpoint);

        // Add a request body for POST/PUT/PATCH
        if (in_array(strtolower($endpoint->method), ['post', 'put', 'patch'])) {
            $operation['requestBody'] = $this->buildRequestBody($method, $resource);
        }

        // Add responses
        $operation['responses'] = $this->buildResponses($method, $endpoint, $resource);

        return $operation;
    }

    protected function buildResponses(\ReflectionMethod $method, ApiEndpoint $endpoint, ?ApiResource $resource): array
    {
        $responses = [];

        // Get custom response attributes
        $responseAttrs = $this->getMethodAttributes($method, ApiResponse::class);

        foreach ($responseAttrs as $response) {
            $responses[$response->code] = [
                'description' => $response->description,
            ];

            if ($response->schema) {
                $responses[$response->code]['content'] = [
                    'application/json' => [
                        'schema' => ['$ref' => "#/components/schemas/{$response->schema}"],
                    ],
                ];
            }

            if ($response->example) {
                $responses[$response->code]['content']['application/json']['example'] = $response->example;
            }
        }

        // Auto-generate standard responses if not defined
        if (empty($responses)) {
            foreach ($endpoint->responses as $code) {
                $responses[$code] = $this->getStandardResponse($code, $method->getName(), $resource);
            }
        }

        return $responses;
    }

    protected function getStandardResponse(int $code, string $methodName, ?ApiResource $resource): array
    {
        $resourceName = $resource?->name ?? 'Resource';

        $standardResponses = [
            200 => [
                'description' => 'Successful operation',
                'content' => [
                    'application/json' => [
                        'schema' => $this->getResponseSchema($methodName, $resource),
                    ],
                ],
            ],
            201 => [
                'description' => "{$resourceName} successfully created",
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => "#/components/schemas/{$resourceName}Resource"],
                    ],
                ],
            ],
            204 => [
                'description' => "{$resourceName} successfully deleted",
            ],
            401 => [
                'description' => 'Unauthenticated',
            ],
            403 => [
                'description' => 'Forbidden',
            ],
            404 => [
                'description' => "{$resourceName} not found",
            ],
            422 => [
                'description' => 'Unprocessable Entity',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ValidationError'],
                    ],
                ],
            ],
        ];

        return $standardResponses[$code] ?? ['description' => 'Response'];
    }

    protected function inferPropertyType(string $field, object $model): array
    {
        // Common field type inference based on field name
        $property = ['type' => 'string'];

        if (str_contains($field, 'id') || str_contains($field, 'Id')) {
            $property['type'] = 'integer';
            $property['description'] = 'Unique identifier';
        } elseif (str_contains($field, 'email') || str_contains($field, 'Email')) {
            $property['type'] = 'string';
            $property['format'] = 'email';
            $property['description'] = 'Email address';
        } elseif (str_contains($field, 'date') || str_contains($field, 'Date')) {
            $property['type'] = 'string';
            $property['format'] = 'date';
            $property['description'] = 'Date field';
        } elseif (str_contains($field, 'price') || str_contains($field, 'Price') || str_contains($field, 'amount') || str_contains($field, 'Amount')) {
            $property['type'] = 'number';
            $property['format'] = 'float';
            $property['description'] = 'Price/Amount field';
        } elseif (str_contains($field, 'note') || str_contains($field, 'Note') || str_contains($field, 'description') || str_contains($field, 'Description')) {
            $property['type'] = 'string';
            $property['nullable'] = true;
            $property['description'] = 'Optional text field';
        }

        return $property;
    }

    protected function castTypeToSwaggerType(string $castType): array
    {
        return match($castType) {
            'integer', 'int' => ['type' => 'integer'],
            'float', 'double', 'decimal' => ['type' => 'number'],
            'boolean', 'bool' => ['type' => 'boolean'],
            'array', 'json' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            default => ['type' => 'string']
        };
    }

    protected function parseValidationRules(string $field, $ruleSet): array
    {
        $property = ['type' => 'string'];

        if (is_string($ruleSet)) {
            $ruleSet = explode('|', $ruleSet);
        }

        if (is_array($ruleSet)) {
            foreach ($ruleSet as $rule) {
                if (is_string($rule)) {
                    if (str_starts_with($rule, 'integer')) {
                        $property['type'] = 'integer';
                    } elseif (str_starts_with($rule, 'numeric')) {
                        $property['type'] = 'number';
                    } elseif (str_starts_with($rule, 'boolean')) {
                        $property['type'] = 'boolean';
                    } elseif (str_starts_with($rule, 'date')) {
                        $property['type'] = 'string';
                        $property['format'] = 'date';
                    } elseif (str_starts_with($rule, 'email')) {
                        $property['type'] = 'string';
                        $property['format'] = 'email';
                    } elseif (str_starts_with($rule, 'nullable')) {
                        $property['nullable'] = true;
                    }
                }
            }
        }

        // Infer from field name if type not determined
        if ($property['type'] === 'string') {
            $inferredProperty = $this->inferPropertyType($field, new \stdClass());
            $property = array_merge($property, $inferredProperty);
        }

        return $property;
    }

    protected function getResponseSchema(string $methodName, ?ApiResource $resource): array
    {
        if (!$resource) {
            return ['type' => 'object'];
        }

        return match($methodName) {
            'index' => ['$ref' => "#/components/schemas/{$resource->name}Collection"],
            'show', 'store', 'update', 'updatePatch' => ['$ref' => "#/components/schemas/{$resource->name}Resource"],
            default => ['type' => 'object']
        };
    }

    // Helper methods
    protected function getClassAttribute(\ReflectionClass $class, string $attributeClass): ?object
    {
        $attributes = $class->getAttributes($attributeClass);

        return !empty($attributes) ? $attributes[0]->newInstance() : null;
    }

    protected function getMethodAttribute(\ReflectionMethod $method, string $attributeClass): ?object
    {
        $attributes = $method->getAttributes($attributeClass);

        return !empty($attributes) ? $attributes[0]->newInstance() : null;
    }

    protected function getMethodAttributes(\ReflectionMethod $method, string $attributeClass): array
    {
        $result = [];
        foreach ($method->getAttributes($attributeClass) as $attribute) {
            $result[] = $attribute->newInstance();
        }

        return $result;
    }

    protected function buildPath(ApiEndpoint $endpoint, ?ApiResource $resource, string $controllerClass, string $methodName): string
    {
        // Try to find actual route first
        $actualPath = $this->getActualRoutePath($controllerClass, $methodName);
        if ($actualPath) {
            return $actualPath;
        }

        // Fallback to old behavior
        $basePath = '/v1';
        $resourcePath = $resource ? '/'.lcfirst($resource->name).'s' : '';

        return $basePath.$resourcePath.$endpoint->path;
    }

    protected function getActualRoutePath(string $controllerClass, string $methodName): ?string
    {
        $routes = Route::getRoutes();

        foreach ($routes as $route) {
            $action = $route->getAction();

            if (isset($action['controller'])) {
                // Handle both string and array controller formats
                if (is_string($action['controller'])) {
                    [$routeController, $routeMethod] = explode('@', $action['controller']);
                } elseif (is_array($action['controller'])) {
                    $routeController = $action['controller'][0];
                    $routeMethod = $action['controller'][1] ?? null;
                } else {
                    continue;
                }

                if ($routeController === $controllerClass && $routeMethod === $methodName) {
                    $uri = $route->uri();
                    // Add leading slash if not present and ensure it starts with /api/v1
                    if (!str_starts_with($uri, '/')) {
                        $uri = '/'.$uri;
                    }
                    if (!str_starts_with($uri, '/api/')) {
                        $uri = '/api'.$uri;
                    }

                    return $uri;
                }
            }
        }

        return null;
    }

    protected function buildParameters(\ReflectionMethod $method, ApiEndpoint $endpoint): array
    {
        $parameters = [];

        // Get custom parameter attributes
        $paramAttrs = $this->getMethodAttributes($method, ApiParameter::class);

        foreach ($paramAttrs as $param) {
            // Skip body parameters as they are handled in buildRequestBody
            if ($param->in === 'body') {
                continue;
            }

            $parameters[] = [
                'name' => $param->name,
                'in' => $param->in,
                'required' => $param->required,
                'description' => $param->description,
                'schema' => ['type' => $param->type],
                'example' => $param->example,
            ];
        }

        // Auto-add ID parameter for routes with {id}
        if (str_contains($endpoint->path, '{id}') && !$this->hasParameter($parameters, 'id')) {
            $parameters[] = [
                'name' => 'id',
                'in' => 'path',
                'required' => true,
                'description' => 'Resource ID',
                'schema' => ['type' => 'integer'],
            ];
        }

        return $parameters;
    }

    protected function hasParameter(array $parameters, string $name): bool
    {
        foreach ($parameters as $param) {
            if ($param['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    protected function hasTag(string $tagName): bool
    {
        foreach ($this->tags as $tag) {
            if ($tag['name'] === $tagName) {
                return true;
            }
        }

        return false;
    }

    protected function buildRequestBody(\ReflectionMethod $method, ?ApiResource $resource): array
    {
        $methodName = $method->getName();
        $schemaRef = match($methodName) {
            'store' => $resource?->requestClass ? "Store{$resource?->name}Request" : null,
            'update', 'updatePatch' => $resource?->updateRequestClass ? "Update{$resource?->name}Request" : null,
            default => null
        };

        if ($schemaRef) {
            return [
                'required' => $methodName === 'store',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => "#/components/schemas/{$schemaRef}"],
                    ],
                ],
            ];
        }

        // Check for ApiParameter attributes with body parameters
        $paramAttrs = $this->getMethodAttributes($method, ApiParameter::class);
        foreach ($paramAttrs as $param) {
            if ($param->in === 'body') {
                $content = [
                    'application/json' => [
                        'schema' => ['type' => $param->type === 'object' ? 'object' : $param->type],
                    ],
                ];

                // Add example if provided
                if ($param->example) {
                    $content['application/json']['example'] = $param->example;
                }

                return [
                    'required' => $param->required,
                    'content' => $content,
                ];
            }
        }

        // Temporary debug: For methods like 'register', return a test requestBody
        if ($methodName === 'register') {
            return [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['type' => 'object'],
                        'example' => [
                            'name' => 'John Doe',
                            'email' => 'john.doe@example.com',
                            'password' => 'password123',
                            'password_confirmation' => 'password123',
                        ],
                    ],
                ],
            ];
        }

        return [
            'content' => [
                'application/json' => [
                    'schema' => ['type' => 'object'],
                ],
            ],
        ];
    }

    protected function generateModelSchema(string $modelClass, string $schemaName): void
    {
        // Analyze model and generate schema
        // This would inspect the model's fillable, casts, etc.
        $this->schemas[$schemaName.'Resource'] = [
            'type' => 'object',
            'properties' => $this->extractModelProperties($modelClass),
        ];

        // Also generate collection schema
        $this->schemas[$schemaName.'Collection'] = [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'array',
                    'items' => [
                        '$ref' => "#/components/schemas/{$schemaName}Resource",
                    ],
                ],
                'links' => [
                    'type' => 'object',
                    'properties' => [
                        'first' => ['type' => 'string', 'nullable' => true],
                        'last' => ['type' => 'string', 'nullable' => true],
                        'prev' => ['type' => 'string', 'nullable' => true],
                        'next' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'meta' => [
                    'type' => 'object',
                    'properties' => [
                        'current_page' => ['type' => 'integer'],
                        'from' => ['type' => 'integer', 'nullable' => true],
                        'last_page' => ['type' => 'integer'],
                        'path' => ['type' => 'string'],
                        'per_page' => ['type' => 'integer'],
                        'to' => ['type' => 'integer', 'nullable' => true],
                        'total' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];
    }

    protected function generateRequestSchema(string $requestClass, string $schemaName): void
    {
        // Analyze request class rules and generate schema
        $required = $this->extractRequiredFields($requestClass);
        $properties = $this->extractRequestProperties($requestClass);

        $schema = [
            'type' => 'object',
            'properties' => (object) $properties, // Force as object instead of array
        ];

        // Only add required if it has items
        if (!empty($required)) {
            $schema['required'] = $required;
        }

        $this->schemas[$schemaName] = $schema;
    }

    protected function extractModelProperties(string $modelClass): array
    {
        if (!class_exists($modelClass)) {
            return [];
        }

        try {
            $modelReflection = new \ReflectionClass($modelClass);
            $modelInstance = $modelReflection->newInstance();

            $properties = [];

            // Get fillable attributes
            if (method_exists($modelInstance, 'getFillable')) {
                $fillable = $modelInstance->getFillable();
                foreach ($fillable as $field) {
                    $properties[$field] = $this->inferPropertyType($field, $modelInstance);
                }
            }

            // Get casts to determine types
            if (method_exists($modelInstance, 'getCasts')) {
                $casts = $modelInstance->getCasts();
                foreach ($casts as $field => $castType) {
                    if (isset($properties[$field])) {
                        $properties[$field] = array_merge($properties[$field], $this->castTypeToSwaggerType($castType));
                    }
                }
            }

            // Add common timestamp fields
            if (isset($properties['created_at']) || isset($properties['updated_at'])) {
                $properties['created_at'] = [
                    'type' => 'string',
                    'format' => 'date-time',
                    'description' => 'Creation timestamp',
                ];
                $properties['updated_at'] = [
                    'type' => 'string',
                    'format' => 'date-time',
                    'description' => 'Last update timestamp',
                ];
            }

            return $properties;
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function extractRequestProperties(string $requestClass): array
    {
        if (!class_exists($requestClass)) {
            return [];
        }

        try {
            $requestReflection = new \ReflectionClass($requestClass);
            $requestInstance = $requestReflection->newInstance();

            $properties = [];

            if (method_exists($requestInstance, 'rules')) {
                $rules = $requestInstance->rules();

                foreach ($rules as $field => $ruleSet) {
                    $properties[$field] = $this->parseValidationRules($field, $ruleSet);
                }
            }

            return $properties;
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function extractRequiredFields(string $requestClass): array
    {
        if (!class_exists($requestClass)) {
            return [];
        }

        try {
            $requestReflection = new \ReflectionClass($requestClass);
            $requestInstance = $requestReflection->newInstance();

            $required = [];

            if (method_exists($requestInstance, 'rules')) {
                $rules = $requestInstance->rules();

                foreach ($rules as $field => $ruleSet) {
                    if (is_string($ruleSet)) {
                        $ruleSet = explode('|', $ruleSet);
                    }

                    if (is_array($ruleSet) && in_array('required', $ruleSet, true)) {
                        $required[] = $field;
                    }
                }
            }

            return $required;
        } catch (\Exception $e) {
            return [];
        }
    }
}
