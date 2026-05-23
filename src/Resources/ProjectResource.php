<?php

namespace Metrique\Pagoti\Resources;

use Metrique\Pagoti\PagotiTransportInterface;
use Metrique\Pagoti\Queries\MediaQuery;
use Metrique\Pagoti\Queries\PagesQuery;

class ProjectResource
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
        $this->client->flushVersion(
            $this->versionKey(),
            PagesQuery::versionKey($this->projectId),
            MediaQuery::versionKey($this->projectId),
        );
    }

    public function get(): array
    {
        return $this->client->request(
            'GET',
            $this->client->apiUrl("projects/{$this->projectId}"),
            fresh: $this->fresh,
            versionKey: $this->versionKey(),
        );
    }

    public function update(array $data): array
    {
        return $this->client->request(
            'PUT',
            $this->client->apiUrl("projects/{$this->projectId}"),
            body: $data,
        );
    }

    public function delete(): void
    {
        $this->client->request(
            'DELETE',
            $this->client->apiUrl("projects/{$this->projectId}"),
        );
    }

    public function pages(): PagesQuery
    {
        return new PagesQuery($this->client, $this->projectId);
    }

    public function page(string $pageId): PageResource
    {
        return new PageResource($this->client, $this->projectId, $pageId);
    }

    public function media(): MediaQuery
    {
        return new MediaQuery($this->client, $this->projectId);
    }

    private function versionKey(): string
    {
        return "pagoti_v_project_{$this->projectId}";
    }
}
