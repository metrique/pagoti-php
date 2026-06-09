<?php

namespace Metrique\Pagoti\Resources;

use Metrique\Pagoti\PaginatedResponse;
use Metrique\Pagoti\PagotiClient;
use Metrique\Pagoti\Resources\Concerns\CanFetchFresh;

class ProjectsResource
{
    use CanFetchFresh;

    public function __construct(
        private readonly PagotiClient $client,
    ) {
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
        $result = $this->client->request(
            'POST',
            $this->client->apiUrl('projects'),
            body: $data,
        );

        $this->flush();

        return $result;
    }
}
