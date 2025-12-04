<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * Has Filtering Trait
 * 
 * Provides common filtering functionality for controllers and services
 * Eliminates duplicate filtering code across modules
 */
trait HasFiltering
{
    /**
     * Apply common filters to query builder
     */
    protected function applyCommonFilters(Builder $query, Request $request): Builder
    {
        // Date range filtering
        if ($request->filled(['start_date', 'end_date'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ]);
        } elseif ($request->filled('start_date')) {
            $query->where('created_at', '>=', Carbon::parse($request->start_date)->startOfDay());
        } elseif ($request->filled('end_date')) {
            $query->where('created_at', '<=', Carbon::parse($request->end_date)->endOfDay());
        }

        // Search by multiple fields
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $searchableFields = $this->getSearchableFields();
            
            $query->where(function ($q) use ($searchTerm, $searchableFields) {
                foreach ($searchableFields as $field) {
                    $q->orWhere($field, 'LIKE', "%{$searchTerm}%");
                }
            });
        }

        // Status filtering
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // ID filtering (for bulk operations)
        if ($request->filled('ids')) {
            $ids = is_array($request->ids) ? $request->ids : explode(',', $request->ids);
            $query->whereIn('id', $ids);
        }

        // Old system ID filtering
        if ($request->filled('old_id')) {
            $query->where('old_id', $request->old_id);
        }

        return $query;
    }

    /**
     * Apply date-specific filtering for models with date fields
     */
    protected function applyDateFiltering(Builder $query, Request $request, string $dateField = 'date'): Builder
    {
        if ($request->filled(['date_from', 'date_to'])) {
            $query->whereBetween($dateField, [
                Carbon::parse($request->date_from)->startOfDay(),
                Carbon::parse($request->date_to)->endOfDay(),
            ]);
        } elseif ($request->filled('date_from')) {
            $query->where($dateField, '>=', Carbon::parse($request->date_from)->startOfDay());
        } elseif ($request->filled('date_to')) {
            $query->where($dateField, '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        // Specific date
        if ($request->filled('date')) {
            $query->whereDate($dateField, Carbon::parse($request->date));
        }

        // Month filtering
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth($dateField, $request->month)
                  ->whereYear($dateField, $request->year);
        }

        return $query;
    }

    /**
     * Apply relationship filtering
     */
    protected function applyRelationshipFiltering(Builder $query, Request $request): Builder
    {
        // Customer filtering
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Order filtering
        if ($request->filled('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        // Internal invoice filtering
        if ($request->filled('internal_invoice_id')) {
            $query->where('internal_invoice_id', $request->internal_invoice_id);
        }

        // Factory invoice filtering
        if ($request->filled('factory_invoice_id')) {
            $query->where('factory_invoice_id', $request->factory_invoice_id);
        }

        return $query;
    }

    /**
     * Apply numeric range filtering
     */
    protected function applyNumericFiltering(Builder $query, Request $request, array $numericFields): Builder
    {
        foreach ($numericFields as $field) {
            // Range filtering
            if ($request->filled(["{$field}_min", "{$field}_max"])) {
                $query->whereBetween($field, [$request->input("{$field}_min"), $request->input("{$field}_max")]);
            } elseif ($request->filled("{$field}_min")) {
                $query->where($field, '>=', $request->input("{$field}_min"));
            } elseif ($request->filled("{$field}_max")) {
                $query->where($field, '<=', $request->input("{$field}_max"));
            }

            // Exact value
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return $query;
    }

    /**
     * Apply ordering to query
     */
    protected function applyOrdering(Builder $query, Request $request, string $defaultOrderBy = 'id', string $defaultDirection = 'desc'): Builder
    {
        $orderBy = $request->input('sort_by', $defaultOrderBy);
        $direction = $request->input('sort_direction', $defaultDirection);

        // Validate direction
        $direction = in_array(strtolower($direction), ['asc', 'desc']) ? $direction : $defaultDirection;

        // Validate orderBy field (implement in child classes)
        if (method_exists($this, 'isValidOrderByField')) {
            $orderBy = $this->isValidOrderByField($orderBy) ? $orderBy : $defaultOrderBy;
        }

        return $query->orderBy($orderBy, $direction);
    }

    /**
     * Get searchable fields for the model (override in implementing classes)
     */
    protected function getSearchableFields(): array
    {
        return ['id']; // Default fallback
    }

    /**
     * Build filters array from request for service layer
     */
    protected function buildFiltersFromRequest(Request $request): array
    {
        return array_filter($request->only([
            'search', 'status', 'customer_id', 'order_id', 'internal_invoice_id', 'factory_invoice_id',
            'start_date', 'end_date', 'date_from', 'date_to', 'date', 'month', 'year',
            'sort_by', 'sort_direction', 'per_page', 'page'
        ]));
    }
}