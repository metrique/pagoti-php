<?php

namespace Metrique\Pagoti;

class PaginatedResponse
{
    public function __construct(
        private readonly array $data,
    ) {
    }

    public function items(): array
    {
        return $this->data['data'];
    }

    public function currentPage(): int
    {
        return $this->data['current_page'];
    }

    public function lastPage(): int
    {
        return $this->data['last_page'];
    }

    public function perPage(): int
    {
        return $this->data['per_page'];
    }

    public function total(): int
    {
        return $this->data['total'];
    }

    public function hasMore(): bool
    {
        return $this->currentPage() < $this->lastPage();
    }
}
