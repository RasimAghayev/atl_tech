<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Has Pagination Links Trait
 * 
 * Centralizes pagination logic to eliminate code duplication
 * Used by all controllers that need pagination
 */
trait HasPaginationLinks
{
    /**
     * Build pagination links with consistent format
     */
    protected function buildPaginationLinks(LengthAwarePaginator $paginator, Request $request): array
    {
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $baseUrl = $request->url();
        $queryParams = $request->query();

        // Remove page parameter for building custom links
        unset($queryParams['page']);
        $queryString = http_build_query($queryParams);
        $queryString = $queryString ? '&' . $queryString : '';

        return [
            'current_page' => $currentPage,
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $lastPage,
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'links' => [
                'first' => $lastPage > 1 ? $baseUrl . '?page=1' . $queryString : null,
                'previous' => $currentPage > 1 ? $baseUrl . '?page=' . ($currentPage - 1) . $queryString : null,
                'next' => $currentPage < $lastPage ? $baseUrl . '?page=' . ($currentPage + 1) . $queryString : null,
                'last' => $lastPage > 1 ? $baseUrl . '?page=' . $lastPage . $queryString : null,
            ],
            'meta' => [
                'has_more_pages' => $paginator->hasMorePages(),
                'on_first_page' => $paginator->onFirstPage(),
                'has_pages' => $paginator->hasPages(),
            ]
        ];
    }

    /**
     * Apply standard pagination parameters
     */
    protected function getPaginationParams(Request $request): array
    {
        return [
            'page' => max(1, (int) $request->query('page', 1)),
            'per_page' => min(100, max(1, (int) $request->query('per_page', 15))),
            'sort_by' => $request->query('sort_by', 'id'),
            'sort_direction' => in_array(strtolower($request->query('sort_direction', 'desc')), ['asc', 'desc']) 
                ? strtolower($request->query('sort_direction', 'desc')) 
                : 'desc'
        ];
    }

    /**
     * Format paginated response with consistent structure
     */
    protected function formatPaginatedResponse(LengthAwarePaginator $paginator, Request $request, $resourceClass = null): array
    {
        $data = $paginator->items();
        
        // Apply resource transformation if provided
        if ($resourceClass && class_exists($resourceClass)) {
            $data = $resourceClass::collection($data)->toArray($request);
        }

        return [
            'data' => $data,
            'pagination' => $this->buildPaginationLinks($paginator, $request)
        ];
    }
}