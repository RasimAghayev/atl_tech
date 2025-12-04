<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use App\Http\Responses\SuccessApiResponse;
use App\Http\Responses\ErrorApiResponse;

/**
 * Has API Responses Trait
 * 
 * Standardizes API response format across all controllers
 * Implements consistent response structure
 */
trait HasApiResponses
{
    /**
     * Return success response with data
     */
    protected function successResponse($data = null, string $message = 'Success', int $statusCode = 200): SuccessApiResponse
    {
        return SuccessApiResponse::make($data, $statusCode, $message);
    }

    /**
     * Return created response
     */
    protected function createdResponse($data = null, string $message = 'Resource created successfully'): SuccessApiResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Return updated response
     */
    protected function updatedResponse($data = null, string $message = 'Resource updated successfully'): SuccessApiResponse
    {
        return $this->successResponse($data, $message, 200);
    }

    /**
     * Return deleted response
     */
    protected function deletedResponse(string $message = 'Resource deleted successfully'): SuccessApiResponse
    {
        return $this->successResponse(null, $message, 200);
    }

    /**
     * Return not found error response
     */
    protected function notFoundResponse(string $message = 'Resource not found'): ErrorApiResponse
    {
        return ErrorApiResponse::make($message, 404);
    }

    /**
     * Return validation error response
     */
    protected function validationErrorResponse($errors, string $message = 'Validation failed'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], 422);
    }

    /**
     * Return server error response
     */
    protected function serverErrorResponse(string $message = 'Internal server error'): ErrorApiResponse
    {
        return ErrorApiResponse::make($message, 500);
    }

    /**
     * Return unauthorized response
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): ErrorApiResponse
    {
        return ErrorApiResponse::make($message, 401);
    }

    /**
     * Return forbidden response
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): ErrorApiResponse
    {
        return ErrorApiResponse::make($message, 403);
    }

    /**
     * Return bad request response
     */
    protected function badRequestResponse(string $message = 'Bad request'): ErrorApiResponse
    {
        return ErrorApiResponse::make($message, 400);
    }

    /**
     * Return no content response
     */
    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Return collection response with pagination
     */
    protected function collectionResponse($collection, $resourceClass = null): JsonResponse
    {
        $data = $resourceClass ? $resourceClass::collection($collection) : $collection;
        
        return $this->successResponse($data);
    }

    /**
     * Return single resource response
     */
    protected function resourceResponse($resource, $resourceClass = null): JsonResponse
    {
        $data = $resourceClass ? new $resourceClass($resource) : $resource;
        
        return $this->successResponse($data);
    }
}