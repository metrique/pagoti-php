<?php

namespace Metrique\Pagoti\Queries;

use Metrique\Pagoti\PaginatedResponse;
use Metrique\Pagoti\PagotiTransportInterface;

class MediaQuery
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
            $this->client->apiUrl("projects/{$this->projectId}/media"),
            ['page' => $page],
            fresh: $this->fresh,
            versionKey: static::versionKey($this->projectId),
        );
    }

    public static function versionKey(string $projectId): string
    {
        return "pagoti_v_project_{$projectId}_media";
    }
}
