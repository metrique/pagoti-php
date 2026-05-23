<?php

namespace Metrique\Pagoti\Resources;

use Metrique\Pagoti\PagotiTransportInterface;

class PageResource
{
    public function __construct(
        private readonly PagotiTransportInterface $client,
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
