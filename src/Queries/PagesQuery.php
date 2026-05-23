<?php

namespace Metrique\Pagoti\Queries;

use Metrique\Pagoti\PaginatedResponse;
use Metrique\Pagoti\PagotiTransportInterface;

class PagesQuery
{
    private bool $fresh = false;

    public function __construct(
        private readonly PagotiTransportInterface $client,
        private readonly string $projectId,
    ) {
    }

    public function fresh(): static
    {
        $this->fresh = true;
        return $this;
    }

    public function flush(): void
    {
        $this->client->flushVersion(static::versionKey($this->projectId));
    }

    public function get(int $page = 1): PaginatedResponse
    {
        return $this->client->request(
            'GET',
            $this->client->apiUrl("projects/{$this->projectId}/pages"),
            ['page' => $page],
            fresh: $this->fresh,
            versionKey: static::versionKey($this->projectId),
        );
    }

    public function create(array $data): array
    {
        return $this->client->request(
            'POST',
            $this->client->apiUrl("projects/{$this->projectId}/pages"),
            body: $data,
        );
    }

    public static function versionKey(string $projectId): string
    {
        return "pagoti_v_project_{$projectId}_pages";
    }
}
