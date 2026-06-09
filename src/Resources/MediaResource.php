<?php

namespace Metrique\Pagoti\Resources;

use Metrique\Pagoti\PaginatedResponse;
use Metrique\Pagoti\PagotiClient;
use Metrique\Pagoti\Resources\Concerns\CanFetchFresh;

class MediaResource
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
