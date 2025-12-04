<?php

declare(strict_types=1);

namespace App\Support\Helpers;

use App\Http\Responses\ErrorApiResponse;
use App\Http\Responses\SuccessApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TransactionHelper
{
    public static function handleWithTransaction(callable $callback, int $successStatus = 200): \Throwable|SuccessApiResponse|ErrorApiResponse
    {
        try {
            return SuccessApiResponse::make($callback(), $successStatus);
        } catch (ValidationException $e) {
            // Validasiya səhvlərini ErrorApiResponse ilə qaytarın
            return ErrorApiResponse::make($e->errors(), 422);
        } catch (AuthorizationException $e) {
            return ErrorApiResponse::make('Authorization failed: '.$e->getMessage(), 403);
        } catch (ModelNotFoundException|NotFoundHttpException $e) {
            return ErrorApiResponse::make('Resource not found: '.$e->getMessage(), 404);
        } catch (\Throwable $e) {
            return ErrorApiResponse::make('An error occurred: '.$e->getMessage(), 500);
        }
    }
}
