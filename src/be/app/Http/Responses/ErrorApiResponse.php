<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag;

class ErrorApiResponse extends ApiErrorResponse
{
    public static function make(mixed $dataOrMessage = [], ?int $code = null): static
    {
        if ($code === 422 && $dataOrMessage instanceof MessageBag) {
            // Validasiya səhvlərini array formatına çevir
            $dataOrMessage = ['errors' => $dataOrMessage->toArray()];
        } elseif ($code === 422 && is_array($dataOrMessage)) {
            // Əgər artıq array-dirsə, olduğu kimi errors hissəsinə qoy
            $dataOrMessage = ['errors' => $dataOrMessage];
        } else {
            $dataOrMessage = ['error' => $dataOrMessage];
        }

        return parent::make($dataOrMessage, $code);
    }

    public function toResponse($request): JsonResponse
    {
        $response = parent::toResponse($request);

        $data = (array) $response->getData();

        if (!isset($data['errors']) || !is_array($data['errors'])) {
            $data['error'] = $this->formatErrorMessage($this->dataOrMessage);
        }

        $data['result'] = [];

        $response->setData($data);

        return $response;
    }

    /**
     * Format error message based on input type
     */
    protected function formatErrorMessage(mixed $error): string|array
    {
        if ($error instanceof \Throwable) {
            return $error->getMessage();
        }

        if (is_array($error)) {
            return $error['errors'] ?? $error['error'];
        }

        if (is_object($error) && method_exists($error, '__toString')) {
            return (string) $error;
        }

        return (string) $error ?: $this->defaultErrorMessage();
    }

    #[\Override]
    protected function defaultErrorMessage(): string
    {
        return 'Something went wrong';
    }
}
