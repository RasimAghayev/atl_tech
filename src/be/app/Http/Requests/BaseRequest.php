<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\ApiFormRequest;

/**
 * Base Request
 * 
 * Provides common validation rules and functionality
 */
abstract class BaseRequest extends ApiFormRequest
{
    /**
     * Common validation rules for IDs
     */
    protected function idRules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Common validation rules for old_id (migration compatibility)
     */
    protected function oldIdRules(): array
    {
        return [
            'old_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Common validation rules for dates
     */
    protected function dateRules(): array
    {
        return [
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'start_date' => ['nullable', 'date', 'before_or_equal:end_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * Common validation rules for prices/amounts
     */
    protected function priceRules(): array
    {
        return [
            'price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
        ];
    }

    /**
     * Common validation rules for percentages
     */
    protected function percentageRules(): array
    {
        return [
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_percentage_one' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_percentage_two' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_percentage_three' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * Common validation rules for relationships
     */
    protected function relationshipRules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'internal_invoice_id' => ['nullable', 'integer', 'exists:internal_invoices,id'],
            'factory_invoice_id' => ['nullable', 'integer', 'exists:factory_invoices,id'],
        ];
    }

    /**
     * Common validation rules for VIN codes
     */
    protected function vinRules(): array
    {
        return [
            'vin' => ['required', 'string', 'size:17', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/'],
        ];
    }

    /**
     * Common validation rules for text fields
     */
    protected function textRules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:1000'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Common validation rules for status
     */
    protected function statusRules(): array
    {
        return [
            'status' => ['required', 'integer', 'in:0,1'],
        ];
    }

    /**
     * Common validation rules for pagination
     */
    protected function paginationRules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * Common validation rules for search
     */
    protected function searchRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get validation messages
     */
    public function messages(): array
    {
        return [
            'required' => 'The :attribute field is required.',
            'integer' => 'The :attribute must be an integer.',
            'numeric' => 'The :attribute must be a number.',
            'min' => 'The :attribute must be at least :min.',
            'max' => 'The :attribute must not exceed :max.',
            'date' => 'The :attribute must be a valid date.',
            'date_format' => 'The :attribute must be in the format :format.',
            'exists' => 'The selected :attribute does not exist.',
            'in' => 'The selected :attribute is invalid.',
            'regex' => 'The :attribute format is invalid.',
            'size' => 'The :attribute must be exactly :size characters.',
            'before_or_equal' => 'The :attribute must be before or equal to :date.',
            'after_or_equal' => 'The :attribute must be after or equal to :date.',
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'order_id' => 'order',
            'internal_invoice_id' => 'internal invoice',
            'factory_invoice_id' => 'factory invoice',
            'old_id' => 'old system ID',
            'per_page' => 'items per page',
            'sort_by' => 'sort field',
            'sort_direction' => 'sort direction',
        ];
    }
}