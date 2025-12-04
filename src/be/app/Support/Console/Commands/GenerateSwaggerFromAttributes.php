<?php

declare(strict_types=1);

namespace App\Support\Console\Commands;

use App\Support\Swagger\Services\SwaggerGenerator;
use Illuminate\Console\Command;

class GenerateSwaggerFromAttributes extends Command
{
    protected $signature = 'swagger:generate-from-attributes
                            {--controller=* : Specific controllers to process}
                            {--path=app : Base path for scanning controllers}';

    protected $description = 'Generate Swagger documentation from PHP attributes';

    /**
     * @throws \JsonException
     */
    public function handle(SwaggerGenerator $generator): int
    {
        $this->info('Generating Swagger documentation from attributes...');

        $controllers = $this->option('controller');
        $basePath = $this->option('path');

        if (empty($controllers)) {
            // Scan all controllers in the path
            $controllers = $this->scanControllers($basePath);
        }

        $documentation = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Call Event API',
                'version' => '1.0.0',
                'description' => 'API for receiving and processing call events from SIP server',
                'contact' => [
                    'email' => 'api@example.com',
                ],
            ],
            'servers' => [
                [
                    'url' => config('app.url').'/api',
                    'description' => 'API Server',
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'token',
                        'description' => 'Enter your API token',
                    ],
                ],
                'schemas' => [],
            ],
            'paths' => [],
            'tags' => [],
        ];

        foreach ($controllers as $controller) {
            $this->info("Processing: {$controller}");

            try {
                $result = $generator->generateFromController($controller);
                $documentation['paths'] = array_merge($documentation['paths'], $result['paths'] ?? []);

                // Merge tags uniquely by name
                if (!empty($result['tags'])) {
                    $existingTagNames = array_column($documentation['tags'], 'name');
                    foreach ($result['tags'] as $tag) {
                        if (!in_array($tag['name'], $existingTagNames, true)) {
                            $documentation['tags'][] = $tag;
                            $existingTagNames[] = $tag['name'];
                        }
                    }
                }

                $documentation['components']['schemas'] = array_merge(
                    $documentation['components']['schemas'] ?? [],
                    $result['schemas'] ?? []
                );
            } catch (\Exception $e) {
                $this->error("Error processing {$controller}: ".$e->getMessage());
            }
        }

        // Add standard error schemas
        $documentation['components']['schemas']['ValidationError'] = [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'example' => 'The given data was invalid.',
                ],
                'errors' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                    ],
                    'example' => [
                        'call_id' => ['The call id field is required.'],
                        'event_type' => ['The selected event type is invalid.'],
                    ],
                ],
            ],
        ];

        $documentation['components']['schemas']['UnauthorizedError'] = [
            'type' => 'object',
            'properties' => [
                'error' => [
                    'type' => 'string',
                    'example' => 'Unauthorized',
                ],
            ],
        ];

        $documentation['components']['schemas']['InternalServerError'] = [
            'type' => 'object',
            'properties' => [
                'error' => [
                    'type' => 'string',
                    'example' => 'An unexpected error occurred.',
                ],
            ],
        ];

        // Save to file
        $outputPath = storage_path('api-docs/swagger.json');
        file_put_contents($outputPath, json_encode($documentation, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        $this->info('Documentation generated successfully!');
        $this->info("Output: {$outputPath}");

        return 0;
    }

    protected function scanControllers(string $path): array
    {
        $controllers = [];
        $files = $this->scanDirectory(base_path($path), '*Controller.php');

        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);
            if ($className) {
                $controllers[] = $className;
            }
        }

        return $controllers;
    }

    protected function scanDirectory(string $dir, string $pattern): array
    {
        $files = [];

        if (!is_dir($dir)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    protected function getClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);

        if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch) &&
            preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            return $namespaceMatch[1].'\\'.$classMatch[1];
        }

        return null;
    }
}
