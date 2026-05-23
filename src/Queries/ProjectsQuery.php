<?php

namespace Metrique\Pagoti\Queries;

use Metrique\Pagoti\PaginatedResponse;
use Metrique\Pagoti\PagotiTransportInterface;

class ProjectsQuery
{
    private bool $fresh = false;

    public function __construct(
        private readonly PagotiTransportInterface $client,
    ) {
    }

    public function fresh(): static
    {
        $this->fresh = true;
        return $this;
    }

    public static function versionKey(): string
    {
        return 'pagoti_v_projects';
    }

    public function flush(): void
    {
        $this->client->flushVersion(static::versionKey());
    }

    public function get(int $page = 1): PaginatedResponse
    {
        return $this->client->request(
            'GET',
            $this->client->apiUrl('projects'),
            ['page' => $page],
            fresh: $this->fresh,
            versionKey: static::versionKey(),
        );
    }

    public function create(array $data): array
    {
        return $this->client->request(
            'POST',
            $this->client->apiUrl('projects'),
            body: $data,
        );
    }
}
