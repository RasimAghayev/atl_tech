<?php

declare(strict_types=1);

namespace App\Support\Traits;

trait PaginationLinksTrait
{
    protected function getSelfLink(): ?string
    {
        return $this->resource->url($this->resource->currentPage());
    }

    protected function getFirstLink(): ?string
    {
        return $this->resource->url(1);
    }

    protected function getLastLink(): ?string
    {
        return $this->resource->url($this->resource->lastPage());
    }

    protected function getPrevLink(): ?string
    {
        return $this->resource->previousPageUrl();
    }

    protected function getNextLink(): ?string
    {
        return $this->resource->nextPageUrl();
    }

    protected function getMetaData(): array
    {
        return [
            'current_page' => $this->resource->currentPage(),
            'from' => $this->resource->firstItem(),
            'last_page' => $this->resource->lastPage(),
            'per_page' => $this->resource->perPage(),
            'to' => $this->resource->lastItem(),
            'total' => $this->resource->total(),
        ];
    }
}
