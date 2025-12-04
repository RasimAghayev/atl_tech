<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base Resource
 * 
 * Provides common resource functionality and consistent data structure
 */
abstract class BaseResource extends JsonResource
{
    /**
     * Common fields that all resources should have
     */
    protected function baseFields(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->format('d.m.Y H:i:s'),
            'updated_at' => $this->updated_at?->format('d.m.Y H:i:s'),
        ];
    }

    /**
     * Include old_id if available (for migration compatibility)
     */
    protected function migrationFields(): array
    {
        return [
            'old_id' => $this->when($this->old_id, $this->old_id),
        ];
    }

    /**
     * Format date field consistently
     */
    protected function formatDate($date, string $format = 'd.m.Y'): ?string
    {
        return $date ? $date->format($format) : null;
    }

    /**
     * Format currency amount
     */
    protected function formatCurrency($amount, string $currency = 'AZN'): array
    {
        return [
            'amount' => (float) $amount,
            'formatted' => number_format((float) $amount, 2, '.', ',') . ' ' . $currency,
            'currency' => $currency
        ];
    }

    /**
     * Format percentage value
     */
    protected function formatPercentage($percentage): array
    {
        return [
            'value' => (float) $percentage,
            'formatted' => number_format((float) $percentage, 2) . '%'
        ];
    }

    /**
     * Include relationship data conditionally
     */
    protected function includeRelationship(string $relationship, $resource = null)
    {
        return $this->when(
            $this->relationLoaded($relationship),
            $resource ?? $this->$relationship
        );
    }

    /**
     * Transform enum to readable format
     */
    protected function transformEnum($enum): array
    {
        if (!$enum) {
            return ['value' => null, 'label' => null];
        }

        return [
            'value' => $enum->value,
            'label' => $enum->getLabel() ?? $enum->name ?? $enum->value
        ];
    }

    /**
     * Get user-friendly status
     */
    protected function getStatusInfo($status): array
    {
        $statusMap = [
            1 => ['label' => 'Active', 'color' => 'green'],
            0 => ['label' => 'Inactive', 'color' => 'red'],
            'pending' => ['label' => 'Pending', 'color' => 'yellow'],
            'completed' => ['label' => 'Completed', 'color' => 'green'],
            'cancelled' => ['label' => 'Cancelled', 'color' => 'red'],
        ];

        return [
            'value' => $status,
            'label' => $statusMap[$status]['label'] ?? 'Unknown',
            'color' => $statusMap[$status]['color'] ?? 'gray'
        ];
    }
}