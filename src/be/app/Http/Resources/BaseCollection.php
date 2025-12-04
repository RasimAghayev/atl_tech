<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Base Collection
 * 
 * Provides consistent collection response structure
 */
abstract class BaseCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => $this->getMeta(),
        ];
    }

    /**
     * Get collection metadata
     */
    protected function getMeta(): array
    {
        $meta = [
            'total' => $this->collection->count(),
        ];

        // Add pagination info if available
        if (method_exists($this->resource, 'total')) {
            $meta = array_merge($meta, [
                'current_page' => $this->resource->currentPage(),
                'per_page' => $this->resource->perPage(),
                'total' => $this->resource->total(),
                'last_page' => $this->resource->lastPage(),
                'from' => $this->resource->firstItem(),
                'to' => $this->resource->lastItem(),
                'has_more_pages' => $this->resource->hasMorePages(),
            ]);
        }

        return $meta;
    }

    /**
     * Get additional data for the resource collection
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Customize the paginated response
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'links' => [
                'first' => $paginated['first_page_url'] ?? null,
                'last' => $paginated['last_page_url'] ?? null,
                'prev' => $paginated['prev_page_url'] ?? null,
                'next' => $paginated['next_page_url'] ?? null,
            ],
            'meta' => [
                'current_page' => $paginated['current_page'] ?? 1,
                'from' => $paginated['from'] ?? 0,
                'last_page' => $paginated['last_page'] ?? 1,
                'per_page' => $paginated['per_page'] ?? 15,
                'to' => $paginated['to'] ?? 0,
                'total' => $paginated['total'] ?? 0,
                'path' => $paginated['path'] ?? $request->url(),
            ],
        ];
    }
}