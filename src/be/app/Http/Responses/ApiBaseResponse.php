<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\{JsonResponse, Resources\Json\JsonResource, Response};

abstract class ApiBaseResponse extends Response implements Responsable
{
    protected mixed $dataOrMessage;

    protected ?int $code;

    private function __construct(mixed $dataOrMessage = [], ?int $code = null)
    {
        parent::__construct();

        $this->dataOrMessage = $dataOrMessage;
        $this->code = $code;
    }

    public static function make(mixed $dataOrMessage = [], ?int $code = null): static
    {
        return new static($dataOrMessage, $code);
    }

    /**
     * @throws \JsonException
     */
    public function toResponse($request): JsonResponse
    {
        $responseArray = [
            'timestamp' => now()->toIso8601String(),
            'path' => $request->path(),
            'method' => $request->method(),
            'error' => null,
            'result' => $this->formatResult($this->dataOrMessage),
        ];

        if ($this->code === 422 && is_array($this->dataOrMessage) && isset($this->dataOrMessage['errors'])) {
            // Validasiya səhvlərini əlavə edin
            $responseArray['error'] = $this->dataOrMessage['errors'];
            $responseArray['result'] = [];
        } elseif ($this->code === 422 && is_string($this->dataOrMessage)) {
            // String formatındakı səhvləri array-a çevirin
            $responseArray['error'] = ['general' => [$this->dataOrMessage]];
            $responseArray['result'] = [];
        }

        $response = new JsonResponse($responseArray);
        $response->setStatusCode($this->code ?? $this->defaultResponseCode());

        $headers = $this->headers->all();
        if (!empty($headers)) {
            $response->headers->add($headers);
        }

        return $response;
    }

    /**
     * Format the result data
     *
     * @throws \JsonException
     */
    protected function formatResult(mixed $result): mixed
    {
        if ($result instanceof JsonResponse) {
            return json_decode($result->getContent(), true, 512, JSON_THROW_ON_ERROR);
        }

        if (is_object($result) && method_exists($result, 'toArray')) {
            if ($result instanceof JsonResource) {
                return $result->resolve(request());
            }

            return $result->toArray(request());
        }

        if (is_object($result) && method_exists($result, 'getData')) {
            return $result->getData(true);
        }

        if (is_array($result) && isset($result['original'])) {
            return $result['original'];
        }

        return $result;
    }

    protected function defaultResponseCode(): int
    {
        return 500;
    }

    /**
     * Get response status code
     */
    public function getStatusCode(): int
    {
        return $this->code ?? $this->defaultResponseCode();
    }
}
