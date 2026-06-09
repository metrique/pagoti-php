<?php

namespace Metrique\Pagoti\Resources;

use Metrique\Pagoti\PaginatedResponse;
use Metrique\Pagoti\PagotiClient;
use Metrique\Pagoti\Resources\Concerns\CanFetchFresh;

class PagesResource
{
    use CanFetchFresh;

    public function __construct(
        private readonly PagotiClient $client,
        private readonly string $projectId,
    ) {
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
