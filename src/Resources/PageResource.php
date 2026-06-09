<?php

namespace Metrique\Pagoti\Resources;

use Metrique\Pagoti\PagotiClient;

class PageResource
{
    public function __construct(
        private readonly PagotiClient $client,
        private readonly string $projectId,
        private readonly string $pageId,
    ) {
    }

    public function get(): array
    {
        return $this->client->request(
            'GET',
            $this->client->apiUrl("projects/{$this->projectId}/pages/{$this->pageId}"),
        );
    }

    public function update(array $data): array
    {
        return $this->client->request(
            'PUT',
            $this->client->apiUrl("projects/{$this->projectId}/pages/{$this->pageId}"),
            body: $data,
        );
    }

    public function delete(): void
    {
        $this->client->request(
            'DELETE',
            $this->client->apiUrl("projects/{$this->projectId}/pages/{$this->pageId}"),
        );
    }
}
